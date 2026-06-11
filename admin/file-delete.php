<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/files');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!Auth::verifyCsrf($csrf)) {
    $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    header('Location: /admin/files');
    exit;
}

$fileId = (int) ($_POST['file_id'] ?? 0);
$returnQs = (string) ($_POST['return_qs'] ?? '');

$uploadRepo = new UploadRepository();
$file = $uploadRepo->deleteFile($fileId);

if (!$file) {
    $_SESSION['flash_error'] = 'File not found or already deleted.';
} else {
    ActivityLog::record('file.deleted', 'file', $fileId, [
        'original_name' => $file['original_name'] ?? '',
        'form_id' => (int) ($file['form_id'] ?? 0),
        'submission_id' => (int) ($file['submission_id'] ?? 0),
    ]);
    $_SESSION['flash_success'] = 'File deleted: ' . ($file['original_name'] ?? 'Unknown');
}

$redirect = '/admin/files' . ($returnQs !== '' ? '?' . $returnQs : '');
header('Location: ' . $redirect);
exit;
