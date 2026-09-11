<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/reviewer-submissions');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/reviewer-submissions');
    exit;
}

$submissionId = (int) ($_POST['submission_id'] ?? 0);
$formId = (int) ($_POST['form_id'] ?? 0);
$redirect = trim((string) ($_POST['redirect'] ?? ''));

$repo = new FormRepository();
$submission = $submissionId > 0 ? $repo->findSubmission($submissionId) : null;

if (!$submission) {
    $_SESSION['flash_error'] = 'Submission not found.';
    header('Location: ' . ($redirect !== '' ? $redirect : '/admin/reviewer-submissions'));
    exit;
}

if ($formId < 1) {
    $formId = (int) ($submission['form_id'] ?? 0);
}

$result = $repo->deleteSubmission($submissionId);
if (!$result['ok']) {
    $_SESSION['flash_error'] = 'Could not delete this submission.';
    header('Location: ' . ($redirect !== '' ? $redirect : '/admin/submissions?form_id=' . $formId));
    exit;
}

ActivityLog::record('submission.deleted', 'submission', $submissionId, [
    'form_id' => $formId,
    'files_removed' => $result['files_removed'],
]);

$_SESSION['flash_success'] = 'Submission #' . $submissionId . ' deleted.';

if ($redirect !== '' && str_starts_with($redirect, '/admin/')) {
    header('Location: ' . $redirect);
    exit;
}

header('Location: /admin/submissions?form_id=' . $formId);
exit;
