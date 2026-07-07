<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/holiday-calendar');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/holiday-calendar');
    exit;
}

$editId = isset($_POST['id']) ? (int) $_POST['id'] : null;
$repo = new HolidayScheduleRepository();
$existing = $editId ? $repo->find($editId) : null;

if ($editId && !$existing) {
    $_SESSION['flash_error'] = 'Holiday not found.';
    header('Location: /admin/holiday-calendar');
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));
$month = (int) ($_POST['holiday_month'] ?? 0);
$day = (int) ($_POST['holiday_day'] ?? 0);
$sendTime = holiday_normalize_send_time((string) ($_POST['send_time'] ?? ''));
$enabled = !empty($_POST['enabled']);

$errors = [];
if ($name === '') {
    $errors[] = 'Holiday name is required.';
}
if ($subject === '') {
    $errors[] = 'Email subject is required.';
}
if ($body === '') {
    $errors[] = 'Email message is required.';
}
if (!holiday_is_valid_date($month, $day)) {
    $errors[] = 'Choose a valid month and day.';
}
if ($sendTime === null) {
    $errors[] = 'Choose a valid send time.';
}

if ($errors !== []) {
    $_SESSION['holiday_edit_errors'] = $errors;
    $_SESSION['holiday_edit_old'] = $_POST;
    header('Location: /admin/holiday-edit' . ($editId ? '?id=' . $editId : ''));
    exit;
}

$user = Auth::currentUser();
$data = [
    'name' => $name,
    'holiday_month' => $month,
    'holiday_day' => $day,
    'send_time' => $sendTime,
    'subject' => $subject,
    'body_html' => $body,
    'enabled' => $enabled,
];

if ($editId) {
    $repo->update($editId, $data);
    ActivityLog::record('holiday.updated', 'holiday_schedule', $editId, ['name' => $name]);
    $_SESSION['flash_success'] = 'Holiday email updated.';
    header('Location: /admin/holiday-edit?id=' . $editId);
    exit;
}

$data['created_by_user_id'] = $user['id'] ?? null;
$data['created_by_name'] = (string) ($user['name'] ?? 'Admin');
$scheduleId = $repo->create($data);
ActivityLog::record('holiday.created', 'holiday_schedule', $scheduleId, ['name' => $name]);
$_SESSION['flash_success'] = 'Holiday email saved. It will send automatically every year.';
header('Location: /admin/holiday-edit?id=' . $scheduleId);
exit;
