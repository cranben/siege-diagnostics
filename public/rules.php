<?php

// ======================================================
// CONFIGURATION AND DATABASE CONNECTION
// ======================================================

$config = require __DIR__ . '/../app/config.php';
$pdo = require __DIR__ . '/../app/db.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ======================================================
// ENABLE / DISABLE RULE ACTION
// ======================================================

// Rules are operationally tunable without changing analysis code. In production,
// protect this mutation path with the same authorization and request-safety
// controls used for other administrative actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ruleId = isset($_POST['rule_id']) ? (int)$_POST['rule_id'] : 0;
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? 'true' : 'false';

    if ($ruleId > 0) {
        $stmt = $pdo->prepare("
            UPDATE diagnostic_rules
            SET enabled = :enabled,
                updated_at = now()
            WHERE id = :id
        ");

        $stmt->execute([
            ':enabled' => $enabled,
            ':id' => $ruleId,
        ]);
    }

    header('Location: /rules.php');
    exit;
}

// ======================================================
// LOAD RULES
// ======================================================

// Conditions describe the Pattern and evidence_template describes the human
// explanation later copied into a finding. Both are JSON-backed rule contracts.
$rules = $pdo->query("
    SELECT
        id,
        rule_key,
        scenario,
        enabled,
        severity,
        confidence,
        diagnostic_direction,
        recommended_next_step,
        conditions,
        evidence_template,
        updated_at
    FROM diagnostic_rules
    ORDER BY scenario, severity DESC, confidence DESC, rule_key
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Diagnostic Rules</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

<?php include __DIR__ . '/../app/nav.php'; ?>

<h1>Siege Diagnostics</h1>
<h2>Diagnostic Rules</h2>

<p>
    These rules are the tunable logic used by the analysis engine. Disable a rule if it creates noisy findings.
</p>

<table>
    <tr>
        <th>ID</th>
        <th>Enabled</th>
        <th>Scenario</th>
        <th>Rule Key</th>
        <th>Severity</th>
        <th>Confidence</th>
        <th>Diagnostic Direction</th>
        <th>Recommended Next Step</th>
        <th>Conditions</th>
        <th>Evidence Template</th>
        <th>Action</th>
    </tr>

    <?php foreach ($rules as $rule): ?>
        <tr>
            <td><?= e($rule['id']) ?></td>
            <td><?= $rule['enabled'] ? 'Yes' : 'No' ?></td>
            <td><?= e($rule['scenario']) ?></td>
            <td><?= e($rule['rule_key']) ?></td>
            <td><?= e($rule['severity']) ?></td>
            <td><?= e($rule['confidence']) ?>%</td>
            <td><?= e($rule['diagnostic_direction']) ?></td>
            <td><?= e($rule['recommended_next_step']) ?></td>
            <td><pre><?= e(json_encode(json_decode($rule['conditions'], true), JSON_PRETTY_PRINT)) ?></pre></td>
            <td><pre><?= e(json_encode(json_decode($rule['evidence_template'], true), JSON_PRETTY_PRINT)) ?></pre></td>
            <td>
                <form method="post" action="/rules.php">
                    <input type="hidden" name="rule_id" value="<?= e($rule['id']) ?>">
                    <?php if ($rule['enabled']): ?>
                        <input type="hidden" name="enabled" value="0">
                        <button type="submit">Disable</button>
                    <?php else: ?>
                        <input type="hidden" name="enabled" value="1">
                        <button type="submit">Enable</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
