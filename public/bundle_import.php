<?php

require_once __DIR__ . '/../app/bundle_cdr_helpers.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_value(?string $value): string
{
    return $value ?? 'Not reported';
}

$bundleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bundleId <= 0) {
    exit('Invalid diagnostics bundle import ID.');
}

$pdo = require __DIR__ . '/../app/db.php';

$stmt = $pdo->prepare("
    SELECT id, original_filename, collection_id, generated_at, collector_version,
           schema_version, sections_json, warnings_json, errors_json, status,
           imported_at
    FROM diagnostics_bundle_imports
    WHERE id = :id
");

$stmt->execute([':id' => $bundleId]);
$bundle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bundle) {
    exit('Diagnostics bundle import not found.');
}

$sectionsJson = bundle_decode_json_field($bundle['sections_json']);
$warnings = bundle_decode_json_field($bundle['warnings_json']);
$errors = bundle_decode_json_field($bundle['errors_json']);

$cdrCollectionPolicy = bundle_find_section($sectionsJson, 'cdr_collection_policy');
$cdrSelectedCallIndex = bundle_find_section($sectionsJson, 'cdr_selected_call_index');
$cdrSelectedCalls = bundle_find_section($sectionsJson, 'cdr_selected_calls');

$selectedCalls = is_array($cdrSelectedCalls) ? bundle_selected_calls($cdrSelectedCalls) : [];
$selectedCallCount = count($selectedCalls);
$selectedCallIndexCount = is_array($cdrSelectedCallIndex) ? bundle_count_like($cdrSelectedCallIndex) : null;
$selectedCallTableLimit = 25;
$visibleSelectedCalls = array_slice($selectedCalls, 0, $selectedCallTableLimit);

$collectionPolicySummary = is_array($cdrCollectionPolicy)
    ? bundle_collection_policy_summary($cdrCollectionPolicy)
    : null;

$exclusionRows = [
    'Raw CDR Body Excluded' => ['raw_cdr_body', 'raw_cdr', 'cdr_body'],
    'CDR Logs Excluded' => ['cdr_logs', 'cdr_log_body', 'cdr_log_bodies'],
    'Recording Audio Excluded' => ['recording_audio', 'audio_recording', 'recording_body'],
    'Transcript Body Excluded' => ['transcript_body', 'transcript_text', 'transcript'],
    'Transcript Summary Text Excluded' => ['transcript_summary_text', 'transcript_summary'],
];

$summaryColumns = [
    'Start Time' => [
        ['v_xml_cdr', 'start_stamp'],
        ['v_xml_cdr', 'start_epoch'],
        ['start_time'],
        ['start_stamp'],
    ],
    'Direction' => [
        ['v_xml_cdr', 'direction'],
        ['direction'],
    ],
    'Caller' => [
        ['v_xml_cdr', 'caller_id_number'],
        ['v_xml_cdr', 'caller_id_name'],
        ['caller'],
        ['caller_id_number'],
    ],
    'Destination' => [
        ['v_xml_cdr', 'destination_number'],
        ['destination'],
        ['destination_number'],
    ],
    'Duration' => [
        ['v_xml_cdr', 'duration'],
        ['duration'],
    ],
    'Status' => [
        ['v_xml_cdr', 'status'],
        ['v_xml_cdr', 'call_status'],
        ['status'],
        ['call_status'],
    ],
    'Hangup Cause' => [
        ['v_xml_cdr', 'hangup_cause'],
        ['hangup_cause'],
    ],
    'Q.850' => [
        ['v_xml_cdr', 'hangup_cause_q850'],
        ['q850'],
    ],
    'SIP Disposition' => [
        ['v_xml_cdr', 'sip_hangup_disposition'],
        ['sip_disposition'],
        ['sip_hangup_disposition'],
    ],
    'MOS' => [
        ['v_xml_cdr', 'rtp_audio_in_mos'],
        ['v_xml_cdr', 'mos'],
        ['mos'],
    ],
    'Codecs' => [
        ['v_xml_cdr', 'read_codec'],
        ['v_xml_cdr', 'write_codec'],
        ['codecs'],
    ],
    'Recording State' => [
        ['recording_metadata', 'availability_state'],
    ],
    'Transcript State' => [
        ['transcript_metadata', 'queue_status'],
    ],
    'Call Flow' => [
        ['v_xml_cdr_flow', 'present'],
    ],
];

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Siege Diagnostics - Bundle Inspection</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/../app/nav.php'; ?>

<h1>Diagnostics Bundle Inspection</h1>
<p><a href="/results.php">Back to Results</a></p>
<p>Original file: <?= e($bundle['original_filename']) ?></p>
<p>Status: <?= e($bundle['status']) ?></p>
<p>Imported at: <?= e($bundle['imported_at']) ?></p>

<h2>Bundle Metadata</h2>
<table>
    <tr>
        <th>Collection ID</th>
        <td><?= e($bundle['collection_id'] ?? 'Not found') ?></td>
    </tr>
    <tr>
        <th>Generated At</th>
        <td><?= e($bundle['generated_at'] ?? 'Not found') ?></td>
    </tr>
    <tr>
        <th>Collector Version</th>
        <td><?= e($bundle['collector_version'] ?? 'Not found') ?></td>
    </tr>
    <tr>
        <th>Schema Version</th>
        <td><?= e($bundle['schema_version'] ?? 'Not found') ?></td>
    </tr>
</table>

<h2>Selected CDR Evidence</h2>
<?php if (!$cdrCollectionPolicy && !$cdrSelectedCallIndex && !$cdrSelectedCalls): ?>
    <p>No first-class CDR evidence sections were found in this imported bundle. The generic section list below is still available.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Selected Call Count</th>
            <td><?= e((string)$selectedCallCount) ?></td>
        </tr>
        <tr>
            <th>Collection Policy Summary</th>
            <td><?= e(display_value($collectionPolicySummary)) ?></td>
        </tr>
        <tr>
            <th>Selected Call Index</th>
            <td>
                <?php if ($cdrSelectedCallIndex): ?>
                    Present<?= $selectedCallIndexCount !== null ? ' (' . e((string)$selectedCallIndexCount) . ')' : '' ?>
                <?php else: ?>
                    Missing
                <?php endif; ?>
            </td>
        </tr>
        <?php foreach ($exclusionRows as $label => $candidates): ?>
            <?php
                $status = is_array($cdrCollectionPolicy)
                    ? bundle_policy_excluded_label($cdrCollectionPolicy, $candidates)
                    : 'No';
            ?>
            <tr>
                <th><?= e($label) ?></th>
                <td><?= e($status) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>Selected Calls</h3>
    <?php if ($selectedCallCount === 0): ?>
        <p>No selected calls were stored in `cdr_selected_calls`.</p>
    <?php else: ?>
        <?php if ($selectedCallCount > $selectedCallTableLimit): ?>
            <p>Showing the first <?= e((string)$selectedCallTableLimit) ?> of <?= e((string)$selectedCallCount) ?> selected calls.</p>
        <?php endif; ?>
        <table>
            <tr>
                <?php foreach (array_keys($summaryColumns) as $columnLabel): ?>
                    <th><?= e($columnLabel) ?></th>
                <?php endforeach; ?>
                <th>Open</th>
            </tr>
            <?php foreach ($visibleSelectedCalls as $callIndex => $call): ?>
                <tr>
                    <?php foreach ($summaryColumns as $columnLabel => $paths): ?>
                        <?php
                            $display = bundle_call_display_value($call, $paths);

                            if ($columnLabel === 'Call Flow') {
                                $display = bundle_call_flow_state($call);
                            }

                            if ($columnLabel === 'Recording State') {
                                $display = bundle_recording_state($call);
                            }

                            if ($columnLabel === 'Transcript State') {
                                $display = bundle_transcript_state($call);
                            }

                            if ($columnLabel === 'Codecs' && $display !== null) {
                                $readCodec = bundle_call_display_value($call, [['v_xml_cdr', 'read_codec']]);
                                $writeCodec = bundle_call_display_value($call, [['v_xml_cdr', 'write_codec']]);

                                if ($readCodec !== null && $writeCodec !== null) {
                                    $display = $readCodec === $writeCodec ? $readCodec : $readCodec . ' / ' . $writeCodec;
                                } elseif ($readCodec !== null) {
                                    $display = $readCodec;
                                } elseif ($writeCodec !== null) {
                                    $display = $writeCodec;
                                }
                            }
                        ?>
                        <td><?= e($display ?? '-') ?></td>
                    <?php endforeach; ?>
                    <td><a href="/bundle_call.php?id=<?= e((string)$bundleId) ?>&amp;call=<?= e((string)$callIndex) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<h2>Sections Found</h2>
<table>
    <tr>
        <th>Section</th>
        <th>File</th>
        <th>Status</th>
        <th>Record Count</th>
        <th>Warnings</th>
        <th>Errors</th>
    </tr>
    <?php if (count($sectionsJson) === 0): ?>
        <tr>
            <td colspan="6">No section JSON files found.</td>
        </tr>
    <?php endif; ?>
    <?php foreach ($sectionsJson as $sectionEntry): ?>
        <?php
            $sectionFile = is_array($sectionEntry) ? ($sectionEntry['file'] ?? '') : '';
            $sectionData = is_array($sectionEntry) ? bundle_section_data($sectionEntry) : [];
            $sectionName = is_array($sectionEntry) ? bundle_section_name($sectionEntry) : 'unknown';
        ?>
        <tr>
            <td><?= e($sectionName) ?></td>
            <td><?= e($sectionFile) ?></td>
            <td><?= e(bundle_string_value($sectionData, ['status']) ?? 'unknown') ?></td>
            <td><?= e(bundle_record_count($sectionData) ?? 'Not reported') ?></td>
            <td><?= e(implode('; ', bundle_list_value($sectionData, ['warnings', 'warning']))) ?></td>
            <td><?= e(implode('; ', bundle_list_value($sectionData, ['errors', 'error']))) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Bundle Warnings</h2>
<?php if (count($warnings) === 0): ?>
    <p>None.</p>
<?php else: ?>
    <ul>
        <?php foreach ($warnings as $warning): ?>
            <li><?= e($warning) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Bundle Errors</h2>
<?php if (count($errors) === 0): ?>
    <p>None.</p>
<?php else: ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

</body>
</html>
