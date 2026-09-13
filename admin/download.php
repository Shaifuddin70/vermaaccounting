<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$fileId = (int) ($_GET['file_id'] ?? 0);
if ($fileId < 1) {
    http_response_code(400);
    exit('Invalid file.');
}

$repo = new FormRepository();
$file = $repo->findSubmissionFile($fileId);

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$form = $repo->find((int) ($file['form_id'] ?? 0));
$submission = $repo->findSubmission((int) ($file['submission_id'] ?? 0));
if (!$form || !$submission) {
    http_response_code(404);
    exit('File not found.');
}

assert_submission_access($form, $submission);

$path = UPLOADS_DIR . '/' . $file['stored_name'];
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('File missing on server.');
}

$realBase = realpath(UPLOADS_DIR);
$realPath = realpath($path);
if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    exit('Access denied.');
}

$name = $file['original_name'] ?: basename($path);
$mime = $file['mime'] ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($realPath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($realPath);
exit;
