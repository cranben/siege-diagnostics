<?php

$config = require __DIR__ . '/../app/config.php';

if (!isset($_FILES['cdr_file']) || $_FILES['cdr_file']['error'] !== UPLOAD_ERR_OK) {
    exit('Upload failed.');
}

$originalName = basename($_FILES['cdr_file']['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($extension !== 'csv') {
    exit('Only CSV files are allowed for now.');
}

// Store an opaque server-side name while retaining the original filename in the
// batch ledger. This avoids using user-controlled filenames as storage paths.
$storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.csv';
$targetPath = rtrim($config['upload_dir'], '/') . '/' . $storedName;

if (!move_uploaded_file($_FILES['cdr_file']['tmp_name'], $targetPath)) {
    exit('Could not save uploaded file.');
}

$pdo = require __DIR__ . '/../app/db.php';

// Upload and import are intentionally separate stages. The batch row records the
// stored artifact and gives later import and analysis work a stable identifier.
// Production changes should keep file storage and this ledger entry consistent
// if either operation fails.
$stmt = $pdo->prepare("
    INSERT INTO cdr_import_batches
    (original_filename, stored_filename, file_type, status)
    VALUES
    (:original_filename, :stored_filename, :file_type, 'uploaded')
");

$stmt->execute([
    ':original_filename' => $originalName,
    ':stored_filename' => $storedName,
    ':file_type' => 'csv',
]);

echo '<h1>Upload Successful</h1>';
echo '<p><a href="/results.php">View import results</a></p>';
echo '<p>Original file: ' . htmlspecialchars($originalName) . '</p>';
echo '<p>Stored file: ' . htmlspecialchars($storedName) . '</p>';
echo '<p>Status: uploaded</p>';
echo '<p><a href="/">Upload another file</a></p>';
