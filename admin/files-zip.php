<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!Auth::verifyCsrf($csrf)) {
    http_response_code(403);
    exit('Invalid request.');
}

if (!class_exists(ZipArchive::class)) {
    http_response_code(500);
    exit('ZIP support is not available on this server.');
}

$rawIds = $_POST['file_ids'] ?? [];
if (is_string($rawIds)) {
    $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
}
if (!is_array($rawIds)) {
    $rawIds = [];
}

$uploadRepo = new UploadRepository();
$files = $uploadRepo->filesByIds($rawIds);

if ($files === []) {
    http_response_code(400);
    exit('No files selected.');
}

$tmp = tempnam(sys_get_temp_dir(), 'verma_zip_');
if ($tmp === false) {
    http_response_code(500);
    exit('Could not create archive.');
}

$zipPath = $tmp . '.zip';
@unlink($tmp);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create archive.');
}

$usedNames = [];
foreach ($files as $file) {
    $path = $uploadRepo->resolvePhysicalPath((string) ($file['stored_name'] ?? ''));
    if ($path === null) {
        continue;
    }

    $name = (string) ($file['original_name'] ?: basename($path));
    $name = str_replace(['/', '\\'], '-', $name);
    if ($name === '') {
        $name = 'file-' . (int) $file['id'];
    }

    $zipName = $name;
    $counter = 2;
    while (isset($usedNames[$zipName])) {
        $dot = strrpos($name, '.');
        if ($dot !== false) {
            $zipName = substr($name, 0, $dot) . ' (' . $counter . ')' . substr($name, $dot);
        } else {
            $zipName = $name . ' (' . $counter . ')';
        }
        $counter++;
    }
    $usedNames[$zipName] = true;

    $zip->addFile($path, $zipName);
}

$zip->close();

if (!is_file($zipPath) || filesize($zipPath) === 0) {
    @unlink($zipPath);
    http_response_code(500);
    exit('Archive is empty or could not be created.');
}

$downloadName = 'verma-files-' . date('Y-m-d-His') . '.zip';

header('Content-Type: application/zip');
header('Content-Length: ' . (string) filesize($zipPath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($zipPath);
@unlink($zipPath);
exit;
