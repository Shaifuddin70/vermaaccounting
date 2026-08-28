<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$userRepo = new UserRepository();
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$userTotal = $userRepo->countUsers();
$pagination = pagination_meta($userTotal, $page, $perPage);
$users = $userRepo->allPaginated($pagination['per_page'], $pagination['offset']);
$counts = $userRepo->counts();
$csrf = Auth::csrfToken();

$paginationPath = '/admin/users';
$paginationQuery = [];
$paginationLabel = 'team members';
$paginationAriaLabel = 'Team list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/users', [], $p, $pagination['per_page']);

$pageTitle = 'Team';
$activeNav = 'users';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Team</h1>
  <div class="admin-header-actions">
    <button type="button" class="admin-btn" id="btn-add-user">+ Add team member</button>
  </div>
</div>

<div class="users-stats" id="users-stats">
  <div class="users-stat">
    <span class="users-stat-value" id="stat-total"><?= $counts['total'] ?></span>
    <span class="users-stat-label">Total</span>
  </div>
  <div class="users-stat">
    <span class="users-stat-value" id="stat-active"><?= $counts['active'] ?></span>
    <span class="users-stat-label">Active</span>
  </div>
  <div class="users-stat">
    <span class="users-stat-value" id="stat-admins"><?= $counts['admins'] ?></span>
    <span class="users-stat-label">Admins</span>
  </div>
  <div class="users-stat">
    <span class="users-stat-value" id="stat-reviewers"><?= $counts['reviewers'] ?></span>
    <span class="users-stat-label">Reviewers</span>
  </div>
  <div class="users-stat">
    <span class="users-stat-value" id="stat-partners"><?= $counts['partners'] ?></span>
    <span class="users-stat-label">Partners</span>
  </div>
</div>

<div class="admin-card">
  <?php if ($userTotal > 0): ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
  <div class="admin-table-scroll">
  <table class="admin-table" id="users-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="users-tbody">
      <?php if (!$users): ?>
        <tr id="empty-row">
          <td colspan="6" class="admin-table-empty-cell">
            <div class="admin-empty-state">
              <span class="admin-empty-state-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
              </span>
              <h2 class="admin-empty-state-title">No team members yet</h2>
              <p class="admin-empty-state-text">Add reviewers, partners, or admins to help process submissions.</p>
              <button type="button" class="admin-btn" id="btn-add-user-empty">Add your first team member</button>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <tr data-user-id="<?= (int) $u['id'] ?>"
              class="<?= $u['status'] === 'inactive' ? 'admin-table-row--muted' : '' ?>">
            <td><strong><?= e($u['name']) ?></strong></td>
            <td><?= e($u['email']) ?></td>
            <td>
              <span class="role-badge role-badge--<?= e($u['role']) ?>">
                <?= e(ucfirst($u['role'])) ?>
              </span>
            </td>
            <td>
              <span class="status-dot status-dot--<?= e($u['status']) ?>"></span>
              <?= e(ucfirst($u['status'])) ?>
            </td>
            <td class="dashboard-date"><?= e(substr($u['created_at'], 0, 10)) ?></td>
            <td class="admin-table-actions">
              <button type="button"
                class="admin-btn admin-btn-sm btn-edit-user"
                data-user-id="<?= (int) $u['id'] ?>"
                data-user-name="<?= e($u['name']) ?>"
                data-user-email="<?= e($u['email']) ?>"
                data-user-role="<?= e($u['role']) ?>"
                data-user-status="<?= e($u['status']) ?>"
                data-user-reference="<?= e($u['reference_code'] ?? '') ?>">
                Edit
              </button>
              <?php if ($u['status'] === 'active'): ?>
                <button type="button"
                  class="admin-btn admin-btn-sm admin-btn-secondary btn-toggle-status"
                  data-id="<?= (int) $u['id'] ?>"
                  data-action="deactivate"
                  data-name="<?= e($u['name']) ?>">
                  Deactivate
                </button>
              <?php else: ?>
                <button type="button"
                  class="admin-btn admin-btn-sm admin-btn-secondary btn-toggle-status"
                  data-id="<?= (int) $u['id'] ?>"
                  data-action="activate"
                  data-name="<?= e($u['name']) ?>">
                  Activate
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if ($userTotal > 0): ?>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<?php if (app_is_local()): ?>
<p class="admin-note">
  The super-admin account configured in <code>config.local.php</code> always has full access and is not listed here.
</p>
<?php endif; ?>

<!-- ======================== USER MODAL ======================== -->
<div id="user-modal" class="umodal-backdrop" aria-modal="true" role="dialog" aria-labelledby="umodal-title" aria-hidden="true">
  <div class="umodal">
    <div class="umodal-header">
      <h2 class="umodal-title" id="umodal-title">Add team member</h2>
      <button type="button" class="umodal-close" id="umodal-close" aria-label="Close">&#x2715;</button>
    </div>

    <div id="umodal-errors" class="admin-alert admin-alert-error" style="display:none;"></div>

    <form id="user-modal-form" method="post" action="/admin/user-save" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" id="umodal-id" value="">

      <div class="umodal-body">
        <div class="admin-field">
          <label for="umodal-name">Full name <span class="required">*</span></label>
          <input type="text" id="umodal-name" name="name" required placeholder="e.g. John Smith" autocomplete="off">
        </div>

        <div class="admin-field">
          <label for="umodal-email">Email address <span class="required">*</span></label>
          <input type="email" id="umodal-email" name="email" required placeholder="john@example.com" autocomplete="off">
        </div>

        <div class="admin-field">
          <label for="umodal-password">
            Password
            <span id="umodal-pw-required" class="required">*</span>
            <span id="umodal-pw-optional" style="font-weight:400;color:#64748b;display:none;">(leave blank to keep current)</span>
          </label>
          <div class="umodal-pw-wrap">
            <input type="password" id="umodal-password" name="password"
              autocomplete="new-password" placeholder="Minimum 8 characters">
            <button type="button" class="umodal-pw-toggle" aria-label="Toggle password visibility">
              <svg id="umodal-eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="umodal-eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <small class="admin-field-hint">Minimum 8 characters.</small>
        </div>

        <div class="umodal-row">
          <div class="admin-field">
            <label for="umodal-role">Role <span class="required">*</span></label>
            <select id="umodal-role" name="role" required>
              <option value="reviewer">Reviewer</option>
              <option value="partner">Partner</option>
              <option value="admin">Admin</option>
            </select>
            <small class="admin-field-hint">Partners only see submissions where they are selected as a reference.</small>
          </div>

          <div class="admin-field">
            <label for="umodal-status">Status</label>
            <select id="umodal-status" name="status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div class="admin-field" id="umodal-reference-wrap" hidden>
          <label for="umodal-reference-code">Reference code <span class="required">*</span></label>
          <input type="text" id="umodal-reference-code" name="reference_code"
            placeholder="e.g. 1001 or JV-REF" pattern="[A-Za-z0-9_-]{2,32}">
          <small class="admin-field-hint">Shown on dropdowns and used when clients enter a reference code.</small>
        </div>
      </div>

      <div class="umodal-footer">
        <button type="submit" id="umodal-submit" class="admin-btn">
          <span id="umodal-submit-text">Create member</span>
          <span id="umodal-submit-spinner" style="display:none;">Saving…</span>
        </button>
        <button type="button" class="admin-btn admin-btn-secondary" id="umodal-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  const csrf = <?= json_encode($csrf) ?>;

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const modal     = document.getElementById('user-modal');
  const form      = document.getElementById('user-modal-form');
  const errorsBox = document.getElementById('umodal-errors');
  const idInput   = document.getElementById('umodal-id');
  const nameInput = document.getElementById('umodal-name');
  const emailInput= document.getElementById('umodal-email');
  const pwInput   = document.getElementById('umodal-password');
  const roleSelect= document.getElementById('umodal-role');
  const statSelect= document.getElementById('umodal-status');
  const refWrap   = document.getElementById('umodal-reference-wrap');
  const refInput  = document.getElementById('umodal-reference-code');
  const title     = document.getElementById('umodal-title');
  const submitBtn = document.getElementById('umodal-submit');
  const submitTxt = document.getElementById('umodal-submit-text');
  const submitSpin= document.getElementById('umodal-submit-spinner');
  const pwReq     = document.getElementById('umodal-pw-required');
  const pwOpt     = document.getElementById('umodal-pw-optional');

  function isOpen() {
    return modal.classList.contains('is-open');
  }

  function syncPartnerFields() {
    const isPartner = roleSelect.value === 'partner';
    if (refWrap) refWrap.hidden = !isPartner;
    if (refInput) refInput.required = isPartner;
  }

  // ── Open / close ─────────────────────────────────────────────────────────
  function openModal(user) {
    const isNew = !user;
    title.textContent     = isNew ? 'Add team member' : 'Edit team member';
    submitTxt.textContent = isNew ? 'Create member' : 'Save changes';
    idInput.value         = isNew ? '' : String(user.id);
    nameInput.value       = isNew ? '' : user.name;
    emailInput.value      = isNew ? '' : user.email;
    pwInput.value         = '';
    roleSelect.value      = isNew ? 'reviewer' : user.role;
    statSelect.value      = isNew ? 'active' : user.status;
    if (refInput) refInput.value = isNew ? '' : (user.reference_code || '');
    pwInput.required      = isNew;
    pwReq.style.display   = isNew ? '' : 'none';
    pwOpt.style.display   = isNew ? 'none' : '';
    syncPartnerFields();
    hideErrors();
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    nameInput.focus();
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    form.reset();
    hideErrors();
  }

  function hideErrors() {
    errorsBox.style.display = 'none';
    errorsBox.innerHTML = '';
  }

  function showErrors(errors) {
    errorsBox.innerHTML = '<ul style="margin:0;padding-left:1.25rem;">'
      + errors.map(function(e){ return '<li>' + escHtml(e) + '</li>'; }).join('')
      + '</ul>';
    errorsBox.style.display = 'block';
    errorsBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function escHtml(str) {
    return str.replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
    });
  }

  // ── Show flash ────────────────────────────────────────────────────────────
  function showFlash(msg) {
    if (window.AdminToast) {
      window.AdminToast.success(msg);
    }
  }

  // ── Submit via fetch ──────────────────────────────────────────────────────
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    hideErrors();
    submitBtn.disabled   = true;
    submitTxt.style.display  = 'none';
    submitSpin.style.display = '';

    const fd = new FormData(form);

    fetch('/admin/user-save', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.errors) {
        showErrors(data.errors);
        return;
      }
      closeModal();
      showFlash(data.message || 'Saved.');
      // Reload to reflect updated table + stats
      setTimeout(function(){ location.reload(); }, 600);
    })
    .catch(function() {
      showErrors(['A network error occurred. Please try again.']);
    })
    .finally(function() {
      submitBtn.disabled   = false;
      submitTxt.style.display  = '';
      submitSpin.style.display = 'none';
    });
  });

  // ── Toggle status via fetch ───────────────────────────────────────────────
  function toggleStatus(id, action, name) {
    if (action === 'deactivate') {
      if (!confirm('Deactivate ' + name + '? They will not be able to log in.')) return;
    }
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('id', id);
    fd.append('action', action);

    fetch('/admin/user-action', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    })
    .then(function(){ location.reload(); })
    .catch(function(){ location.reload(); });
  }

  // ── Event wiring ──────────────────────────────────────────────────────────
  const btnAdd = document.getElementById('btn-add-user');
  if (btnAdd) btnAdd.addEventListener('click', function(){ openModal(null); });

  const emptyBtn = document.getElementById('btn-add-user-empty');
  if (emptyBtn) emptyBtn.addEventListener('click', function(){ openModal(null); });

  const btnClose = document.getElementById('umodal-close');
  const btnCancel = document.getElementById('umodal-cancel');
  if (btnClose) btnClose.addEventListener('click', closeModal);
  if (btnCancel) btnCancel.addEventListener('click', closeModal);

  modal.addEventListener('click', function(e){
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && isOpen()) closeModal();
  });

  roleSelect?.addEventListener('change', syncPartnerFields);

  document.querySelectorAll('.btn-edit-user').forEach(function(btn){
    btn.addEventListener('click', function(){
      openModal({
        id:     btn.dataset.userId,
        name:   btn.dataset.userName || '',
        email:  btn.dataset.userEmail || '',
        role:   btn.dataset.userRole || 'reviewer',
        status: btn.dataset.userStatus || 'active',
        reference_code: btn.dataset.userReference || '',
      });
    });
  });

  document.querySelectorAll('.btn-toggle-status').forEach(function(btn){
    btn.addEventListener('click', function(){
      toggleStatus(btn.dataset.id, btn.dataset.action, btn.dataset.name);
    });
  });

  // ── Password visibility toggle ────────────────────────────────────────────
  const pwToggle = document.querySelector('.umodal-pw-toggle');
  if (pwToggle) {
    pwToggle.addEventListener('click', function(){
      const show = pwInput.type === 'password';
      pwInput.type = show ? 'text' : 'password';
      const eyeShow = document.getElementById('umodal-eye-show');
      const eyeHide = document.getElementById('umodal-eye-hide');
      if (eyeShow) eyeShow.style.display = show ? 'none' : '';
      if (eyeHide) eyeHide.style.display = show ? '' : 'none';
    });
  }

  // Ensure modal starts closed (e.g. after bfcache restore)
  closeModal();
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
