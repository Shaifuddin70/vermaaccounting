<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$userRepo = new UserRepository();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editUser = $editId ? $userRepo->find($editId) : null;
$isNew = ($editUser === null);

if ($editId && !$editUser) {
    header('Location: /admin/users.php');
    exit;
}

$errors = $_SESSION['user_edit_errors'] ?? [];
$old    = $_SESSION['user_edit_old'] ?? [];
unset($_SESSION['user_edit_errors'], $_SESSION['user_edit_old']);

$csrf = Auth::csrfToken();
$pageTitle = $isNew ? 'Add team member' : 'Edit: ' . ($editUser['name'] ?? '');
$activeNav = 'users';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $isNew ? 'Add team member' : 'Edit team member' ?></h1>
  <a href="/admin/users.php" class="admin-btn admin-btn-secondary">← Back to team</a>
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

<div class="admin-card" style="max-width:560px;">
  <form method="post" action="/admin/user-save.php">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <?php if (!$isNew): ?>
      <input type="hidden" name="id" value="<?= $editId ?>">
    <?php endif; ?>

    <div class="admin-field">
      <label for="ue-name">Full name <span class="required">*</span></label>
      <input type="text" id="ue-name" name="name" required
        value="<?= e($old['name'] ?? $editUser['name'] ?? '') ?>"
        placeholder="e.g. John Smith">
    </div>

    <div class="admin-field">
      <label for="ue-email">Email address <span class="required">*</span></label>
      <input type="email" id="ue-email" name="email" required
        value="<?= e($old['email'] ?? $editUser['email'] ?? '') ?>"
        placeholder="john@example.com">
      <small class="admin-field-hint">Used to log in.</small>
    </div>

    <div class="admin-field">
      <label for="ue-password">
        Password <?php if (!$isNew): ?><span style="font-weight:400;color:#64748b;">(leave blank to keep current)</span><?php else: ?><span class="required">*</span><?php endif; ?>
      </label>
      <input type="password" id="ue-password" name="password"
        <?= $isNew ? 'required' : '' ?> autocomplete="new-password"
        placeholder="<?= $isNew ? 'Set a password' : 'Leave blank to keep unchanged' ?>">
      <small class="admin-field-hint">Minimum 8 characters.</small>
    </div>

    <div class="user-edit-row">
      <div class="admin-field">
        <label for="ue-role">Role <span class="required">*</span></label>
        <select id="ue-role" name="role" required>
          <option value="reviewer" <?= ($old['role'] ?? $editUser['role'] ?? 'reviewer') === 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
          <option value="admin"    <?= ($old['role'] ?? $editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
        <small class="admin-field-hint">Reviewers can view and complete submissions. Admins have full access.</small>
      </div>

      <div class="admin-field">
        <label for="ue-status">Status</label>
        <select id="ue-status" name="status">
          <option value="active"   <?= ($old['status'] ?? $editUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($old['status'] ?? $editUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>

    <div style="display:flex;gap:0.5rem;margin-top:1.5rem;">
      <button type="submit" class="admin-btn"><?= $isNew ? 'Create member' : 'Save changes' ?></button>
      <a href="/admin/users.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
