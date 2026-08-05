<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$company = invoice_company_settings();
$errors = $_SESSION['invoice_settings_errors'] ?? [];
$old = $_SESSION['invoice_settings_old'] ?? [];
unset($_SESSION['invoice_settings_errors'], $_SESSION['invoice_settings_old']);

if ($old !== []) {
    foreach ($company as $key => $default) {
        if (array_key_exists($key, $old)) {
            $company[$key] = (string) $old[$key];
        }
    }
}

$csrf = Auth::csrfToken();
$pageTitle = 'Invoice company details';
$activeNav = 'invoices';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Invoice company details</h1>
  <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← Invoices</a>
</div>

<p class="admin-field-hint" style="margin-top:0;">These details appear in the header of every generated invoice.</p>

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
  <form method="post" action="/admin/invoice-settings-save">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="admin-field">
      <label for="co-name">Company name <span class="required">*</span></label>
      <input type="text" id="co-name" name="name" required value="<?= e($company['name']) ?>">
    </div>
    <div class="admin-field">
      <label for="co-street">Street / suite</label>
      <input type="text" id="co-street" name="street" value="<?= e($company['street']) ?>">
    </div>
    <div class="admin-field">
      <label for="co-city">City, province &amp; postal</label>
      <input type="text" id="co-city" name="city_line" value="<?= e($company['city_line']) ?>"
        placeholder="e.g. Orleans, Ontario K1W0N3">
    </div>
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="co-country">Country</label>
        <input type="text" id="co-country" name="country" value="<?= e($company['country']) ?>">
      </div>
      <div class="admin-field">
        <label for="co-phone">Phone</label>
        <input type="text" id="co-phone" name="phone" value="<?= e($company['phone']) ?>">
      </div>
    </div>
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="co-website">Website</label>
        <input type="text" id="co-website" name="website" value="<?= e($company['website']) ?>">
      </div>
      <div class="admin-field">
        <label for="co-email">Payment email</label>
        <input type="email" id="co-email" name="payment_email" value="<?= e($company['payment_email']) ?>">
        <small class="admin-field-hint">Used in default invoice notes.</small>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary">Save details</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
