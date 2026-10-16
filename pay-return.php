<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

// Converge may return via GET redirect (REDG) or HTTP POST depending on terminal settings.
$incoming = array_merge($_GET, $_POST);

$reference = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($incoming['ref'] ?? $incoming['ssl_invoice_number'] ?? '')) ?? '';
$txnId = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($incoming['ssl_txn_id'] ?? '')) ?? '';
$attempt = $reference !== '' ? online_payment_find_by_reference($reference) : null;

$invoice = null;
$submission = null;
$payUrl = '';
$contextLabel = '';

if ($attempt) {
    $submissionId = (int) ($attempt['submission_id'] ?? 0);
    $invoiceId = (int) ($attempt['invoice_id'] ?? 0);
    if ($submissionId > 0) {
        $submission = (new FormRepository())->findSubmission($submissionId);
        if ($submission) {
            $payUrl = submission_pay_url($submission);
            $form = (new FormRepository())->find((int) $submission['form_id']);
            $contextLabel = $form ? (string) $form['title'] : 'Advance payment';
        }
    } elseif ($invoiceId > 0) {
        $invoice = (new InvoiceRepository())->find($invoiceId);
        if ($invoice) {
            $payUrl = invoice_pay_url($invoice);
            $contextLabel = 'Invoice #' . (string) $invoice['invoice_number'];
        }
    }
}

$outcome = ['status' => 'unknown', 'message' => ''];
if ($attempt && ($invoice || $submission)) {
    // Redirect params are only a hint; Converge is queried before anything is recorded.
    $outcome = elavon_confirm_payment($attempt, $txnId);
    if ($invoice) {
        $invoice = (new InvoiceRepository())->find((int) $invoice['id']) ?? $invoice;
    }
}

$company = invoice_company_settings();
$pageTitle = 'Payment';
$seoTitle = 'Payment | Verma Accounting';
$seoDescription = 'Payment result.';
$seoNoindex = true;

include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="<?= e(asset_versioned('css/custom-form.css')) ?>">
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
          <?php if ($contextLabel !== ''): ?>
            <p class="vf-card-desc"><?= e($contextLabel) ?></p>
          <?php endif; ?>
        </div>
      </header>
      <div class="vf-card-body">
        <?php if ($outcome['status'] === 'approved'): ?>
          <div class="pay-alert pay-alert-success">
            Thank you! Your payment of <strong><?= e(invoice_format_money((float) ($attempt['amount'] ?? 0))) ?></strong> was approved.
          </div>
          <?php if ($invoice): ?>
            <?php $remaining = invoice_amount_due($invoice); ?>
            <p class="pay-note">
              <?= $remaining > 0
                ? 'Remaining balance: ' . e(invoice_format_money($remaining)) . '.'
                : 'Your invoice is now fully paid.' ?>
              Please keep the receipt from Elavon for your records.
            </p>
          <?php else: ?>
            <p class="pay-note">Your advance payment has been recorded. Please keep the receipt from Elavon for your records.</p>
          <?php endif; ?>
        <?php elseif ($outcome['status'] === 'declined'): ?>
          <div class="pay-alert pay-alert-error">Your card was not charged: <?= e($outcome['message']) ?></div>
          <?php if ($payUrl !== ''): ?>
            <p><a class="pay-button" href="<?= e($payUrl) ?>">Try again</a></p>
          <?php endif; ?>
        <?php elseif ($outcome['status'] === 'pending'): ?>
          <p class="pay-note">
            We haven’t received confirmation from Elavon yet. If you completed the payment, there’s nothing more to do —
            we’ll update your records once it’s confirmed.
            <?php if ($payUrl !== ''): ?>
              You can also <a href="<?= e($payUrl) ?>">return to the payment page</a> and use “Check payment status”.
            <?php endif; ?>
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
