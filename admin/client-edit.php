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

$unsubscribed = array_key_exists('email_unsubscribed', $old)
    ? (bool) $old['email_unsubscribed']
    : trim((string) ($editClient['email_unsubscribed_at'] ?? '')) !== '';

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
      <div class="admin-field">
        <label for="ce-address">Address</label>
        <textarea id="ce-address" name="address" rows="2"
          placeholder="Street, City, Province Postal code" autocomplete="street-address"><?= e($value('address')) ?></textarea>
      </div>
    </section>

    <section class="admin-card client-detail-card">
      <h2 class="client-detail-title">Account &amp; filing</h2>
      <div class="admin-field">
        <label for="ce-status">Filing status</label>
        <select id="ce-status" name="status">
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
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
