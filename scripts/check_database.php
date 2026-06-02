<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

function report_check(string $label, bool $passed, string $detail = ''): bool
{
    $status = $passed ? 'PASS' : 'FAIL';
    $suffix = $detail !== '' ? " - {$detail}" : '';

    echo "[{$status}] {$label}{$suffix}\n";

    return $passed;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_name = :table_name
        )
    ");

    $stmt->execute([
        ':table_name' => $table,
    ]);

    return (bool)$stmt->fetchColumn();
}

function load_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = :table_name
    ");

    $stmt->execute([
        ':table_name' => $table,
    ]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function index_exists(PDO $pdo, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM pg_catalog.pg_indexes
            WHERE schemaname = current_schema()
              AND indexname = :index_name
        )
    ");

    $stmt->execute([
        ':index_name' => $index,
    ]);

    return (bool)$stmt->fetchColumn();
}

function cascade_foreign_key_exists(
    PDO $pdo,
    string $table,
    string $column,
    string $referencedTable,
    string $referencedColumn
): bool {
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM pg_catalog.pg_constraint constraint_row
            JOIN pg_catalog.pg_class table_row
              ON table_row.oid = constraint_row.conrelid
            JOIN pg_catalog.pg_namespace namespace_row
              ON namespace_row.oid = table_row.relnamespace
            JOIN pg_catalog.pg_class referenced_table_row
              ON referenced_table_row.oid = constraint_row.confrelid
            JOIN pg_catalog.pg_attribute column_row
              ON column_row.attrelid = table_row.oid
             AND column_row.attnum = constraint_row.conkey[1]
            JOIN pg_catalog.pg_attribute referenced_column_row
              ON referenced_column_row.attrelid = referenced_table_row.oid
             AND referenced_column_row.attnum = constraint_row.confkey[1]
            WHERE constraint_row.contype = 'f'
              AND namespace_row.nspname = current_schema()
              AND table_row.relname = :table_name
              AND column_row.attname = :column_name
              AND referenced_table_row.relname = :referenced_table_name
              AND referenced_column_row.attname = :referenced_column_name
              AND constraint_row.confdeltype = 'c'
        )
    ");

    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
        ':referenced_table_name' => $referencedTable,
        ':referenced_column_name' => $referencedColumn,
    ]);

    return (bool)$stmt->fetchColumn();
}

$requiredTables = [
    'cdr_import_batches' => [
        'id',
        'original_filename',
        'stored_filename',
        'file_type',
        'source_type',
        'source_label',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'uploaded_at',
        'imported_at',
    ],
    'cdr_records' => [
        'id',
        'batch_id',
        'uuid',
        'domain_name',
        'domain_uuid',
        'extension',
        'caller_id_number',
        'destination_number',
        'direction',
        'start_stamp',
        'answer_stamp',
        'end_stamp',
        'duration',
        'billsec',
        'hangup_cause',
        'bridge_hangup_cause',
        'sip_hangup_disposition',
        'sip_term_status',
        'q850_cause',
        'read_codec',
        'write_codec',
        'rtp_audio_in_mos',
        'rtp_audio_in_jitter_min_variance',
        'rtp_audio_in_jitter_max_variance',
        'rtp_audio_in_packet_count',
        'rtp_audio_in_skip_packet_count',
        'remote_media_ip',
        'network_addr',
        'user_agent',
        'sip_call_id',
        'sip_from_host',
        'sip_to_host',
        'sip_req_uri',
        'sip_user_agent',
        'sip_network_ip',
        'call_direction',
        'call_status',
        'recording_file',
        'accountcode',
        'context',
        'cc_queue',
        'cc_agent',
        'cc_member_uuid',
        'destination_country',
        'destination_type',
        'raw_data',
        'created_at',
    ],
    'diagnostic_rules' => [
        'id',
        'rule_key',
        'scenario',
        'enabled',
        'severity',
        'confidence',
        'diagnostic_direction',
        'recommended_next_step',
        'conditions',
        'evidence_template',
        'created_at',
        'updated_at',
    ],
    'diagnostic_findings' => [
        'id',
        'batch_id',
        'rule_id',
        'scenario',
        'diagnostic_direction',
        'severity',
        'confidence',
        'matched_call_count',
        'group_key',
        'group_value',
        'evidence',
        'recommended_next_step',
        'created_at',
    ],
    'diagnostic_finding_calls' => [
        'finding_id',
        'cdr_record_id',
    ],
];

$requiredIndexes = [
    'idx_cdr_records_batch_id',
    'idx_cdr_records_call_status',
    'idx_cdr_records_batch_direction',
    'idx_cdr_records_batch_sip_hangup_disposition',
    'idx_cdr_records_batch_extension',
    'idx_cdr_records_batch_duration',
    'idx_cdr_records_batch_rtp_audio_in_mos',
    'idx_cdr_records_batch_call_status',
    'idx_diagnostic_rules_enabled_priority',
    'idx_diagnostic_findings_batch_priority',
    'idx_diagnostic_finding_calls_cdr_record_id',
];

$requiredCascadeForeignKeys = [
    ['cdr_records', 'batch_id', 'cdr_import_batches', 'id'],
    ['diagnostic_findings', 'batch_id', 'cdr_import_batches', 'id'],
    ['diagnostic_finding_calls', 'finding_id', 'diagnostic_findings', 'id'],
    ['diagnostic_finding_calls', 'cdr_record_id', 'cdr_records', 'id'],
];

echo "Siege Diagnostics database check\n";
echo "================================\n";

try {
    $pdo = require __DIR__ . '/../app/db.php';
    report_check('PostgreSQL connection', true);
} catch (Throwable $e) {
    report_check('PostgreSQL connection', false, $e->getMessage());
    exit(1);
}

$allPassed = true;

echo "\nRequired tables and columns\n";
echo "---------------------------\n";

foreach ($requiredTables as $table => $requiredColumns) {
    $tableExists = table_exists($pdo, $table);
    $allPassed = report_check("Table {$table}", $tableExists) && $allPassed;

    if (!$tableExists) {
        continue;
    }

    $existingColumns = load_columns($pdo, $table);

    foreach ($requiredColumns as $column) {
        $allPassed = report_check(
            "Column {$table}.{$column}",
            in_array($column, $existingColumns, true)
        ) && $allPassed;
    }
}

echo "\nRequired indexes\n";
echo "----------------\n";

foreach ($requiredIndexes as $index) {
    $allPassed = report_check("Index {$index}", index_exists($pdo, $index)) && $allPassed;
}

echo "\nRequired cascade foreign keys\n";
echo "-----------------------------\n";

foreach ($requiredCascadeForeignKeys as [$table, $column, $referencedTable, $referencedColumn]) {
    $label = "Cascade {$table}.{$column} -> {$referencedTable}.{$referencedColumn}";
    $allPassed = report_check(
        $label,
        cascade_foreign_key_exists($pdo, $table, $column, $referencedTable, $referencedColumn)
    ) && $allPassed;
}

echo "\n";

if ($allPassed) {
    echo "Database check passed.\n";
    exit(0);
}

echo "Database check failed.\n";
exit(1);
