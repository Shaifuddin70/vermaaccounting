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
$openStats = email_tracking_stats_for_campaign($campaignId);
$openedRows = email_tracking_opens_for_campaign($campaignId, 100);
$mailReady = Mailer::fromAppConfig() !== null;
$clientEmailCount = (new ClientRepository())->countWithEmail();
$scheduledAt = (string) ($campaign['scheduled_at'] ?? '');
$scheduledFuture = $status === 'scheduled' && $scheduledAt !== '' && $scheduledAt > now_iso();

$csrf = Auth::csrfToken();
$pageTitle = 'Campaign: ' . ($campaign['name'] ?? '');
$activeNav = 'campaigns';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e((string) $campaign['name']) ?></h1>
  <div class="admin-header-actions">
    <?php if (campaign_is_editable($campaign)): ?>
      <a href="/admin/campaign-edit?id=<?= $campaignId ?>" class="admin-btn admin-btn-secondary">Edit</a>
    <?php endif; ?>
    <a href="/admin/campaigns" class="admin-btn admin-btn-secondary">← All campaigns</a>
  </div>
</div>

<?php if (!$mailReady): ?>
  <div class="admin-alert admin-alert-error">
    Email is not set up yet. Check <a href="/admin/email-settings">Email settings</a> or contact your administrator.
  </div>
<?php elseif ($status === 'draft'): ?>
  <div class="admin-alert admin-alert-warning">
    This campaign is a <strong>draft</strong>. Click <strong>Start sending now</strong> below when you are ready.
  </div>
<?php elseif ($scheduledFuture): ?>
  <div class="admin-alert admin-alert-warning">
    Scheduled for <?= e(campaign_format_datetime($scheduledAt)) ?>. Emails will send automatically at that time, or you can send immediately below.
  </div>
<?php elseif ($status === 'failed'): ?>
  <div class="admin-alert admin-alert-error">
    This campaign could not send<?= $recipientTotal === 0 ? ' — no recipients were queued' : '' ?>.
    Click <strong>Retry sending</strong> below.
  </div>
<?php elseif ($status === 'sending' && $pending > 0): ?>
  <div class="admin-alert admin-alert-success">
    Sending in progress — <?= number_format($pending) ?> email<?= $pending === 1 ? '' : 's' ?> remaining.
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
        <span class="submission-meta-label">Opened</span>
        <strong><?= number_format($openStats['opened']) ?></strong>
        <?php if ($openStats['tracked'] > 0): ?>
          <div class="admin-field-hint" style="margin:0.2rem 0 0;"><?= e((string) $openStats['open_rate']) ?>% of tracked</div>
        <?php endif; ?>
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
          <strong><?= e(campaign_format_datetime((string) $campaign['completed_at'])) ?></strong>
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
      <?php if (in_array($status, ['draft', 'scheduled', 'failed'], true)): ?>
        <form method="post" action="/admin/campaign-action" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <input type="hidden" name="action" value="send_test">
          <button type="submit" class="admin-btn admin-btn-secondary" <?= $mailReady ? '' : 'disabled' ?>
            title="Sends a preview to the first admin email in Email settings">Send test email</button>
        </form>
      <?php endif; ?>
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
    <?php
    $sampleClient = campaign_sample_client();
    [$previewSubject, $previewHtml] = build_campaign_email(
        (string) ($campaign['subject'] ?? ''),
        (string) ($campaign['body_html'] ?? ''),
        $sampleClient
    );
    ?>
    <p class="admin-field-hint" style="margin-top:0;">
      Shown with sample tokens filled in (as clients will receive it).
      Use <strong>Send test email</strong> to receive a copy at your admin address.
    </p>
    <p><strong>Subject:</strong> <?= e($previewSubject) ?></p>
    <iframe
      class="campaign-email-preview-frame"
      title="Campaign email preview"
      sandbox=""
      srcdoc="<?= e($previewHtml) ?>"
    ></iframe>
    <p class="admin-field-hint" style="margin-top:1rem;">
      Created by <?= e((string) ($campaign['created_by_name'] ?? 'Admin')) ?>
      on <?= e(campaign_format_datetime((string) $campaign['created_at'])) ?>
    </p>
  </div>
</div>

<?php if ($openStats['opened'] > 0): ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Opened by</h2>
    <p class="admin-field-hint" style="margin-top:0;">
      Based on the tracking pixel loaded by the recipient’s email app.
    </p>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>First opened</th>
            <th>Last opened</th>
            <th>Opens</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($openedRows as $row): ?>
            <tr>
              <td><?= e((string) ($row['to_email'] ?? '')) ?></td>
              <td><?= e(campaign_format_datetime((string) ($row['opened_at'] ?? ''))) ?></td>
              <td><?= e(campaign_format_datetime((string) ($row['last_opened_at'] ?? $row['opened_at'] ?? ''))) ?></td>
              <td><?= number_format((int) ($row['open_count'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($failed > 0): ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Failed deliveries</h2>
    <div class="admin-table-wrap">
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
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
