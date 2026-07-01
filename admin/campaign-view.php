<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$campaignId = (int) ($_GET['id'] ?? 0);
$campaignRepo = new EmailCampaignRepository();
$campaign = $campaignId > 0 ? $campaignRepo->find($campaignId) : null;

if (!$campaign) {
    header('Location: /admin/campaigns');
    exit;
}

$status = (string) ($campaign['status'] ?? 'draft');
$recipientTotal = (int) ($campaign['recipient_count'] ?? 0);
$sent = (int) ($campaign['sent_count'] ?? 0);
$failed = (int) ($campaign['failed_count'] ?? 0);
$pending = $campaignRepo->countRecipients($campaignId, 'pending');
$mailReady = Mailer::fromAppConfig() !== null;
$clientEmailCount = (new ClientRepository())->countWithEmail();
$scheduledAt = (string) ($campaign['scheduled_at'] ?? '');
$scheduledFuture = $status === 'scheduled' && $scheduledAt !== '' && $scheduledAt > now_iso();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = Auth::csrfToken();
$pageTitle = 'Campaign: ' . ($campaign['name'] ?? '');
$activeNav = 'campaigns';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e((string) $campaign['name']) ?></h1>
  <div class="admin-header-actions">
    <?php if ($status === 'draft'): ?>
      <a href="/admin/campaign-edit?id=<?= $campaignId ?>" class="admin-btn admin-btn-secondary">Edit</a>
    <?php endif; ?>
    <a href="/admin/campaigns" class="admin-btn admin-btn-secondary">← All campaigns</a>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="admin-alert admin-alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="admin-alert admin-alert-error"><?= e($flashError) ?></div>
<?php endif; ?>

<?php if (!$mailReady): ?>
  <div class="admin-alert admin-alert-error">
    Mail is not configured for this environment. Campaigns cannot send until SMTP is set up in
    <a href="/admin/email-settings">Email settings</a>.
  </div>
<?php elseif ($status === 'draft'): ?>
  <div class="admin-alert admin-alert-warning">
    This campaign is a <strong>draft</strong>. The cron job does not send drafts — click <strong>Start sending now</strong> below when you are ready.
  </div>
<?php elseif ($scheduledFuture): ?>
  <div class="admin-alert admin-alert-warning">
    Scheduled for <?= e(campaign_format_datetime($scheduledAt)) ?>. The cron job will start sending at that time, or you can send immediately below.
  </div>
<?php elseif ($status === 'failed'): ?>
  <div class="admin-alert admin-alert-error">
    This campaign could not send<?= $recipientTotal === 0 ? ' — no recipients were queued' : '' ?>.
    Click <strong>Retry sending</strong> below after fixing the issue.
  </div>
<?php elseif ($status === 'sending' && $pending > 0): ?>
  <div class="admin-alert admin-alert-success">
  Sending in progress — <?= number_format($pending) ?> email<?= $pending === 1 ? '' : 's' ?> remaining. Cron runs every few minutes, or use <strong>Process next batch</strong> below.
  </div>
<?php elseif ($clientEmailCount === 0 && in_array($status, ['draft', 'scheduled', 'sending', 'failed'], true)): ?>
  <div class="admin-alert admin-alert-error">
    No clients have email addresses. <a href="/admin/clients">Add client emails</a> before sending.
  </div>
<?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <div class="campaign-meta-grid">
      <div>
        <span class="submission-meta-label">Status</span>
        <span class="campaign-status-badge campaign-status-badge--<?= e($status) ?>">
          <?= e(campaign_status_label($status)) ?>
        </span>
      </div>
      <div>
        <span class="submission-meta-label">Recipients</span>
        <strong><?= number_format($recipientTotal) ?></strong>
      </div>
      <div>
        <span class="submission-meta-label">Sent</span>
        <strong><?= number_format($sent) ?></strong>
      </div>
      <div>
        <span class="submission-meta-label">Failed</span>
        <strong><?= number_format($failed) ?></strong>
      </div>
      <div>
        <span class="submission-meta-label">Pending</span>
        <strong><?= number_format($pending) ?></strong>
      </div>
      <?php if (!empty($campaign['scheduled_at'])): ?>
        <div>
          <span class="submission-meta-label">Scheduled</span>
          <strong><?= e(campaign_format_datetime((string) $campaign['scheduled_at'])) ?></strong>
        </div>
      <?php endif; ?>
      <?php if (!empty($campaign['completed_at'])): ?>
        <div>
          <span class="submission-meta-label">Completed</span>
          <strong><?= e((string) $campaign['completed_at']) ?> UTC</strong>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($recipientTotal > 0 && in_array($status, ['scheduled', 'sending', 'sent'], true)): ?>
      <div class="campaign-progress-wrap">
        <?php
          $doneCount = $sent + $failed;
          $pct = $recipientTotal > 0 ? min(100, (int) round(($doneCount / $recipientTotal) * 100)) : 0;
        ?>
        <div class="campaign-progress-bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="campaign-progress-fill" style="width:<?= $pct ?>%;"></div>
        </div>
        <p class="admin-field-hint" style="margin:0.5rem 0 0;"><?= $pct ?>% complete</p>
      </div>
    <?php endif; ?>

    <div class="admin-form-actions" style="margin-top:1.25rem;">
      <?php if (in_array($status, ['draft', 'failed'], true) && $clientEmailCount > 0): ?>
        <form method="post" action="/admin/campaign-action" class="inline-form"
          onsubmit="return confirm('Start sending this campaign to all clients with email addresses?');">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <input type="hidden" name="action" value="launch">
          <button type="submit" class="admin-btn admin-btn-primary" <?= $mailReady ? '' : 'disabled' ?>>
            <?= $status === 'failed' ? 'Retry sending' : 'Start sending now' ?>
          </button>
        </form>
      <?php endif; ?>
      <?php if ($scheduledFuture && $clientEmailCount > 0): ?>
        <form method="post" action="/admin/campaign-action" class="inline-form"
          onsubmit="return confirm('Send this campaign now instead of waiting for the scheduled time?');">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <input type="hidden" name="action" value="send_now">
          <button type="submit" class="admin-btn admin-btn-primary" <?= $mailReady ? '' : 'disabled' ?>>Send now</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($status, ['scheduled', 'sending'], true)): ?>
        <form method="post" action="/admin/campaign-action" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <input type="hidden" name="action" value="process_batch">
          <button type="submit" class="admin-btn admin-btn-primary">Process next batch (<?= campaign_batch_size() ?>)</button>
        </form>
        <form method="post" action="/admin/campaign-action" class="inline-form"
          onsubmit="return confirm('Cancel this campaign? Pending emails will not be sent.');">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <input type="hidden" name="action" value="cancel">
          <button type="submit" class="admin-btn admin-btn-secondary">Cancel campaign</button>
        </form>
      <?php endif; ?>
      <?php if ($status === 'draft'): ?>
        <form method="post" action="/admin/campaign-action" class="inline-form"
          onsubmit="return confirm('Delete this draft campaign?');">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="admin-btn admin-btn-secondary">Delete draft</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title">Email preview</h2>
    <p><strong>Subject:</strong> <?= e((string) $campaign['subject']) ?></p>
    <div class="campaign-body-preview"><?= nl2br(e((string) $campaign['body_html'])) ?></div>
    <p class="admin-field-hint" style="margin-top:1rem;">
      Created by <?= e((string) ($campaign['created_by_name'] ?? 'Admin')) ?>
      on <?= e((string) $campaign['created_at']) ?> UTC
    </p>
  </div>
</div>

<?php if ($failed > 0): ?>
  <div class="admin-card" style="margin-top:1rem;">
    <h2 class="admin-card-title">Failed deliveries</h2>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Email</th>
          <th>Error</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaignRepo->recipients($campaignId, 50, 0, 'failed') as $row): ?>
          <tr>
            <td><?= e((string) $row['client_name']) ?></td>
            <td><?= e((string) $row['email']) ?></td>
            <td><?= e((string) ($row['error_message'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
