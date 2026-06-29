<?php

require_once __DIR__ . '/../app/bundle_cdr_helpers.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_rows(array $rows): array
{
    $display = [];

    foreach ($rows as $label => $value) {
        $string = bundle_stringify_value($value);

        if ($string !== null) {
            $display[$label] = $string;
        }
    }

    return $display;
}

function display_optional_value($value, string $empty = '-'): string
{
    $string = bundle_stringify_value($value);
    return $string ?? $empty;
}

function display_metadata_rows(array $rows): array
{
    $display = [];

    foreach ($rows as $label => $config) {
        $value = $config['value'] ?? null;
        $empty = $config['empty'] ?? '-';
        $display[$label] = display_optional_value($value, $empty);
    }

    return $display;
}

function pretty_json_block($value): ?string
{
    if (!is_array($value)) {
        return null;
    }

    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : null;
}

$bundleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$callIndex = isset($_GET['call']) ? (int)$_GET['call'] : -1;

if ($bundleId <= 0) {
    exit('Invalid diagnostics bundle import ID.');
}

if ($callIndex < 0) {
    exit('Invalid selected call index.');
}

$pdo = require __DIR__ . '/../app/db.php';

$stmt = $pdo->prepare("
    SELECT id, original_filename, collection_id, status, imported_at, sections_json
    FROM diagnostics_bundle_imports
    WHERE id = :id
");

$stmt->execute([':id' => $bundleId]);
$bundle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bundle) {
    exit('Diagnostics bundle import not found.');
}

$sectionsJson = bundle_decode_json_field($bundle['sections_json']);
$cdrSelectedCalls = bundle_find_section($sectionsJson, 'cdr_selected_calls');

if (!$cdrSelectedCalls) {
    exit('Selected CDR evidence was not found in this imported bundle.');
}

$selectedCalls = bundle_selected_calls($cdrSelectedCalls);
$call = bundle_safe_call($selectedCalls, $callIndex);

if ($call === null) {
    exit('Selected call evidence not found for that call index.');
}

$recordingMetadataSource = isset($call['recording_metadata']) && is_array($call['recording_metadata'])
    ? $call['recording_metadata']
    : null;
$transcriptMetadataSource = isset($call['transcript_metadata']) && is_array($call['transcript_metadata'])
    ? $call['transcript_metadata']
    : null;
$callFlowSource = isset($call['v_xml_cdr_flow']) && is_array($call['v_xml_cdr_flow'])
    ? $call['v_xml_cdr_flow']
    : null;

$callSummary = display_rows([
    'Call Index' => $callIndex,
    'Start Time' => bundle_call_value($call, [
        ['v_xml_cdr', 'start_stamp'],
        ['v_xml_cdr', 'start_epoch'],
        ['start_time'],
        ['start_stamp'],
    ]),
    'Direction' => bundle_call_value($call, [
        ['v_xml_cdr', 'direction'],
        ['direction'],
    ]),
    'Caller' => bundle_call_value($call, [
        ['v_xml_cdr', 'caller_id_number'],
        ['v_xml_cdr', 'caller_id_name'],
        ['caller'],
        ['caller_id_number'],
    ]),
    'Destination' => bundle_call_value($call, [
        ['v_xml_cdr', 'destination_number'],
        ['destination'],
        ['destination_number'],
    ]),
    'Duration' => bundle_call_value($call, [
        ['v_xml_cdr', 'duration'],
        ['duration'],
    ]),
    'Status' => bundle_call_value($call, [
        ['v_xml_cdr', 'status'],
        ['v_xml_cdr', 'call_status'],
        ['status'],
        ['call_status'],
    ]),
    'Hangup Cause' => bundle_call_value($call, [
        ['v_xml_cdr', 'hangup_cause'],
        ['hangup_cause'],
    ]),
    'Q.850' => bundle_call_value($call, [
        ['v_xml_cdr', 'hangup_cause_q850'],
        ['q850'],
    ]),
    'SIP Disposition' => bundle_call_value($call, [
        ['v_xml_cdr', 'sip_hangup_disposition'],
        ['sip_disposition'],
        ['sip_hangup_disposition'],
    ]),
    'MOS' => bundle_call_value($call, [
        ['v_xml_cdr', 'rtp_audio_in_mos'],
        ['v_xml_cdr', 'mos'],
        ['mos'],
    ]),
    'Read Codec' => bundle_call_value($call, [
        ['v_xml_cdr', 'read_codec'],
        ['read_codec'],
    ]),
    'Write Codec' => bundle_call_value($call, [
        ['v_xml_cdr', 'write_codec'],
        ['write_codec'],
    ]),
]);

$routingAndFlow = display_rows([
    'Call UUID' => bundle_call_value($call, [
        ['v_xml_cdr', 'uuid'],
        ['uuid'],
    ]),
    'Bridge UUID' => bundle_call_value($call, [
        ['v_xml_cdr', 'bridge_uuid'],
        ['bridge_uuid'],
    ]),
    'Context' => bundle_call_value($call, [
        ['v_xml_cdr', 'context'],
        ['context'],
    ]),
    'Domain Name' => bundle_call_value($call, [
        ['v_xml_cdr', 'domain_name'],
        ['domain_name'],
    ]),
    'Hostname' => bundle_call_value($call, [
        ['v_xml_cdr', 'hostname'],
        ['hostname'],
    ]),
    'Caller Destination' => bundle_call_value($call, [
        ['v_xml_cdr', 'caller_destination'],
        ['caller_destination'],
    ]),
    'Last App' => bundle_call_value($call, [
        ['v_xml_cdr', 'last_app'],
        ['last_app'],
    ]),
    'Last Arg' => bundle_call_value($call, [
        ['v_xml_cdr', 'last_arg'],
        ['last_arg'],
    ]),
    'Caller ID Name' => bundle_call_value($call, [
        ['v_xml_cdr', 'caller_id_name'],
        ['caller_id_name'],
    ]),
    'Call Flow State' => bundle_call_flow_state($call),
]);

$recordingMetadata = $recordingMetadataSource === null
    ? []
    : display_metadata_rows([
        'Recording State' => [
            'value' => $recordingMetadataSource['availability_state'] ?? null,
        ],
        'Record Path' => [
            'value' => $recordingMetadataSource['record_path'] ?? null,
        ],
        'Record Name' => [
            'value' => $recordingMetadataSource['record_name'] ?? null,
        ],
        'Record Length' => [
            'value' => $recordingMetadataSource['record_length'] ?? null,
        ],
        'File Exists' => [
            'value' => $recordingMetadataSource['file_exists'] ?? null,
        ],
        'Size Bytes' => [
            'value' => $recordingMetadataSource['size_bytes'] ?? null,
        ],
        'Modified Time' => [
            'value' => $recordingMetadataSource['mtime'] ?? null,
        ],
        'Extension' => [
            'value' => $recordingMetadataSource['extension'] ?? null,
        ],
        'MIME Guess' => [
            'value' => $recordingMetadataSource['mime_guess'] ?? null,
        ],
        'Warnings' => [
            'value' => $recordingMetadataSource['warnings'] ?? null,
            'empty' => 'None',
        ],
    ]);

$transcriptRequested = $transcriptMetadataSource !== null
    ? bundle_boolish($transcriptMetadataSource['requested'] ?? null)
    : null;
$transcriptRequestedDisplay = $transcriptRequested === false
    ? 'Not requested'
    : display_optional_value($transcriptMetadataSource['requested'] ?? null);

$transcriptMetadata = $transcriptMetadataSource === null
    ? []
    : display_metadata_rows([
        'Requested' => [
            'value' => $transcriptRequestedDisplay,
        ],
        'Queue UUID' => [
            'value' => $transcriptMetadataSource['queue_uuid'] ?? null,
        ],
        'Queue Status' => [
            'value' => $transcriptMetadataSource['queue_status'] ?? null,
        ],
        'Duration' => [
            'value' => $transcriptMetadataSource['duration'] ?? null,
        ],
        'Hostname' => [
            'value' => $transcriptMetadataSource['hostname'] ?? null,
        ],
        'Audio Path' => [
            'value' => $transcriptMetadataSource['audio_path'] ?? null,
        ],
        'Audio Name' => [
            'value' => $transcriptMetadataSource['audio_name'] ?? null,
        ],
        'Transcript Row Present' => [
            'value' => $transcriptMetadataSource['transcript_row_present'] ?? null,
        ],
        'JSON Valid' => [
            'value' => $transcriptMetadataSource['json_valid'] ?? null,
        ],
        'Segment Count' => [
            'value' => $transcriptMetadataSource['segment_count'] ?? null,
        ],
        'Summary Present' => [
            'value' => $transcriptMetadataSource['summary_present'] ?? null,
        ],
        'Summary Length' => [
            'value' => $transcriptMetadataSource['summary_length'] ?? null,
        ],
    ]);

$callFlowEvidenceMetadata = [];
$callFlowEvidenceJson = null;

if ($callFlowSource !== null) {
    $callFlowEvidenceMetadata = display_metadata_rows([
        'Present' => [
            'value' => $callFlowSource['present'] ?? null,
        ],
        'Size Bytes' => [
            'value' => $callFlowSource['size_bytes'] ?? null,
        ],
        'SHA256' => [
            'value' => $callFlowSource['sha256'] ?? null,
        ],
        'JSON Valid' => [
            'value' => $callFlowSource['json_valid'] ?? null,
        ],
    ]);

    $flowPresent = bundle_boolish($callFlowSource['present'] ?? null);
    $callFlowValue = $callFlowSource['call_flow'] ?? null;
    if ($flowPresent === true && is_array($callFlowValue)) {
        $callFlowEvidenceJson = pretty_json_block($callFlowValue);
    }
}

$rawCdrProvenance = display_rows([
    'Section Status' => bundle_call_value($cdrSelectedCalls, [
        ['status'],
    ]),
    'Source File' => bundle_call_value($call, [
        ['source_file'],
        ['provenance', 'source_file'],
        ['v_xml_cdr', 'xml_cdr_filename'],
    ]),
    'XML CDR UUID' => bundle_call_value($call, [
        ['v_xml_cdr', 'xml_cdr_uuid'],
        ['v_xml_cdr', 'uuid'],
    ]),
    'SIP Call ID' => bundle_call_value($call, [
        ['v_xml_cdr', 'sip_call_id'],
        ['sip_call_id'],
    ]),
    'Import Record ID' => bundle_call_value($call, [
        ['import_record_id'],
        ['provenance', 'import_record_id'],
    ]),
    'Stored Keys' => implode(', ', array_keys($call)),
    'Stored v_xml_cdr Keys' => isset($call['v_xml_cdr']) && is_array($call['v_xml_cdr'])
        ? implode(', ', array_keys($call['v_xml_cdr']))
        : null,
]);

$failedImportMetadata = display_rows([
    'Import Status' => bundle_call_value($call, [
        ['import_status'],
        ['failed_import', 'status'],
    ]),
    'Failure Reason' => bundle_call_value($call, [
        ['failure_reason'],
        ['failed_import', 'reason'],
        ['error'],
    ]),
    'Failure Code' => bundle_call_value($call, [
        ['failure_code'],
        ['failed_import', 'code'],
    ]),
    'Warnings' => bundle_call_value($call, [
        ['warnings'],
        ['failed_import', 'warnings'],
    ]),
    'Errors' => bundle_call_value($call, [
        ['errors'],
        ['failed_import', 'errors'],
    ]),
]);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Siege Diagnostics - Selected Call Evidence</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/../app/nav.php'; ?>

<h1>Selected Call Evidence</h1>
<p><a href="/bundle_import.php?id=<?= e((string)$bundleId) ?>">Back to Bundle</a></p>
<p>Bundle ID: <?= e($bundle['id']) ?></p>
<p>Original file: <?= e($bundle['original_filename']) ?></p>
<p>Collection ID: <?= e($bundle['collection_id'] ?? 'Not found') ?></p>

<?php
$sectionsToRender = [
    'Call Summary' => $callSummary,
    'Routing and Call Flow' => $routingAndFlow,
    'Recording Metadata' => $recordingMetadata,
    'Transcript Metadata' => $transcriptMetadata,
    'Raw CDR Provenance' => $rawCdrProvenance,
    'Failed Import Metadata' => $failedImportMetadata,
];
?>

<?php foreach ($sectionsToRender as $heading => $rows): ?>
    <h2><?= e($heading) ?></h2>
    <?php if (count($rows) === 0): ?>
        <p>No data reported for this section.</p>
    <?php else: ?>
        <table>
            <?php foreach ($rows as $label => $value): ?>
                <tr>
                    <th><?= e($label) ?></th>
                    <td><?= e($value) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($heading === 'Routing and Call Flow' && $callFlowEvidenceJson !== null): ?>
        <h3>Call Flow Evidence</h3>
        <table>
            <?php foreach ($callFlowEvidenceMetadata as $label => $value): ?>
                <tr>
                    <th><?= e($label) ?></th>
                    <td><?= e($value) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <pre><code><?= e($callFlowEvidenceJson) ?></code></pre>
    <?php endif; ?>
<?php endforeach; ?>

</body>
</html>
