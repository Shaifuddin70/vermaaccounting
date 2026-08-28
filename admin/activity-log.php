<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$filterAction  = (string) ($_GET['action'] ?? '');
$filterUser    = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null;
$filterSubject = (string) ($_GET['subject'] ?? '');

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$filterUserId = $filterUser ?: null;
$filterActionVal = $filterAction !== '' ? $filterAction : null;
$filterSubjectVal = $filterSubject !== '' ? $filterSubject : null;
$logTotal = ActivityLog::count($filterUserId, $filterActionVal, $filterSubjectVal);
$pagination = pagination_meta($logTotal, $page, $perPage);
$logs = ActivityLog::recent(
    $pagination['per_page'],
    $filterUserId,
    $filterActionVal,
    $filterSubjectVal,
    null,
    $pagination['offset']
);

$paginationPath = '/admin/activity-log';
$paginationQuery = array_filter([
    'action' => $filterAction !== '' ? $filterAction : null,
    'user_id' => $filterUser !== null && $filterUser > 0 ? $filterUser : null,
    'subject' => $filterSubject !== '' ? $filterSubject : null,
], fn ($v) => $v !== null && $v !== '');
$paginationLabel = 'entries';
$paginationAriaLabel = 'Activity log pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/activity-log', $paginationQuery, $p, $pagination['per_page']);

$userRepo = new UserRepository();
$allUsers = $userRepo->all();

$pageTitle = 'Activity log';
$activeNav = 'activity';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Activity log</h1>
</div>

<div class="admin-card activity-filters">
  <form method="get" class="activity-filter-form">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <div class="admin-field" style="margin:0;">
      <label for="af-action">Action</label>
      <select id="af-action" name="action" onchange="this.form.submit()">
        <option value="">All actions</option>
        <option value="submission.status_changed" <?= $filterAction === 'submission.status_changed' ? 'selected' : '' ?>>Changed status</option>
        <option value="submission.edited"         <?= $filterAction === 'submission.edited'         ? 'selected' : '' ?>>Edited submission</option>
        <option value="file.deleted"              <?= $filterAction === 'file.deleted'              ? 'selected' : '' ?>>Deleted file</option>
        <option value="user.created"              <?= $filterAction === 'user.created'              ? 'selected' : '' ?>>Created user</option>
        <option value="user.updated"              <?= $filterAction === 'user.updated'              ? 'selected' : '' ?>>Updated user</option>
        <option value="user.deactivated"          <?= $filterAction === 'user.deactivated'          ? 'selected' : '' ?>>Deactivated user</option>
        <option value="user.activated"            <?= $filterAction === 'user.activated'            ? 'selected' : '' ?>>Activated user</option>
        <option value="auth.login"                <?= $filterAction === 'auth.login'                ? 'selected' : '' ?>>Sign in</option>
        <option value="auth.logout"               <?= $filterAction === 'auth.logout'               ? 'selected' : '' ?>>Sign out</option>
      </select>
    </div>
    <div class="admin-field" style="margin:0;">
      <label for="af-user">Team member</label>
      <select id="af-user" name="user_id" onchange="this.form.submit()">
        <option value="">All members</option>
        <?php foreach ($allUsers as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $filterUser === (int) $u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="admin-field" style="margin:0;">
      <label for="af-subject">Subject</label>
      <select id="af-subject" name="subject" onchange="this.form.submit()">
        <option value="">All subjects</option>
        <option value="submission" <?= $filterSubject === 'submission' ? 'selected' : '' ?>>Submission</option>
        <option value="user"       <?= $filterSubject === 'user'       ? 'selected' : '' ?>>User</option>
      </select>
    </div>
    <?php if ($filterAction || $filterUser || $filterSubject): ?>
      <a href="/admin/activity-log" class="admin-btn admin-btn-secondary admin-btn-sm activity-filter-clear">Clear filters</a>
    <?php endif; ?>
  </form>
</div>

<div class="admin-card">
  <?php if (!$logs): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
      </span>
      <h2 class="admin-empty-state-title">No activity<?= ($filterAction || $filterUser || $filterSubject) ? ' for these filters' : ' recorded yet' ?></h2>
      <?php if ($filterAction || $filterUser || $filterSubject): ?>
        <p class="admin-empty-state-text">Try removing one or more filters to see more results.</p>
        <a href="/admin/activity-log" class="admin-btn admin-btn-secondary">Clear filters</a>
      <?php else: ?>
        <p class="admin-empty-state-text">Sign-ins, submission updates, and team changes will appear here automatically.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <table class="admin-table activity-table">
      <thead>
        <tr>
          <th>When</th>
          <th>Who</th>
          <th>Action</th>
          <th>Subject</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log):
          $meta = $log['meta_json'] ? json_decode($log['meta_json'], true) : [];
          $isConfigAdmin = ($log['user_id'] === null);
        ?>
          <tr>
            <td class="dashboard-date activity-time">
              <?= e($log['created_at']) ?>
            </td>
            <td>
              <span class="activity-actor">
                <?= e($log['user_name'] ?: 'Admin') ?>
                <?php if ($isConfigAdmin): ?>
                  <span class="role-badge role-badge--admin" style="font-size:0.7rem;">config</span>
                <?php endif; ?>
              </span>
            </td>
            <td>
              <span class="activity-action activity-action--<?= e(str_replace('.', '-', $log['action'])) ?>">
                <?= e(ActivityLog::actionLabel($log['action'])) ?>
              </span>
            </td>
            <td>
              <?php if ($log['subject_type'] === 'submission' && $log['subject_id']): ?>
                <?php
                  $formIdForLink = (int) ($meta['form_id'] ?? 0);
                  $subIdForLink  = (int) $log['subject_id'];
                ?>
                <?php if ($formIdForLink): ?>
                  <a href="/admin/submission?id=<?= $subIdForLink ?>&form_id=<?= $formIdForLink ?>">
                    Submission #<?= $subIdForLink ?>
                  </a>
                <?php else: ?>
                  Submission #<?= $subIdForLink ?>
                <?php endif; ?>
              <?php elseif ($log['subject_type'] === 'user' && $log['subject_id']): ?>
                <a href="/admin/user-edit?id=<?= (int) $log['subject_id'] ?>">User #<?= (int) $log['subject_id'] ?></a>
              <?php elseif ($log['subject_type']): ?>
                <?= e(ucfirst($log['subject_type'])) ?> #<?= (int) $log['subject_id'] ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td class="activity-meta">
              <?php if ($log['action'] === 'submission.status_changed' && $meta): ?>
                <span class="submission-status-badge submission-status-badge--<?= e($meta['from_status'] ?? '') ?>">
                  <?= e(ucfirst($meta['from_status'] ?? '')) ?>
                </span>
                → 
                <span class="submission-status-badge submission-status-badge--<?= e($meta['to_status'] ?? '') ?>">
                  <?= e(ucfirst($meta['to_status'] ?? '')) ?>
                </span>
              <?php elseif ($meta): ?>
                <span class="activity-meta-text">
                  <?php foreach ($meta as $k => $v):
                    if (in_array($k, ['form_id'], true)) continue;
                  ?>
                    <span><?= e($k) ?>: <?= e(is_string($v) ? $v : json_encode($v)) ?></span>
                  <?php endforeach; ?>
                </span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
