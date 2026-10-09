<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('forms.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/forms');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/forms');
    exit;
}

$ids = is_array($_POST['form_ids'] ?? null) ? $_POST['form_ids'] : [];
(new FormRepository())->saveSubmitMenuOrder($ids);
ActivityLog::record('forms.menu_reordered', 'form', null, ['order' => array_map('intval', $ids)]);
$_SESSION['flash_success'] = 'Submit Document menu order saved.';
header('Location: /admin/forms#submit-menu-order');
exit;
