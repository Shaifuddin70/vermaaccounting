<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$invoiceRepo = new InvoiceRepository();
$serviceRepo = new InvoiceServiceRepository();
$clientRepo = new ClientRepository();

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$invoice = $editId ? $invoiceRepo->find($editId) : null;

if ($editId && !$invoice) {
    header('Location: /admin/invoices');
    exit;
}

$existingItems = $editId ? $invoiceRepo->itemsForInvoice($editId) : [];
$errors = $_SESSION['invoice_edit_errors'] ?? [];
$old = $_SESSION['invoice_edit_old'] ?? [];
unset($_SESSION['invoice_edit_errors'], $_SESSION['invoice_edit_old']);

$services = $serviceRepo->activeForSelect();
$lineItems = [];
if ($old !== []) {
    $lineItems = invoice_parse_items_from_post($old);
} else {
    foreach ($existingItems as $item) {
        $lineItems[] = [
            'service_id' => !empty($item['service_id']) ? (int) $item['service_id'] : null,
            'name' => (string) ($item['name'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'unit_price' => number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
            'quantity' => rtrim(rtrim(number_format((float) ($item['quantity'] ?? 1), 2, '.', ''), '0'), '.') ?: '1',
        ];
    }
}

// Normalize prices/qty for display when coming from parsed POST (floats).
foreach ($lineItems as &$li) {
    if (is_float($li['unit_price'] ?? null) || is_int($li['unit_price'] ?? null)) {
        $li['unit_price'] = number_format((float) $li['unit_price'], 2, '.', '');
    }
    if (is_float($li['quantity'] ?? null) || is_int($li['quantity'] ?? null)) {
        $li['quantity'] = rtrim(rtrim(number_format((float) $li['quantity'], 2, '.', ''), '0'), '.') ?: '1';
    }
}
unset($li);

$clients = $clientRepo->listForSelect();

$invoiceNumber = (string) ($old['invoice_number'] ?? $invoice['invoice_number'] ?? $invoiceRepo->nextInvoiceNumber());
$clientId = (string) ($old['client_id'] ?? ($invoice['client_id'] ?? ''));
$billToCompany = (string) ($old['bill_to_company'] ?? $invoice['bill_to_company'] ?? '');
$billToName = (string) ($old['bill_to_name'] ?? $invoice['bill_to_name'] ?? '');
$billToStreet = (string) ($old['bill_to_street'] ?? $invoice['bill_to_street'] ?? '');
$billToCity = (string) ($old['bill_to_city'] ?? $invoice['bill_to_city'] ?? '');
$billToProvince = (string) ($old['bill_to_province'] ?? $invoice['bill_to_province'] ?? 'Ontario');
$billToPostal = (string) ($old['bill_to_postal'] ?? $invoice['bill_to_postal'] ?? '');
$billToCountry = (string) ($old['bill_to_country'] ?? $invoice['bill_to_country'] ?? 'Canada');
$invoiceDate = (string) ($old['invoice_date'] ?? $invoice['invoice_date'] ?? date('Y-m-d'));
$dueDate = (string) ($old['due_date'] ?? $invoice['due_date'] ?? date('Y-m-d'));
$discountPercent = (string) ($old['discount_percent'] ?? ($invoice ? rtrim(rtrim(number_format((float) $invoice['discount_percent'], 4, '.', ''), '0'), '.') : '0'));
if ($discountPercent === '') {
    $discountPercent = '0';
}
$status = (string) ($old['status'] ?? $invoice['status'] ?? 'draft');
$notes = (string) ($old['notes'] ?? $invoice['notes'] ?? invoice_default_notes());

$clientsList = [];
foreach ($clients as $c) {
    $clientsList[] = [
        'id' => (int) $c['id'],
        'name' => (string) $c['name'],
        'company' => (string) $c['company'],
        'email' => (string) $c['email'],
    ];
}

$servicesList = [];
foreach ($services as $s) {
    $servicesList[] = [
        'id' => (int) $s['id'],
        'name' => (string) $s['name'],
        'description' => (string) ($s['description'] ?? ''),
        'unit_price' => number_format((float) ($s['unit_price'] ?? 0), 2, '.', ''),
    ];
}

$selectedClient = null;
if ($clientId !== '') {
    foreach ($clientsList as $c) {
        if ((string) $c['id'] === $clientId) {
            $selectedClient = $c;
            break;
        }
    }
    if ($selectedClient === null && $invoice) {
        $selectedClient = [
            'id' => (int) $clientId,
            'name' => (string) ($invoice['client_name'] ?? $billToName),
            'company' => (string) ($invoice['client_company'] ?? $billToCompany),
            'email' => (string) ($invoice['client_email'] ?? ''),
        ];
    }
}

$csrf = Auth::csrfToken();
$pageTitle = $invoice ? 'Edit invoice #' . $invoice['invoice_number'] : 'New invoice';
$activeNav = 'invoices';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $invoice ? 'Edit invoice #' . e((string) $invoice['invoice_number']) : 'New invoice' ?></h1>
  <div class="admin-header-actions">
    <?php if ($invoice): ?>
      <a href="/admin/invoice-view?id=<?= (int) $invoice['id'] ?>" class="admin-btn admin-btn-secondary" target="_blank">Preview</a>
    <?php endif; ?>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← Invoices</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="admin-alert admin-alert-error">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($services === []): ?>
  <div class="admin-alert admin-alert-info">
    No catalog services yet. You can still add custom lines, or
    <a href="/admin/invoice-service-edit">create a service</a>.
  </div>
<?php endif; ?>

<form method="post" action="/admin/invoice-save" id="invoice-form" class="invoice-edit-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <?php if ($invoice): ?>
    <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
  <?php endif; ?>

  <div class="invoice-composer">
    <div class="invoice-composer-main">
    <section class="invoice-composer-panel">
      <div class="invoice-composer-grid">
        <div class="invoice-client-picker" id="invoice-client-picker">
          <input type="hidden" name="client_id" id="client-id" value="<?= e($clientId) ?>">
          <label for="client-search">Client</label>
          <div class="invoice-client-search-wrap">
            <input type="search" id="client-search" placeholder="Search name, company, or email…"
              autocomplete="off" aria-autocomplete="list" aria-controls="client-results" aria-expanded="false">
            <div id="client-results" class="invoice-client-results" role="listbox" hidden></div>
          </div>
          <div id="client-selected" class="invoice-client-selected" <?= $selectedClient ? '' : 'hidden' ?>>
            <div class="invoice-client-selected-main">
              <strong id="client-selected-name"><?= e((string) ($selectedClient['name'] ?? '')) ?></strong>
              <span class="admin-field-hint" id="client-selected-meta">
                <?php
                  if ($selectedClient) {
                      $bits = array_filter([
                          (string) ($selectedClient['company'] ?? ''),
                          (string) ($selectedClient['email'] ?? ''),
                      ]);
                      echo e(implode(' · ', $bits));
                  }
                ?>
              </span>
            </div>
            <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="client-clear">Clear</button>
          </div>
        </div>

        <div class="invoice-meta-grid">
          <div class="admin-field">
            <label for="invoice-number">Invoice #</label>
            <input type="text" id="invoice-number" name="invoice_number" required value="<?= e($invoiceNumber) ?>">
          </div>
          <div class="admin-field">
            <label for="invoice-status">Status</label>
            <select id="invoice-status" name="status">
              <?php foreach (invoice_status_options() as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e(invoice_status_label($opt)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field">
            <label for="invoice-date">Date</label>
            <input type="date" id="invoice-date" name="invoice_date" required value="<?= e($invoiceDate) ?>">
          </div>
          <div class="admin-field">
            <label for="due-date">Due</label>
            <input type="date" id="due-date" name="due_date" required value="<?= e($dueDate) ?>">
          </div>
        </div>
      </div>

      <details class="invoice-billto-details" open>
        <summary>Bill to address</summary>
        <div class="invoice-billto-grid">
          <div class="admin-field">
            <label for="bill-company">Company</label>
            <input type="text" id="bill-company" name="bill_to_company" value="<?= e($billToCompany) ?>">
          </div>
          <div class="admin-field">
            <label for="bill-name">Contact name</label>
            <input type="text" id="bill-name" name="bill_to_name" value="<?= e($billToName) ?>">
          </div>
          <div class="admin-field invoice-billto-street">
            <label for="bill-street">Street</label>
            <input type="text" id="bill-street" name="bill_to_street" value="<?= e($billToStreet) ?>">
          </div>
          <div class="admin-field">
            <label for="bill-city">City</label>
            <input type="text" id="bill-city" name="bill_to_city" value="<?= e($billToCity) ?>">
          </div>
          <div class="admin-field">
            <label for="bill-province">Province</label>
            <input type="text" id="bill-province" name="bill_to_province" value="<?= e($billToProvince) ?>">
          </div>
          <div class="admin-field">
            <label for="bill-postal">Postal</label>
            <input type="text" id="bill-postal" name="bill_to_postal" value="<?= e($billToPostal) ?>">
          </div>
          <div class="admin-field">
            <label for="bill-country">Country</label>
            <input type="text" id="bill-country" name="bill_to_country" value="<?= e($billToCountry) ?>">
          </div>
        </div>
      </details>
    </section>

    <section class="invoice-composer-panel">
      <div class="invoice-lines-head">
        <h2>Line items</h2>
        <a href="/admin/invoice-services" class="admin-btn admin-btn-secondary admin-btn-sm">Manage services</a>
      </div>

      <div class="invoice-add-bar">
        <select id="service-add-select" aria-label="Add a service">
          <option value="">Add a service…</option>
          <?php foreach ($servicesList as $s): ?>
            <option value="<?= (int) $s['id'] ?>">
              <?= e($s['name']) ?> — <?= e(invoice_format_money($s['unit_price'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="admin-btn admin-btn-primary" id="service-add-btn">Add</button>
        <button type="button" class="admin-btn admin-btn-secondary" id="custom-add-btn">Custom line</button>
      </div>

      <div class="invoice-lines-table-wrap">
        <table class="invoice-lines-table" aria-label="Invoice line items">
          <thead>
            <tr>
              <th class="col-service">Service</th>
              <th class="col-price">Price</th>
              <th class="col-qty">Qty</th>
              <th class="col-amount">Amount</th>
              <th class="col-actions"></th>
            </tr>
          </thead>
          <tbody id="invoice-lines-body">
            <?php foreach ($lineItems as $item): ?>
              <tr class="invoice-line-row" data-line-row>
                <td class="col-service">
                  <input type="hidden" name="line_service_id[]" value="<?= e((string) ($item['service_id'] ?? '')) ?>">
                  <input type="text" name="line_name[]" value="<?= e((string) $item['name']) ?>" class="invoice-line-name" required placeholder="Service name">
                  <textarea name="line_description[]" rows="2" class="invoice-line-desc" placeholder="Description (optional)"><?= e((string) ($item['description'] ?? '')) ?></textarea>
                </td>
                <td class="col-price">
                  <input type="number" name="line_price[]" min="0" step="0.01" value="<?= e((string) $item['unit_price']) ?>" class="invoice-calc-input invoice-line-price">
                </td>
                <td class="col-qty">
                  <input type="number" name="line_qty[]" min="0.01" step="0.01" value="<?= e((string) $item['quantity']) ?>" class="invoice-calc-input invoice-line-qty">
                </td>
                <td class="col-amount">
                  <span class="invoice-line-amount">$0.00</span>
                </td>
                <td class="col-actions">
                  <button type="button" class="invoice-line-remove" aria-label="Remove line">&times;</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="invoice-lines-empty admin-field-hint" id="invoice-lines-empty" <?= $lineItems ? 'hidden' : '' ?>>
          No line items yet. Choose a service from the dropdown or add a custom line.
        </p>
      </div>

      <div class="admin-field invoice-notes-field">
        <label for="invoice-notes">Notes / Terms</label>
        <textarea id="invoice-notes" name="notes" rows="3"><?= e($notes) ?></textarea>
      </div>
    </section>
    </div>

    <aside class="invoice-composer-side">
      <div class="invoice-summary invoice-composer-panel">
        <h2 class="invoice-summary-title">Totals</h2>
        <div class="admin-field">
          <label for="discount-percent">Discount %</label>
          <input type="number" id="discount-percent" name="discount_percent" min="0" max="100" step="0.01" value="<?= e($discountPercent) ?>">
        </div>
        <div class="invoice-totals-preview" id="invoice-totals-preview" aria-live="polite">
          <div class="invoice-totals-row"><span>Subtotal</span><strong id="preview-subtotal">$0.00</strong></div>
          <div class="invoice-totals-row" id="preview-discount-row" hidden><span id="preview-discount-label">Discount</span><strong id="preview-discount">$0.00</strong></div>
          <div class="invoice-totals-row invoice-totals-row--total"><span>Total</span><strong id="preview-total">$0.00</strong></div>
        </div>
        <div class="invoice-summary-actions">
          <button type="submit" class="admin-btn admin-btn-primary"><?= $invoice ? 'Save invoice' : 'Create invoice' ?></button>
          <a href="/admin/invoices" class="admin-btn admin-btn-secondary">Cancel</a>
        </div>
      </div>
    </aside>
  </div>
</form>

<template id="invoice-line-template">
  <tr class="invoice-line-row" data-line-row>
    <td class="col-service">
      <input type="hidden" name="line_service_id[]" value="">
      <input type="text" name="line_name[]" value="" class="invoice-line-name" required placeholder="Service name">
      <textarea name="line_description[]" rows="2" class="invoice-line-desc" placeholder="Description (optional)"></textarea>
    </td>
    <td class="col-price">
      <input type="number" name="line_price[]" min="0" step="0.01" value="0" class="invoice-calc-input invoice-line-price">
    </td>
    <td class="col-qty">
      <input type="number" name="line_qty[]" min="0.01" step="0.01" value="1" class="invoice-calc-input invoice-line-qty">
    </td>
    <td class="col-amount">
      <span class="invoice-line-amount">$0.00</span>
    </td>
    <td class="col-actions">
      <button type="button" class="invoice-line-remove" aria-label="Remove line">&times;</button>
    </td>
  </tr>
</template>

<script>
window.INVOICE_CLIENTS = <?= json_encode($clientsList, JSON_UNESCAPED_UNICODE) ?>;
window.INVOICE_SERVICES = <?= json_encode($servicesList, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/admin/js/invoice-edit.js?v=3" defer></script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
