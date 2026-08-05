<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$settings = birthday_email_settings();
$clientRepo = new ClientRepository();
$withDob = $clientRepo->countWithBirthday();
$upcoming = $clientRepo->upcomingBirthdays(30, 12);
$csrf = Auth::csrfToken();
$loadEmailEditor = true;
$loadEmailTemplateImage = true;
$pageTitle = 'Birthday emails';
$activeNav = 'birthdays';

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Birthday emails</h1>
  <div class="admin-header-actions">
    <a href="/admin/holiday-calendar" class="admin-btn admin-btn-secondary">Holiday emails</a>
  </div>
</div>

<div class="admin-alert admin-alert-info">
  Sends automatically each day at the chosen time to clients whose birthday is today
  (<?= number_format($withDob) ?> client<?= $withDob === 1 ? '' : 's' ?> currently have a date of birth + email).
  Cron must keep running for delivery.
</div>

<div class="admin-grid-2">
  <div class="admin-card">
    <form method="post" action="/admin/birthday-emails-save" id="birthday-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <div class="admin-field">
        <label for="birthday-subject">Email subject <span class="required">*</span></label>
        <input type="text" id="birthday-subject" name="subject" required value="<?= e($settings['subject']) ?>">
        <small class="admin-field-hint">Tokens: {client_name}, {client_email}, {sin}, {company}, {year}</small>
      </div>

      <div class="admin-field">
        <label for="birthday-send-time">Send time <span class="required">*</span></label>
        <input type="time" id="birthday-send-time" name="send_time" required
          value="<?= e(holiday_send_time_input_value($settings['send_time'])) ?>">
        <small class="admin-field-hint"><?= e(app_timezone_label()) ?></small>
      </div>

      <?php
      $templateImageFixedName = 'Birthday';
      $templateImageInitialName = 'Birthday';
      $templateImageBodyId = 'birthday-body';
      $templateImageCsrf = $csrf;
      include __DIR__ . '/includes/email-template-image.php';
      ?>

      <?php
      $emailEditorId = 'birthday-body';
      $emailEditorValue = $settings['body_html'];
      $emailEditorPlaceholder = 'Write your birthday HTML email…';
      $emailEditorHint = 'Tokens: {client_name}, {client_email}, {sin}, {company}, {year}. Year fills automatically when sent. Use Preview to see the final email.';
      $emailEditorSubjectId = 'birthday-subject';
      include __DIR__ . '/includes/email-editor.php';
      ?>

      <label class="admin-checkbox-label">
        <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
        <span>Send birthday emails automatically</span>
      </label>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save birthday email</button>
        <button type="submit" formaction="/admin/birthday-emails-action" name="action" value="send_test"
          class="admin-btn admin-btn-secondary" formnovalidate
          title="Sends to the first admin email in Email settings">Send test email</button>
        <button type="submit" formaction="/admin/birthday-emails-action" name="action" value="reset_template"
          class="admin-btn admin-btn-secondary" formnovalidate
          onclick="return confirm('Replace the message with the default birthday template?');">
          Reset to default template
        </button>
      </div>
    </form>
  </div>

  <div class="holiday-sidebar">
    <div class="admin-card">
      <h2 class="admin-card-title">How it works</h2>
      <ul class="admin-field-hint" style="margin:0;padding-left:1.25rem;">
        <li>Date of birth is saved from form submissions (Date of birth fields) and client CSV imports.</li>
        <li>Only clients with both a birthday and an email address are emailed.</li>
        <li>Each client is emailed once per year on their birthday.</li>
        <li>Feb 29 birthdays send on Feb 28 in non-leap years.</li>
      </ul>
    </div>

    <div class="admin-card">
      <h2 class="admin-card-title">Upcoming (30 days)</h2>
      <?php if ($upcoming === []): ?>
        <p class="admin-field-hint" style="margin:0;">No upcoming birthdays with email on file.</p>
      <?php else: ?>
        <ul class="holiday-upcoming-list">
          <?php foreach ($upcoming as $row): ?>
            <li class="holiday-upcoming-item">
              <a href="/admin/client?id=<?= (int) $row['id'] ?>" class="holiday-upcoming-link">
                <strong><?= e((string) $row['name']) ?></strong>
                <span><?= e((string) ($row['next_birthday'] ?? '')) ?></span>
                <span><?= (int) ($row['days_until'] ?? 0) === 0 ? 'Today' : ('in ' . (int) $row['days_until'] . ' day' . ((int) $row['days_until'] === 1 ? '' : 's')) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
