<?php

$config = require __DIR__ . '/config.php';

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $config['db']['host'],
    $config['db']['port'],
    $config['db']['name']
);

return new PDO(
    $dsn,
    $config['db']['user'],
    $config['db']['pass'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);
