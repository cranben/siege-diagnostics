<?php

// Planned direct-source adapter. A future implementation should write the same
// normalized cdr_records shape as the CSV path so analysis remains source-agnostic.
class FusionPbxPostgresSource implements CdrSourceInterface
{
    public function __construct(
        private PDO $pdo,
        private array $config
    ) {}

    public function import(int $batchId): array
    {
        // Do not partially implement this path without defining batch tracking
        // and normalization parity with scripts/import_csv.php.
        throw new Exception("Not implemented yet.");
    }
}
