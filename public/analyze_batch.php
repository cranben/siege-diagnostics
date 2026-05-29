<?php

// ======================================================
// CURRENT BATCH
// ======================================================
$batchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($batchId <= 0) {
    exit('Invalid batch ID.');
}

$cmd = sprintf(
    'php %s %d 2>&1',
    escapeshellarg('/var/www/siege-diagnostics/scripts/analyze_batch.php'),
    $batchId
);

$output = shell_exec($cmd);

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analyze Batch</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
//Navigation Bar
<?php include __DIR__ . '/../app/nav.php'; ?>

<h1>Analyze Batch <?= e($batchId) ?></h1>

<pre><?= e($output) ?></pre>

<p>
    <a href="/findings.php?batch_id=<?= e($batchId) ?>">View Findings</a>
</p>

<p>
    <a href="/results.php">Back to Results</a>
</p>

</body>
</html>

