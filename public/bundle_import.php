<?php

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function decode_json_field($value): array {
    if (!is_string($value) || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function string_value(array $data, array $keys): ?string {
    foreach ($keys as $key) {
        if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }

    return null;
}

function list_value(array $data, array $keys): array {
    foreach ($keys as $key) {
        if (!isset($data[$key])) {
            continue;
        }

        if (is_array($data[$key])) {
            return array_map('strval', $data[$key]);
        }

        if (trim((string)$data[$key]) !== '') {
            return [(string)$data[$key]];
        }
    }

    return [];
}

function record_count(array $section): ?int {
    foreach (['record_count', 'records_count', 'count'] as $key) {
        if (isset($section[$key]) && is_numeric($section[$key])) {
            return (int)$section[$key];
        }
    }

    if (isset($section['records']) && is_array($section['records'])) {
        return count($section['records']);
    }

    return null;
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

$sectionsJson = decode_json_field($bundle['sections_json']);
$warnings = decode_json_field($bundle['warnings_json']);
$errors = decode_json_field($bundle['errors_json']);

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
            $sectionData = is_array($sectionEntry) && isset($sectionEntry['data']) && is_array($sectionEntry['data'])
                ? $sectionEntry['data']
                : [];
            $sectionName = string_value($sectionData, ['section', 'section_name', 'name'])
                ?? basename((string)$sectionFile, '.json');
        ?>
        <tr>
            <td><?= e($sectionName) ?></td>
            <td><?= e($sectionFile) ?></td>
            <td><?= e(string_value($sectionData, ['status']) ?? 'unknown') ?></td>
            <td><?= e(record_count($sectionData) ?? 'Not reported') ?></td>
            <td><?= e(implode('; ', list_value($sectionData, ['warnings', 'warning']))) ?></td>
            <td><?= e(implode('; ', list_value($sectionData, ['errors', 'error']))) ?></td>
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
