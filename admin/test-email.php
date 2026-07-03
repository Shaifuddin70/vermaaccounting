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
            $error = 'Mail is not available. Check your email settings or try again from the live site.';
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
                $error = $mailer->getLastError() ?: 'Send failed. Please try again.';
            }
        }
    }
}

$mail = mail_config();
$pageTitle = 'Test email';
$activeNav = 'email';
if ($result) {
    $adminToastMessages = [['type' => 'success', 'message' => $result]];
} elseif ($error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminToastMessages = [['type' => 'error', 'message' => $error]];
}
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Test email</h1>
  <div class="admin-header-actions">
    <a href="/admin/email-settings" class="admin-btn admin-btn-secondary">← Email settings</a>
  </div>
</div>

<div class="admin-card email-test-card">
  <h2 class="admin-card-title">Send test</h2>
  <p class="admin-field-hint" style="margin-top:0;">Send a test message to confirm email delivery is working.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <div class="admin-field">
      <label for="test-email-to">Send to</label>
      <input type="email" id="test-email-to" name="to" value="<?= e($to) ?>" required>
    </div>
    <button type="submit" class="admin-btn admin-btn-primary" <?= empty($mail['enabled']) ? 'disabled' : '' ?>>Send test email</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
