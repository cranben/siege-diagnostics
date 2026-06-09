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

function json_for_db($value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

            $pdo = require __DIR__ . '/../app/db.php';
            $status = count($inspection['errors']) > 0 ? 'inspected_with_errors' : 'inspected';

            $stmt = $pdo->prepare("
                INSERT INTO diagnostics_bundle_imports (
                    original_filename,
                    collection_id,
                    generated_at,
                    collector_version,
                    schema_version,
                    manifest_json,
                    collector_json,
                    sections_json,
                    warnings_json,
                    errors_json,
                    status,
                    imported_at
                ) VALUES (
                    :original_filename,
                    :collection_id,
                    :generated_at,
                    :collector_version,
                    :schema_version,
                    :manifest_json,
                    :collector_json,
                    :sections_json,
                    :warnings_json,
                    :errors_json,
                    :status,
                    now()
                )
                RETURNING id
            ");

            $stmt->execute([
                ':original_filename' => $originalName,
                ':collection_id' => $inspection['collection_id'],
                ':generated_at' => $inspection['generated_at'],
                ':collector_version' => $inspection['collector_version'],
                ':schema_version' => $inspection['schema_version'],
                ':manifest_json' => $inspection['manifest_json'] === null ? null : json_for_db($inspection['manifest_json']),
                ':collector_json' => $inspection['collector_json'] === null ? null : json_for_db($inspection['collector_json']),
                ':sections_json' => json_for_db($inspection['sections_json']),
                ':warnings_json' => json_for_db($inspection['warnings']),
                ':errors_json' => json_for_db($inspection['errors']),
                ':status' => $status,
            ]);

            $bundleId = $stmt->fetchColumn();

            header('Location: /bundle_import.php?id=' . urlencode((string)$bundleId));
            exit;
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
    <title>Siege Diagnostics - Bundle Upload</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/../app/nav.php'; ?>

<h1>Diagnostics Bundle Upload</h1>

<?php if ($fatalError): ?>
    <p><strong>Error:</strong> <?= e($fatalError) ?></p>
    <p><a href="/">Back to Upload</a></p>
<?php endif; ?>

</body>
</html>
