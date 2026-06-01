<?php

// Scaffold for moving the current CSV importer behind CdrSourceInterface.
// The live implementation still exists in scripts/import_csv.php; preserve its
// normalization behavior when this source adapter is completed.
$config = require __DIR__ . '/../app/config.php';
$pdo = require __DIR__ . '/../app/db.php';
