<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/birthday-emails');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/birthday-emails');
    exit;
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'send_test') {
    $draft = [
        'subject' => trim((string) ($_POST['subject'] ?? '')),
        'body_html' => trim((string) ($_POST['body'] ?? '')),
        'send_time' => (string) ($_POST['send_time'] ?? '09:00:00'),
        'enabled' => !empty($_POST['enabled']),
    ];
    if ($draft['subject'] === '' || $draft['body_html'] === '') {
        $settings = birthday_email_settings();
        $draft['subject'] = $draft['subject'] !== '' ? $draft['subject'] : $settings['subject'];
        $draft['body_html'] = $draft['body_html'] !== '' ? $draft['body_html'] : $settings['body_html'];
    }
    $result = birthday_send_test_email($draft);
    if (!$result['ok']) {
        $_SESSION['flash_error'] = $result['error'] ?? 'Test send failed.';
        header('Location: /admin/birthday-emails');
        exit;
    }
    $to = trim((string) ($result['to'] ?? ''));
    $_SESSION['flash_success'] = $to !== ''
        ? 'Test birthday email sent to ' . $to . '.'
        : 'Test birthday email sent.';
    header('Location: /admin/birthday-emails');
    exit;
}

if ($action === 'reset_template') {
    $current = birthday_email_settings();
    birthday_email_save_settings([
        'enabled' => $current['enabled'],
        'send_time' => $current['send_time'],
        'subject' => birthday_email_default_settings()['subject'],
        'body_html' => birthday_email_default_html(),
    ]);
    $_SESSION['flash_success'] = 'Birthday email template reset to default.';
    header('Location: /admin/birthday-emails');
    exit;
}

$_SESSION['flash_error'] = 'Unknown action.';
header('Location: /admin/birthday-emails');
exit;
