<?php

$config = require __DIR__ . '/../app/config.php';
$pdo = require __DIR__ . '/../app/db.php';

$batches = $pdo->query("
    SELECT id, original_filename, status, total_rows, imported_rows, failed_rows, uploaded_at, imported_at
    FROM cdr_import_batches
    ORDER BY id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$bundleImports = $pdo->query("
    SELECT id, original_filename, collection_id, generated_at, collector_version,
           schema_version, status, imported_at
    FROM diagnostics_bundle_imports
    ORDER BY imported_at DESC, id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// These summaries are currently system-wide, not scoped to the selected batch.
// Keep that distinction visible if this page later grows batch-specific views.
$statusCounts = $pdo->query("
    SELECT COALESCE(call_status, 'unknown') AS call_status, COUNT(*) AS total
    FROM cdr_records
    GROUP BY call_status
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$recentCalls = $pdo->query("
    SELECT start_stamp, call_status, direction, caller_id_number, destination_number,
           duration, billsec, sip_hangup_disposition, rtp_audio_in_mos, read_codec, write_codec
    FROM cdr_records
    ORDER BY id DESC
    LIMIT 25
")->fetchAll(PDO::FETCH_ASSOC);

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Siege Diagnostics - Import Results</title>
    <link rel="stylesheet" href="/assets/css/app.css">
     <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #ccc; padding: 8px; font-size: 14px; }
        th { background: #eee; text-align: left; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; }
    </style>
</head>
<body>

<!-- Menu -->
<?php include __DIR__ . '/../app/nav.php'; ?>

<h1>Siege Diagnostics</h1>
<h2>Import Results</h2>

<div class="nav">
    <a href="/">Upload CDR</a>
    <a href="/results.php">Results</a>
</div>

<h3>Diagnostics Bundle Imports</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Original File</th>
        <th>Collection ID</th>
        <th>Generated At</th>
        <th>Collector Version</th>
        <th>Schema Version</th>
        <th>Status</th>
        <th>Imported At</th>
        <th>View</th>
    </tr>
    <?php if (count($bundleImports) === 0): ?>
        <tr>
            <td colspan="9">No diagnostics bundle imports yet.</td>
        </tr>
    <?php endif; ?>
    <?php foreach ($bundleImports as $bundle): ?>
        <tr>
            <td><?= e($bundle['id']) ?></td>
            <td><?= e($bundle['original_filename']) ?></td>
            <td><?= e($bundle['collection_id']) ?></td>
            <td><?= e($bundle['generated_at']) ?></td>
            <td><?= e($bundle['collector_version']) ?></td>
            <td><?= e($bundle['schema_version']) ?></td>
            <td><?= e($bundle['status']) ?></td>
            <td><?= e($bundle['imported_at']) ?></td>
            <td><a href="/bundle_import.php?id=<?= e($bundle['id']) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
</table>


<!-- Batch Table Header -->

<h3>Recent CDR Import Batches</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Original File</th>
        <th>Status</th>
        <th>Total</th>
        <th>Imported</th>
        <th>Failed</th>
        <th>Uploaded</th>
        <th>Imported At</th>
	<th>Import</th>
	<th>Analyze</th> 
	<th>Findings</th>
	<th>Delete</th>
    
</tr> 

<!-- Batch Row Loop  -->

    <?php foreach ($batches as $batch): ?>

        <tr>
            <td><?= e($batch['id']) ?></td>
            <td><?= e($batch['original_filename']) ?></td>
            <td><?= e($batch['status']) ?></td>
            <td><?= e($batch['total_rows']) ?></td>
            <td><?= e($batch['imported_rows']) ?></td>
            <td><?= e($batch['failed_rows']) ?></td>
            <td><?= e($batch['uploaded_at']) ?></td>
            <td><?= e($batch['imported_at']) ?></td>
	    
	    <td>
		<?php if ($batch['status'] === 'uploaded' || $batch['imported_rows'] == 0): ?>
                    <a href="/import_batch.php?id=<?= e($batch['id']) ?>">Import</a>
            	<?php else: ?>
                    Imported
             	<?php endif; ?>
       	    </td>

            <td>
                <?php if ($batch['imported_rows'] > 0): ?>
		    <a href="/analyze_batch.php?id=<?= e($batch['id']) ?>">Analyze</a>
		<?php else: ?>
                    Import first
                <?php endif; ?>
	    </td>
	    
	    <td>
                <?php if ($batch['imported_rows'] > 0): ?>
                    <a href="/findings.php?batch_id=<?= e($batch['id']) ?>">View</a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>

            <td>
                <form method="post" action="/delete_batch.php" onsubmit="return confirm('Delete this import batch and its related call data?');">
                    <input type="hidden" name="batch_id" value="<?= e($batch['id']) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Call Status Counts</h3>
<table>
    <tr>
        <th>Status</th>
        <th>Total</th>
    </tr>
    <?php foreach ($statusCounts as $row): ?>
        <tr>
            <td><?= e($row['call_status']) ?></td>
            <td><?= e($row['total']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Recent Imported Calls</h3>
<table>
    <tr>
        <th>Start</th>
        <th>Status</th>
        <th>Direction</th>
        <th>Caller</th>
        <th>Destination</th>
        <th>Duration</th>
        <th>Billsec</th>
        <th>Hangup Disposition</th>
        <th>MOS</th>
        <th>Read Codec</th>
        <th>Write Codec</th>
    </tr>
    <?php foreach ($recentCalls as $call): ?>
        <tr>
            <td><?= e($call['start_stamp']) ?></td>
            <td><?= e($call['call_status']) ?></td>
            <td><?= e($call['direction']) ?></td>
            <td><?= e($call['caller_id_number']) ?></td>
            <td><?= e($call['destination_number']) ?></td>
            <td><?= e($call['duration']) ?></td>
            <td><?= e($call['billsec']) ?></td>
            <td><?= e($call['sip_hangup_disposition']) ?></td>
            <td><?= e($call['rtp_audio_in_mos']) ?></td>
            <td><?= e($call['read_codec']) ?></td>
            <td><?= e($call['write_codec']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
