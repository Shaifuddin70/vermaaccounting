<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$total = email_tracking_count();
$pagination = pagination_meta($total, $page, $perPage);
$rows = email_tracking_recent($pagination['per_page'], $pagination['offset']);
$stats = email_tracking_overall_stats();

$paginationUrl = fn (int $p) => pagination_url('/admin/email-opens', [], $p, $pagination['per_page']);
$paginationPath = '/admin/email-opens';
$paginationQuery = [];
$paginationLabel = 'tracked emails';
$paginationAriaLabel = 'Email opens pages';

$pageTitle = 'Email opens';
$activeNav = 'email-opens';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Email opens</h1>
  <div class="admin-header-actions">
    <a href="/admin/campaigns" class="admin-btn admin-btn-secondary">Campaigns</a>
  </div>
</div>

<div class="admin-alert admin-alert-info">
  Opens are recorded when a recipient’s email app loads images (tracking pixel).
  Some apps preload images or block them, so this is a useful signal — not proof every open was a careful read.
</div>

<div class="admin-card" style="margin-bottom:1rem;">
  <div class="campaign-meta-grid">
    <div>
      <span class="submission-meta-label">Tracked sends</span>
      <strong><?= number_format($stats['tracked']) ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Opened</span>
      <strong><?= number_format($stats['opened']) ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Open rate</span>
      <strong><?= e((string) $stats['open_rate']) ?>%</strong>
    </div>
  </div>
</div>

<div class="admin-card">
  <h2 class="admin-card-title">Recent sends</h2>
  <?php if ($rows === []): ?>
    <p class="admin-field-hint" style="margin:0;">No tracked emails yet. Opens appear after new emails are sent and opened.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Sent</th>
            <th>Type</th>
            <th>To</th>
            <th>Subject</th>
            <th>Opened</th>
            <th>Opens</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
              $openedAt = (string) ($row['opened_at'] ?? '');
              $campaignId = (int) ($row['campaign_id'] ?? 0);
            ?>
            <tr>
              <td><?= e(campaign_format_datetime((string) ($row['sent_at'] ?? ''))) ?></td>
              <td>
                <?= e(email_tracking_kind_label((string) ($row['kind'] ?? 'email'))) ?>
                <?php if ($campaignId > 0): ?>
                  <br><a href="/admin/campaign-view?id=<?= $campaignId ?>">Campaign #<?= $campaignId ?></a>
                <?php endif; ?>
              </td>
              <td><?= e((string) ($row['to_email'] ?? '')) ?></td>
              <td><?= e((string) ($row['subject'] ?? '')) ?></td>
              <td>
                <?php if ($openedAt !== ''): ?>
                  <span class="campaign-status-badge campaign-status-badge--sent">Yes</span>
                  <div class="admin-field-hint" style="margin:0.25rem 0 0;"><?= e(campaign_format_datetime($openedAt)) ?></div>
                <?php else: ?>
                  <span class="admin-field-hint">Not yet</span>
                <?php endif; ?>
              </td>
              <td><?= number_format((int) ($row['open_count'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
