<?php

$config = require __DIR__ . '/../app/config.php';
$pdo = require __DIR__ . '/../app/db.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ======================================================
// CURRENT BATCH
// ======================================================
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 1;

$findingsStmt = $pdo->prepare("
    SELECT
        id,
        scenario,
        diagnostic_direction,
        severity,
        confidence,
        matched_call_count,
        group_key,
        group_value,
        evidence,
        recommended_next_step,
        created_at
    FROM diagnostic_findings
    WHERE batch_id = :batch_id
    ORDER BY
        severity DESC,
        confidence DESC,
        matched_call_count DESC,
        id ASC
");

$findingsStmt->execute([
    ':batch_id' => $batchId
]);

$findings = $findingsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Siege Diagnostics Findings</title>
    
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 25px;
            background: #f4f4f4;
        }

        h1, h2 {
            margin-bottom: 10px;
        }

        .nav {
            margin-bottom: 20px;
        }

        .nav a {
            margin-right: 15px;
        }

        .finding {
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
        }

        .severity-5 {
            border-left: 8px solid #b30000;
        }

        .severity-4 {
            border-left: 8px solid #d96c00;
        }

        .severity-3 {
            border-left: 8px solid #ccaa00;
        }

        .severity-2 {
            border-left: 8px solid #0077cc;
        }

        .severity-1 {
            border-left: 8px solid #888;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 13px;
        }

        th {
            background: #eee;
            text-align: left;
        }

        ul {
            margin-top: 8px;
        }

        .meta {
            margin-bottom: 10px;
        }

        .meta strong {
            display: inline-block;
            min-width: 180px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../app/nav.php'; ?>
<h1>Siege Diagnostics</h1>
<h2>Diagnostic Findings</h2>

<div class="nav">
    <a href="/">Upload</a>
    <a href="/results.php">Results</a>
    <a href="/findings.php?batch_id=<?= e($batchId) ?>">Findings</a>
</div>

<p>
    <strong>Batch ID:</strong> <?= e($batchId) ?>
</p>

<?php if (empty($findings)): ?>

    <div class="finding">
        <h3>No findings found.</h3>
        <p>Run analyze_batch.php first.</p>
    </div>

<?php endif; ?>

<?php foreach ($findings as $finding): ?>

<?php

// A materialized finding represents Scenario -> Pattern -> Evidence. The link
// table below completes the workflow by projecting that evidence into concrete
// Call Details that a developer or operator can inspect.
$evidence = json_decode($finding['evidence'], true);

$callsStmt = $pdo->prepare("
    SELECT
        c.id,
        c.start_stamp,
        c.extension,
        c.direction,
        c.caller_id_number,
        c.destination_number,
        c.duration,
        c.billsec,
        c.call_status,
        c.sip_hangup_disposition,
        c.rtp_audio_in_mos,
        c.read_codec,
        c.write_codec
    FROM diagnostic_finding_calls fc
    JOIN cdr_records c
        ON c.id = fc.cdr_record_id
    WHERE fc.finding_id = :finding_id
    ORDER BY c.start_stamp ASC
    LIMIT 25
");

$callsStmt->execute([
    ':finding_id' => $finding['id']
]);

$calls = $callsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="finding severity-<?= e($finding['severity']) ?>">

    <h3>
        <?= e($finding['scenario']) ?>
    </h3>

    <div class="meta">
        <div>
            <strong>Diagnostic Direction:</strong>
            <?= e($finding['diagnostic_direction']) ?>
        </div>

        <div>
            <strong>Severity:</strong>
            <?= e($finding['severity']) ?>
        </div>

        <div>
            <strong>Confidence:</strong>
            <?= e($finding['confidence']) ?>%
        </div>

        <div>
            <strong>Matched Calls:</strong>
            <?= e($finding['matched_call_count']) ?>
        </div>

        <?php if (!empty($finding['group_key'])): ?>
        <div>
            <strong>Grouped By:</strong>
            <?= e($finding['group_key']) ?> =
            <?= e($finding['group_value']) ?>
        </div>
        <?php endif; ?>

    </div>

    <h4>Evidence</h4>

    <ul>
        <?php foreach ($evidence as $item): ?>
            <li><?= e($item) ?></li>
        <?php endforeach; ?>
    </ul>

    <h4>Recommended Next Step</h4>

    <p>
        <?= e($finding['recommended_next_step']) ?>
    </p>

    <h4>Matched Calls</h4>

    <table>
        <tr>
            <th>Start</th>
            <th>Extension</th>
            <th>Direction</th>
            <th>Caller</th>
            <th>Destination</th>
            <th>Duration</th>
            <th>Billsec</th>
            <th>Status</th>
            <th>Disposition</th>
            <th>MOS</th>
            <th>Codec In</th>
            <th>Codec Out</th>
        </tr>

        <?php foreach ($calls as $call): ?>

        <tr>
            <td><?= e($call['start_stamp']) ?></td>
            <td><?= e($call['extension']) ?></td>
            <td><?= e($call['direction']) ?></td>
            <td><?= e($call['caller_id_number']) ?></td>
            <td><?= e($call['destination_number']) ?></td>
            <td><?= e($call['duration']) ?></td>
            <td><?= e($call['billsec']) ?></td>
            <td><?= e($call['call_status']) ?></td>
            <td><?= e($call['sip_hangup_disposition']) ?></td>
            <td><?= e($call['rtp_audio_in_mos']) ?></td>
            <td><?= e($call['read_codec']) ?></td>
            <td><?= e($call['write_codec']) ?></td>
        </tr>

        <?php endforeach; ?>

    </table>

</div>

<?php endforeach; ?>

</body>
</html>
