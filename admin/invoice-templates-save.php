<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('invoices.settings');

if (partner_user_id() !== null) {
    $_SESSION['flash_error'] = 'This area is only available to admins.';
    header('Location: /admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/invoice-templates');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/invoice-templates');
    exit;
}

$collect = static function (string $kind): array {
    $rows = is_array($_POST[$kind] ?? null) ? $_POST[$kind] : [];
    $default = (string) ($_POST[$kind . '_default'] ?? '');
    $out = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row) || !empty($row['delete'])) {
            continue;
        }
        $row['is_default'] = (string) $index === $default;
        $out[] = $row;
    }
    return $out;
};

$templates = ['notes' => $collect('notes'), 'emails' => $collect('emails')];
invoice_save_templates($templates);
ActivityLog::record('invoice_templates.updated', 'invoice_settings', null);

$saved = invoice_templates();
$_SESSION['flash_success'] = sprintf(
    'Templates saved: %d notes, %d email.',
    count($saved['notes']),
    count($saved['emails'])
);
header('Location: /admin/invoice-templates');
exit;
