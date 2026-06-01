<?php

// All CDR sources should converge on the same normalized cdr_records model.
// Keeping the import contract source-agnostic lets the diagnostic engine operate
// on CSV uploads today and direct PBX integrations later without branching rules.
interface CdrSourceInterface
{
    public function import(int $batchId): array;
}
