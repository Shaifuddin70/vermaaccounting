<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$result = null;
$error = null;
$mail = mail_config();
$defaultTo = $mail['admin_emails'][0] ?? $mail['admin_email'] ?? '';
$to = trim((string) ($_POST['to'] ?? $defaultTo));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $mailer = Mailer::fromAppConfig();
        if ($mailer === null) {
            $error = 'Mail is disabled or From email is missing in config.local.php.';
        } else {
            $mail = mail_config();
            $ok = $mailer->send(
                $to,
                'Verma Accounting — test email',
                submission_email_layout(
                    'Test email',
                    '<p style="margin:0 0 16px;color:#334155;">This is a test email sent at <strong>'
                        . e(gmdate('Y-m-d H:i:s')) . ' UTC</strong>.</p>'
                        . '<p style="margin:0;color:#334155;">If you received this, submission notifications are configured correctly.</p>',
                    'Test message from the Verma Accounting admin panel.'
                ),
                'Test email from Verma Accounting admin panel.'
            );
            if ($ok) {
                $result = 'Test email sent to ' . $to . '. Check your inbox and spam folder.';
            } else {
                $error = $mailer->getLastError() ?: 'Send failed with no error details.';
            }
        }
    }
}

$mail = mail_config();
$mailCanSend = Mailer::fromAppConfig() !== null;
$mailEnv = app_environment();
$pageTitle = 'Test email';
$activeNav = 'email';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Test email</h1>
  <div class="admin-header-actions">
    <a href="/admin/email-settings" class="admin-btn admin-btn-secondary">← Email settings</a>
  </div>
</div>

<?php if ($result): ?>
  <div class="admin-alert admin-alert-success"><?= e($result) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="admin-alert admin-alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2 class="admin-card-title">Send test</h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
      <div class="admin-field">
        <label for="test-email-to">Send to</label>
        <input type="email" id="test-email-to" name="to" value="<?= e($to) ?>" required>
      </div>
      <button type="submit" class="admin-btn" <?= empty($mail['enabled']) ? 'disabled' : '' ?>>Send test email</button>
    </form>
  </div>

  <div class="admin-card email-server-card">
    <h2 class="admin-card-title">Current mail config</h2>
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
        <span class="submission-meta-label">Admin notifications</span>
        <strong><?= !empty($mail['admin_emails']) ? e(implode(', ', $mail['admin_emails'])) : '—' ?></strong>
      </div>
    </div>
    <?php if ($mailEnv === 'local'): ?>
      <p class="admin-note" style="margin-top:1rem;">
        Local development uses disabled mail. Test from the <strong>live server</strong> to verify production SMTP.
      </p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
