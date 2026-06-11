<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

Auth::requireRole('admin');
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

$pageTitle = 'All forms';
$activeNav = 'forms';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>All forms</h1>
  <a href="/admin/form-builder" class="admin-btn">+ New form</a>
</div>

<div class="admin-card">
  <?php if (!$forms): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
      </span>
      <h2 class="admin-empty-state-title">No forms yet</h2>
      <p class="admin-empty-state-text">Build your first form to start collecting client information and documents.</p>
      <a href="/admin/form-builder" class="admin-btn">Create your first form</a>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
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
                <a href="/admin/form-builder?id=<?= $fid ?>" class="admin-btn admin-btn-sm">Edit</a>
                <a href="/admin/submissions?form_id=<?= $fid ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Responses</a>
                <?php if ($subCount > 0): ?>
                  <a href="/admin/export-csv?form_id=<?= $fid ?>" class="admin-btn admin-btn-sm admin-btn-secondary">CSV</a>
                <?php else: ?>
                  <span class="admin-btn admin-btn-sm admin-btn-disabled" title="No submissions yet">CSV</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
