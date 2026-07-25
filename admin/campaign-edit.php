<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$campaignRepo = new EmailCampaignRepository();
$clientRepo = new ClientRepository();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$campaign = $editId ? $campaignRepo->find($editId) : null;

if ($editId && !$campaign) {
    header('Location: /admin/campaigns');
    exit;
}

if ($campaign && !campaign_is_editable($campaign)) {
    header('Location: /admin/campaign-view?id=' . $editId);
    exit;
}

$campaignStatus = (string) ($campaign['status'] ?? '');
$errors = $_SESSION['campaign_edit_errors'] ?? [];
$old = $_SESSION['campaign_edit_old'] ?? [];
unset($_SESSION['campaign_edit_errors'], $_SESSION['campaign_edit_old']);

$name = (string) ($old['name'] ?? $campaign['name'] ?? '');
$subject = (string) ($old['subject'] ?? $campaign['subject'] ?? '');
$body = (string) ($old['body'] ?? $campaign['body_html'] ?? '');
$sendAction = (string) ($old['send_action'] ?? '');
if ($sendAction === '') {
    $sendAction = $campaignStatus === 'scheduled' ? 'schedule' : 'draft';
}
$scheduledAt = (string) ($old['scheduled_at'] ?? '');
if ($scheduledAt === '' && !empty($campaign['scheduled_at'])) {
    $scheduledAt = campaign_datetime_local_value((string) $campaign['scheduled_at']);
}

$recipientCount = $clientRepo->countWithEmail();
$csrf = Auth::csrfToken();
$loadEmailEditor = true;
$pageTitle = $campaign ? 'Edit campaign' : 'New campaign';
$activeNav = 'campaigns';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $campaign ? 'Edit campaign' : 'New campaign' ?></h1>
  <a href="/admin/campaigns" class="admin-btn admin-btn-secondary">← All campaigns</a>
</div>

<?php if ($campaignStatus === 'scheduled'): ?>
  <div class="admin-alert admin-alert-info">
    Scheduled for <?= e(campaign_format_datetime((string) ($campaign['scheduled_at'] ?? ''))) ?>.
    Update the content or send time below, or choose <strong>Send now</strong> to send immediately.
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="admin-alert admin-alert-error">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <form method="post" action="/admin/campaign-save" id="campaign-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <?php if ($campaign): ?>
        <input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>">
      <?php endif; ?>

      <div class="admin-field">
        <label for="campaign-name">Campaign name <span class="required">*</span></label>
        <input type="text" id="campaign-name" name="name" required value="<?= e($name) ?>"
          placeholder="e.g. Spring tax filing reminder">
        <small class="admin-field-hint">Internal label — clients do not see this.</small>
      </div>

      <div class="admin-field">
        <label for="campaign-subject">Email subject <span class="required">*</span></label>
        <input type="text" id="campaign-subject" name="subject" required value="<?= e($subject) ?>"
          placeholder="e.g. Special offer for Verma Accounting clients">
        <small class="admin-field-hint">Tokens: {client_name}, {client_email}, {sin}, {company}</small>
      </div>

      <?php
      $emailEditorId = 'campaign-body';
      $emailEditorValue = $body;
      $emailEditorPlaceholder = 'Write your HTML email…';
      include __DIR__ . '/includes/email-editor.php';
      ?>

      <fieldset class="admin-field campaign-send-options">
        <legend>When to send</legend>
        <label class="admin-checkbox-label">
          <input type="radio" name="send_action" value="draft" <?= $sendAction === 'draft' ? 'checked' : '' ?>>
          <span>Save as draft</span>
        </label>
        <label class="admin-checkbox-label">
          <input type="radio" name="send_action" value="now" <?= $sendAction === 'now' ? 'checked' : '' ?>>
          <span>Send to all clients now</span>
        </label>
        <label class="admin-checkbox-label">
          <input type="radio" name="send_action" value="schedule" <?= $sendAction === 'schedule' ? 'checked' : '' ?>>
          <span>Schedule for later</span>
        </label>
        <div class="campaign-schedule-wrap" id="campaign-schedule-wrap" <?= $sendAction === 'schedule' ? '' : 'hidden' ?>>
          <label for="campaign-scheduled-at">Send date &amp; time (<?= e(campaign_timezone_label()) ?>)</label>
          <input type="datetime-local" id="campaign-scheduled-at" name="scheduled_at" value="<?= e($scheduledAt) ?>">
        </div>
      </fieldset>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">
          <?= $campaignStatus === 'scheduled' ? 'Save changes' : 'Save campaign' ?>
        </button>
        <button type="submit" formaction="/admin/campaign-action" name="action" value="send_test" class="admin-btn admin-btn-secondary"
          formnovalidate title="Sends a preview to the first admin email in Email settings">Send test email</button>
      </div>
    </form>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title">Audience</h2>
    <p style="margin:0 0 1rem;">
      This campaign will go to <strong><?= number_format($recipientCount) ?></strong> unique client email<?= $recipientCount === 1 ? '' : 's' ?>.
    </p>
    <p class="admin-field-hint" style="margin:0;">
      Only clients with an email address in the Clients list are included. Duplicate emails are sent once.
    </p>
    <p class="admin-field-hint" style="margin-top:1rem;">
      <a href="/admin/clients">Manage clients →</a>
    </p>

    <h2 class="admin-card-title" style="margin-top:1.5rem;">Tips</h2>
    <ul class="admin-field-hint" style="margin:0;padding-left:1.25rem;">
      <li>Use {client_name} to personalize the greeting.</li>
      <li>Send a test email (goes to the admin address in Email settings) before scheduling.</li>
      <li>Large lists are sent in batches of <?= campaign_batch_size() ?> per run.</li>
      <li>Schedule times use <?= e(app_timezone_label()) ?> — change in <a href="/admin/email-settings">Email settings</a>.</li>
    </ul>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('campaign-form');
  const scheduleWrap = document.getElementById('campaign-schedule-wrap');
  form.querySelectorAll('input[name="send_action"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      scheduleWrap.hidden = radio.value !== 'schedule' || !radio.checked;
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
