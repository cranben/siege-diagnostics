<?php

interface CdrSourceInterface
{
    public function import(int $batchId): array;
}
