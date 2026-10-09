<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

Auth::requireCapability('forms.view', 'forms.manage');
$repo = new FormRepository();
$submissionCounts = $repo->submissionCountsByFormId();

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$totalForms = $repo->countForms();
$pagination = pagination_meta($totalForms, $page, $perPage);
$forms = $repo->allPaginated($pagination['per_page'], $pagination['offset']);

$paginationPath = '/admin/forms';
$paginationQuery = [];
$paginationLabel = 'forms';
$paginationAriaLabel = 'Forms list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/forms', [], $p, $pagination['per_page']);

$menuForms = Auth::can('forms.manage') ? $repo->submitMenuForms() : [];

$csrf = Auth::csrfToken();
$pageTitle = 'All forms';
$activeNav = 'forms';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>All forms</h1>
  <?php if (Auth::can('forms.manage')): ?>
  <div class="admin-header-actions">
    <a href="/admin/form-builder?template=tax-intake" class="admin-btn admin-btn-secondary">Tax intake template</a>
    <a href="/admin/form-builder" class="admin-btn">+ New form</a>
  </div>
  <?php endif; ?>
</div>

<div class="admin-card">
  <?php if (!$forms): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
      </span>
      <h2 class="admin-empty-state-title">No forms yet</h2>
      <p class="admin-empty-state-text">Build your first form to start collecting client information and documents.</p>
      <?php if (Auth::can('forms.manage')): ?>
      <div class="admin-header-actions">
        <a href="/admin/form-builder?template=tax-intake" class="admin-btn">Start from tax intake</a>
        <a href="/admin/form-builder" class="admin-btn admin-btn-secondary">Blank form</a>
      </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <div class="admin-table-scroll">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug / URL</th>
          <th>Status</th>
          <th>Submissions</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($forms as $form):
          $fid = (int) $form['id'];
          $subCount = $submissionCounts[$fid]['all'] ?? 0;
          $deleteConfirm = $subCount > 0
              ? 'Delete “' . $form['title'] . '”? This will permanently remove the form and its ' . number_format($subCount) . ' submission' . ($subCount === 1 ? '' : 's') . '. This cannot be undone.'
              : 'Delete “' . $form['title'] . '”? This cannot be undone.';
        ?>
          <tr>
            <td>
              <?= e($form['title']) ?>
              <?php if (!empty($form['is_site_cta'])): ?>
                <span class="badge badge-published" style="margin-left:0.35rem;">Site CTA</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($form['status'] === 'published'): ?>
                <a href="/form/<?= e($form['slug']) ?>" target="_blank" rel="noopener">/form/<?= e($form['slug']) ?></a>
                <button type="button" class="admin-copy-btn" data-copy-path="/form/<?= e($form['slug']) ?>" title="Copy link" aria-label="Copy link to <?= e($form['title']) ?>">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
              <?php else: ?>
                <code>/form/<?= e($form['slug']) ?></code> <span class="badge badge-draft">draft</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= e($form['status']) ?>"><?= e($form['status']) ?></span></td>
            <td>
              <?php if ($subCount > 0): ?>
                <a href="/admin/submissions?form_id=<?= $fid ?>"><?= number_format($subCount) ?></a>
              <?php else: ?>
                <span style="color:#94a3b8;">0</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="admin-table-actions">
                <?php if (Auth::can('forms.manage')): ?>
                <a href="/admin/form-builder?id=<?= $fid ?>" class="admin-btn admin-btn-sm">Edit</a>
                <?php endif; ?>
                <?php if (Auth::can('submissions.view')): ?>
                <a href="/admin/submissions?form_id=<?= $fid ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Responses</a>
                <?php endif; ?>
                <?php if (Auth::can('submissions.export')): ?>
                <?php if ($subCount > 0): ?>
                  <a href="/admin/export-csv?form_id=<?= $fid ?>" class="admin-btn admin-btn-sm admin-btn-secondary">CSV</a>
                <?php else: ?>
                  <span class="admin-btn admin-btn-sm admin-btn-disabled" title="No submissions yet">CSV</span>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (Auth::can('forms.manage')): ?>
                <form method="post" action="/admin/form-action" class="inline-form"
                  onsubmit="return confirm(<?= e(json_encode($deleteConfirm)) ?>);">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="form_id" value="<?= $fid ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<?php if ($menuForms !== []): ?>
<div class="admin-card menu-order-card" id="submit-menu-order">
  <h2 class="menu-order-title">Submit Document menu order</h2>
  <p class="admin-field-hint">
    Drag forms (or use the arrows) to set the order they appear in the website’s <strong>Submit Document</strong> menu.
    Only published forms with “Show in Submit Document menu” turned on are listed.
  </p>
  <form method="post" action="/admin/forms-menu-order">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <ol class="menu-order-list" data-menu-order>
      <?php foreach ($menuForms as $menuForm): ?>
        <li class="menu-order-item" draggable="true">
          <input type="hidden" name="form_ids[]" value="<?= (int) $menuForm['id'] ?>">
          <span class="menu-order-handle" aria-hidden="true">⠿</span>
          <span class="menu-order-name"><?= e((string) $menuForm['title']) ?></span>
          <span class="menu-order-actions">
            <button type="button" class="menu-order-move" data-move="-1" aria-label="Move up" title="Move up">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
            </button>
            <button type="button" class="menu-order-move" data-move="1" aria-label="Move down" title="Move down">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
          </span>
        </li>
      <?php endforeach; ?>
    </ol>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary" data-menu-order-save disabled>Save order</button>
    </div>
  </form>
</div>
<script>
  (function () {
    var list = document.querySelector('[data-menu-order]');
    if (!list) return;
    var save = document.querySelector('[data-menu-order-save]');
    var dragging = null;
    function refreshArrows() {
      var items = list.querySelectorAll('.menu-order-item');
      items.forEach(function (item, i) {
        item.querySelector('[data-move="-1"]').disabled = i === 0;
        item.querySelector('[data-move="1"]').disabled = i === items.length - 1;
      });
    }
    function changed() {
      refreshArrows();
      if (save) save.disabled = false;
    }
    refreshArrows();

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-move]');
      if (!btn) return;
      var item = btn.closest('.menu-order-item');
      if (btn.getAttribute('data-move') === '-1' && item.previousElementSibling) {
        list.insertBefore(item, item.previousElementSibling);
      } else if (btn.getAttribute('data-move') === '1' && item.nextElementSibling) {
        list.insertBefore(item.nextElementSibling, item);
      } else {
        return;
      }
      btn.focus();
      changed();
    });

    list.addEventListener('dragstart', function (e) {
      dragging = e.target.closest('.menu-order-item');
      if (!dragging) return;
      dragging.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', '');
    });
    list.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      var over = e.target.closest('.menu-order-item');
      if (!over || over === dragging) return;
      var rect = over.getBoundingClientRect();
      var after = e.clientY > rect.top + rect.height / 2;
      list.insertBefore(dragging, after ? over.nextElementSibling : over);
    });
    list.addEventListener('dragend', function () {
      if (!dragging) return;
      dragging.classList.remove('is-dragging');
      dragging = null;
      changed();
    });
  })();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
