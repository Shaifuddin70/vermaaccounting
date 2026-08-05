<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/invoice-settings');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/invoice-settings');
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$errors = [];
if ($name === '') {
    $errors[] = 'Company name is required.';
}

if ($errors !== []) {
    $_SESSION['invoice_settings_errors'] = $errors;
    $_SESSION['invoice_settings_old'] = $_POST;
    header('Location: /admin/invoice-settings');
    exit;
}

invoice_save_company_settings($_POST);
ActivityLog::record('invoice_settings.updated', 'invoice_settings', null);
$_SESSION['flash_success'] = 'Invoice company details saved.';
header('Location: /admin/invoice-settings');
exit;
