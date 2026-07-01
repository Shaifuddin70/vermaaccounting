<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/email-settings');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['email_settings_errors'] = ['Invalid request. Please try again.'];
    header('Location: /admin/email-settings');
    exit;
}

$errors = [];
$rawEmails = $_POST['admin_emails'] ?? [];
if (!is_array($rawEmails)) {
    $rawEmails = [(string) $rawEmails];
}

$adminEmails = normalize_admin_emails($rawEmails);
$adminEnabled = !empty($_POST['admin_notification_enabled']);
$clientEnabled = !empty($_POST['client_confirmation_enabled']);
$adminSubject = trim((string) ($_POST['admin_notification_subject'] ?? ''));
$clientSubject = trim((string) ($_POST['client_confirmation_subject'] ?? ''));

if ($adminEnabled && $adminEmails === []) {
    $errors[] = 'Add at least one admin notification email, or turn off admin notifications.';
}

foreach ($rawEmails as $email) {
    $email = trim((string) $email);
    if ($email === '') {
        continue;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address: ' . $email;
    }
}

if ($adminEnabled && $adminSubject === '') {
    $errors[] = 'Admin notification subject is required.';
}

if ($clientEnabled && $clientSubject === '') {
    $errors[] = 'Client confirmation subject is required.';
}

if ($errors) {
    $_SESSION['email_settings_errors'] = $errors;
    $_SESSION['email_settings_old'] = [
        'admin_emails' => $rawEmails,
        'admin_notification_enabled' => $adminEnabled,
        'client_confirmation_enabled' => $clientEnabled,
        'admin_notification_subject' => $adminSubject,
        'client_confirmation_subject' => $clientSubject,
    ];
    header('Location: /admin/email-settings');
    exit;
}

$settings = [
    'admin_emails' => $adminEmails,
    'admin_notification' => [
        'enabled' => $adminEnabled,
        'subject' => $adminSubject,
    ],
    'client_confirmation' => [
        'enabled' => $clientEnabled,
        'subject' => $clientSubject,
    ],
];

$repo = new SettingsRepository();
$repo->saveMailSettings($settings);

ActivityLog::record('settings.email_updated', 'settings', null, [
    'admin_emails' => $adminEmails,
    'admin_notification_enabled' => $adminEnabled,
    'client_confirmation_enabled' => $clientEnabled,
]);

header('Location: /admin/email-settings?saved=1');
exit;
