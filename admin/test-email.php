<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$result = null;
$error = null;
$to = trim((string) ($_POST['to'] ?? mail_config()['admin_email'] ?? ''));

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
$pageTitle = 'Test email';
$activeNav = 'clients';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Test email</h1>
  <div class="admin-header-actions">
    <a href="/admin/clients" class="admin-btn admin-btn-secondary">← Back</a>
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

  <div class="admin-card">
    <h2 class="admin-card-title">Current mail config</h2>
    <table class="admin-table clients-import-guide">
      <tbody>
        <tr><td>Enabled</td><td><?= !empty($mail['enabled']) ? 'Yes' : 'No' ?></td></tr>
        <tr><td>Transport</td><td><?= e((string) ($mail['transport'] ?? '')) ?></td></tr>
        <tr><td>From</td><td><?= e((string) ($mail['from_email'] ?? '')) ?></td></tr>
        <tr><td>Admin notifications</td><td><?= e((string) ($mail['admin_email'] ?? '')) ?></td></tr>
        <tr><td>SMTP host</td><td><?= e((string) ($mail['smtp']['host'] ?? '')) ?></td></tr>
        <tr><td>SMTP port</td><td><?= e((string) ($mail['smtp']['port'] ?? '')) ?></td></tr>
        <tr><td>SMTP user</td><td><?= e((string) ($mail['smtp']['username'] ?? '')) ?></td></tr>
      </tbody>
    </table>
    <p class="admin-note" style="margin-top:1rem;">
      Test from your <strong>live server</strong> after updating DNS/SMTP. Local MAMP often cannot send through hosting mail.
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
