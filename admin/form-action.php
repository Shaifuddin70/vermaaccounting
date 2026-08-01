<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/forms');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/forms');
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$formId = (int) ($_POST['form_id'] ?? 0);
$repo = new FormRepository();
$form = $formId > 0 ? $repo->find($formId) : null;

if (!$form) {
    $_SESSION['flash_error'] = 'Form not found.';
    header('Location: /admin/forms');
    exit;
}

if ($action === 'delete') {
    $title = (string) ($form['title'] ?? '');
    $slug = (string) ($form['slug'] ?? '');
    $counts = $repo->submissionCountsByFormId();
    $subCount = (int) ($counts[$formId]['all'] ?? 0);

    if (!$repo->delete($formId)) {
        $_SESSION['flash_error'] = 'Could not delete the form.';
        header('Location: /admin/forms');
        exit;
    }

    ActivityLog::record('form.deleted', 'form', $formId, [
        'title' => $title,
        'slug' => $slug,
        'submissions' => $subCount,
    ]);
    $_SESSION['flash_success'] = $subCount > 0
        ? 'Form deleted along with ' . number_format($subCount) . ' submission' . ($subCount === 1 ? '' : 's') . '.'
        : 'Form deleted.';
    header('Location: /admin/forms');
    exit;
}

$_SESSION['flash_error'] = 'Unknown action.';
header('Location: /admin/forms');
exit;
