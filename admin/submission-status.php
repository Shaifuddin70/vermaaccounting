<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid session.');
}

$submissionId = (int) ($_POST['submission_id'] ?? 0);
$formId = (int) ($_POST['form_id'] ?? 0);
$status = (string) ($_POST['status'] ?? 'complete');
$tab = (string) ($_POST['tab'] ?? 'all');
$year = (string) ($_POST['year'] ?? '');

$repo = new FormRepository();
$submission = $repo->findSubmissionForForm($submissionId, $formId);

if (!$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

$newStatus = $status === 'pending' ? 'pending' : 'complete';
$oldStatus = $submission['status'] ?? 'pending';
$repo->setSubmissionStatus($submissionId, $newStatus);

ActivityLog::record('submission.status_changed', 'submission', $submissionId, [
    'form_id'     => $formId,
    'from_status' => $oldStatus,
    'to_status'   => $newStatus,
    'tax_year'    => $submission['tax_year'] ?? null,
]);

$redirect = '/admin/submissions.php?form_id=' . $formId . '&tab=' . rawurlencode($tab);
if ($year !== '' && $year !== 'all') {
    $redirect .= '&year=' . rawurlencode($year);
} elseif ($year === 'all') {
    $redirect .= '&year=all';
}
if (!empty($_POST['redirect_view'])) {
    $redirect = '/admin/submission.php?id=' . $submissionId . '&form_id=' . $formId;
}

header('Location: ' . $redirect);
exit;
