<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
// Reviewers can only see published forms; admins see all
$forms = array_filter($repo->all(), fn ($f) => ($f['status'] ?? '') === 'published');
$submissionCounts = $repo->submissionCountsByFormId();

$pageTitle = 'Submissions';
$activeNav = 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Submissions</h1>
</div>

<?php if (!$forms): ?>
  <div class="admin-card" style="text-align:center;padding:2.5rem 1.5rem;color:var(--admin-muted);">
    <p style="margin:0;font-size:0.9375rem;">No published forms yet.</p>
  </div>
<?php else: ?>
  <div class="rs-grid">
    <?php foreach ($forms as $form):
      $fid = (int) $form['id'];
      $counts = $submissionCounts[$fid] ?? ['all' => 0, 'pending' => 0, 'complete' => 0];
    ?>
      <a href="/admin/submissions.php?form_id=<?= $fid ?>" class="rs-card">
        <div class="rs-card-header">
          <span class="rs-card-title"><?= e($form['title']) ?></span>
          <span class="rs-card-slug">/form/<?= e($form['slug']) ?></span>
        </div>
        <div class="rs-card-stats">
          <div class="rs-stat">
            <span class="rs-stat-value"><?= $counts['all'] ?></span>
            <span class="rs-stat-label">Total</span>
          </div>
          <div class="rs-stat rs-stat--warn">
            <span class="rs-stat-value"><?= $counts['pending'] ?></span>
            <span class="rs-stat-label">Pending</span>
          </div>
          <div class="rs-stat rs-stat--ok">
            <span class="rs-stat-value"><?= $counts['complete'] ?></span>
            <span class="rs-stat-label">Complete</span>
          </div>
        </div>
        <?php if ($counts['pending'] > 0): ?>
          <div class="rs-card-badge">
            <?= $counts['pending'] ?> pending
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
        <?php else: ?>
          <div class="rs-card-view">View responses →</div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
