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
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Invoice #<?= e((string) $invoice['invoice_number']) ?></h1>
  <div class="admin-header-actions">
    <?php if (Auth::can('invoices.edit')): ?>
    <a href="/admin/invoice-edit?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Edit</a>
    <?php endif; ?>
    <?php if ($canRecordPayment && $amountDue > 0): ?>
    <button type="button" class="admin-btn admin-btn-primary" data-open-payment>Record payment</button>
    <?php endif; ?>
    <a href="/admin/invoice-pdf?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Download PDF</a>
    <a href="/admin/invoice-view?id=<?= $id ?>&print=1" class="admin-btn admin-btn-secondary" target="_blank">Print</a>
    <?php if (Auth::can('invoices.send')): ?>
    <a href="#send-invoice" class="admin-btn admin-btn-primary">Send to client</a>
    <?php endif; ?>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← All invoices</a>
  </div>
</div>

<div class="admin-card invoice-status-bar">
  <div class="invoice-view-meta">
    <?php if (Auth::can('invoices.edit')): ?>
    <form method="post" action="/admin/invoice-action" class="invoice-status-form">
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
    <?php else: ?>
      <div class="invoice-status-form">
        <span class="admin-field-hint">Status</span>
        <strong><?= e(invoice_status_label($status)) ?></strong>
      </div>
    <?php endif; ?>
    <?php if ($clientName !== ''): ?>
      <div class="invoice-view-client">
        <span class="admin-field-hint">Client</span>
        <strong><?= e($clientName) ?></strong>
        <?php if ($clientCompany !== '' && strcasecmp($clientCompany, $clientName) !== 0): ?>
          <span class="admin-field-hint"><?= e($clientCompany) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <p class="admin-field-hint" style="margin:0;">Download PDF or email it directly to the client.</p>
</div>

<div class="admin-card invoice-payments" id="payments">
  <div class="invoice-payments-head">
    <h2 class="admin-card-title">Payments</h2>
    <?php if ($canRecordPayment && $amountDue > 0): ?>
      <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-open-payment>+ Record payment</button>
    <?php endif; ?>
  </div>

  <div class="invoice-payments-summary">
    <div class="invoice-payments-stat">
      <span>Invoice amount</span>
      <strong><?= e(invoice_format_money($invoiceDue)) ?></strong>
    </div>
    <div class="invoice-payments-stat invoice-payments-stat--paid">
      <span>Paid</span>
      <strong><?= e(invoice_format_money($amountPaid)) ?></strong>
    </div>
    <div class="invoice-payments-stat <?= $amountDue > 0 ? 'invoice-payments-stat--due' : 'invoice-payments-stat--settled' ?>">
      <span>Balance due</span>
      <strong><?= e(invoice_format_money($amountDue)) ?></strong>
    </div>
  </div>
  <div class="invoice-payments-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $paidPercent ?>">
    <span style="width: <?= $paidPercent ?>%"></span>
  </div>
  <p class="admin-field-hint invoice-payments-progress-label">
    <?php if ($amountDue <= 0 && $amountPaid > 0): ?>
      Fully paid.
    <?php elseif ($amountPaid > 0): ?>
      <?= $paidPercent ?>% paid across <?= count($payments) ?> payment<?= count($payments) === 1 ? '' : 's' ?>.
    <?php else: ?>
      No payments recorded yet.
    <?php endif; ?>
  </p>

  <?php if ($payments !== []): ?>
  <div class="admin-table-scroll">
    <table class="admin-table invoice-payments-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Method</th>
          <th>Reference / note</th>
          <th>Recorded by</th>
          <th class="invoice-payments-amount">Amount</th>
          <?php if ($canRecordPayment): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $payment): ?>
          <?php
            $ref = trim((string) ($payment['reference'] ?? ''));
            $note = trim((string) ($payment['note'] ?? ''));
          ?>
          <tr>
            <td><?= e(invoice_format_date((string) $payment['payment_date'])) ?></td>
            <td><?= e(invoice_payment_method_label($payment['method'] ?? null)) ?></td>
            <td>
              <?php if ($ref === '' && $note === ''): ?>
                <span class="admin-field-hint">—</span>
              <?php else: ?>
                <?php if ($ref !== ''): ?><div><?= e($ref) ?></div><?php endif; ?>
                <?php if ($note !== ''): ?><div class="admin-field-hint"><?= e($note) ?></div><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= e((string) ($payment['recorded_by_name'] ?? '') ?: '—') ?></td>
            <td class="invoice-payments-amount"><strong><?= e(invoice_format_money((float) $payment['amount'])) ?></strong></td>
            <?php if ($canRecordPayment): ?>
            <td class="admin-table-actions">
              <form method="post" action="/admin/invoice-action"
                onsubmit="return confirm('Remove this payment of <?= e(invoice_format_money((float) $payment['amount'])) ?>?');">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Remove</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
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

<?php if (Auth::can('invoices.send')): ?>
<div class="admin-card" id="send-invoice">
  <h2 class="admin-card-title">Send invoice PDF to client</h2>
  <?php if (!$mailEnabled): ?>
    <div class="admin-alert admin-alert-info">
      Email is currently disabled. Enable it in
      <a href="/admin/email-settings">Email settings</a>
      (or use production mail config) before sending.
    </div>
  <?php endif; ?>
  <form method="post" action="/admin/invoice-action" class="invoice-send-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="send_email">
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="to-email">Client email <span class="required">*</span></label>
        <input type="email" id="to-email" name="to_email" required value="<?= e($defaultTo) ?>"
          placeholder="client@example.com" <?= $mailEnabled ? '' : 'disabled' ?>>
        <small class="admin-field-hint">
          <?= $defaultTo !== '' ? 'Prefilled from the linked client record.' : 'No client email on file — enter one to send.' ?>
        </small>
      </div>
      <div class="admin-field">
        <label for="email-message">Optional message</label>
        <textarea id="email-message" name="email_message" rows="3" placeholder="Add a short note for the client…"
          <?= $mailEnabled ? '' : 'disabled' ?>><?= e($defaultMessage) ?></textarea>
      </div>
    </div>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary" <?= $mailEnabled ? '' : 'disabled' ?>
        onclick="return confirm('Send invoice #<?= e((string) $invoice['invoice_number']) ?> PDF to this email?');">
        Send PDF by email
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<link rel="stylesheet" href="/admin/css/invoice-print.css?v=3">
<div class="invoice-preview-frame">
  <?php require __DIR__ . '/includes/invoice-document.php'; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
