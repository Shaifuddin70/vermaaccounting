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
// Include inactive services that are already on this invoice
$selectedServiceIds = [];
if ($old !== []) {
    $parsed = invoice_parse_items_from_post($old);
    foreach ($parsed as $item) {
        if (!empty($item['service_id'])) {
            $selectedServiceIds[(int) $item['service_id']] = $item;
        }
    }
} else {
    foreach ($existingItems as $item) {
        if (!empty($item['service_id'])) {
            $selectedServiceIds[(int) $item['service_id']] = $item;
        }
    }
}

$serviceIdsOnForm = array_keys($selectedServiceIds);
foreach ($serviceIdsOnForm as $sid) {
    $found = false;
    foreach ($services as $s) {
        if ((int) $s['id'] === $sid) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $extra = $serviceRepo->find($sid);
        if ($extra) {
            $services[] = $extra;
        }
    }
}

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

$customItems = [];
if ($old !== []) {
    $customNames = is_array($old['custom_name'] ?? null) ? $old['custom_name'] : [];
    foreach ($customNames as $idx => $cname) {
        if (trim((string) $cname) === '') {
            continue;
        }
        $customItems[] = [
            'name' => (string) $cname,
            'description' => (string) (($old['custom_description'][$idx] ?? '')),
            'unit_price' => (string) (($old['custom_price'][$idx] ?? '0')),
            'quantity' => (string) (($old['custom_qty'][$idx] ?? '1')),
        ];
    }
} else {
    foreach ($existingItems as $item) {
        if (!empty($item['service_id'])) {
            continue;
        }
        $customItems[] = [
            'name' => (string) ($item['name'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'unit_price' => number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
            'quantity' => rtrim(rtrim(number_format((float) ($item['quantity'] ?? 1), 2, '.', ''), '0'), '.') ?: '1',
        ];
    }
}

$clientsJson = [];
foreach ($clients as $c) {
    $clientsJson[(string) $c['id']] = [
        'name' => $c['name'],
        'company' => $c['company'],
        'email' => $c['email'],
    ];
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
      <a href="/admin/invoice-view?id=<?= (int) $invoice['id'] ?>" class="admin-btn admin-btn-secondary" target="_blank">Preview / print</a>
    <?php endif; ?>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← All invoices</a>
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
    No active services found. <a href="/admin/invoice-service-edit">Add a service</a> first, or use a custom line item below.
  </div>
<?php endif; ?>

<form method="post" action="/admin/invoice-save" id="invoice-form" class="invoice-edit-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <?php if ($invoice): ?>
    <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
  <?php endif; ?>

  <div class="admin-grid-2">
    <div class="admin-card">
      <h2 class="admin-card-title">Invoice details</h2>

      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="invoice-number">Invoice number <span class="required">*</span></label>
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
      </div>

      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="invoice-date">Invoice date <span class="required">*</span></label>
          <input type="date" id="invoice-date" name="invoice_date" required value="<?= e($invoiceDate) ?>">
        </div>
        <div class="admin-field">
          <label for="due-date">Payment due <span class="required">*</span></label>
          <input type="date" id="due-date" name="due_date" required value="<?= e($dueDate) ?>">
        </div>
      </div>

      <div class="admin-field">
        <label for="client-id">Client (optional)</label>
        <select id="client-id" name="client_id">
          <option value="">— Manual bill-to —</option>
          <?php foreach ($clients as $c): ?>
            <?php
              $label = $c['name'];
              if ($c['company'] !== '') {
                  $label .= ' (' . $c['company'] . ')';
              }
            ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $clientId ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="admin-field-hint">Selecting a client prefills the bill-to name and company. Address is always entered below.</small>
      </div>

      <h2 class="admin-card-title" style="margin-top:1.25rem;">Bill to</h2>
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="bill-company">Company</label>
          <input type="text" id="bill-company" name="bill_to_company" value="<?= e($billToCompany) ?>">
        </div>
        <div class="admin-field">
          <label for="bill-name">Contact name</label>
          <input type="text" id="bill-name" name="bill_to_name" value="<?= e($billToName) ?>">
        </div>
      </div>
      <div class="admin-field">
        <label for="bill-street">Street</label>
        <input type="text" id="bill-street" name="bill_to_street" value="<?= e($billToStreet) ?>">
      </div>
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="bill-city">City</label>
          <input type="text" id="bill-city" name="bill_to_city" value="<?= e($billToCity) ?>">
        </div>
        <div class="admin-field">
          <label for="bill-province">Province</label>
          <input type="text" id="bill-province" name="bill_to_province" value="<?= e($billToProvince) ?>">
        </div>
      </div>
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="bill-postal">Postal code</label>
          <input type="text" id="bill-postal" name="bill_to_postal" value="<?= e($billToPostal) ?>">
        </div>
        <div class="admin-field">
          <label for="bill-country">Country</label>
          <input type="text" id="bill-country" name="bill_to_country" value="<?= e($billToCountry) ?>">
        </div>
      </div>
    </div>

    <div class="admin-card">
      <h2 class="admin-card-title">Totals &amp; notes</h2>
      <div class="admin-field">
        <label for="discount-percent">Discount (%)</label>
        <input type="number" id="discount-percent" name="discount_percent" min="0" max="100" step="0.01" value="<?= e($discountPercent) ?>">
      </div>
      <div class="invoice-totals-preview" id="invoice-totals-preview" aria-live="polite">
        <div class="invoice-totals-row"><span>Subtotal</span><strong id="preview-subtotal">$0.00</strong></div>
        <div class="invoice-totals-row" id="preview-discount-row" hidden><span id="preview-discount-label">Discount</span><strong id="preview-discount">$0.00</strong></div>
        <div class="invoice-totals-row invoice-totals-row--total"><span>Total</span><strong id="preview-total">$0.00</strong></div>
      </div>
      <div class="admin-field" style="margin-top:1rem;">
        <label for="invoice-notes">Notes / Terms</label>
        <textarea id="invoice-notes" name="notes" rows="5"><?= e($notes) ?></textarea>
      </div>
    </div>
  </div>

  <div class="admin-card" style="margin-top:1.25rem;">
    <div class="invoice-section-head">
      <h2 class="admin-card-title" style="margin:0;">Select services</h2>
      <a href="/admin/invoice-service-edit" class="admin-btn admin-btn-secondary admin-btn-sm">+ Manage services</a>
    </div>
    <?php if ($services === []): ?>
      <p class="admin-field-hint">No catalog services yet. Add custom line items below.</p>
    <?php else: ?>
      <div class="invoice-service-picker">
        <?php foreach ($services as $service):
          $sid = (int) $service['id'];
          $selected = isset($selectedServiceIds[$sid]);
          $sel = $selectedServiceIds[$sid] ?? null;
          $priceVal = $sel
            ? number_format((float) ($sel['unit_price'] ?? $service['unit_price']), 2, '.', '')
            : number_format((float) $service['unit_price'], 2, '.', '');
          $qtyVal = $sel
            ? (rtrim(rtrim(number_format((float) ($sel['quantity'] ?? 1), 2, '.', ''), '0'), '.') ?: '1')
            : '1';
          $nameVal = $sel ? (string) ($sel['name'] ?? $service['name']) : (string) $service['name'];
          $descVal = $sel ? (string) ($sel['description'] ?? $service['description'] ?? '') : (string) ($service['description'] ?? '');
        ?>
          <div class="invoice-service-row<?= $selected ? ' is-selected' : '' ?>" data-service-row>
            <label class="invoice-service-check admin-checkbox-label">
              <input type="checkbox" name="service_ids[]" value="<?= $sid ?>" class="invoice-service-toggle"
                <?= $selected ? 'checked' : '' ?>>
              <span class="invoice-service-check-label">
                <strong><?= e((string) $service['name']) ?></strong>
                <span class="admin-field-hint"><?= e(invoice_format_money($service['unit_price'] ?? 0)) ?> catalog price</span>
              </span>
            </label>
            <div class="invoice-service-fields" <?= $selected ? '' : 'hidden' ?>>
              <input type="hidden" name="service_name[<?= $sid ?>]" value="<?= e($nameVal) ?>">
              <div class="admin-field">
                <label>Description on invoice</label>
                <textarea name="service_description[<?= $sid ?>]" rows="2" <?= $selected ? '' : 'disabled' ?>><?= e($descVal) ?></textarea>
              </div>
              <div class="admin-fields-2col">
                <div class="admin-field">
                  <label>Price</label>
                  <input type="number" name="service_price[<?= $sid ?>]" min="0" step="0.01" value="<?= e($priceVal) ?>"
                    class="invoice-calc-input" <?= $selected ? '' : 'disabled' ?>>
                </div>
                <div class="admin-field">
                  <label>Qty</label>
                  <input type="number" name="service_qty[<?= $sid ?>]" min="0.01" step="0.01" value="<?= e($qtyVal) ?>"
                    class="invoice-calc-input" <?= $selected ? '' : 'disabled' ?>>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="admin-card" style="margin-top:1.25rem;">
    <div class="invoice-section-head">
      <h2 class="admin-card-title" style="margin:0;">Custom line items</h2>
      <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="add-custom-item">+ Add line</button>
    </div>
    <div id="custom-items" class="invoice-custom-items">
      <?php foreach ($customItems as $ci): ?>
        <div class="invoice-custom-row" data-custom-row>
          <div class="admin-fields-2col">
            <div class="admin-field">
              <label>Name</label>
              <input type="text" name="custom_name[]" value="<?= e($ci['name']) ?>" class="invoice-calc-input">
            </div>
            <div class="admin-field invoice-custom-actions">
              <label>Price</label>
              <div class="invoice-custom-price-row">
                <input type="number" name="custom_price[]" min="0" step="0.01" value="<?= e($ci['unit_price']) ?>" class="invoice-calc-input">
                <input type="number" name="custom_qty[]" min="0.01" step="0.01" value="<?= e($ci['quantity']) ?>" class="invoice-calc-input" title="Quantity" aria-label="Quantity">
                <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm remove-custom-item" aria-label="Remove">Remove</button>
              </div>
            </div>
          </div>
          <div class="admin-field">
            <label>Description</label>
            <textarea name="custom_description[]" rows="2"><?= e($ci['description']) ?></textarea>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-form-actions" style="margin-top:1.25rem;">
    <button type="submit" class="admin-btn admin-btn-primary"><?= $invoice ? 'Save invoice' : 'Create invoice' ?></button>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">Cancel</a>
  </div>
</form>

<template id="custom-item-template">
  <div class="invoice-custom-row" data-custom-row>
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label>Name</label>
        <input type="text" name="custom_name[]" value="" class="invoice-calc-input">
      </div>
      <div class="admin-field invoice-custom-actions">
        <label>Price</label>
        <div class="invoice-custom-price-row">
          <input type="number" name="custom_price[]" min="0" step="0.01" value="0" class="invoice-calc-input">
          <input type="number" name="custom_qty[]" min="0.01" step="0.01" value="1" class="invoice-calc-input" title="Quantity" aria-label="Quantity">
          <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm remove-custom-item" aria-label="Remove">Remove</button>
        </div>
      </div>
    </div>
    <div class="admin-field">
      <label>Description</label>
      <textarea name="custom_description[]" rows="2"></textarea>
    </div>
  </div>
</template>

<script>
window.INVOICE_CLIENTS = <?= json_encode($clientsJson, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/admin/js/invoice-edit.js?v=1" defer></script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
