<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$clientRepo = new ClientRepository();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editClient = $editId ? $clientRepo->find($editId) : null;
$isNew = ($editClient === null);
Auth::requireCapability($isNew ? 'clients.create' : 'clients.edit');

if ($editId && !$editClient) {
    header('Location: /admin/clients');
    exit;
}
if ($editClient) {
    assert_client_access($editClient);
}

$errors = $_SESSION['client_edit_errors'] ?? [];
$old = $_SESSION['client_edit_old'] ?? [];
unset($_SESSION['client_edit_errors'], $_SESSION['client_edit_old']);

$meta = client_notes_meta($editClient['notes'] ?? '');
$value = static function (string $key) use ($old, $editClient, $meta): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if (array_key_exists($key, $meta)) {
        return (string) $meta[$key];
    }
    return (string) ($editClient[$key] ?? '');
};

$savedAddress = client_address_from_row($editClient);
if ($isNew && $savedAddress['country'] === '') {
    $savedAddress['country'] = 'Canada';
}
$addressValue = static function (string $part) use ($old, $savedAddress): string {
    $key = 'address_' . $part;
    return array_key_exists($key, $old) ? (string) $old[$key] : $savedAddress[$part];
};
$provinceOptions = array_values(client_provinces());
sort($provinceOptions);
$currentProvince = $addressValue('province');
if ($currentProvince !== '' && !in_array($currentProvince, $provinceOptions, true)) {
    $provinceOptions[] = $currentProvince;
}

$unsubscribed = array_key_exists('email_unsubscribed', $old)
    ? (bool) $old['email_unsubscribed']
    : trim((string) ($editClient['email_unsubscribed_at'] ?? '')) !== '';

$isActive = array_key_exists('is_active', $old)
    ? (bool) $old['is_active']
    : ($isNew || (int) ($editClient['is_active'] ?? 1) === 1);

$lastActivity = $value('last_activity');
$lastActivityIsDate = $lastActivity === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastActivity) === 1;
$statuses = client_known_statuses();
$currentStatus = $value('status');
if ($currentStatus !== '' && !in_array($currentStatus, $statuses, true)) {
    $statuses[] = $currentStatus;
}

$displayName = $isNew ? 'New client' : (string) $editClient['name'];
$cancelUrl = $isNew ? '/admin/clients' : '/admin/client?id=' . (int) $editId;

$csrf = Auth::csrfToken();
$pageTitle = $isNew ? 'Add client' : 'Edit client';
$activeNav = 'clients';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $isNew ? 'Add client' : 'Edit client' ?></h1>
  <div class="admin-header-actions">
    <?php if (!$isNew): ?>
      <a href="/admin/client?id=<?= (int) $editId ?>" class="admin-btn admin-btn-secondary">View profile</a>
    <?php endif; ?>
    <a href="/admin/clients" class="admin-btn admin-btn-secondary">← All clients</a>
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

<form method="post" action="/admin/client-save" class="client-edit-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <?php if (!$isNew): ?>
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
  <?php endif; ?>

  <section class="admin-card client-hero client-edit-hero">
    <div class="client-hero-main">
      <span class="client-avatar client-avatar--lg" id="ce-avatar" aria-hidden="true"><?= e($isNew ? '+' : client_initials($displayName)) ?></span>
      <div class="client-hero-text">
        <h2 class="client-hero-name" id="ce-display-name"><?= e($displayName) ?></h2>
        <p class="client-hero-facts">
          <?php if ($isNew): ?>
            Fill in what you know — only the name is required.
          <?php else: ?>
            Client ID <?= (int) $editId ?> · added <?= e(substr((string) ($editClient['created_at'] ?? ''), 0, 10)) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </section>

  <div class="client-detail-grid">
    <section class="admin-card client-detail-card">
      <h2 class="client-detail-title">Personal details</h2>
      <div class="admin-field">
        <label for="ce-name">Full name <span class="required">*</span></label>
        <input type="text" id="ce-name" name="name" required
          value="<?= e($value('name')) ?>" placeholder="e.g. Jane Smith" autocomplete="name">
      </div>
      <div class="user-edit-row">
        <div class="admin-field">
          <label for="ce-dob">Date of birth</label>
          <input type="date" id="ce-dob" name="date_of_birth" value="<?= e($value('date_of_birth')) ?>">
        </div>
        <div class="admin-field">
          <label for="ce-sin">SIN</label>
          <input type="text" id="ce-sin" name="sin" value="<?= e($value('sin')) ?>"
            placeholder="###-###-###" autocomplete="off" inputmode="numeric">
        </div>
      </div>
      <div class="admin-field">
        <label for="ce-company">Company</label>
        <input type="text" id="ce-company" name="company" value="<?= e($value('company')) ?>"
          placeholder="Optional" autocomplete="organization">
      </div>
      <small class="admin-field-hint">SIN must be unique across clients.</small>
    </section>

    <section class="admin-card client-detail-card">
      <h2 class="client-detail-title">Contact</h2>
      <div class="admin-field">
        <label for="ce-email">Email</label>
        <input type="email" id="ce-email" name="email" value="<?= e($value('email')) ?>"
          placeholder="name@example.com" autocomplete="email">
      </div>
      <div class="user-edit-row">
        <div class="admin-field">
          <label for="ce-phone">Phone</label>
          <input type="tel" id="ce-phone" name="phone" value="<?= e($value('phone')) ?>"
            placeholder="(613) 555-0100" autocomplete="tel">
        </div>
        <div class="admin-field">
          <label for="ce-other-phone">Other phone</label>
          <input type="tel" id="ce-other-phone" name="other_phone" value="<?= e($value('other_phone')) ?>"
            placeholder="Optional">
        </div>
      </div>
    </section>

    <section class="admin-card client-detail-card">
      <h2 class="client-detail-title">Address</h2>
      <div class="admin-field">
        <label for="ce-street">Street address</label>
        <input type="text" id="ce-street" name="address_street" value="<?= e($addressValue('street')) ?>"
          placeholder="e.g. 12-264 Washington Street" autocomplete="street-address">
      </div>
      <div class="user-edit-row">
        <div class="admin-field">
          <label for="ce-city">City</label>
          <input type="text" id="ce-city" name="address_city" value="<?= e($addressValue('city')) ?>"
            placeholder="e.g. Ottawa" autocomplete="address-level2">
        </div>
        <div class="admin-field">
          <label for="ce-province">Province / territory</label>
          <select id="ce-province" name="address_province" autocomplete="address-level1">
            <option value="">— Select —</option>
            <?php foreach ($provinceOptions as $provinceName): ?>
              <option value="<?= e($provinceName) ?>" <?= $provinceName === $addressValue('province') ? 'selected' : '' ?>><?= e($provinceName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="user-edit-row">
        <div class="admin-field">
          <label for="ce-postal">Postal code</label>
          <input type="text" id="ce-postal" name="address_postal" value="<?= e($addressValue('postal')) ?>"
            placeholder="A1A 1A1" autocomplete="postal-code" maxlength="16">
        </div>
        <div class="admin-field">
          <label for="ce-country">Country</label>
          <input type="text" id="ce-country" name="address_country" value="<?= e($addressValue('country')) ?>"
            placeholder="Canada" autocomplete="country-name">
        </div>
      </div>
    </section>

    <section class="admin-card client-detail-card">
      <h2 class="client-detail-title">Account &amp; filing</h2>
      <div class="admin-field">
        <span class="client-field-label" id="ce-active-label">Client status</span>
        <div class="client-active-toggle" role="radiogroup" aria-labelledby="ce-active-label">
          <label>
            <input type="radio" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
            <span><i class="client-active-dot client-active-dot--on" aria-hidden="true"></i>Active</span>
          </label>
          <label>
            <input type="radio" name="is_active" value="0" <?= $isActive ? '' : 'checked' ?>>
            <span><i class="client-active-dot" aria-hidden="true"></i>Inactive</span>
          </label>
        </div>
      </div>
      <div class="admin-field">
        <label for="ce-status">Filing status</label>
        <select id="ce-status" name="status" data-inactive-statuses="<?= e(json_encode(array_values(array_filter(
            $statuses,
            'client_status_implies_inactive'
        )))) ?>">
          <option value="">— None —</option>
          <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $status === $currentStatus ? 'selected' : '' ?>><?= e($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-field">
        <label for="ce-last-activity">Last activity</label>
        <input type="<?= $lastActivityIsDate ? 'date' : 'text' ?>" id="ce-last-activity" name="last_activity"
          value="<?= e($lastActivity) ?>">
      </div>
      <div class="admin-field">
        <label class="admin-checkbox-label">
          <input type="checkbox" name="email_unsubscribed" value="1" <?= $unsubscribed ? 'checked' : '' ?>>
          <span>Unsubscribed from marketing emails</span>
        </label>
        <small class="admin-field-hint">Unsubscribed clients are excluded from campaigns and birthday emails.</small>
      </div>
    </section>
  </div>

  <section class="admin-card client-detail-card client-notes-card">
    <h2 class="client-detail-title">Notes</h2>
    <div class="admin-field" style="margin-bottom:0;">
      <label for="ce-notes" class="visually-hidden">Notes</label>
      <textarea id="ce-notes" name="notes" rows="4" placeholder="Internal notes about this client…"><?= e($value('notes')) ?></textarea>
    </div>
  </section>

  <div class="client-edit-actions">
    <a href="<?= e($cancelUrl) ?>" class="admin-btn admin-btn-secondary">Cancel</a>
    <button type="submit" class="admin-btn"><?= $isNew ? 'Create client' : 'Save changes' ?></button>
  </div>
</form>

<script>
(function () {
  var input = document.getElementById('ce-name');
  var title = document.getElementById('ce-display-name');
  var avatar = document.getElementById('ce-avatar');
  if (!input || !title || !avatar) return;
  var fallback = <?= json_encode($isNew ? 'New client' : $displayName) ?>;
  input.addEventListener('input', function () {
    var name = input.value.trim();
    title.textContent = name || fallback;
    var parts = name.split(/\s+/).filter(Boolean);
    avatar.textContent = parts.length
      ? (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase()
      : <?= json_encode($isNew ? '+' : client_initials($displayName)) ?>;
  });
})();

(function () {
  var select = document.getElementById('ce-status');
  var inactive = document.querySelector('input[name="is_active"][value="0"]');
  if (!select || !inactive) return;
  var statuses = JSON.parse(select.getAttribute('data-inactive-statuses') || '[]');
  select.addEventListener('change', function () {
    if (statuses.indexOf(select.value) !== -1) inactive.checked = true;
  });
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
