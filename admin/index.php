<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$submissionCounts = $repo->submissionCountsByFormId();
$globalSubs = $repo->globalSubmissionCounts();

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$formStats = $repo->formStatusCounts();
$totalForms = $formStats['total'];
$pagination = pagination_meta($totalForms, $page, $perPage);
$forms = $repo->allPaginated($pagination['per_page'], $pagination['offset']);
$recentSubmissions = $repo->recentSubmissions(5);

$isAdmin = Auth::userRole() === 'admin';
$allFormsList = $repo->all();
if (!$isAdmin) {
    $allFormsList = array_values(array_filter(
        $allFormsList,
        fn (array $f) => ($f['status'] ?? '') === 'published'
    ));
}

$pendingForms = [];
$formsWithPending = 0;
foreach ($allFormsList as $form) {
    $fid = (int) $form['id'];
    $pending = $submissionCounts[$fid]['pending'] ?? 0;
    if ($pending > 0) {
        $formsWithPending++;
        $pendingForms[] = ['form' => $form, 'pending' => $pending];
    }
}
usort($pendingForms, fn (array $a, array $b) => $b['pending'] <=> $a['pending']);
$pendingForms = array_slice($pendingForms, 0, 5);

$paginationPath = '/admin/';
$paginationQuery = [];
$paginationLabel = 'forms';
$paginationAriaLabel = 'Dashboard forms pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/', [], $p, $pagination['per_page']);

$currentUser = Auth::currentUser();
$displayName = trim((string) ($currentUser['name'] ?? 'Admin'));
$nameParts = preg_split('/\s+/', $displayName) ?: [];
$firstName = $nameParts[0] ?? $displayName;

function fmt_date(string $dt): string
{
    try {
        $d = new DateTime($dt);
        return $d->format('M j, Y');
    } catch (Exception) {
        return $dt;
    }
}

function fmt_relative(string $dt): string
{
    try {
        $then = new DateTime($dt);
        $now = new DateTime();
        $diff = $now->diff($then);
        if ($diff->days === 0) {
            if ($diff->h > 0) {
                return $diff->h . 'h ago';
            }
            if ($diff->i > 0) {
                return $diff->i . 'm ago';
            }
            return 'Just now';
        }
        if ($diff->days === 1) {
            return 'Yesterday';
        }
        if ($diff->days < 7) {
            return $diff->days . 'd ago';
        }
        return $then->format('M j');
    } catch (Exception) {
        return $dt;
    }
}

function dashboard_greeting(string $firstName): string
{
    $hour = (int) date('G');
    if ($hour < 12) {
        return 'Good morning, ' . $firstName;
    }
    if ($hour < 17) {
        return 'Good afternoon, ' . $firstName;
    }
    return 'Good evening, ' . $firstName;
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout-start.php';
?>

<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
  <div class="admin-header-actions">
    <?php if ($globalSubs['pending'] > 0): ?>
      <a href="/admin/reviewer-submissions?tab=pending" class="admin-btn admin-btn-secondary">Review pending</a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <a href="/admin/form-builder" class="admin-btn">New form</a>
    <?php endif; ?>
  </div>
</div>

<div class="md-dashboard">
  <header class="md-page-header md-page-header--dashboard">
    <div class="md-page-header-text">
      <p class="md-dashboard-greeting"><?= e(dashboard_greeting($firstName)) ?></p>
      <p class="md-page-sub">
        <?= e(date('l, F j, Y')) ?>
        <?php if ($isAdmin): ?>
          · <?= (int) $formStats['published'] ?> published<?= $formStats['drafts'] > 0 ? ', ' . (int) $formStats['drafts'] . ' draft' . ($formStats['drafts'] === 1 ? '' : 's') : '' ?>
        <?php else: ?>
          · <?= count($allFormsList) ?> form<?= count($allFormsList) === 1 ? '' : 's' ?> to review
        <?php endif; ?>
      </p>
    </div>
  </header>

  <div class="md-kpi-row">
    <a href="<?= $isAdmin ? '/admin/forms' : '/admin/reviewer-submissions' ?>" class="md-kpi-card md-kpi-card--link">
      <span class="md-kpi-icon md-kpi-icon--navy" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value"><?= number_format($totalForms) ?></div>
        <div class="md-kpi-label">Forms</div>
        <?php if ($isAdmin): ?>
          <div class="md-kpi-sub"><?= (int) $formStats['published'] ?> live · <?= (int) $formStats['drafts'] ?> draft</div>
        <?php else: ?>
          <div class="md-kpi-sub">Published &amp; active</div>
        <?php endif; ?>
      </div>
    </a>

    <a href="/admin/reviewer-submissions" class="md-kpi-card md-kpi-card--link">
      <span class="md-kpi-icon md-kpi-icon--blue" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value"><?= number_format($globalSubs['all']) ?></div>
        <div class="md-kpi-label">Responses</div>
        <div class="md-kpi-sub">All submissions</div>
      </div>
    </a>

    <a href="/admin/reviewer-submissions?tab=pending" class="md-kpi-card md-kpi-card--link<?= $globalSubs['pending'] > 0 ? ' md-kpi-card--warn' : '' ?>">
      <span class="md-kpi-icon md-kpi-icon--amber" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value<?= $globalSubs['pending'] > 0 ? ' md-kpi-value--amber' : '' ?>"><?= number_format($globalSubs['pending']) ?></div>
        <div class="md-kpi-label">Pending review</div>
        <div class="md-kpi-sub">
          <?= $formsWithPending > 0 ? $formsWithPending . ' form' . ($formsWithPending === 1 ? '' : 's') . ' need attention' : 'Nothing waiting' ?>
        </div>
      </div>
    </a>

    <div class="md-kpi-card">
      <span class="md-kpi-icon md-kpi-icon--green" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value md-kpi-value--green"><?= number_format($globalSubs['complete']) ?></div>
        <div class="md-kpi-label">Complete</div>
        <div class="md-kpi-sub">Marked done</div>
      </div>
    </div>
  </div>

  <div class="md-content-grid">
    <div class="md-main-col">
      <div class="admin-card md-forms-card">
        <div class="md-forms-card-head">
          <h2 class="md-forms-card-title">Your forms</h2>
          <div class="md-forms-card-tools">
            <?php if ($totalForms > 0): ?>
              <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
            <?php endif; ?>
            <?php if ($forms): ?>
              <a href="<?= $isAdmin ? '/admin/forms' : '/admin/reviewer-submissions' ?>" class="md-text-link">View all</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$forms): ?>
          <div class="md-empty">
            <p>No forms yet.</p>
            <?php if ($isAdmin): ?>
              <a href="/admin/form-builder" class="admin-btn admin-btn-sm">Create your first form</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Form</th>
                <th>Status</th>
                <th class="rs-th-num">Pending</th>
                <th class="rs-th-num">Total</th>
                <th>Updated</th>
                <th class="rs-th-go" aria-hidden="true"><span class="visually-hidden">Open</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($forms as $form):
                $fid = (int) $form['id'];
                $counts = $submissionCounts[$fid] ?? ['all' => 0, 'pending' => 0, 'complete' => 0];
                $status = $form['status'] ?? 'draft';
                $hasPending = $counts['pending'] > 0;
                $openUrl = '/admin/submissions?form_id=' . $fid . ($hasPending ? '&tab=pending' : '');
              ?>
                <tr class="rs-row">
                  <td>
                    <a href="<?= e($openUrl) ?>" class="rs-form-link"><?= e($form['title']) ?></a>
                    <?php if ($status === 'published'): ?>
                      <span class="rs-form-slug">/form/<?= e($form['slug']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge badge-<?= e($status) ?>"><?= e($status) ?></span></td>
                  <td class="rs-td-num">
                    <?php if ($hasPending): ?>
                      <span class="rs-pending-count"><?= $counts['pending'] ?></span>
                    <?php else: ?>
                      <span class="rs-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="rs-td-num"><?= number_format($counts['all']) ?></td>
                  <td class="dashboard-date"><?= e(fmt_date($form['updated_at'])) ?></td>
                  <td class="rs-td-go">
                    <a href="<?= e($openUrl) ?>" class="rs-go-link" aria-label="Open <?= e($form['title']) ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <?php if ($totalForms > 0): ?>
          <div class="md-surface-footer">
            <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <aside class="md-side-col">
      <?php if ($pendingForms): ?>
        <div class="md-surface md-surface--alert">
          <div class="md-surface-header">
            <h2 class="md-surface-title">Needs attention</h2>
            <a href="/admin/reviewer-submissions?tab=pending" class="md-text-link">View all</a>
          </div>
          <ul class="md-pending-list">
            <?php foreach ($pendingForms as $item):
              $form = $item['form'];
              $fid = (int) $form['id'];
              $openUrl = '/admin/submissions?form_id=' . $fid . '&tab=pending';
            ?>
              <li class="md-pending-item">
                <span class="md-pending-name"><?= e($form['title']) ?></span>
                <a href="<?= e($openUrl) ?>" class="md-pending-link">
                  <?= (int) $item['pending'] ?> pending
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($recentSubmissions): ?>
        <div class="md-surface">
          <div class="md-surface-header">
            <h2 class="md-surface-title">Recent responses</h2>
            <a href="/admin/reviewer-submissions" class="md-text-link">View all</a>
          </div>
          <ul class="md-activity-list">
            <?php foreach ($recentSubmissions as $sub):
              $status = $sub['status'] ?? 'pending';
              $subUrl = '/admin/submission?id=' . (int) $sub['id'] . '&form_id=' . (int) $sub['form_id'];
            ?>
              <li class="md-activity-item">
                <span class="md-activity-dot md-activity-dot--<?= e($status) ?>" aria-hidden="true"></span>
                <div class="md-activity-body">
                  <a href="<?= e($subUrl) ?>" class="md-activity-title"><?= e($sub['form_title']) ?></a>
                  <div class="md-activity-row">
                    <span class="md-status-pill md-status-pill--<?= e($status) ?> md-status-pill--xs"><?= e(submission_status_label($status)) ?></span>
                    <span class="md-activity-time"><?= e(fmt_relative($sub['created_at'])) ?></span>
                  </div>
                </div>
                <a href="<?= e($subUrl) ?>" class="md-activity-view" aria-label="View submission">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="md-surface">
        <div class="md-surface-header">
          <h2 class="md-surface-title">Quick links</h2>
        </div>
        <nav class="md-quick-links" aria-label="Quick links">
          <a href="/admin/reviewer-submissions" class="md-quick-link">
            <span class="md-quick-link-icon md-quick-link-icon--primary" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
            </span>
            <span class="md-quick-link-text">
              <strong>Submissions</strong>
              <small>Review all responses</small>
            </span>
          </a>
          <?php if ($isAdmin): ?>
            <a href="/admin/forms" class="md-quick-link">
              <span class="md-quick-link-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
              </span>
              <span class="md-quick-link-text">
                <strong>All forms</strong>
                <small>Manage &amp; publish</small>
              </span>
            </a>
            <a href="/admin/clients" class="md-quick-link">
              <span class="md-quick-link-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
              </span>
              <span class="md-quick-link-text">
                <strong>Clients</strong>
                <small>Contact directory</small>
              </span>
            </a>
            <a href="/admin/files" class="md-quick-link">
              <span class="md-quick-link-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
              </span>
              <span class="md-quick-link-text">
                <strong>Files</strong>
                <small>Uploads &amp; storage</small>
              </span>
            </a>
          <?php endif; ?>
        </nav>
      </div>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
