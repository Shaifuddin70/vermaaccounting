<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$forms = $repo->all();

$pageTitle = 'All forms';
$activeNav = 'forms';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>All forms</h1>
  <a href="/admin/form-builder.php" class="admin-btn">+ New form</a>
</div>

<div class="admin-card">
  <?php if (!$forms): ?>
    <p style="color:#64748b;">No forms yet.</p>
  <?php else: ?>
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
          $subs = $repo->submissionsForForm((int) $form['id']);
        ?>
          <tr>
            <td><?= e($form['title']) ?></td>
            <td>
              <?php if ($form['status'] === 'published'): ?>
                <a href="/form/<?= e($form['slug']) ?>" target="_blank" rel="noopener">/form/<?= e($form['slug']) ?></a>
              <?php else: ?>
                <code>/form/<?= e($form['slug']) ?></code> <span class="badge badge-draft">draft</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= e($form['status']) ?>"><?= e($form['status']) ?></span></td>
            <td><?= count($subs) ?></td>
            <td style="white-space:nowrap;">
              <a href="/admin/form-builder.php?id=<?= (int) $form['id'] ?>">Edit</a>
              ·
              <a href="/admin/submissions.php?form_id=<?= (int) $form['id'] ?>">Responses</a>
              <?php if ($subs): ?>
                · <a href="/admin/export-csv.php?form_id=<?= (int) $form['id'] ?>">CSV</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
