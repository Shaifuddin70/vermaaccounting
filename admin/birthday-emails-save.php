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

$subject = trim((string) ($_POST['subject'] ?? ''));
$body = campaign_email_normalize_year_token(trim((string) ($_POST['body'] ?? '')));
$sendTime = holiday_normalize_send_time((string) ($_POST['send_time'] ?? ''));
$enabled = !empty($_POST['enabled']);

if ($body !== '') {
    $body = canada_holiday_email_apply_template_image(
        $body,
        canada_holiday_email_image_url_for_name('Birthday')
    );
}

$errors = [];
if ($subject === '') {
    $errors[] = 'Email subject is required.';
}
if ($body === '') {
    $errors[] = 'Email message is required.';
}
if ($sendTime === null) {
    $errors[] = 'Choose a valid send time.';
}

if ($errors !== []) {
    $_SESSION['flash_error'] = implode(' ', $errors);
    header('Location: /admin/birthday-emails');
    exit;
}

birthday_email_save_settings([
    'enabled' => $enabled,
    'send_time' => $sendTime,
    'subject' => $subject,
    'body_html' => $body,
]);

ActivityLog::record('birthday.settings_updated', 'birthday_emails', null, [
    'enabled' => $enabled,
]);
$_SESSION['flash_success'] = 'Birthday email settings saved.';
header('Location: /admin/birthday-emails');
exit;
