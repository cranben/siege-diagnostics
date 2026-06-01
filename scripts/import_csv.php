<?php

$config = require __DIR__ . '/../app/config.php';

if ($argc < 2) {
    exit("Usage: php import_csv.php <batch_id>\n");
}

$batchId = (int)$argv[1];

$pdo = require __DIR__ . '/../app/db.php';

require_once __DIR__ . '/../app/data_sources/CdrSourceInterface.php';
require_once __DIR__ . '/../app/data_sources/CsvImportSource.php';

$source = new CsvImportSource($pdo, $config);

try {
    $result = $source->import($batchId);

    echo "Import complete.\n";
    echo "Batch ID: {$result['batch_id']}\n";
    echo "Total rows: {$result['total_rows']}\n";
    echo "Imported rows: {$result['imported_rows']}\n";
    echo "Failed rows: {$result['failed_rows']}\n";
} catch (Throwable $e) {
    $message = $e->getMessage();

    if (
        str_starts_with($message, 'Batch not found: ') ||
        str_starts_with($message, 'File not readable: ') ||
        $message === 'Could not open file.' ||
        $message === 'CSV has no header row.'
    ) {
        exit($message . "\n");
    }

    exit("Import failed: " . $message . "\n");
}
