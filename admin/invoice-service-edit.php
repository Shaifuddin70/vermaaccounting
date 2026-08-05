<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$repo = new InvoiceServiceRepository();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$service = $editId ? $repo->find($editId) : null;

if ($editId && !$service) {
    header('Location: /admin/invoice-services');
    exit;
}

$errors = $_SESSION['invoice_service_errors'] ?? [];
$old = $_SESSION['invoice_service_old'] ?? [];
unset($_SESSION['invoice_service_errors'], $_SESSION['invoice_service_old']);

$name = (string) ($old['name'] ?? $service['name'] ?? '');
$description = (string) ($old['description'] ?? $service['description'] ?? '');
$unitPrice = (string) ($old['unit_price'] ?? ($service ? number_format((float) $service['unit_price'], 2, '.', '') : ''));
$sortOrder = (string) ($old['sort_order'] ?? ($service['sort_order'] ?? $repo->nextSortOrder()));
$isActive = array_key_exists('is_active', $old) ? !empty($old['is_active']) : ($service ? !empty($service['is_active']) : true);

$csrf = Auth::csrfToken();
$pageTitle = $service ? 'Edit service' : 'Add service';
$activeNav = 'invoices';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $service ? 'Edit service' : 'Add service' ?></h1>
  <a href="/admin/invoice-services" class="admin-btn admin-btn-secondary">← All services</a>
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

<div class="admin-card" style="max-width:40rem;">
  <form method="post" action="/admin/invoice-service-save">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <?php if ($service): ?>
      <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
    <?php endif; ?>

    <div class="admin-field">
      <label for="service-name">Service name <span class="required">*</span></label>
      <input type="text" id="service-name" name="name" required value="<?= e($name) ?>"
        placeholder="e.g. Corporate Tax Return">
    </div>

    <div class="admin-field">
      <label for="service-description">Description</label>
      <textarea id="service-description" name="description" rows="4"
        placeholder="Shown on the invoice under the service name"><?= e($description) ?></textarea>
    </div>

    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="service-price">Unit price (CAD) <span class="required">*</span></label>
        <input type="number" id="service-price" name="unit_price" required min="0" step="0.01" value="<?= e($unitPrice) ?>">
      </div>
      <div class="admin-field">
        <label for="service-sort">Sort order</label>
        <input type="number" id="service-sort" name="sort_order" step="1" value="<?= e($sortOrder) ?>">
      </div>
    </div>

    <div class="admin-field">
      <label class="admin-checkbox-label">
        <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
        <span>Active (available when creating invoices)</span>
      </label>
    </div>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><?= $service ? 'Save changes' : 'Add service' ?></button>
      <a href="/admin/invoice-services" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
