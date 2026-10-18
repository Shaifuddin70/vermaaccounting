<?php
declare(strict_types=1);

/**
 * Waiting room after starting an Elavon payment.
 * Card entry happens in a new Elavon tab; this page stays open and polls until confirmed.
 */

require_once __DIR__ . '/lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
Auth::startSession();

$token = trim((string) ($_GET['t'] ?? ''));
$reference = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['ref'] ?? '')) ?? '';
$elavonUrl = trim((string) ($_GET['hpp'] ?? ''));
$alreadyOpened = isset($_GET['opened']) && (string) $_GET['opened'] !== '' && (string) $_GET['opened'] !== '0';

$invoice = $token !== '' ? invoice_find_by_pay_token($token) : null;
$submission = ($invoice || $token === '') ? null : submission_find_by_pay_token($token);
$attempt = $reference !== '' ? online_payment_find_by_reference($reference) : null;

if ($attempt === null || (!$invoice && !$submission)) {
    http_response_code(404);
}

// Only allow redirecting to Converge hosted-payments URLs.
if ($elavonUrl !== '' && !preg_match('#^https://api\.(demo\.)?convergepay\.com/hosted-payments(\?|$)#i', $elavonUrl)) {
    $elavonUrl = '';
}

$status = (string) ($attempt['status'] ?? 'unknown');
$amount = (float) ($attempt['amount'] ?? 0);
$payUrl = '';
$contextLabel = 'Payment';

if ($invoice) {
    $payUrl = invoice_pay_url($invoice);
    $contextLabel = 'Invoice #' . (string) ($invoice['invoice_number'] ?? '');
} elseif ($submission) {
    $payUrl = submission_pay_url($submission);
    $form = (new FormRepository())->find((int) $submission['form_id']);
    $contextLabel = $form ? (string) $form['title'] : 'Advance payment';
}

if ($status === 'pending' && $attempt && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $result = elavon_confirm_payment($attempt);
        $status = $result['status'];
        $attempt = online_payment_find_by_reference($reference) ?? $attempt;
        $amount = (float) ($attempt['amount'] ?? $amount);
    }
}

$company = invoice_company_settings();
$pageTitle = 'Complete payment';
$seoTitle = 'Complete payment | Verma Accounting';
$seoDescription = 'Complete your secure card payment.';
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
          <h1 class="vf-card-title" id="pay-wait-title">
            <?php if ($status === 'approved'): ?>Payment received
            <?php elseif ($status === 'declined'): ?>Payment not completed
            <?php elseif ($attempt): ?>Waiting for payment
            <?php else: ?>Payment not found<?php endif; ?>
          </h1>
          <p class="vf-card-desc"><?= e($contextLabel) ?></p>
        </div>
      </header>
      <div class="vf-card-body" id="pay-wait-body"
        data-ref="<?= e($reference) ?>"
        data-status="<?= e($status) ?>"
        data-hpp="<?= e($elavonUrl) ?>"
        data-opened="<?= $alreadyOpened ? '1' : '0' ?>"
        data-pay-url="<?= e($payUrl) ?>">
        <?php if ($attempt === null): ?>
          <p class="pay-note">We couldn’t find this payment. If you were charged, contact us at
            <a href="mailto:<?= e($company['payment_email']) ?>"><?= e($company['payment_email']) ?></a>.</p>
        <?php elseif ($status === 'approved'): ?>
          <div class="pay-alert pay-alert-success" role="status">
            Thank you! Your payment of <strong><?= e(invoice_format_money($amount)) ?></strong> was approved and recorded.
          </div>
        <?php elseif ($status === 'declined'): ?>
          <div class="pay-alert pay-alert-error" role="alert">Your card was not charged.</div>
          <?php if ($payUrl !== ''): ?>
            <p><a class="pay-button" href="<?= e($payUrl) ?>">Try again</a></p>
          <?php endif; ?>
        <?php else: ?>
          <div class="pay-wait-loading" id="pay-wait-loading" aria-live="polite">
            <div class="pay-wait-spinner" aria-hidden="true"></div>
            <p class="pay-wait-status" id="pay-wait-polling">Waiting for payment…</p>
            <p class="pay-note pay-wait-hint">Complete your card payment in the Elavon tab. Keep this page open.</p>
          </div>
          <?php if ($elavonUrl !== ''): ?>
            <p class="pay-wait-reopen">
              <a href="<?= e($elavonUrl) ?>" target="_blank" rel="noopener noreferrer" id="pay-wait-open">Open payment tab again</a>
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<script>
(function () {
  var body = document.getElementById('pay-wait-body');
  if (!body) return;
  var ref = body.getAttribute('data-ref') || '';
  var status = body.getAttribute('data-status') || '';
  var hpp = body.getAttribute('data-hpp') || '';
  var opened = body.getAttribute('data-opened') === '1';
  var payUrl = body.getAttribute('data-pay-url') || '';
  var title = document.getElementById('pay-wait-title');
  var polling = document.getElementById('pay-wait-polling');

  // Only auto-open when arriving via no-JS redirect (pay page did not open the tab).
  if (hpp && status === 'pending' && !opened) {
    try { window.open(hpp, '_blank', 'noopener,noreferrer'); } catch (e) {}
  }
  if (!ref || status !== 'pending') return;

  var tries = 0;
  var timer = setInterval(async function () {
    tries += 1;
    if (tries > 120) {
      clearInterval(timer);
      if (polling) polling.textContent = 'Still waiting. If you already paid, refresh this page or contact us.';
      return;
    }
    try {
      var res = await fetch('/api/elavon-status?ref=' + encodeURIComponent(ref), { credentials: 'same-origin' });
      var json = await res.json();
      if (!json || !json.status) return;
      if (json.status === 'approved') {
        clearInterval(timer);
        if (title) title.textContent = 'Payment received';
        body.innerHTML = '<div class="pay-alert pay-alert-success" role="status">Thank you! Your payment of <strong>'
          + (json.amount_label || '') + '</strong> was approved and recorded.</div>';
      } else if (json.status === 'declined') {
        clearInterval(timer);
        if (title) title.textContent = 'Payment not completed';
        var html = '<div class="pay-alert pay-alert-error" role="alert">Your card was not charged'
          + (json.message ? ': ' + json.message : '.') + '</div>';
        if (payUrl) {
          html += '<p><a class="pay-button" href="' + payUrl + '">Try again</a></p>';
        }
        body.innerHTML = html;
      }
    } catch (e) {}
  }, 3000);
})();
</script>
<?php include __DIR__ . '/components/footer.php'; ?>
