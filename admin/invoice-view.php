<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('invoices.view');

$id = (int) ($_GET['id'] ?? 0);
$repo = new InvoiceRepository();
$partnerId = partner_user_id();
$invoice = $id > 0 ? $repo->find($id, $partnerId) : null;

if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /admin/invoices');
    exit;
}
assert_invoice_access($invoice);

$items = $repo->itemsForInvoice($id);
$company = invoice_company_settings();
$billLines = invoice_bill_to_lines($invoice);
$currency = (string) ($invoice['currency'] ?? 'CAD');
$discountState = invoice_discount_state($invoice);
$dueState = invoice_due_state($invoice);
$subtotal = (float) ($invoice['subtotal'] ?? 0);
$total = (float) ($invoice['total'] ?? 0);
$amountDue = (float) $dueState['amount_due'];
$status = (string) ($invoice['status'] ?? 'draft');
$csrf = Auth::csrfToken();
$print = isset($_GET['print']);
$mail = mail_config();
$mailEnabled = !empty($mail['enabled']);

$sendOld = $_SESSION['invoice_send_old'] ?? [];
unset($_SESSION['invoice_send_old']);
$defaultTo = (string) ($sendOld['to_email'] ?? invoice_recipient_email($invoice));
$defaultMessage = (string) ($sendOld['email_message'] ?? '');

$payments = $repo->payments($id);
$invoiceDue = (float) $dueState['invoice_due'];
$amountPaid = (float) $dueState['amount_paid'];
$paidPercent = $invoiceDue > 0 ? (int) min(100, round($amountPaid / $invoiceDue * 100)) : ($amountPaid > 0 ? 100 : 0);
$canRecordPayment = Auth::can('invoices.edit');
$paymentOld = $_SESSION['invoice_payment_old'] ?? null;
unset($_SESSION['invoice_payment_old']);
$dueDate = (string) ($invoice['due_date'] ?? '');
$isOverdue = $amountDue > 0 && $status !== 'draft' && $dueDate !== '' && $dueDate < date('Y-m-d');
$balanceTone = $amountDue <= 0 ? ($amountPaid > 0 || $status === 'paid' ? 'settled' : 'neutral') : ($isOverdue ? 'overdue' : 'due');
$statusBadge = match ($status) {
    'approved' => 'admin-badge-success',
    'sent' => 'admin-badge-info',
    'paid' => 'admin-badge-paid',
    default => 'admin-badge-muted',
};
$paymentForm = [
    'amount' => (string) ($paymentOld['amount'] ?? ($amountDue > 0 ? number_format($amountDue, 2, '.', '') : '')),
    'payment_date' => (string) ($paymentOld['payment_date'] ?? date('Y-m-d')),
    'method' => (string) ($paymentOld['method'] ?? ''),
    'reference' => (string) ($paymentOld['reference'] ?? ''),
    'note' => (string) ($paymentOld['note'] ?? ''),
];

if ($print) {
    $docTitle = 'Invoice_' . $invoice['invoice_number'] . '_' . ($invoice['invoice_date'] ?? '');
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($docTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/css/invoice-print.css?v=3">
</head>
<body class="invoice-print-body">
  <div class="invoice-print-toolbar no-print">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
    <a href="/admin/invoice-view?id=<?= $id ?>">← Back to invoice</a>
  </div>
  <?php require __DIR__ . '/includes/invoice-document.php'; ?>
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () { window.print(); }, 250);
    });
  </script>
</body>
</html>
    <?php
    exit;
}

$pageTitle = 'Invoice #' . $invoice['invoice_number'];
$activeNav = 'invoices';
$clientName = trim((string) ($invoice['client_name'] ?? ''));
$clientCompany = trim((string) ($invoice['client_company'] ?? ''));
$clientId = (int) ($invoice['client_id'] ?? 0);
$billName = $clientName !== '' ? $clientName : trim((string) ($invoice['bill_to_name'] ?? ''));
if ($billName === '') {
    $billName = trim((string) ($invoice['bill_to_company'] ?? ''));
}
$createdBy = trim((string) ($invoice['created_by_name'] ?? ''));
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Invoice #<?= e((string) $invoice['invoice_number']) ?></h1>
  <div class="admin-header-actions">
    <?php if (Auth::can('invoices.edit')): ?>
    <a href="/admin/invoice-edit?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Edit</a>
    <?php endif; ?>
    <a href="/admin/invoice-pdf?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Download PDF</a>
    <a href="/admin/invoice-view?id=<?= $id ?>&print=1" class="admin-btn admin-btn-secondary" target="_blank">Print</a>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← All invoices</a>
  </div>
</div>

<div class="invoice-view-layout">
  <div class="invoice-view-main">
    <link rel="stylesheet" href="/admin/css/invoice-print.css?v=3">
    <div class="invoice-preview-frame">
      <?php require __DIR__ . '/includes/invoice-document.php'; ?>
    </div>
  </div>

  <aside class="invoice-view-aside">
    <section class="admin-card invoice-side-card invoice-summary-card invoice-summary-card--<?= e($balanceTone) ?>">
      <div class="invoice-summary-top">
        <span class="admin-badge <?= e($statusBadge) ?>"><?= e(invoice_status_label($status)) ?></span>
        <?php if ($isOverdue): ?>
          <span class="admin-badge invoice-badge-overdue">Overdue</span>
        <?php elseif ($amountPaid > 0 && $amountDue > 0): ?>
          <span class="admin-badge admin-badge-partial">Partially paid</span>
        <?php endif; ?>
      </div>
      <div class="invoice-summary-balance">
        <span>Balance due</span>
        <strong><?= e(invoice_format_money($amountDue)) ?> <small><?= e($currency) ?></small></strong>
      </div>
      <div class="invoice-payments-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $paidPercent ?>">
        <span style="width: <?= $paidPercent ?>%"></span>
      </div>
      <dl class="invoice-summary-figures">
        <div>
          <dt>Invoice amount</dt>
          <dd><?= e(invoice_format_money($invoiceDue)) ?></dd>
        </div>
        <div>
          <dt>Paid</dt>
          <dd class="is-paid"><?= e(invoice_format_money($amountPaid)) ?></dd>
        </div>
      </dl>
      <?php if ($canRecordPayment && $amountDue > 0): ?>
        <button type="button" class="admin-btn admin-btn-primary invoice-side-btn" data-open-payment>Record payment</button>
      <?php elseif ($amountDue <= 0 && ($amountPaid > 0 || $status === 'paid')): ?>
        <p class="invoice-summary-settled">Paid in full</p>
      <?php endif; ?>
    </section>

    <section class="admin-card invoice-side-card">
      <h2 class="invoice-side-title">Details</h2>
      <?php if (Auth::can('invoices.edit')): ?>
        <form method="post" action="/admin/invoice-action" class="invoice-status-form invoice-side-status">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="status">
          <label for="status-change">Status</label>
          <select id="status-change" name="status" onchange="this.form.submit()">
            <?php foreach (invoice_status_options() as $opt): ?>
              <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e(invoice_status_label($opt)) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <dl class="invoice-side-list">
        <div>
          <dt>Client</dt>
          <dd>
            <?php if ($clientId > 0 && Auth::can('clients.view')): ?>
              <a href="/admin/client?id=<?= $clientId ?>"><?= e($billName) ?></a>
            <?php else: ?>
              <?= e($billName !== '' ? $billName : '—') ?>
            <?php endif; ?>
            <?php if ($clientCompany !== '' && strcasecmp($clientCompany, $billName) !== 0): ?>
              <span class="admin-field-hint"><?= e($clientCompany) ?></span>
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt>Invoice date</dt>
          <dd><?= e(invoice_format_date((string) ($invoice['invoice_date'] ?? ''))) ?></dd>
        </div>
        <div>
          <dt>Payment due</dt>
          <dd class="<?= $isOverdue ? 'is-overdue' : '' ?>"><?= e(invoice_format_date($dueDate)) ?></dd>
        </div>
        <?php if ($createdBy !== ''): ?>
        <div>
          <dt>Created by</dt>
          <dd><?= e($createdBy) ?></dd>
        </div>
        <?php endif; ?>
      </dl>
    </section>

    <section class="admin-card invoice-side-card" id="payments">
      <div class="invoice-side-head">
        <h2 class="invoice-side-title">Payments<?php if ($payments !== []): ?> <span class="invoice-side-count"><?= count($payments) ?></span><?php endif; ?></h2>
        <?php if ($canRecordPayment && $amountDue > 0): ?>
          <button type="button" class="invoice-side-link" data-open-payment>+ Add</button>
        <?php endif; ?>
      </div>
      <?php if ($payments === []): ?>
        <p class="invoice-side-empty">No payments recorded yet.</p>
      <?php else: ?>
        <ul class="invoice-payment-list">
          <?php foreach ($payments as $payment):
            $ref = trim((string) ($payment['reference'] ?? ''));
            $note = trim((string) ($payment['note'] ?? ''));
            $by = trim((string) ($payment['recorded_by_name'] ?? ''));
            ?>
            <li class="invoice-payment-item">
              <div class="invoice-payment-item-main">
                <strong><?= e(invoice_format_money((float) $payment['amount'])) ?></strong>
                <span><?= e(invoice_format_date((string) $payment['payment_date'])) ?> · <?= e(invoice_payment_method_label($payment['method'] ?? null)) ?></span>
                <?php if ($ref !== ''): ?><span class="invoice-payment-item-ref">Ref: <?= e($ref) ?></span><?php endif; ?>
                <?php if ($note !== ''): ?><span class="invoice-payment-item-note"><?= e($note) ?></span><?php endif; ?>
                <?php if ($by !== ''): ?><span class="invoice-payment-item-by">Recorded by <?= e($by) ?></span><?php endif; ?>
              </div>
              <?php if ($canRecordPayment): ?>
                <form method="post" action="/admin/invoice-action"
                  onsubmit="return confirm(<?= e_js('Remove this payment of ' . invoice_format_money((float) $payment['amount']) . '?') ?>);">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="action" value="delete_payment">
                  <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                  <button type="submit" class="invoice-payment-remove" aria-label="Remove payment" title="Remove payment">&times;</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (Auth::can('invoices.send')): ?>
    <section class="admin-card invoice-side-card" id="send-invoice">
      <h2 class="invoice-side-title">Email to client</h2>
      <?php if (!$mailEnabled): ?>
        <p class="invoice-side-notice">
          Email is disabled. Enable it in <a href="/admin/email-settings">Email settings</a> to send.
        </p>
      <?php endif; ?>
      <form method="post" action="/admin/invoice-action" class="invoice-send-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="send_email">
        <div class="admin-field">
          <label for="to-email">Client email <span class="required">*</span></label>
          <input type="email" id="to-email" name="to_email" required value="<?= e($defaultTo) ?>"
            placeholder="client@example.com" <?= $mailEnabled ? '' : 'disabled' ?>>
          <?php if ($defaultTo === ''): ?>
            <small class="admin-field-hint">No client email on file — enter one to send.</small>
          <?php endif; ?>
        </div>
        <div class="admin-field">
          <label for="email-message">Message <span class="admin-field-hint">(optional)</span></label>
          <textarea id="email-message" name="email_message" rows="3" placeholder="Add a short note for the client…"
            <?= $mailEnabled ? '' : 'disabled' ?>><?= e($defaultMessage) ?></textarea>
        </div>
        <button type="submit" class="admin-btn admin-btn-secondary invoice-side-btn" <?= $mailEnabled ? '' : 'disabled' ?>
          onclick="return confirm(<?= e_js('Send invoice #' . $invoice['invoice_number'] . ' PDF to this email?') ?>);">
          Send PDF by email
        </button>
      </form>
    </section>
    <?php endif; ?>
  </aside>
</div>

<?php if ($canRecordPayment): ?>
<dialog class="invoice-payment-dialog" id="payment-dialog" <?= $paymentOld !== null ? 'data-autoopen' : '' ?>>
  <form method="post" action="/admin/invoice-action">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="record_payment">
    <div class="invoice-payment-dialog-head">
      <h2>Record payment</h2>
      <p class="admin-field-hint">
        Invoice #<?= e((string) $invoice['invoice_number']) ?> · Balance due
        <strong><?= e(invoice_format_money($amountDue)) ?></strong>
      </p>
    </div>
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="payment-amount">Amount (<?= e($currency) ?>) <span class="required">*</span></label>
        <input type="number" id="payment-amount" name="amount" step="0.01" min="0.01"
          max="<?= e(number_format($amountDue, 2, '.', '')) ?>" required inputmode="decimal"
          value="<?= e($paymentForm['amount']) ?>">
        <small class="admin-field-hint">Enter a partial amount if the client paid part of the balance.</small>
      </div>
      <div class="admin-field">
        <label for="payment-date">Payment date <span class="required">*</span></label>
        <input type="date" id="payment-date" name="payment_date" required value="<?= e($paymentForm['payment_date']) ?>">
      </div>
      <div class="admin-field">
        <label for="payment-method">Method</label>
        <select id="payment-method" name="method">
          <option value="">— Select —</option>
          <?php foreach (invoice_payment_methods() as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $paymentForm['method'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-field">
        <label for="payment-reference">Reference</label>
        <input type="text" id="payment-reference" name="reference" maxlength="191"
          placeholder="e-Transfer ref, cheque #…" value="<?= e($paymentForm['reference']) ?>">
      </div>
    </div>
    <div class="admin-field">
      <label for="payment-note">Note</label>
      <textarea id="payment-note" name="note" rows="2" placeholder="Optional internal note"><?= e($paymentForm['note']) ?></textarea>
    </div>
    <div class="invoice-payment-dialog-actions">
      <button type="button" class="admin-btn admin-btn-secondary" data-close-payment>Cancel</button>
      <button type="submit" class="admin-btn admin-btn-primary">Save payment</button>
    </div>
  </form>
</dialog>
<script>
  (function () {
    var dialog = document.getElementById('payment-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var amount = document.getElementById('payment-amount');
    function open() {
      dialog.showModal();
      if (amount) { amount.focus(); amount.select(); }
    }
    document.querySelectorAll('[data-open-payment]').forEach(function (btn) {
      btn.addEventListener('click', open);
    });
    dialog.querySelectorAll('[data-close-payment]').forEach(function (btn) {
      btn.addEventListener('click', function () { dialog.close(); });
    });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
    if (dialog.hasAttribute('data-autoopen')) open();
  })();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
