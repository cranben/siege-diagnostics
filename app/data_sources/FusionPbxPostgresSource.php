<?php

class FusionPbxPostgresSource implements CdrSourceInterface
{
    public function __construct(
        private PDO $pdo,
        private array $config
    ) {}

    public function import(int $batchId): array
    {
        throw new Exception("Not implemented yet.");
    }
}
