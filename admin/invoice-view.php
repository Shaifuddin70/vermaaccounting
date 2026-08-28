<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);
$repo = new InvoiceRepository();
$invoice = $id > 0 ? $repo->find($id) : null;

if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /admin/invoices');
    exit;
}

$items = $repo->itemsForInvoice($id);
$company = invoice_company_settings();
$billLines = invoice_bill_to_lines($invoice);
$currency = (string) ($invoice['currency'] ?? 'CAD');
$discountState = invoice_discount_state($invoice);
$subtotal = (float) ($invoice['subtotal'] ?? 0);
$total = (float) ($invoice['total'] ?? 0);
$status = (string) ($invoice['status'] ?? 'draft');
$csrf = Auth::csrfToken();
$print = isset($_GET['print']);
$mail = mail_config();
$mailEnabled = !empty($mail['enabled']);

$sendOld = $_SESSION['invoice_send_old'] ?? [];
unset($_SESSION['invoice_send_old']);
$defaultTo = (string) ($sendOld['to_email'] ?? invoice_recipient_email($invoice));
$defaultMessage = (string) ($sendOld['email_message'] ?? '');

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
    <a href="/admin/invoice-edit?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Edit</a>
    <a href="/admin/invoice-pdf?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Download PDF</a>
    <a href="/admin/invoice-view?id=<?= $id ?>&print=1" class="admin-btn admin-btn-secondary" target="_blank">Print</a>
    <a href="#send-invoice" class="admin-btn admin-btn-primary">Send to client</a>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← All invoices</a>
  </div>
</div>

<div class="admin-card invoice-status-bar">
  <div class="invoice-view-meta">
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

<link rel="stylesheet" href="/admin/css/invoice-print.css?v=3">
<div class="invoice-preview-frame">
  <?php require __DIR__ . '/includes/invoice-document.php'; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
