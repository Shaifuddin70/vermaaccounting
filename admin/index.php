<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$forms = $repo->all();
$published = count(array_filter($forms, fn ($f) => $f['status'] === 'published'));

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Dashboard</h1>
  <a href="/admin/form-builder.php" class="admin-btn">+ New form</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="admin-card">
    <div style="font-size:2rem;font-weight:700;"><?= count($forms) ?></div>
    <div style="color:#64748b;">Total forms</div>
  </div>
  <div class="admin-card">
    <div style="font-size:2rem;font-weight:700;"><?= $published ?></div>
    <div style="color:#64748b;">Published</div>
  </div>
</div>

<div class="admin-card">
  <h2 style="margin:0 0 1rem;font-size:1.1rem;">Recent forms</h2>
  <?php if (!$forms): ?>
    <p style="color:#64748b;">No forms yet. <a href="/admin/form-builder.php">Create your first form</a>.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Status</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($forms, 0, 5) as $form): ?>
          <tr>
            <td><?= e($form['title']) ?></td>
            <td><span class="badge badge-<?= e($form['status']) ?>"><?= e($form['status']) ?></span></td>
            <td><?= e($form['updated_at']) ?></td>
            <td>
              <a href="/admin/form-builder.php?id=<?= (int) $form['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
