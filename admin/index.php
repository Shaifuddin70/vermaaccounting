<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$forms = $repo->all();
$submissionCounts = $repo->submissionCountsByFormId();
$globalSubs = $repo->globalSubmissionCounts();
$recentSubs = $repo->recentSubmissions(10);

$totalForms = count($forms);
$published = count(array_filter($forms, fn ($f) => ($f['status'] ?? '') === 'published'));
$drafts = $totalForms - $published;
$formsWithPending = 0;
foreach ($forms as $form) {
    if (($submissionCounts[(int) $form['id']]['pending'] ?? 0) > 0) {
        $formsWithPending++;
    }
}

function fmt_date(string $dt): string {
    try {
        $d = new DateTime($dt);
        return $d->format('M j, Y');
    } catch (Exception) {
        return $dt;
    }
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout-start.php';
?>

<div class="admin-header">
  <h1>Dashboard</h1>
  <div class="admin-header-actions">
    <?php if (Auth::userRole() === 'admin'): ?>
      <a href="/admin/form-builder.php" class="admin-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New form
      </a>
    <?php endif; ?>
  </div>
</div>

  <!-- KPI row -->
  <div class="md-kpi-row">
    <div class="md-kpi-card">
      <div class="md-kpi-icon md-kpi-icon--navy">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="md-kpi-body">
        <div class="md-kpi-value"><?= $totalForms ?></div>
        <div class="md-kpi-label">Total forms</div>
        <div class="md-kpi-sub"><?= $published ?> published &middot; <?= $drafts ?> draft</div>
      </div>
    </div>

    <div class="md-kpi-card">
      <div class="md-kpi-icon md-kpi-icon--blue">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="md-kpi-body">
        <div class="md-kpi-value"><?= $globalSubs['all'] ?></div>
        <div class="md-kpi-label">Total responses</div>
        <div class="md-kpi-sub">Across all forms</div>
      </div>
    </div>

    <div class="md-kpi-card md-kpi-card--warn">
      <div class="md-kpi-icon md-kpi-icon--amber">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div class="md-kpi-body">
        <div class="md-kpi-value md-kpi-value--amber"><?= $globalSubs['pending'] ?></div>
        <div class="md-kpi-label">Pending review</div>
        <div class="md-kpi-sub"><?= $formsWithPending ?> form<?= $formsWithPending === 1 ? '' : 's' ?> need attention</div>
      </div>
    </div>

    <div class="md-kpi-card">
      <div class="md-kpi-icon md-kpi-icon--green">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="md-kpi-body">
        <div class="md-kpi-value md-kpi-value--green"><?= $globalSubs['complete'] ?></div>
        <div class="md-kpi-label">Completed</div>
        <div class="md-kpi-sub">Marked as complete</div>
      </div>
    </div>
  </div>

  <!-- Main content grid -->
  <div class="md-content-grid">

    <!-- Forms table -->
    <div class="md-surface md-surface--main">
      <div class="md-surface-header">
        <h2 class="md-surface-title">Forms overview</h2>
        <?php if ($forms): ?>
          <a href="/admin/forms.php" class="md-text-link">View all <span aria-hidden="true">→</span></a>
        <?php endif; ?>
      </div>

      <?php if (!$forms): ?>
        <div class="md-empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#cbd5e1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p>No forms yet. <a href="/admin/form-builder.php" class="md-text-link">Create your first form</a> to start collecting responses.</p>
        </div>
      <?php else: ?>
        <div class="md-table-wrap">
          <table class="md-table">
            <thead>
              <tr>
                <th>Form</th>
                <th>Status</th>
                <th>Responses</th>
                <th>Pending</th>
                <th>Complete</th>
                <th>Last updated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($forms as $form):
                $fid = (int) $form['id'];
                $counts = $submissionCounts[$fid] ?? ['all' => 0, 'pending' => 0, 'complete' => 0];
                $status = $form['status'] ?? 'draft';
                $hasPending = $counts['pending'] > 0;
              ?>
                <tr class="<?= $hasPending ? 'md-tr--warn' : '' ?>">
                  <td class="md-td-form">
                    <div class="md-form-name-wrap">
                      <span class="md-form-name"><?= e($form['title']) ?></span>
                      <?php if (!empty($form['is_site_cta'])): ?>
                        <span class="md-chip md-chip--blue">Site CTA</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($status === 'published'): ?>
                      <a href="/form/<?= e($form['slug']) ?>" class="md-form-slug" target="_blank" rel="noopener">/form/<?= e($form['slug']) ?></a>
                    <?php else: ?>
                      <span class="md-form-slug">/form/<?= e($form['slug']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="md-status-pill md-status-pill--<?= e($status) ?>"><?= $status === 'published' ? 'Published' : 'Draft' ?></span>
                  </td>
                  <td class="md-td-num"><?= $counts['all'] ?></td>
                  <td class="md-td-num">
                    <?php if ($hasPending): ?>
                      <span class="md-num-badge md-num-badge--warn"><?= $counts['pending'] ?></span>
                    <?php else: ?>
                      <span class="md-td-zero">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="md-td-num">
                    <?php if ($counts['complete'] > 0): ?>
                      <span class="md-num-badge md-num-badge--ok"><?= $counts['complete'] ?></span>
                    <?php else: ?>
                      <span class="md-td-zero">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="md-td-date"><?= e(fmt_date($form['updated_at'])) ?></td>
                  <td class="md-td-actions">
                    <a href="/admin/submissions.php?form_id=<?= $fid ?>" class="md-btn-ghost">Responses</a>
                    <?php if (Auth::userRole() === 'admin'): ?>
                      <a href="/admin/form-builder.php?id=<?= $fid ?>" class="md-btn-ghost md-btn-ghost--primary">Edit</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right column -->
    <div class="md-col-side">

      <!-- Pending attention card -->
      <?php if ($globalSubs['pending'] > 0): ?>
        <div class="md-surface md-surface--alert">
          <div class="md-surface-header">
            <h2 class="md-surface-title">Needs attention</h2>
            <span class="md-badge-count"><?= $globalSubs['pending'] ?></span>
          </div>
          <ul class="md-pending-list">
            <?php foreach ($forms as $form):
              $fid = (int) $form['id'];
              $pending = $submissionCounts[$fid]['pending'] ?? 0;
              if ($pending < 1) continue;
            ?>
              <li class="md-pending-item">
                <span class="md-pending-name"><?= e($form['title']) ?></span>
                <a href="/admin/submissions.php?form_id=<?= $fid ?>&tab=pending" class="md-pending-link">
                  <?= $pending ?> pending
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Recent activity -->
      <div class="md-surface">
        <div class="md-surface-header">
          <h2 class="md-surface-title">Recent submissions</h2>
        </div>

        <?php if (!$recentSubs): ?>
          <div class="md-empty md-empty--sm">
            <p>No submissions yet.</p>
          </div>
        <?php else: ?>
          <ul class="md-activity-list">
            <?php foreach ($recentSubs as $sub):
              $subStatus = $sub['status'] ?? 'pending';
              $formId = (int) $sub['form_id'];
              $subId = (int) $sub['id'];
            ?>
              <li class="md-activity-item">
                <div class="md-activity-dot md-activity-dot--<?= e($subStatus) ?>"></div>
                <div class="md-activity-body">
                  <a href="/admin/submission.php?id=<?= $subId ?>&form_id=<?= $formId ?>" class="md-activity-title"><?= e($sub['form_title']) ?></a>
                  <div class="md-activity-row">
                    <span class="md-status-pill md-status-pill--<?= e($subStatus) ?> md-status-pill--xs"><?= e(submission_status_label($subStatus)) ?></span>
                    <time class="md-activity-time"><?= e(fmt_date($sub['created_at'])) ?></time>
                  </div>
                </div>
                <a href="/admin/submission.php?id=<?= $subId ?>&form_id=<?= $formId ?>" class="md-activity-view" aria-label="View submission">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

    </div><!-- /md-col-side -->
  </div><!-- /md-content-grid -->

<?php require __DIR__ . '/includes/layout-end.php'; ?>
