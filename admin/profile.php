<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$profile = profile_for_current_user();
$errors = $_SESSION['profile_errors'] ?? [];
$old = $_SESSION['profile_old'] ?? [];
unset($_SESSION['profile_errors'], $_SESSION['profile_old']);

$saved = isset($_GET['saved']);
$name = (string) ($old['name'] ?? $profile['name'] ?? '');
$email = (string) ($old['email'] ?? $profile['email'] ?? '');
$avatarUrl = user_avatar_url([
    'avatar_path' => $profile['avatar_path'] ?? '',
]);
$initials = user_initials($name);
$isConfigAdmin = !empty($profile['is_config_admin']);
$role = (string) ($profile['role'] ?? '');
$csrf = Auth::csrfToken();
$pageTitle = 'My profile';
$activeNav = 'profile';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>My profile</h1>
</div>

<?php if ($saved): ?>
  <div class="admin-alert admin-alert-success">Profile updated.</div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="admin-alert admin-alert-error">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admin-card profile-page-card">
    <form method="post" action="/admin/profile-save" enctype="multipart/form-data" class="profile-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <div class="profile-avatar-section">
        <div class="profile-avatar-preview" id="profile-avatar-preview">
          <?php if ($avatarUrl): ?>
            <img src="<?= e($avatarUrl) ?>" alt="" class="profile-avatar-image" id="profile-avatar-image">
          <?php else: ?>
            <span class="profile-avatar-initials" id="profile-avatar-initials"><?= e($initials) ?></span>
          <?php endif; ?>
        </div>
        <div class="profile-avatar-actions">
          <div class="admin-field">
            <label for="profile-avatar">Profile image</label>
            <input type="file" id="profile-avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="admin-field-hint">JPEG, PNG, GIF, or WebP. Max 2 MB.</small>
          </div>
          <?php if (($profile['avatar_path'] ?? '') !== ''): ?>
            <label class="admin-checkbox-label">
              <input type="checkbox" name="remove_avatar" value="1">
              <span>Remove current image</span>
            </label>
          <?php endif; ?>
        </div>
      </div>

      <div class="admin-field">
        <label for="profile-name">Full name <span class="required">*</span></label>
        <input type="text" id="profile-name" name="name" required value="<?= e($name) ?>">
      </div>

      <div class="admin-field">
        <label for="profile-email">Email address<?= $isConfigAdmin ? '' : ' <span class="required">*</span>' ?></label>
        <input type="email" id="profile-email" name="email" <?= $isConfigAdmin ? '' : 'required' ?>
          value="<?= e($email) ?>" placeholder="<?= $isConfigAdmin ? 'Optional contact email' : 'you@example.com' ?>">
        <?php if ($isConfigAdmin): ?>
          <small class="admin-field-hint">Optional contact email for test sends and display.</small>
        <?php else: ?>
          <small class="admin-field-hint">Used to log in and receive notifications.</small>
        <?php endif; ?>
      </div>

      <?php if (!$isConfigAdmin): ?>
        <div class="admin-field">
          <label>Role</label>
          <input type="text" value="<?= e(ucfirst($role)) ?>" disabled>
        </div>
      <?php endif; ?>

      <?php if ($role === 'partner' && !empty($profile['reference_code'])): ?>
        <div class="admin-field">
          <label>Partner reference code</label>
          <input type="text" value="<?= e((string) $profile['reference_code']) ?>" disabled>
          <small class="admin-field-hint">Ask an admin to change your reference code in Team settings.</small>
        </div>
      <?php endif; ?>

      <?php if (!$isConfigAdmin): ?>
        <div class="admin-field">
          <label for="profile-password">New password</label>
          <input type="password" id="profile-password" name="password" autocomplete="new-password"
            placeholder="Leave blank to keep current password">
          <small class="admin-field-hint">Minimum 8 characters.</small>
        </div>
      <?php else: ?>
        <div class="admin-field">
          <label for="profile-username">Login username</label>
          <input type="text" id="profile-username" value="<?= e((string) ($profile['username'] ?? 'admin')) ?>" disabled>
          <small class="admin-field-hint">Used to sign in. Cannot be changed here.</small>
        </div>
        <div class="admin-field">
          <label>Role</label>
          <input type="text" value="<?= e(ucfirst($role ?: 'admin')) ?>" disabled>
        </div>
        <div class="admin-alert admin-alert-info profile-config-admin-note">
          Your login password is managed in the server config file. Contact your site administrator to change it.
        </div>
      <?php endif; ?>

      <button type="submit" class="admin-btn admin-btn-primary">Save profile</button>
    </form>
  </div>
</div>

<script>
(function () {
  const input = document.getElementById('profile-avatar');
  const preview = document.getElementById('profile-avatar-preview');
  const nameInput = document.getElementById('profile-name');
  if (!input || !preview) return;

  input.addEventListener('change', function () {
    const file = input.files && input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (event) {
      preview.innerHTML = '<img src="' + event.target.result + '" alt="" class="profile-avatar-image">';
    };
    reader.readAsDataURL(file);
  });

  if (nameInput) {
    nameInput.addEventListener('input', function () {
      const initials = document.getElementById('profile-avatar-initials');
      if (!initials) return;
      const parts = nameInput.value.trim().split(/\s+/).filter(Boolean);
      let text = 'VA';
      if (parts.length >= 2) {
        text = (parts[0][0] + parts[1][0]).toUpperCase();
      } else if (parts.length === 1 && parts[0]) {
        text = parts[0].slice(0, 2).toUpperCase();
      }
      initials.textContent = text;
    });
  }
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
