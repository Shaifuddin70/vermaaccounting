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

$settingsRepo = new SettingsRepository();
$appTimezone = (string) ($old['app_timezone'] ?? $settingsRepo->getAppTimezone());

$csrf = Auth::csrfToken();
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

<div class="admin-card email-settings-page">
  <form method="post" action="/admin/email-settings-save" id="email-settings-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="email-settings-grid">
      <section class="email-settings-tile email-settings-tile--admin">
        <div class="email-settings-section-head">
          <h2 class="email-settings-section-title">Admin alerts</h2>
          <p class="email-settings-section-desc">Notify your team when someone submits a form.</p>
        </div>
        <label class="email-settings-toggle">
          <input type="checkbox" name="admin_notification_enabled" value="1" id="admin-notification-enabled" <?= $adminEnabled ? 'checked' : '' ?>>
          <span>Send admin notification on new submissions</span>
        </label>
        <div class="email-settings-panel" id="admin-notification-panel" <?= $adminEnabled ? '' : 'hidden' ?>>
          <div class="admin-field" style="margin-bottom:0;">
            <label for="admin-email-first">Notification emails</label>
            <div class="admin-email-list" id="admin-email-list">
              <?php foreach ($adminEmails as $i => $email): ?>
                <div class="admin-email-row">
                  <input type="email" name="admin_emails[]" value="<?= e($email) ?>" placeholder="admin@example.com" autocomplete="email"<?= $i === 0 ? ' id="admin-email-first"' : '' ?>>
                  <button type="button" class="admin-email-remove" aria-label="Remove email" title="Remove">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="admin-email-add">+ Add email</button>
          </div>
          <div class="admin-field">
            <label for="admin-notification-subject">Subject line</label>
            <input type="text" id="admin-notification-subject" name="admin_notification_subject"
              value="<?= e($adminSubject) ?>"
              placeholder="New submission: {form_title} (#{submission_id})">
            <small class="admin-field-hint">{form_title}, {submission_id}, {client_name}, {client_email}, {tax_year}</small>
          </div>
        </div>
      </section>

      <section class="email-settings-tile email-settings-tile--client">
        <div class="email-settings-section-head">
          <h2 class="email-settings-section-title">Client confirmation</h2>
          <p class="email-settings-section-desc">Automatic reply when a client submits a form.</p>
        </div>
        <label class="email-settings-toggle">
          <input type="checkbox" name="client_confirmation_enabled" value="1" id="client-confirmation-enabled" <?= $clientEnabled ? 'checked' : '' ?>>
          <span>Send confirmation email to the client</span>
        </label>
        <div class="email-settings-panel" id="client-confirmation-panel" <?= $clientEnabled ? '' : 'hidden' ?>>
          <div class="admin-field" style="margin-bottom:0;">
            <label for="client-confirmation-subject">Subject line</label>
            <input type="text" id="client-confirmation-subject" name="client_confirmation_subject"
              value="<?= e($clientSubject) ?>"
              placeholder="We received your submission — {form_title}">
          </div>
        </div>
      </section>

      <section class="email-settings-tile email-settings-tile--campaign">
        <div class="email-settings-section-head">
          <h2 class="email-settings-section-title">Campaign scheduling</h2>
          <p class="email-settings-section-desc">Timezone for viewing and scheduling bulk email campaigns.</p>
        </div>
        <div class="admin-field" style="margin-bottom:0;">
          <label for="app-timezone">Timezone</label>
          <select id="app-timezone" name="app_timezone">
            <?php foreach (app_timezone_option_groups() as $groupLabel => $zones): ?>
              <optgroup label="<?= e($groupLabel) ?>">
                <?php foreach ($zones as $tzId => $tzLabel): ?>
                  <option value="<?= e($tzId) ?>" title="<?= e($tzId) ?>" <?= $appTimezone === $tzId ? 'selected' : '' ?>>
                    <?= e($tzLabel) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <div class="email-settings-clock-card" id="email-settings-clock">
            <span class="email-settings-clock-label">Current time</span>
            <strong class="email-settings-clock-time"><?= e(app_now_local_formatted($appTimezone)) ?></strong>
            <span class="email-settings-clock-tz"><?= e(app_timezone_label($appTimezone)) ?></span>
          </div>
        </div>
      </section>
    </div>

    <div class="email-settings-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary">Save settings</button>
    </div>
  </form>
</div>

<template id="admin-email-row-template">
  <div class="admin-email-row">
    <input type="email" name="admin_emails[]" value="" placeholder="admin@example.com" autocomplete="email">
    <button type="button" class="admin-email-remove" aria-label="Remove email" title="Remove">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
</template>

<script>
(function () {
  const list = document.getElementById('admin-email-list');
  const template = document.getElementById('admin-email-row-template');
  const addBtn = document.getElementById('admin-email-add');

  function bindPanelToggle(checkboxId, panelId) {
    const checkbox = document.getElementById(checkboxId);
    const panel = document.getElementById(panelId);
    if (!checkbox || !panel) return;
    const sync = function () {
      panel.hidden = !checkbox.checked;
    };
    checkbox.addEventListener('change', sync);
    sync();
  }

  bindPanelToggle('admin-notification-enabled', 'admin-notification-panel');
  bindPanelToggle('client-confirmation-enabled', 'client-confirmation-panel');

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
