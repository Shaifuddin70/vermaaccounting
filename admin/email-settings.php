<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$mail = mail_config();
$errors = $_SESSION['email_settings_errors'] ?? [];
$old = $_SESSION['email_settings_old'] ?? [];
unset($_SESSION['email_settings_errors'], $_SESSION['email_settings_old']);

$saved = isset($_GET['saved']);
$adminEmails = $old['admin_emails'] ?? $mail['admin_emails'] ?? [];
if (!is_array($adminEmails)) {
    $adminEmails = [$adminEmails];
}
$adminEmails = array_values(array_filter(array_map('strval', $adminEmails), static fn(string $email): bool => trim($email) !== ''));
if ($adminEmails === []) {
    $adminEmails = [''];
}

$adminEnabled = array_key_exists('admin_notification_enabled', $old)
    ? !empty($old['admin_notification_enabled'])
    : !empty($mail['admin_notification']['enabled']);
$clientEnabled = array_key_exists('client_confirmation_enabled', $old)
    ? !empty($old['client_confirmation_enabled'])
    : !empty($mail['client_confirmation']['enabled']);
$adminSubject = (string) ($old['admin_notification_subject']
    ?? $mail['admin_notification']['subject']
    ?? 'New submission: {form_title} (#{submission_id})');
$clientSubject = (string) ($old['client_confirmation_subject']
    ?? $mail['client_confirmation']['subject']
    ?? 'We received your submission — {form_title}');

$csrf = Auth::csrfToken();
$mailCanSend = Mailer::fromAppConfig() !== null;
$mailEnv = app_environment();
$pageTitle = 'Email settings';
$activeNav = 'email';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Email settings</h1>
  <div class="admin-header-actions">
    <a href="/admin/test-email" class="admin-btn admin-btn-secondary">Send test email</a>
  </div>
</div>

<?php if ($saved): ?>
  <div class="admin-alert admin-alert-success">Email settings saved.</div>
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
    <h2 class="admin-card-title">Notification settings</h2>
    <form method="post" action="/admin/email-settings-save" id="email-settings-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <div class="admin-field">
        <label>Admin notification emails</label>
        <p class="admin-field-hint" style="margin-top:0;">Submission alerts are sent to every address listed below.</p>
        <div class="admin-email-list" id="admin-email-list">
          <?php foreach ($adminEmails as $email): ?>
            <div class="admin-email-row">
              <input type="email" name="admin_emails[]" value="<?= e($email) ?>" placeholder="admin@example.com" autocomplete="email">
              <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm admin-email-remove" aria-label="Remove email">Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="admin-email-add" style="margin-top:0.75rem;">Add email</button>
      </div>

      <div class="admin-field">
        <label class="admin-checkbox-label">
          <input type="checkbox" name="admin_notification_enabled" value="1" <?= $adminEnabled ? 'checked' : '' ?>>
          <span>Send admin notification on new submissions</span>
        </label>
      </div>

      <div class="admin-field">
        <label for="admin-notification-subject">Admin notification subject</label>
        <input type="text" id="admin-notification-subject" name="admin_notification_subject"
          value="<?= e($adminSubject) ?>"
          placeholder="New submission: {form_title} (#{submission_id})">
        <small class="admin-field-hint">Tokens: {form_title}, {submission_id}, {client_name}, {client_email}, {tax_year}</small>
      </div>

      <div class="admin-field">
        <label class="admin-checkbox-label">
          <input type="checkbox" name="client_confirmation_enabled" value="1" <?= $clientEnabled ? 'checked' : '' ?>>
          <span>Send confirmation email to the client</span>
        </label>
      </div>

      <div class="admin-field">
        <label for="client-confirmation-subject">Client confirmation subject</label>
        <input type="text" id="client-confirmation-subject" name="client_confirmation_subject"
          value="<?= e($clientSubject) ?>"
          placeholder="We received your submission — {form_title}">
      </div>

      <button type="submit" class="admin-btn admin-btn-primary">Save settings</button>
    </form>
  </div>

  <div class="admin-card email-server-card">
    <h2 class="admin-card-title">Server mail config</h2>
    <p class="admin-field-hint" style="margin-top:0;">
      SMTP credentials and the From address are set in <code>config.local.php</code> on the server.
    </p>

    <div class="email-server-meta">
      <div class="email-server-meta-item">
        <span class="submission-meta-label">Environment</span>
        <span class="campaign-status-badge campaign-status-badge--<?= $mailEnv === 'production' ? 'sent' : 'draft' ?>">
          <?= $mailEnv === 'production' ? 'Production' : 'Local development' ?>
        </span>
      </div>
      <div class="email-server-meta-item">
        <span class="submission-meta-label">Ready to send</span>
        <span class="campaign-status-badge campaign-status-badge--<?= $mailCanSend ? 'sent' : 'failed' ?>">
          <?= $mailCanSend ? 'Yes' : 'No' ?>
        </span>
      </div>
      <div class="email-server-meta-item">
        <span class="submission-meta-label">From</span>
        <strong><?= e((string) ($mail['from_email'] ?? '—')) ?></strong>
      </div>
      <div class="email-server-meta-item">
        <span class="submission-meta-label">Transport</span>
        <strong><?= e((string) ($mail['transport'] ?? '—')) ?></strong>
      </div>
      <div class="email-server-meta-item">
        <span class="submission-meta-label">SMTP host</span>
        <strong><?= e((string) ($mail['smtp']['host'] ?? '—')) ?></strong>
      </div>
      <div class="email-server-meta-item">
        <span class="submission-meta-label">SMTP user</span>
        <strong><?= e((string) ($mail['smtp']['username'] ?? '—')) ?></strong>
      </div>
    </div>

    <?php if ($mailEnv === 'local'): ?>
      <div class="admin-alert admin-alert-info" style="margin-top:1rem;">
        Mail is disabled on local development (MAMP). On the live site, production mail settings from config are used automatically.
      </div>
    <?php elseif (!$mailCanSend): ?>
      <div class="admin-alert admin-alert-error" style="margin-top:1rem;">
        Mail is not ready. Check <code>mail.enabled</code> and <code>from_email</code> in <code>config.local.php</code> on the server.
      </div>
    <?php endif; ?>

    <p class="admin-field-hint" style="margin-top:1rem;">
      Admin notification fallback: <strong><?= e((string) ($mail['admin_email'] ?: 'not set')) ?></strong>
    </p>
  </div>
</div>

<template id="admin-email-row-template">
  <div class="admin-email-row">
    <input type="email" name="admin_emails[]" value="" placeholder="admin@example.com" autocomplete="email">
    <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm admin-email-remove" aria-label="Remove email">Remove</button>
  </div>
</template>

<script>
(function () {
  const list = document.getElementById('admin-email-list');
  const template = document.getElementById('admin-email-row-template');
  const addBtn = document.getElementById('admin-email-add');

  function bindRemove(row) {
    const btn = row.querySelector('.admin-email-remove');
    if (!btn) return;
    btn.addEventListener('click', function () {
      const rows = list.querySelectorAll('.admin-email-row');
      if (rows.length <= 1) {
        row.querySelector('input').value = '';
        return;
      }
      row.remove();
    });
  }

  list.querySelectorAll('.admin-email-row').forEach(bindRemove);

  addBtn.addEventListener('click', function () {
    const row = template.content.firstElementChild.cloneNode(true);
    list.appendChild(row);
    bindRemove(row);
    row.querySelector('input').focus();
  });
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
