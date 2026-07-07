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

$action = (string) ($_POST['action'] ?? '');
$scheduleId = (int) ($_POST['id'] ?? 0);
$repo = new HolidayScheduleRepository();
$schedule = $scheduleId > 0 ? $repo->find($scheduleId) : null;

if (!$schedule) {
    $_SESSION['flash_error'] = 'Holiday not found.';
    header('Location: /admin/holiday-calendar');
    exit;
}

$redirect = '/admin/holiday-edit?id=' . $scheduleId;

if ($action === 'send_test') {
    $draft = [
        'subject' => trim((string) ($_POST['subject'] ?? $schedule['subject'] ?? '')),
        'body_html' => trim((string) ($_POST['body'] ?? $schedule['body_html'] ?? '')),
    ];
    $result = holiday_send_test_email($draft);
    if (!$result['ok']) {
        $_SESSION['holiday_edit_errors'] = [$result['error'] ?? 'Test send failed.'];
        $_SESSION['holiday_edit_old'] = $_POST;
        header('Location: ' . $redirect);
        exit;
    }
    $_SESSION['flash_success'] = 'Test email sent.';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'toggle') {
    $enabled = empty($schedule['enabled']);
    $repo->update($scheduleId, ['enabled' => $enabled]);
    $_SESSION['flash_success'] = $enabled ? 'Holiday email enabled.' : 'Holiday email paused.';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $repo->delete($scheduleId);
    ActivityLog::record('holiday.deleted', 'holiday_schedule', $scheduleId, [
        'name' => (string) ($schedule['name'] ?? ''),
    ]);
    $_SESSION['flash_success'] = 'Holiday email deleted.';
    header('Location: /admin/holiday-calendar');
    exit;
}

$_SESSION['flash_error'] = 'Unknown action.';
header('Location: /admin/holiday-calendar');
exit;
