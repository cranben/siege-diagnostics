<?php

require_once __DIR__ . '/../app/BundleInspector.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function upload_error_message(int $error): string {
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded ZIP is too large.',
        UPLOAD_ERR_PARTIAL => 'Uploaded ZIP was only partially received.',
        UPLOAD_ERR_NO_FILE => 'No ZIP file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing an upload temp directory.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded ZIP.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        default => 'Upload failed.',
    };
}

$inspection = null;
$fatalError = null;
$originalName = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fatalError = 'Upload a diagnostics bundle from the home page.';
} elseif (!isset($_FILES['bundle_zip'])) {
    $fatalError = 'No ZIP file was uploaded.';
} else {
    $file = $_FILES['bundle_zip'];
    $originalName = basename((string)$file['name']);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $fatalError = upload_error_message((int)$file['error']);
    } elseif (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
        $fatalError = 'Only .zip diagnostics bundles are allowed.';
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $fatalError = 'Uploaded file could not be verified.';
    } else {
        try {
            $inspector = new BundleInspector();
            $inspection = $inspector->inspect($file['tmp_name']);
        } catch (Throwable $e) {
            $fatalError = $e->getMessage();
        }
    }
}

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

<?php if ($fatalError): ?>
    <p><strong>Error:</strong> <?= e($fatalError) ?></p>
    <p><a href="/">Back to Upload</a></p>
<?php elseif ($inspection): ?>
    <p>Original file: <?= e($originalName) ?></p>

    <h2>Bundle Metadata</h2>
    <table>
        <tr>
            <th>Collection ID</th>
            <td><?= e($inspection['collection_id'] ?? 'Not found') ?></td>
        </tr>
        <tr>
            <th>Generated At</th>
            <td><?= e($inspection['generated_at'] ?? 'Not found') ?></td>
        </tr>
        <tr>
            <th>Collector Version</th>
            <td><?= e($inspection['collector_version'] ?? 'Not found') ?></td>
        </tr>
        <tr>
            <th>Schema Version</th>
            <td><?= e($inspection['schema_version'] ?? 'Not found') ?></td>
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
        <?php if (count($inspection['sections']) === 0): ?>
            <tr>
                <td colspan="6">No section JSON files found.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($inspection['sections'] as $section): ?>
            <tr>
                <td><?= e($section['name']) ?></td>
                <td><?= e($section['file']) ?></td>
                <td><?= e($section['status']) ?></td>
                <td><?= e($section['record_count'] ?? 'Not reported') ?></td>
                <td><?= e(implode('; ', $section['warnings'])) ?></td>
                <td><?= e(implode('; ', $section['errors'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Bundle Warnings</h2>
    <?php if (count($inspection['warnings']) === 0): ?>
        <p>None.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($inspection['warnings'] as $warning): ?>
                <li><?= e($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Bundle Errors</h2>
    <?php if (count($inspection['errors']) === 0): ?>
        <p>None.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($inspection['errors'] as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="/">Inspect another bundle</a></p>
<?php endif; ?>

</body>
</html>
