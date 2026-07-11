<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$partnerId = partner_user_id();
$submissionCounts = $repo->submissionCountsByFormId($partnerId);
$globalSubs = $repo->globalSubmissionCounts($partnerId);
$activity = $repo->submissionActivityCounts($partnerId);

$formStats = $repo->formStatusCounts();
$recentSubmissions = $repo->recentSubmissions(8, $partnerId);

$isAdmin = Auth::userRole() === 'admin';
$isPartner = Auth::userRole() === 'partner';
$allFormsList = $repo->all();
if (!$isAdmin) {
    $allFormsList = array_values(array_filter(
        $allFormsList,
        fn (array $f) => ($f['status'] ?? '') === 'published'
    ));
}
if ($isPartner) {
    $allFormsList = array_values(array_filter(
        $allFormsList,
        fn (array $f) => (($submissionCounts[(int) $f['id']]['all'] ?? 0) > 0)
    ));
}

$pendingForms = [];
$formsWithPending = 0;
foreach ($allFormsList as $form) {
    $fid = (int) $form['id'];
    $pending = $submissionCounts[$fid]['pending'] ?? 0;
    if ($pending > 0) {
        $formsWithPending++;
        $pendingForms[] = [
            'form' => $form,
            'pending' => $pending,
            'total' => $submissionCounts[$fid]['all'] ?? 0,
            'complete' => $submissionCounts[$fid]['complete'] ?? 0,
        ];
    }
}
usort($pendingForms, fn (array $a, array $b) => $b['pending'] <=> $a['pending']);
$pendingForms = array_slice($pendingForms, 0, 6);

$topForms = [];
foreach ($allFormsList as $form) {
    $fid = (int) $form['id'];
    $counts = $submissionCounts[$fid] ?? ['all' => 0, 'pending' => 0, 'complete' => 0];
    if ($counts['all'] < 1 && ($form['status'] ?? '') !== 'published') {
        continue;
    }
    $topForms[] = [
        'form' => $form,
        'counts' => $counts,
    ];
}
usort($topForms, fn (array $a, array $b) => $b['counts']['all'] <=> $a['counts']['all']);
$topForms = array_slice($topForms, 0, 6);

$clientCount = 0;
$clientEmailCount = 0;
$campaignQueue = [
    'sending' => 0,
    'scheduled_future' => 0,
    'draft' => 0,
    'sent' => 0,
];
$nextHoliday = null;
$storageStats = null;
if ($isAdmin) {
    $clientRepo = new ClientRepository();
    $clientCount = $clientRepo->count();
    $clientEmailCount = $clientRepo->countWithEmail();
    $campaignQueue = (new EmailCampaignRepository())->queueDiagnostics();
    $storageStats = (new UploadRepository())->storageStats();

    $holidayRepo = new HolidayScheduleRepository();
    $holidayCandidates = $holidayRepo->all();
    usort($holidayCandidates, static function (array $a, array $b): int {
        $aNext = holiday_next_send_at($a);
        $bNext = holiday_next_send_at($b);
        if ($aNext === null && $bNext === null) {
            return 0;
        }
        if ($aNext === null) {
            return 1;
        }
        if ($bNext === null) {
            return -1;
        }
        return $aNext <=> $bNext;
    });
    foreach ($holidayCandidates as $schedule) {
        if (empty($schedule['enabled'])) {
            continue;
        }
        $nextHoliday = $schedule;
        break;
    }
}

$completionRate = $globalSubs['all'] > 0
    ? (int) round(($globalSubs['complete'] / $globalSubs['all']) * 100)
    : 0;

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

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout-start.php';
?>

<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
</div>

<div class="md-dashboard">
  <div class="md-kpi-row">
    <a href="/admin/reviewer-submissions?tab=pending" class="md-kpi-card md-kpi-card--link<?= $globalSubs['pending'] > 0 ? ' md-kpi-card--warn' : '' ?>">
      <span class="md-kpi-icon md-kpi-icon--amber" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value<?= $globalSubs['pending'] > 0 ? ' md-kpi-value--amber' : '' ?>"><?= number_format($globalSubs['pending']) ?></div>
        <div class="md-kpi-label">Pending review</div>
        <div class="md-kpi-sub">
          <?= $formsWithPending > 0 ? $formsWithPending . ' form' . ($formsWithPending === 1 ? '' : 's') . ' waiting' : 'Nothing waiting' ?>
        </div>
      </div>
    </a>

    <a href="/admin/reviewer-submissions" class="md-kpi-card md-kpi-card--link">
      <span class="md-kpi-icon md-kpi-icon--blue" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value"><?= number_format($activity['today']) ?></div>
        <div class="md-kpi-label">New today</div>
        <div class="md-kpi-sub"><?= number_format($activity['week']) ?> in the last 7 days</div>
      </div>
    </a>

    <div class="md-kpi-card">
      <span class="md-kpi-icon md-kpi-icon--green" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
      </span>
      <div class="md-kpi-body">
        <div class="md-kpi-value md-kpi-value--green"><?= number_format($activity['completed_week']) ?></div>
        <div class="md-kpi-label">Completed this week</div>
        <div class="md-kpi-sub"><?= $completionRate ?>% overall complete</div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      <a href="/admin/forms" class="md-kpi-card md-kpi-card--link">
        <span class="md-kpi-icon md-kpi-icon--navy" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
        </span>
        <div class="md-kpi-body">
          <div class="md-kpi-value"><?= number_format($formStats['published']) ?></div>
          <div class="md-kpi-label">Live forms</div>
          <div class="md-kpi-sub">
            <?= (int) $formStats['drafts'] ?> draft<?= $formStats['drafts'] === 1 ? '' : 's' ?>
            · <?= number_format($globalSubs['all']) ?> total responses
          </div>
        </div>
      </a>
    <?php else: ?>
      <a href="/admin/reviewer-submissions" class="md-kpi-card md-kpi-card--link">
        <span class="md-kpi-icon md-kpi-icon--navy" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        </span>
        <div class="md-kpi-body">
          <div class="md-kpi-value"><?= number_format($globalSubs['all']) ?></div>
          <div class="md-kpi-label">All responses</div>
          <div class="md-kpi-sub"><?= number_format($globalSubs['complete']) ?> marked complete</div>
        </div>
      </a>
    <?php endif; ?>
  </div>

  <div class="md-content-grid">
    <div class="md-main-col">
      <?php if ($pendingForms): ?>
        <div class="admin-card md-dash-card">
          <div class="md-forms-card-head">
            <div>
              <h2 class="md-forms-card-title">Needs attention</h2>
              <p class="md-dash-card-sub">Forms with submissions waiting for review</p>
            </div>
            <a href="/admin/reviewer-submissions?tab=pending" class="md-text-link">View all pending</a>
          </div>
          <ul class="md-attention-list">
            <?php foreach ($pendingForms as $item):
              $form = $item['form'];
              $fid = (int) $form['id'];
              $openUrl = '/admin/submissions?form_id=' . $fid . '&tab=pending';
              $pct = $item['total'] > 0 ? (int) round(($item['pending'] / $item['total']) * 100) : 0;
            ?>
              <li class="md-attention-item">
                <div class="md-attention-main">
                  <a href="<?= e($openUrl) ?>" class="md-attention-title"><?= e((string) $form['title']) ?></a>
                  <span class="md-attention-meta">
                    <?= number_format((int) $item['total']) ?> total
                    · <?= number_format((int) $item['complete']) ?> complete
                  </span>
                  <div class="md-attention-bar" aria-hidden="true">
                    <span class="md-attention-bar-fill" style="width:<?= max(8, min(100, $pct)) ?>%"></span>
                  </div>
                </div>
                <a href="<?= e($openUrl) ?>" class="md-attention-count">
                  <strong><?= number_format((int) $item['pending']) ?></strong>
                  <span>pending</span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="admin-card md-dash-card">
        <div class="md-forms-card-head">
          <div>
            <h2 class="md-forms-card-title">Recent activity</h2>
            <p class="md-dash-card-sub">Latest client submissions across forms</p>
          </div>
          <a href="/admin/reviewer-submissions" class="md-text-link">View all</a>
        </div>

        <?php if (!$recentSubmissions): ?>
          <div class="md-empty">
            <p>No submissions yet.</p>
            <?php if ($isAdmin): ?>
              <a href="/admin/form-builder" class="admin-btn admin-btn-sm">Create a form</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <ul class="md-recent-list">
            <?php foreach ($recentSubmissions as $sub):
              $status = (string) ($sub['status'] ?? 'pending');
              $subUrl = '/admin/submission?id=' . (int) $sub['id'] . '&form_id=' . (int) $sub['form_id'];
            ?>
              <li class="md-recent-item">
                <span class="md-activity-dot md-activity-dot--<?= e($status) ?>" aria-hidden="true"></span>
                <div class="md-recent-body">
                  <a href="<?= e($subUrl) ?>" class="md-recent-title"><?= e((string) $sub['form_title']) ?></a>
                  <div class="md-recent-meta">
                    <span class="md-status-pill md-status-pill--<?= e($status) ?> md-status-pill--xs"><?= e(submission_status_label($status)) ?></span>
                    <span>#<?= (int) $sub['id'] ?></span>
                    <span><?= e(fmt_relative((string) $sub['created_at'])) ?></span>
                  </div>
                </div>
                <a href="<?= e($subUrl) ?>" class="md-recent-open" aria-label="Open submission #<?= (int) $sub['id'] ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <?php if ($topForms): ?>
        <div class="admin-card md-dash-card">
          <div class="md-forms-card-head">
            <div>
              <h2 class="md-forms-card-title">Form overview</h2>
              <p class="md-dash-card-sub">Most active forms by total responses</p>
            </div>
            <a href="<?= $isAdmin ? '/admin/forms' : '/admin/reviewer-submissions' ?>" class="md-text-link">
              <?= $isAdmin ? 'Manage forms' : 'All submissions' ?>
            </a>
          </div>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Form</th>
                  <th>Status</th>
                  <th class="rs-th-num">Pending</th>
                  <th class="rs-th-num">Total</th>
                  <th class="rs-th-go" aria-hidden="true"><span class="visually-hidden">Open</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topForms as $item):
                  $form = $item['form'];
                  $fid = (int) $form['id'];
                  $counts = $item['counts'];
                  $status = (string) ($form['status'] ?? 'draft');
                  $hasPending = $counts['pending'] > 0;
                  $openUrl = '/admin/submissions?form_id=' . $fid . ($hasPending ? '&tab=pending' : '');
                ?>
                  <tr class="rs-row">
                    <td>
                      <a href="<?= e($openUrl) ?>" class="rs-form-link"><?= e((string) $form['title']) ?></a>
                      <?php if ($status === 'published'): ?>
                        <span class="rs-form-slug">/form/<?= e((string) $form['slug']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= e($status) ?>"><?= e($status) ?></span></td>
                    <td class="rs-td-num">
                      <?php if ($hasPending): ?>
                        <span class="rs-pending-count"><?= number_format((int) $counts['pending']) ?></span>
                      <?php else: ?>
                        <span class="rs-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="rs-td-num"><?= number_format((int) $counts['all']) ?></td>
                    <td class="rs-td-go">
                      <a href="<?= e($openUrl) ?>" class="rs-go-link" aria-label="Open <?= e((string) $form['title']) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside class="md-side-col">
      <?php if ($isAdmin): ?>
        <div class="md-surface">
          <div class="md-surface-header">
            <h2 class="md-surface-title">Workspace snapshot</h2>
          </div>
          <ul class="md-snapshot-list">
            <li>
              <a href="/admin/clients" class="md-snapshot-link">
                <span class="md-snapshot-label">Clients</span>
                <span class="md-snapshot-value"><?= number_format($clientCount) ?></span>
                <span class="md-snapshot-note"><?= number_format($clientEmailCount) ?> with email</span>
              </a>
            </li>
            <li>
              <a href="/admin/campaigns" class="md-snapshot-link">
                <span class="md-snapshot-label">Campaigns</span>
                <span class="md-snapshot-value">
                  <?= number_format((int) $campaignQueue['sending'] + (int) $campaignQueue['scheduled_future']) ?>
                </span>
                <span class="md-snapshot-note">
                  <?= (int) $campaignQueue['sending'] ?> sending
                  · <?= (int) $campaignQueue['scheduled_future'] ?> scheduled
                </span>
              </a>
            </li>
            <li>
              <a href="/admin/holiday-calendar" class="md-snapshot-link">
                <span class="md-snapshot-label">Next holiday email</span>
                <?php if ($nextHoliday): ?>
                  <span class="md-snapshot-value md-snapshot-value--text"><?= e((string) $nextHoliday['name']) ?></span>
                  <span class="md-snapshot-note"><?= e(holiday_format_next_send($nextHoliday)) ?></span>
                <?php else: ?>
                  <span class="md-snapshot-value md-snapshot-value--text">None set</span>
                  <span class="md-snapshot-note">Add annual greetings</span>
                <?php endif; ?>
              </a>
            </li>
            <?php if ($storageStats): ?>
              <li>
                <a href="/admin/files" class="md-snapshot-link">
                  <span class="md-snapshot-label">Storage</span>
                  <span class="md-snapshot-value md-snapshot-value--text">
                    <?= e(format_file_size((int) $storageStats['used_bytes'])) ?>
                  </span>
                  <span class="md-snapshot-note">
                    <?= number_format((int) $storageStats['file_count']) ?> files
                    · <?= e(number_format((float) $storageStats['percent_used'], 0)) ?>% of quota
                  </span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="md-surface">
        <div class="md-surface-header">
          <h2 class="md-surface-title">Workload</h2>
        </div>
        <div class="md-workload">
          <div class="md-workload-row">
            <span>Pending</span>
            <strong><?= number_format($globalSubs['pending']) ?></strong>
          </div>
          <div class="md-workload-track" aria-hidden="true">
            <?php
              $pendingShare = $globalSubs['all'] > 0
                ? max(4, min(100, (int) round(($globalSubs['pending'] / $globalSubs['all']) * 100)))
                : 0;
            ?>
            <span class="md-workload-fill md-workload-fill--pending" style="width:<?= $pendingShare ?>%"></span>
          </div>
          <div class="md-workload-row">
            <span>Complete</span>
            <strong><?= number_format($globalSubs['complete']) ?></strong>
          </div>
          <div class="md-workload-track" aria-hidden="true">
            <span class="md-workload-fill md-workload-fill--complete" style="width:<?= max($completionRate ? 4 : 0, $completionRate) ?>%"></span>
          </div>
          <p class="md-workload-note">
            <?= number_format($activity['week']) ?> new in 7 days
            · <?= number_format($activity['completed_week']) ?> finished this week
          </p>
        </div>
      </div>

      <div class="md-surface">
        <div class="md-surface-header">
          <h2 class="md-surface-title">Quick links</h2>
        </div>
        <nav class="md-quick-links" aria-label="Quick links">
          <a href="/admin/reviewer-submissions?tab=pending" class="md-quick-link">
            <span class="md-quick-link-icon md-quick-link-icon--primary" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </span>
            <span class="md-quick-link-text">
              <strong>Pending inbox</strong>
              <small><?= number_format($globalSubs['pending']) ?> left to review</small>
            </span>
          </a>
          <a href="/admin/reviewer-submissions" class="md-quick-link">
            <span class="md-quick-link-icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
            </span>
            <span class="md-quick-link-text">
              <strong>All submissions</strong>
              <small><?= number_format($globalSubs['all']) ?> total responses</small>
            </span>
          </a>
          <?php if ($isAdmin): ?>
            <a href="/admin/clients" class="md-quick-link">
              <span class="md-quick-link-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
              </span>
              <span class="md-quick-link-text">
                <strong>Clients</strong>
                <small><?= number_format($clientEmailCount) ?> emailable contacts</small>
              </span>
            </a>
            <a href="/admin/campaigns" class="md-quick-link">
              <span class="md-quick-link-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>
              </span>
              <span class="md-quick-link-text">
                <strong>Campaigns</strong>
                <small><?= (int) $campaignQueue['sent'] ?> sent · <?= (int) $campaignQueue['draft'] ?> draft</small>
              </span>
            </a>
          <?php endif; ?>
        </nav>
      </div>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
