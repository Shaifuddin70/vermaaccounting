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
$form = $repo->find($formId);
$submission = $repo->findSubmissionForForm($submissionId, $formId);

if (!$form || !$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

assert_submission_access($form, $submission);

$newStatus = $status === 'pending' ? 'pending' : 'complete';
$oldStatus = $submission['status'] ?? 'pending';
$repo->setSubmissionStatus($submissionId, $newStatus);

ActivityLog::record('submission.status_changed', 'submission', $submissionId, [
    'form_id'     => $formId,
    'from_status' => $oldStatus,
    'to_status'   => $newStatus,
    'tax_year'    => $submission['tax_year'] ?? null,
]);

$redirect = '/admin/submissions?form_id=' . $formId . '&tab=' . rawurlencode($tab);
if ($year !== '' && $year !== 'all') {
    $redirect .= '&year=' . rawurlencode($year);
} elseif ($year === 'all') {
    $redirect .= '&year=all';
}
$page = (int) ($_POST['page'] ?? 0);
if ($page > 1) {
    $redirect .= '&page=' . $page;
}
$perPage = (int) ($_POST['per_page'] ?? 0);
if ($perPage > 0 && in_array($perPage, admin_per_page_options(), true) && $perPage !== pagination_default_per_page()) {
    $redirect .= '&per_page=' . $perPage;
}
if (!empty($_POST['redirect_view'])) {
    $redirect = '/admin/submission?id=' . $submissionId . '&form_id=' . $formId;
}

header('Location: ' . $redirect);
exit;
