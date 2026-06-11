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
    <p style="color:#64748b;">No forms yet.</p>
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
              <?php else: ?>
                <code>/form/<?= e($form['slug']) ?></code> <span class="badge badge-draft">draft</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= e($form['status']) ?>"><?= e($form['status']) ?></span></td>
            <td><?= $subCount ?></td>
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
