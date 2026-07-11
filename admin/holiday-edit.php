<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$repo = new HolidayScheduleRepository();
$clientRepo = new ClientRepository();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$schedule = $editId ? $repo->find($editId) : null;

if ($editId && !$schedule) {
    header('Location: /admin/holiday-calendar');
    exit;
}

$errors = $_SESSION['holiday_edit_errors'] ?? [];
$old = $_SESSION['holiday_edit_old'] ?? [];
unset($_SESSION['holiday_edit_errors'], $_SESSION['holiday_edit_old']);

$name = (string) ($old['name'] ?? $schedule['name'] ?? '');
$subject = (string) ($old['subject'] ?? $schedule['subject'] ?? '');
$body = (string) ($old['body'] ?? $schedule['body_html'] ?? '');
$month = (int) ($old['holiday_month'] ?? $schedule['holiday_month'] ?? ($_GET['month'] ?? 1));
$day = (int) ($old['holiday_day'] ?? $schedule['holiday_day'] ?? ($_GET['day'] ?? 1));
$sendTime = (string) ($old['send_time'] ?? holiday_send_time_input_value((string) ($schedule['send_time'] ?? '09:00:00')));
$enabled = array_key_exists('enabled', $old) ? !empty($old['enabled']) : ($schedule ? !empty($schedule['enabled']) : true);

$recipientCount = $clientRepo->countWithEmail();
$csrf = Auth::csrfToken();
$loadEmailEditor = true;
$pageTitle = $schedule ? 'Edit holiday email' : 'Add holiday email';
$activeNav = 'holidays';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $schedule ? 'Edit holiday email' : 'Add holiday email' ?></h1>
  <a href="/admin/holiday-calendar" class="admin-btn admin-btn-secondary">← Calendar</a>
</div>

<?php if ($schedule): ?>
  <div class="admin-alert admin-alert-info">
    Next send: <strong><?= e(holiday_format_next_send($schedule)) ?></strong>
    <?php if (!empty($schedule['last_sent_year'])): ?>
      · Last sent in <?= (int) $schedule['last_sent_year'] ?>
      <?php if (!empty($schedule['last_campaign_id'])): ?>
        (<a href="/admin/campaign-view?id=<?= (int) $schedule['last_campaign_id'] ?>">view campaign</a>)
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="admin-alert admin-alert-error">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <form method="post" action="/admin/holiday-save" id="holiday-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <?php if ($schedule): ?>
        <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
      <?php endif; ?>

      <div class="admin-field">
        <label for="holiday-name">Holiday name <span class="required">*</span></label>
        <input type="text" id="holiday-name" name="name" required value="<?= e($name) ?>"
          placeholder="e.g. Christmas, Diwali, Canada Day">
        <small class="admin-field-hint">Internal label shown on the calendar.</small>
      </div>

      <div class="admin-field-row">
        <div class="admin-field">
          <label for="holiday-month">Month <span class="required">*</span></label>
          <select id="holiday-month" name="holiday_month" required>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= e(holiday_month_name($m)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="holiday-day">Day <span class="required">*</span></label>
          <select id="holiday-day" name="holiday_day" required>
            <?php for ($d = 1; $d <= 31; $d++): ?>
              <option value="<?= $d ?>" <?= $day === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="holiday-send-time">Send time <span class="required">*</span></label>
          <input type="time" id="holiday-send-time" name="send_time" required value="<?= e($sendTime) ?>">
          <small class="admin-field-hint"><?= e(app_timezone_label()) ?></small>
        </div>
      </div>

      <div class="admin-field">
        <label for="holiday-subject">Email subject <span class="required">*</span></label>
        <input type="text" id="holiday-subject" name="subject" required value="<?= e($subject) ?>"
          placeholder="e.g. Happy holidays from Verma Accounting">
        <small class="admin-field-hint">Tokens: {client_name}, {client_email}, {cin}, {company}</small>
      </div>

      <?php
      $emailEditorId = 'holiday-body';
      $emailEditorValue = $body;
      $emailEditorPlaceholder = 'Write your holiday HTML email…';
      include __DIR__ . '/includes/email-editor.php';
      ?>

      <label class="admin-checkbox-label">
        <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
        <span>Send automatically every year</span>
      </label>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary"><?= $schedule ? 'Save changes' : 'Save holiday' ?></button>
        <?php if ($schedule): ?>
          <button type="submit" formaction="/admin/holiday-action" name="action" value="send_test" class="admin-btn admin-btn-secondary" formnovalidate>Send test to me</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title">Audience</h2>
    <p style="margin:0 0 1rem;">
      This holiday email goes to <strong><?= number_format($recipientCount) ?></strong> unique client email<?= $recipientCount === 1 ? '' : 's' ?>.
    </p>
    <p class="admin-field-hint" style="margin:0;">
      Only clients with an email address in the Clients list are included.
    </p>

    <h2 class="admin-card-title" style="margin-top:1.5rem;">Tips</h2>
    <ul class="admin-field-hint" style="margin:0;padding-left:1.25rem;">
      <li>Use {client_name} to personalize the greeting.</li>
      <li>Send a test email before enabling automatic sends.</li>
      <li>Uncheck “Send automatically” to pause a holiday without deleting it.</li>
      <li>Feb 29 holidays send on Feb 28 in non-leap years.</li>
    </ul>

    <?php if ($schedule): ?>
      <form method="post" action="/admin/holiday-action" class="holiday-delete-form"
        onsubmit="return confirm('Delete this holiday email? This cannot be undone.');">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="admin-btn admin-btn-secondary holiday-btn-delete">Delete holiday</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
