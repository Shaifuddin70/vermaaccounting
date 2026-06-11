<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$tab = (string) ($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'pending'], true)) {
  $tab = 'all';
}

$repo = new FormRepository();
$submissionCounts = $repo->submissionCountsByFormId();
$allForms = $repo->all();

$forms = Auth::userRole() === 'admin'
  ? $allForms
  : array_values(array_filter($allForms, fn($f) => ($f['status'] ?? '') === 'published'));

usort($forms, function (array $a, array $b) use ($submissionCounts): int {
  $aid = (int) $a['id'];
  $bid = (int) $b['id'];
  $pendingA = $submissionCounts[$aid]['pending'] ?? 0;
  $pendingB = $submissionCounts[$bid]['pending'] ?? 0;
  if ($pendingA !== $pendingB) {
    return $pendingB <=> $pendingA;
  }
  return strcasecmp((string) $a['title'], (string) $b['title']);
});

$totals = ['all' => 0, 'pending' => 0];
$formsWithPending = 0;
foreach ($forms as $form) {
  $fid = (int) $form['id'];
  $counts = $submissionCounts[$fid] ?? ['all' => 0, 'pending' => 0, 'complete' => 0];
  $totals['all'] += $counts['all'];
  $totals['pending'] += $counts['pending'];
  if ($counts['pending'] > 0) {
    $formsWithPending++;
  }
}

$visibleForms = $tab === 'pending'
  ? array_values(array_filter($forms, function (array $form) use ($submissionCounts): bool {
    $fid = (int) $form['id'];
    return ($submissionCounts[$fid]['pending'] ?? 0) > 0;
  }))
  : $forms;

function rs_page_url(string $tab): string
{
  return $tab === 'all' ? '/admin/reviewer-submissions' : '/admin/reviewer-submissions?tab=pending';
}

$pageTitle = 'Submissions';
$activeNav = 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>

<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
</div>

<div class="rs-page">

  <?php if ($forms): ?>
    <nav class="admin-tabs rs-tabs" aria-label="Filter forms">
      <a href="<?= e(rs_page_url('all')) ?>" class="admin-tab <?= $tab === 'all' ? 'is-active' : '' ?>">
        All forms <span class="admin-tab-count"><?= count($forms) ?></span>
      </a>
      <a href="<?= e(rs_page_url('pending')) ?>" class="admin-tab <?= $tab === 'pending' ? 'is-active' : '' ?>">
        Needs review <span class="admin-tab-count"><?= $formsWithPending ?></span>
      </a>
    </nav>
  <?php endif; ?>

  <div class="admin-card rs-card">
    <?php if (!$forms): ?>
      <div class="rs-empty">
        <p>
          <?php if (Auth::userRole() === 'admin'): ?>
            No forms yet. <a href="/admin/form-builder">Create a form</a> and publish it to start collecting responses.
          <?php else: ?>
            No published forms are available for review yet.
          <?php endif; ?>
        </p>
      </div>
    <?php elseif (!$visibleForms): ?>
      <div class="rs-empty">
        <p>Nothing pending right now.</p>
        <p><a href="<?= e(rs_page_url('all')) ?>">View all forms</a></p>
      </div>
    <?php else: ?>
      <table class="admin-table rs-table">
        <thead>
          <tr>
            <th>Form</th>
            <?php if (Auth::userRole() === 'admin'): ?><th>Status</th><?php endif; ?>
            <th class="rs-th-num">Pending</th>
            <th class="rs-th-num">Total</th>
            <th class="rs-th-go" aria-hidden="true"><span class="visually-hidden">Open</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($visibleForms as $form):
            $fid = (int) $form['id'];
            $counts = $submissionCounts[$fid] ?? ['all' => 0, 'pending' => 0, 'complete' => 0];
            $status = $form['status'] ?? 'draft';
            $hasPending = $counts['pending'] > 0;
            $openUrl = '/admin/submissions?form_id=' . $fid . ($hasPending ? '&tab=pending' : '');
          ?>
            <tr class="rs-row">
              <td class="rs-td-form">
                <a href="<?= e($openUrl) ?>" class="rs-form-link"><?= e($form['title']) ?></a>
                <?php if ($status === 'published'): ?>
                  <span class="rs-form-slug">/form/<?= e($form['slug']) ?></span>
                <?php endif; ?>
              </td>
              <?php if (Auth::userRole() === 'admin'): ?>
                <td>
                  <span class="badge badge-<?= e($status) ?>"><?= e($status) ?></span>
                </td>
              <?php endif; ?>
              <td class="rs-td-num">
                <?php if ($hasPending): ?>
                  <span class="rs-pending-count"><?= $counts['pending'] ?></span>
                <?php else: ?>
                  <span class="rs-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="rs-td-num"><?= number_format($counts['all']) ?></td>
              <td class="rs-td-go">
                <a href="<?= e($openUrl) ?>" class="rs-go-link" aria-label="Open <?= e($form['title']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>