<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
Auth::startSession();

$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$invoice = invoice_find_by_pay_token($token);
$error = null;

if ($invoice && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $result = elavon_start_invoice_payment($invoice);
        if ($result['ok']) {
            header('Location: ' . $result['redirect'], true, 303);
            exit;
        }
        $error = $result['error'] ?? 'Could not start the payment.';
    }
}

if (!$invoice) {
    http_response_code(404);
}

$amountDue = $invoice ? invoice_amount_due($invoice) : 0.0;
$canPay = $invoice && invoice_can_pay_online($invoice);
$company = invoice_company_settings();
$pageTitle = 'Pay invoice';
$seoTitle = 'Pay your invoice | Verma Accounting';
$seoDescription = 'Secure online invoice payment.';
$seoNoindex = true;

include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="/css/custom-form.css?v=22">
<main class="main-content vf-page pay-page">
  <div class="vf-container pay-container">
    <div class="vf-card">
      <header class="vf-card-header">
        <a href="<?= e(app_base_url() ?: '/') ?>" class="vf-card-header-logo" aria-label="Verma Accounting home">
          <?= brand_logo_img_html('vf-card-logo-img', 160, 36) ?>
        </a>
        <div class="vf-card-header-text">
          <h1 class="vf-card-title"><?= $invoice ? 'Invoice #' . e((string) $invoice['invoice_number']) : 'Payment link not found' ?></h1>
          <?php if ($invoice): ?>
            <p class="vf-card-desc"><?= e($company['name']) ?></p>
          <?php endif; ?>
        </div>
      </header>
      <div class="vf-card-body">
        <?php if (!$invoice): ?>
          <p class="pay-note">This payment link is not valid. Please use the link in your invoice email, or contact us at
            <a href="mailto:<?= e($company['payment_email']) ?>"><?= e($company['payment_email']) ?></a>.</p>
        <?php else: ?>
          <?php if ($error !== null): ?>
            <div class="pay-alert pay-alert-error" role="alert"><?= e($error) ?></div>
          <?php endif; ?>

          <dl class="pay-summary">
            <?php $billName = trim((string) ($invoice['bill_to_name'] ?? '')) ?: trim((string) ($invoice['bill_to_company'] ?? '')); ?>
            <?php if ($billName !== ''): ?>
              <div><dt>Billed to</dt><dd><?= e($billName) ?></dd></div>
            <?php endif; ?>
            <div><dt>Invoice date</dt><dd><?= e(invoice_format_date((string) ($invoice['invoice_date'] ?? ''))) ?></dd></div>
            <div><dt>Payment due</dt><dd><?= e(invoice_format_date((string) ($invoice['due_date'] ?? ''))) ?></dd></div>
            <div class="pay-summary-total"><dt>Amount due</dt><dd><?= e(invoice_format_money($amountDue)) ?> <?= e((string) ($invoice['currency'] ?? 'CAD')) ?></dd></div>
          </dl>

          <?php if ($amountDue <= 0): ?>
            <div class="pay-alert pay-alert-success">This invoice is fully paid. Thank you!</div>
          <?php elseif (!$canPay): ?>
            <p class="pay-note">Online card payment is not available for this invoice right now. Please pay by Interac e-Transfer to
              <a href="mailto:<?= e($company['payment_email']) ?>"><?= e($company['payment_email']) ?></a>.</p>
          <?php else: ?>
            <form method="post" action="/pay" class="pay-form">
              <input type="hidden" name="t" value="<?= e($token) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
              <button type="submit" class="pay-button">Pay <?= e(invoice_format_money($amountDue)) ?> by card</button>
            </form>
            <p class="pay-secure">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              You’ll enter your card on Elavon’s secure payment page. We never see or store your card number.
            </p>
            <?php if (elavon_config()['environment'] !== 'live'): ?>
              <p class="pay-test-mode">Test mode — no real charge will be made.</p>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
