<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
Auth::startSession();

$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$invoice = invoice_find_by_pay_token($token);
$submission = $invoice ? null : submission_find_by_pay_token($token);
$error = null;
$success = null;

$form = null;
$schema = [];
$data = [];
$payTarget = null;
if ($submission) {
    $form = (new FormRepository())->find((int) $submission['form_id']);
    $schema = $form ? (new FormRepository())->decodeSchema($form) : ['fields' => []];
    $raw = json_decode((string) ($submission['data_json'] ?? '{}'), true);
    $data = is_array($raw) ? $raw : [];
    $payTarget = submission_elavon_pay_target((int) $submission['id'], $schema, $data);
}

$pendingAttempts = [];
if ($invoice) {
    $pendingAttempts = array_values(array_filter(
        online_payments_for_invoice((int) $invoice['id']),
        static fn (array $row): bool => ($row['status'] ?? '') === 'pending'
    ));
} elseif ($submission) {
    $pendingAttempts = array_values(array_filter(
        online_payments_for_submission((int) $submission['id']),
        static fn (array $row): bool => ($row['status'] ?? '') === 'pending'
    ));
}

if (($invoice || $submission) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'pay');
        if ($action === 'check_status') {
            $confirmed = false;
            foreach ($pendingAttempts as $attempt) {
                $result = elavon_confirm_payment($attempt);
                if ($result['status'] === 'approved') {
                    $confirmed = true;
                    $success = 'Payment confirmed and recorded.';
                    break;
                }
                if ($result['status'] === 'declined') {
                    $error = $result['message'] ?: 'The card was declined.';
                } elseif ($error === null) {
                    $error = $result['message'] ?: 'No matching card transaction was found yet.';
                }
            }
            if ($confirmed) {
                $error = null;
                if ($invoice) {
                    $invoice = (new InvoiceRepository())->find((int) $invoice['id']) ?? $invoice;
                } elseif ($submission) {
                    $payTarget = submission_elavon_pay_target((int) $submission['id'], $schema, $data);
                }
                $pendingAttempts = [];
            }
        } elseif ($invoice) {
            $result = elavon_start_invoice_payment($invoice);
            if ($result['ok']) {
                // Stay on our site and poll Converge — NextGen HPP "Continue" often never returns.
                $wait = '/pay-wait?t=' . rawurlencode($token)
                    . '&ref=' . rawurlencode((string) ($result['reference'] ?? ''))
                    . '&hpp=' . rawurlencode((string) ($result['redirect'] ?? ''));
                header('Location: ' . $wait, true, 303);
                exit;
            }
            $error = $result['error'] ?? 'Could not start the payment.';
        } elseif ($submission) {
            $result = elavon_start_submission_payment($submission, $schema, $data);
            if ($result['ok']) {
                $wait = '/pay-wait?t=' . rawurlencode($token)
                    . '&ref=' . rawurlencode((string) ($result['reference'] ?? ''))
                    . '&hpp=' . rawurlencode((string) ($result['redirect'] ?? ''));
                header('Location: ' . $wait, true, 303);
                exit;
            }
            $error = $result['error'] ?? 'Could not start the payment.';
        }
    }
}

if (!$invoice && !$submission) {
    http_response_code(404);
}

$company = invoice_company_settings();
$seoNoindex = true;

if ($invoice) {
    $amountDue = invoice_amount_due($invoice);
    $canPay = invoice_can_pay_online($invoice);
    $pageTitle = 'Pay invoice';
    $seoTitle = 'Pay your invoice | Verma Accounting';
    $seoDescription = 'Secure online invoice payment.';
    $heading = 'Invoice #' . (string) $invoice['invoice_number'];
    $subheading = $company['name'];
    $alreadyPaid = $amountDue <= 0;
} elseif ($submission) {
    $amountDue = $payTarget['amount'] ?? 0.0;
    $canPay = $payTarget !== null;
    $pageTitle = 'Pay advance';
    $seoTitle = 'Pay your advance | Verma Accounting';
    $seoDescription = 'Secure online advance payment.';
    $heading = $form ? (string) $form['title'] : 'Advance payment';
    $subheading = 'Advance payment';
    $alreadyPaid = false;
    foreach ((new SubmissionPaymentRepository())->forSubmission((int) $submission['id']) as $row) {
        if (($row['method'] ?? '') === 'credit_card' && in_array((string) ($row['status'] ?? ''), ['verified', 'applied'], true)) {
            $alreadyPaid = true;
            $amountDue = (float) ($row['amount'] ?? 0);
            break;
        }
    }
} else {
    $amountDue = 0.0;
    $canPay = false;
    $alreadyPaid = false;
    $pageTitle = 'Pay';
    $seoTitle = 'Payment | Verma Accounting';
    $seoDescription = 'Secure online payment.';
    $heading = 'Payment link not found';
    $subheading = '';
}

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
          <h1 class="vf-card-title"><?= e($heading) ?></h1>
          <?php if ($subheading !== ''): ?>
            <p class="vf-card-desc"><?= e($subheading) ?></p>
          <?php endif; ?>
        </div>
      </header>
      <div class="vf-card-body">
        <?php if (!$invoice && !$submission): ?>
          <p class="pay-note">This payment link is not valid. Please use the link from your email or form confirmation, or contact us at
            <a href="mailto:<?= e($company['payment_email']) ?>"><?= e($company['payment_email']) ?></a>.</p>
        <?php else: ?>
          <?php if ($success !== null): ?>
            <div class="pay-alert pay-alert-success" role="status"><?= e($success) ?></div>
          <?php endif; ?>
          <?php if ($error !== null): ?>
            <div class="pay-alert pay-alert-error" role="alert"><?= e($error) ?></div>
          <?php endif; ?>

          <?php if ($invoice): ?>
            <dl class="pay-summary">
              <?php $billName = trim((string) ($invoice['bill_to_name'] ?? '')) ?: trim((string) ($invoice['bill_to_company'] ?? '')); ?>
              <?php if ($billName !== ''): ?>
                <div><dt>Billed to</dt><dd><?= e($billName) ?></dd></div>
              <?php endif; ?>
              <div><dt>Invoice date</dt><dd><?= e(invoice_format_date((string) ($invoice['invoice_date'] ?? ''))) ?></dd></div>
              <div><dt>Payment due</dt><dd><?= e(invoice_format_date((string) ($invoice['due_date'] ?? ''))) ?></dd></div>
              <div class="pay-summary-total"><dt>Amount due</dt><dd><?= e(invoice_format_money($amountDue)) ?> <?= e((string) ($invoice['currency'] ?? 'CAD')) ?></dd></div>
            </dl>
          <?php else: ?>
            <dl class="pay-summary">
              <div class="pay-summary-total"><dt>Amount due</dt><dd><?= e(invoice_format_money((float) $amountDue)) ?> CAD</dd></div>
            </dl>
          <?php endif; ?>

          <?php if ($alreadyPaid || ($invoice && $amountDue <= 0)): ?>
            <div class="pay-alert pay-alert-success"><?= $invoice ? 'This invoice is fully paid. Thank you!' : 'This advance is already paid. Thank you!' ?></div>
          <?php elseif (!$canPay): ?>
            <p class="pay-note">Online card payment is not available right now. Please pay by Interac e-Transfer to
              <a href="mailto:<?= e($company['payment_email']) ?>"><?= e($company['payment_email']) ?></a>.</p>
          <?php else: ?>
            <form method="post" action="/pay" class="pay-form">
              <input type="hidden" name="t" value="<?= e($token) ?>">
              <input type="hidden" name="action" value="pay">
              <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
              <button type="submit" class="pay-button">Pay <?= e(invoice_format_money((float) $amountDue)) ?> by card</button>
            </form>
            <p class="pay-secure">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              You’ll enter your card on Elavon’s secure payment page. We never see or store your card number.
            </p>
            <?php if (elavon_config()['environment'] !== 'live'): ?>
              <p class="pay-test-mode">Test mode — no real charge will be made.</p>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($pendingAttempts !== []): ?>
            <form method="post" action="/pay" class="pay-form" style="margin-top:1rem;">
              <input type="hidden" name="t" value="<?= e($token) ?>">
              <input type="hidden" name="action" value="check_status">
              <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
              <button type="submit" class="pay-button" style="background:#0f766e;">I already paid — check status</button>
            </form>
            <p class="pay-note">If Elavon approved your card but this page didn’t update, use the button above to pull the confirmation.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
