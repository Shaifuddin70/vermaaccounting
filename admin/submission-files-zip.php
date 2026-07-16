<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

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

$formId = (int) ($_POST['form_id'] ?? 0);
$submissionId = (int) ($_POST['submission_id'] ?? 0);
$fieldId = trim((string) ($_POST['field_id'] ?? ''));

$repo = new FormRepository();
$form = $repo->find($formId);
$submission = $repo->findSubmissionForForm($submissionId, $formId);

if (!$form || !$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

assert_submission_access($form, $submission);

$files = $repo->filesForSubmission($submissionId);
if ($fieldId !== '') {
    $files = array_values(array_filter(
        $files,
        static fn(array $file): bool => (string) ($file['field_id'] ?? '') === $fieldId
    ));
}

if ($files === []) {
    http_response_code(400);
    exit('No files to download.');
}

$tmp = tempnam(sys_get_temp_dir(), 'verma_sub_zip_');
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

$uploadRepo = new UploadRepository();
$usedNames = [];
$added = 0;

foreach ($files as $file) {
    $path = $uploadRepo->resolvePhysicalPath((string) ($file['stored_name'] ?? ''));
    if ($path === null) {
        continue;
    }

    $name = (string) ($file['original_name'] ?: basename($path));
    $name = str_replace(['/', '\\'], '-', $name);
    if ($name === '') {
        $name = 'file-' . (int) ($file['id'] ?? 0);
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

    if ($zip->addFile($path, $zipName)) {
        $added++;
    }
}

$zip->close();

if ($added < 1 || !is_file($zipPath) || filesize($zipPath) === 0) {
    @unlink($zipPath);
    http_response_code(500);
    exit('Archive is empty or could not be created.');
}

$schema = $repo->decodeSchema($form);
$basename = submission_client_download_basename($submission, $schema, 'submission');
$downloadName = $basename . '.zip';

header('Content-Type: application/zip');
header('Content-Length: ' . (string) filesize($zipPath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($zipPath);
@unlink($zipPath);
exit;
