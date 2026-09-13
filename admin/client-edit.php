<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('clients.manage');

$clientRepo = new ClientRepository();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editClient = $editId ? $clientRepo->find($editId) : null;
$isNew = ($editClient === null);

if ($editId && !$editClient) {
    header('Location: /admin/clients');
    exit;
}
if ($editClient) {
    assert_client_access($editClient);
}
if ($isNew && partner_user_id() !== null) {
    $_SESSION['flash_error'] = 'Partners can only work with clients linked to their submissions.';
    header('Location: /admin/clients');
    exit;
}

$errors = $_SESSION['client_edit_errors'] ?? [];
$old = $_SESSION['client_edit_old'] ?? [];
unset($_SESSION['client_edit_errors'], $_SESSION['client_edit_old']);

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

<div class="admin-card" style="max-width:640px;">
  <form method="post" action="/admin/client-save">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <?php if (!$isNew): ?>
      <input type="hidden" name="id" value="<?= (int) $editId ?>">
    <?php endif; ?>

    <div class="user-edit-row">
      <div class="admin-field">
        <label for="ce-name">Full name <span class="required">*</span></label>
        <input type="text" id="ce-name" name="name" required
          value="<?= e($old['name'] ?? $editClient['name'] ?? '') ?>"
          placeholder="e.g. Jane Smith" autocomplete="name">
      </div>
      <div class="admin-field">
        <label for="ce-company">Company</label>
        <input type="text" id="ce-company" name="company"
          value="<?= e($old['company'] ?? $editClient['company'] ?? '') ?>"
          placeholder="Optional" autocomplete="organization">
      </div>
    </div>

    <div class="user-edit-row">
      <div class="admin-field">
        <label for="ce-email">Email</label>
        <input type="email" id="ce-email" name="email"
          value="<?= e($old['email'] ?? $editClient['email'] ?? '') ?>"
          placeholder="optional@email.com" autocomplete="email">
      </div>
      <div class="admin-field">
        <label for="ce-phone">Phone</label>
        <input type="tel" id="ce-phone" name="phone"
          value="<?= e($old['phone'] ?? $editClient['phone'] ?? '') ?>"
          placeholder="Optional" autocomplete="tel">
      </div>
    </div>

    <div class="user-edit-row">
      <div class="admin-field">
        <label for="ce-sin">SIN</label>
        <input type="text" id="ce-sin" name="sin"
          value="<?= e($old['sin'] ?? $editClient['sin'] ?? '') ?>"
          placeholder="###-###-###" autocomplete="off">
        <small class="admin-field-hint">Must be unique if provided.</small>
      </div>
      <div class="admin-field">
        <label for="ce-dob">Date of birth</label>
        <input type="date" id="ce-dob" name="date_of_birth"
          value="<?= e($old['date_of_birth'] ?? $editClient['date_of_birth'] ?? '') ?>">
      </div>
    </div>

    <div class="admin-field">
      <label for="ce-notes">Notes</label>
      <textarea id="ce-notes" name="notes" rows="3" placeholder="Internal notes…"><?= e($old['notes'] ?? $editClient['notes'] ?? '') ?></textarea>
    </div>

    <div class="admin-form-actions" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem;">
      <button type="submit" class="admin-btn"><?= $isNew ? 'Create client' : 'Save changes' ?></button>
      <a href="<?= $isNew ? '/admin/clients' : '/admin/client?id=' . (int) $editId ?>" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
