<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$parsed = $token !== '' ? email_unsubscribe_parse_token($token) : null;
$done = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($parsed === null) {
        $error = 'This unsubscribe link is invalid or expired.';
    } else {
        (new ClientRepository())->setEmailUnsubscribed($parsed['client_id'], true);
        $done = true;
    }
}

$pageTitle = 'Unsubscribe';
$seoTitle = 'Unsubscribe | Verma Accounting';
$seoDescription = 'Manage marketing email preferences for Verma Accounting.';

include __DIR__ . '/components/header.php';
?>
<main class="main-content" style="padding:3rem 1rem;">
  <div style="max-width:32rem;margin:0 auto;">
    <h1 style="margin:0 0 1rem;font-size:1.75rem;">Email preferences</h1>
    <?php if ($done): ?>
      <p>You have been unsubscribed from Verma Accounting marketing emails (campaigns, holiday, and birthday messages).</p>
      <p style="color:#64748b;">You may still receive transactional messages such as form confirmations or invoices when needed.</p>
    <?php elseif ($error !== null): ?>
      <p style="color:#b91c1c;"><?= e($error) ?></p>
    <?php elseif ($parsed === null): ?>
      <p>This unsubscribe link is invalid. If you continue to receive unwanted mail, contact us at
        <a href="mailto:info@vermaaccounting.ca">info@vermaaccounting.ca</a>.</p>
    <?php else: ?>
      <p>Unsubscribe <strong><?= e($parsed['email']) ?></strong> from Verma Accounting marketing emails?</p>
      <form method="post" action="/email-unsubscribe" style="margin-top:1.25rem;">
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <button type="submit" class="cta-button primary">Confirm unsubscribe</button>
      </form>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
