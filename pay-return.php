<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$reference = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['ref'] ?? '')) ?? '';
$attempt = $reference !== '' ? online_payment_find_by_reference($reference) : null;
$invoice = $attempt ? (new InvoiceRepository())->find((int) $attempt['invoice_id']) : null;

$outcome = ['status' => 'unknown', 'message' => ''];
if ($attempt && $invoice) {
    // The redirect's own result fields are only a hint; Converge is queried before anything is recorded.
    $outcome = elavon_confirm_payment($attempt, (string) ($_GET['ssl_txn_id'] ?? ''));
    $invoice = (new InvoiceRepository())->find((int) $attempt['invoice_id']) ?? $invoice;
}

$company = invoice_company_settings();
$payUrl = $invoice ? invoice_pay_url($invoice) : '';
$pageTitle = 'Payment';
$seoTitle = 'Payment | Verma Accounting';
$seoDescription = 'Invoice payment result.';
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
          <h1 class="vf-card-title">
            <?php if ($outcome['status'] === 'approved'): ?>Payment received
            <?php elseif ($outcome['status'] === 'declined'): ?>Payment not completed
            <?php elseif ($outcome['status'] === 'pending'): ?>Confirming your payment
            <?php else: ?>Payment not found<?php endif; ?>
          </h1>
          <?php if ($invoice): ?>
            <p class="vf-card-desc">Invoice #<?= e((string) $invoice['invoice_number']) ?></p>
          <?php endif; ?>
        </div>
      </header>
      <div class="vf-card-body">
        <?php if ($outcome['status'] === 'approved'): ?>
          <div class="pay-alert pay-alert-success">
            Thank you! Your payment of <strong><?= e(invoice_format_money((float) ($attempt['amount'] ?? 0))) ?></strong> was approved.
          </div>
          <?php $remaining = invoice_amount_due($invoice); ?>
          <p class="pay-note">
            <?= $remaining > 0
              ? 'Remaining balance: ' . e(invoice_format_money($remaining)) . '.'
              : 'Your invoice is now fully paid.' ?>
            Please keep the receipt from Elavon for your records.
          </p>
        <?php elseif ($outcome['status'] === 'declined'): ?>
          <div class="pay-alert pay-alert-error">Your card was not charged: <?= e($outcome['message']) ?></div>
          <p><a class="pay-button" href="<?= e($payUrl) ?>">Try again</a></p>
        <?php elseif ($outcome['status'] === 'pending'): ?>
          <p class="pay-note">
            We haven’t received confirmation from Elavon yet. If you completed the payment, there’s nothing more to do —
            we’ll update your invoice once it’s confirmed. If you cancelled, you can
            <a href="<?= e($payUrl) ?>">return to the invoice</a> and try again.
          </p>
        <?php else: ?>
          <p class="pay-note">We couldn’t find this payment. If you were charged, please contact us at
            <a href="mailto:<?= e($company['payment_email']) ?>"><?= e($company['payment_email']) ?></a>.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
