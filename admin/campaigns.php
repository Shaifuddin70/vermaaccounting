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

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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

<?php if ($flashSuccess): ?>
  <div class="admin-alert admin-alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="admin-alert admin-alert-error"><?= e($flashError) ?></div>
<?php endif; ?>

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
  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Campaign</th>
          <th>Status</th>
          <th>Recipients</th>
          <th>Scheduled</th>
          <th>Progress</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaigns as $campaign):
          $id = (int) $campaign['id'];
          $status = (string) ($campaign['status'] ?? 'draft');
          $recipientTotal = (int) ($campaign['recipient_count'] ?? 0);
          $sent = (int) ($campaign['sent_count'] ?? 0);
          $failed = (int) ($campaign['failed_count'] ?? 0);
        ?>
          <tr>
            <td>
              <strong><?= e((string) $campaign['name']) ?></strong>
              <div class="admin-table-sub"><?= e((string) $campaign['subject']) ?></div>
            </td>
            <td>
              <span class="campaign-status-badge campaign-status-badge--<?= e($status) ?>">
                <?= e(campaign_status_label($status)) ?>
              </span>
            </td>
            <td><?= $recipientTotal > 0 ? number_format($recipientTotal) : '—' ?></td>
            <td>
              <?php if (!empty($campaign['scheduled_at'])): ?>
                <?php
                  $schedUtc = (string) $campaign['scheduled_at'];
                  $schedFuture = $status === 'scheduled' && $schedUtc > now_iso();
                ?>
                <?= e(campaign_format_datetime($schedUtc)) ?>
                <?php if ($schedFuture): ?>
                  <div class="admin-table-sub">Waiting — <a href="/admin/campaign-view?id=<?= $id ?>">send now</a></div>
                <?php endif; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <?php if ($recipientTotal > 0 && in_array($status, ['sending', 'sent', 'cancelled', 'failed'], true)): ?>
                <?= number_format($sent) ?> sent<?php if ($failed > 0): ?>, <?= number_format($failed) ?> failed<?php endif; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td class="admin-table-actions">
              <a href="/admin/campaign-view?id=<?= $id ?>" class="admin-btn admin-btn-secondary admin-btn-sm">View</a>
              <?php if ($status === 'draft'): ?>
                <a href="/admin/campaign-edit?id=<?= $id ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Edit</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php require __DIR__ . '/includes/pagination.php'; ?>
  </div>
<?php endif; ?>

<div class="admin-card" style="margin-top:1rem;">
  <h2 class="admin-card-title">Automatic sending</h2>
  <p class="admin-field-hint" style="margin-top:0;">
    Scheduled campaigns are sent in batches. Add this cron job on your server (every 5 minutes):
  </p>
  <pre class="campaign-cron-snippet">*/5 * * * * VERMA_ENV=production /usr/local/bin/php <?= e(PROJECT_ROOT) ?>/scripts/send-campaign-batch.php >> ~/campaign-cron.log 2>&1</pre>
  <p class="admin-field-hint">
    On shared hosting (Namecheap/cPanel), use the full PHP path from Cron Jobs and
    <code>VERMA_ENV=production</code> so the script uses your live database, not local MAMP settings.
  </p>
  <p class="admin-field-hint">You can also process the queue manually from a campaign’s detail page.</p>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
