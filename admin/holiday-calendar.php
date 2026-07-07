<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$repo = new HolidayScheduleRepository();
$clientRepo = new ClientRepository();
$tz = app_timezone();
$now = new DateTimeImmutable('now', $tz);

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) $now->format('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) $now->format('n');
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$monthStart = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-1', $year, $month), $tz);
if ($monthStart === false) {
    $monthStart = $now->modify('first day of this month');
    $year = (int) $monthStart->format('Y');
    $month = (int) $monthStart->format('n');
}

$daysInMonth = (int) $monthStart->format('t');
$startWeekday = (int) $monthStart->format('w'); // 0 = Sunday
$schedules = $repo->forMonth($month);
$byDay = holiday_schedules_by_day($month, $schedules);
$allSchedules = $repo->all();
$recipientCount = $clientRepo->countWithEmail();

$upcoming = $allSchedules;
usort($upcoming, static function (array $a, array $b): int {
    $aNext = holiday_next_send_at($a);
    $bNext = holiday_next_send_at($b);
    if ($aNext === null && $bNext === null) {
        return 0;
    }
    if ($aNext === null) {
        return 1;
    }
    if ($bNext === null) {
        return -1;
    }
    return $aNext <=> $bNext;
});
$upcoming = array_slice($upcoming, 0, 6);

$prevMonth = $monthStart->modify('-1 month');
$nextMonth = $monthStart->modify('+1 month');
$csrf = Auth::csrfToken();
$pageTitle = 'Holiday emails';
$activeNav = 'holidays';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Holiday emails</h1>
  <div class="admin-header-actions">
    <a href="/admin/holiday-edit" class="admin-btn admin-btn-primary">Add holiday</a>
  </div>
</div>

<div class="admin-grid-2 holiday-layout">
  <div class="admin-card holiday-calendar-card">
    <div class="holiday-calendar-toolbar">
      <a href="/admin/holiday-calendar?year=<?= (int) $prevMonth->format('Y') ?>&amp;month=<?= (int) $prevMonth->format('n') ?>" class="admin-btn admin-btn-secondary admin-btn-sm">← <?= e($prevMonth->format('M Y')) ?></a>
      <h2 class="holiday-calendar-title"><?= e($monthStart->format('F Y')) ?></h2>
      <a href="/admin/holiday-calendar?year=<?= (int) $nextMonth->format('Y') ?>&amp;month=<?= (int) $nextMonth->format('n') ?>" class="admin-btn admin-btn-secondary admin-btn-sm"><?= e($nextMonth->format('M Y')) ?> →</a>
    </div>

    <div class="holiday-calendar-grid" role="grid" aria-label="<?= e($monthStart->format('F Y')) ?> holiday calendar">
      <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
        <div class="holiday-calendar-weekday" role="columnheader"><?= $weekday ?></div>
      <?php endforeach; ?>

      <?php for ($blank = 0; $blank < $startWeekday; $blank++): ?>
        <div class="holiday-calendar-day is-outside" aria-hidden="true"></div>
      <?php endfor; ?>

      <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $daySchedules = $byDay[$day] ?? [];
        $isToday = (int) $now->format('Y') === $year && (int) $now->format('n') === $month && (int) $now->format('j') === $day;
      ?>
        <div class="holiday-calendar-day<?= $isToday ? ' is-today' : '' ?><?= $daySchedules !== [] ? ' has-holidays' : '' ?>">
          <div class="holiday-calendar-day-head">
            <span class="holiday-calendar-day-num"><?= $day ?></span>
            <a href="/admin/holiday-edit?month=<?= $month ?>&amp;day=<?= $day ?>" class="holiday-calendar-add" title="Add holiday on this day" aria-label="Add holiday on <?= e(holiday_date_label($month, $day)) ?>">+</a>
          </div>
          <?php if ($daySchedules !== []): ?>
            <ul class="holiday-calendar-events">
              <?php foreach ($daySchedules as $schedule):
                $enabled = !empty($schedule['enabled']);
                $scheduleId = (int) ($schedule['id'] ?? 0);
              ?>
                <li>
                  <a href="/admin/holiday-edit?id=<?= $scheduleId ?>" class="holiday-calendar-event<?= $enabled ? '' : ' is-disabled' ?>">
                    <span class="holiday-calendar-event-name"><?= e((string) $schedule['name']) ?></span>
                    <span class="holiday-calendar-event-time"><?= e(holiday_send_time_input_value((string) ($schedule['send_time'] ?? ''))) ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <div class="holiday-sidebar">
    <div class="admin-card">
      <h2 class="admin-card-title">How it works</h2>
      <p style="margin:0 0 0.75rem;">
        Add holidays to the calendar with a custom email for each one. Enabled holidays send automatically every year to
        <strong><?= number_format($recipientCount) ?></strong> client<?= $recipientCount === 1 ? '' : 's' ?> with an email address.
      </p>
      <ul class="admin-field-hint" style="margin:0;padding-left:1.25rem;">
        <li>Send times use <?= e(app_timezone_label()) ?>.</li>
        <li>Emails repeat annually — set them once.</li>
        <li>Each send creates a campaign you can review under Campaigns.</li>
        <li>The server cron must run every few minutes for delivery.</li>
      </ul>
    </div>

    <div class="admin-card">
      <h2 class="admin-card-title">Upcoming</h2>
      <?php if ($upcoming === []): ?>
        <p class="admin-field-hint" style="margin:0;">No holidays scheduled yet.</p>
      <?php else: ?>
        <ul class="holiday-upcoming-list">
          <?php foreach ($upcoming as $schedule):
            $scheduleId = (int) ($schedule['id'] ?? 0);
            $enabled = !empty($schedule['enabled']);
          ?>
            <li class="holiday-upcoming-item<?= $enabled ? '' : ' is-disabled' ?>">
              <a href="/admin/holiday-edit?id=<?= $scheduleId ?>" class="holiday-upcoming-link">
                <strong><?= e((string) $schedule['name']) ?></strong>
                <span><?= e(holiday_date_label((int) $schedule['holiday_month'], (int) $schedule['holiday_day'])) ?></span>
                <span><?= e(holiday_format_next_send($schedule)) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if ($allSchedules !== []): ?>
      <div class="admin-card">
        <h2 class="admin-card-title">All holidays</h2>
        <div class="admin-table-wrap">
          <table class="admin-table holiday-list-table">
            <thead>
              <tr>
                <th>Holiday</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allSchedules as $schedule):
                $scheduleId = (int) ($schedule['id'] ?? 0);
                $enabled = !empty($schedule['enabled']);
              ?>
                <tr>
                  <td>
                    <a href="/admin/holiday-edit?id=<?= $scheduleId ?>"><?= e((string) $schedule['name']) ?></a>
                    <span class="admin-table-sub"><?= e(holiday_date_label((int) $schedule['holiday_month'], (int) $schedule['holiday_day'])) ?> at <?= e(holiday_send_time_input_value((string) ($schedule['send_time'] ?? ''))) ?></span>
                  </td>
                  <td>
                    <span class="admin-badge <?= $enabled ? 'admin-badge-info' : 'admin-badge-muted' ?>"><?= $enabled ? 'Active' : 'Paused' ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
