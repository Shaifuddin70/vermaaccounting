<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$campaignRepo = new EmailCampaignRepository();
$clientRepo = new ClientRepository();

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$total = $campaignRepo->count();
$pagination = pagination_meta($total, $page, $perPage);
$campaigns = $campaignRepo->all($pagination['per_page'], $pagination['offset']);
$recipientCount = $clientRepo->countWithEmail();

$paginationPath = '/admin/campaigns';
$paginationQuery = [];
$paginationLabel = 'campaigns';
$paginationAriaLabel = 'Campaign list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/campaigns', [], $p, $pagination['per_page']);

$csrf = Auth::csrfToken();
$pageTitle = 'Email campaigns';
$activeNav = 'campaigns';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Email campaigns</h1>
  <div class="admin-header-actions">
    <a href="/admin/campaign-edit" class="admin-btn admin-btn-primary">+ New campaign</a>
  </div>
</div>

<div class="admin-card campaign-summary-card">
  <p class="admin-field-hint" style="margin:0;">
    Send events, offers, and announcements to all clients with an email on file.
    <strong><?= number_format($recipientCount) ?></strong> unique client email<?= $recipientCount === 1 ? '' : 's' ?> available.
  </p>
</div>

<?php if ($campaigns === []): ?>
  <div class="admin-card admin-empty-state">
    <h2 class="admin-empty-state-title">No campaigns yet</h2>
    <p class="admin-empty-state-text">Create a campaign to schedule a bulk email to your clients.</p>
    <a href="/admin/campaign-edit" class="admin-btn admin-btn-primary">Create campaign</a>
  </div>
<?php else: ?>
  <div class="admin-card campaigns-list-card">
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <div class="admin-table-wrap">
      <table class="admin-table campaigns-table">
        <colgroup>
          <col class="campaigns-col-name">
          <col class="campaigns-col-status">
          <col class="campaigns-col-recipients">
          <col class="campaigns-col-scheduled">
          <col class="campaigns-col-progress">
          <col class="campaigns-col-actions">
        </colgroup>
        <thead>
          <tr>
            <th>Campaign</th>
            <th>Status</th>
            <th>Recipients</th>
            <th>Scheduled</th>
            <th>Progress</th>
            <th class="campaigns-th-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($campaigns as $campaign):
            $id = (int) $campaign['id'];
            $status = (string) ($campaign['status'] ?? 'draft');
            $recipientTotal = (int) ($campaign['recipient_count'] ?? 0);
            $sent = (int) ($campaign['sent_count'] ?? 0);
            $failed = (int) ($campaign['failed_count'] ?? 0);
            $canEdit = campaign_is_editable($campaign);
            $canDelete = !in_array($status, ['sending', 'sent'], true);
            $schedUtc = (string) ($campaign['scheduled_at'] ?? '');
            $schedFuture = $status === 'scheduled' && $schedUtc !== '' && $schedUtc > now_iso();
          ?>
            <tr>
              <td class="campaigns-cell-name">
                <a href="/admin/campaign-view?id=<?= $id ?>" class="campaigns-name-link">
                  <strong><?= e((string) $campaign['name']) ?></strong>
                </a>
                <div class="admin-table-sub"><?= e((string) $campaign['subject']) ?></div>
              </td>
              <td>
                <span class="campaign-status-badge campaign-status-badge--<?= e($status) ?>">
                  <?= e(campaign_status_label($status)) ?>
                </span>
              </td>
              <td class="campaigns-cell-num"><?= $recipientTotal > 0 ? number_format($recipientTotal) : '—' ?></td>
              <td class="campaigns-cell-scheduled">
                <?php if ($schedUtc !== ''): ?>
                  <span class="campaigns-scheduled-time"><?= e(campaign_format_datetime($schedUtc, false)) ?></span>
                  <span class="admin-table-sub"><?= e(app_timezone_label()) ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="campaigns-cell-progress">
                <?php if ($recipientTotal > 0 && in_array($status, ['sending', 'sent', 'failed'], true)): ?>
                  <?php
                    $doneCount = $sent + $failed;
                    $pct = min(100, (int) round(($doneCount / $recipientTotal) * 100));
                  ?>
                  <span class="campaigns-progress-text"><?= number_format($sent) ?> sent<?php if ($failed > 0): ?>, <?= number_format($failed) ?> failed<?php endif; ?></span>
                  <?php if ($status === 'sending'): ?>
                    <span class="admin-table-sub"><?= $pct ?>% complete</span>
                  <?php endif; ?>
                <?php elseif ($schedFuture): ?>
                  <span class="admin-table-sub">Waiting to send</span>
                <?php elseif ($status === 'draft'): ?>
                  <span class="admin-table-sub">Not scheduled</span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="campaigns-cell-actions">
                <div class="campaigns-actions-row">
                  <a href="/admin/campaign-view?id=<?= $id ?>" class="admin-btn admin-btn-secondary admin-btn-sm">View</a>
                  <?php if ($canEdit): ?>
                    <a href="/admin/campaign-edit?id=<?= $id ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Edit</a>
                  <?php endif; ?>
                  <?php
                    $showSendNow = $schedFuture;
                    $showDelete = $canDelete;
                    $useMoreMenu = $showSendNow && $showDelete;
                  ?>
                  <?php if ($showSendNow && !$useMoreMenu): ?>
                    <form method="post" action="/admin/campaign-action" class="campaigns-action-form">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="campaign_id" value="<?= $id ?>">
                      <input type="hidden" name="action" value="send_now">
                      <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Send</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($showDelete && !$useMoreMenu): ?>
                    <form method="post" action="/admin/campaign-action" class="campaigns-action-form"
                      onsubmit="return confirm('Delete this campaign? This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="campaign_id" value="<?= $id ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm campaigns-btn-delete">Delete</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($useMoreMenu): ?>
                    <details class="campaigns-more-menu">
                      <summary class="admin-btn admin-btn-secondary admin-btn-sm campaigns-more-trigger" aria-label="More actions">More</summary>
                      <div class="campaigns-more-panel">
                        <form method="post" action="/admin/campaign-action" class="campaigns-action-form">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="campaign_id" value="<?= $id ?>">
                          <input type="hidden" name="action" value="send_now">
                          <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm campaigns-more-btn">Send now</button>
                        </form>
                        <form method="post" action="/admin/campaign-action" class="campaigns-action-form"
                          onsubmit="return confirm('Delete this campaign? This cannot be undone.');">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="campaign_id" value="<?= $id ?>">
                          <input type="hidden" name="action" value="delete">
                          <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm campaigns-more-btn campaigns-btn-delete">Delete</button>
                        </form>
                      </div>
                    </details>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
