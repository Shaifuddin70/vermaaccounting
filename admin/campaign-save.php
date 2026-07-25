<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/campaigns');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    header('Location: /admin/campaigns');
    exit;
}

$editId = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$name = trim((string) ($_POST['name'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = campaign_email_normalize_year_token(trim((string) ($_POST['body'] ?? '')));
$sendAction = (string) ($_POST['send_action'] ?? 'draft');
$scheduledRaw = trim((string) ($_POST['scheduled_at'] ?? ''));

$errors = [];
if ($name === '') {
    $errors[] = 'Campaign name is required.';
}
if ($subject === '') {
    $errors[] = 'Subject is required.';
}
if ($body === '') {
    $errors[] = 'Message body is required.';
}
if (!in_array($sendAction, ['draft', 'now', 'schedule'], true)) {
    $sendAction = 'draft';
}

$campaignRepo = new EmailCampaignRepository();
$clientRepo = new ClientRepository();
$existing = $editId ? $campaignRepo->find($editId) : null;

if ($editId && (!$existing || !campaign_is_editable($existing))) {
    $_SESSION['flash_error'] = 'This campaign cannot be edited.';
    header('Location: /admin/campaigns');
    exit;
}

$scheduledAt = null;
if ($sendAction === 'schedule') {
    $scheduledAt = campaign_parse_scheduled_at($scheduledRaw);
    if ($scheduledAt === null) {
        $errors[] = 'Choose a valid schedule date and time.';
    } elseif ($scheduledAt <= now_iso()) {
        $unchanged = $existing
            && ($existing['status'] ?? '') === 'scheduled'
            && (string) ($existing['scheduled_at'] ?? '') === $scheduledAt;
        if (!$unchanged) {
            $errors[] = 'Schedule time must be in the future.';
        }
    }
}

if ($sendAction !== 'draft') {
    $recipientCount = $clientRepo->countWithEmail();
    if ($recipientCount === 0) {
        $errors[] = 'No clients with email addresses found. Add client emails before sending.';
    }
}

if ($errors) {
    $_SESSION['campaign_edit_errors'] = $errors;
    $_SESSION['campaign_edit_old'] = compact('name', 'subject', 'body', 'sendAction', 'scheduledRaw') + [
        'scheduled_at' => $scheduledRaw,
        'send_action' => $sendAction,
    ];
    $back = $editId ? '/admin/campaign-edit?id=' . $editId : '/admin/campaign-edit';
    header('Location: ' . $back);
    exit;
}

$user = Auth::currentUser();
$status = 'draft';
if ($sendAction === 'now') {
    $status = 'sending';
    $scheduledAt = now_iso();
} elseif ($sendAction === 'schedule') {
    $status = 'scheduled';
}

$data = [
    'name' => $name,
    'subject' => $subject,
    'body_html' => $body,
    'status' => $status,
    'scheduled_at' => $sendAction === 'draft' ? null : $scheduledAt,
    'created_by_user_id' => $user['id'] ?? null,
    'created_by_name' => (string) ($user['name'] ?? 'Admin'),
];

if ($editId) {
    $campaignRepo->update($editId, $data);
    $campaignId = $editId;
    ActivityLog::record('campaign.updated', 'campaign', $campaignId, ['name' => $name, 'status' => $status]);
} else {
    $campaignId = $campaignRepo->create($data);
    ActivityLog::record('campaign.created', 'campaign', $campaignId, ['name' => $name, 'status' => $status]);
}

if ($sendAction === 'draft') {
    if ($editId) {
        $campaignRepo->clearRecipients($campaignId);
        $campaignRepo->update($campaignId, [
            'recipient_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }
    $_SESSION['flash_success'] = 'Campaign saved as draft.';
    header('Location: /admin/campaign-edit?id=' . $campaignId);
    exit;
}

$campaignRepo->clearRecipients($campaignId);
$recipients = $clientRepo->recipientsForCampaign();
$added = $campaignRepo->addRecipients($campaignId, $recipients);
$campaignRepo->refreshCounts($campaignId);
$campaignRepo->update($campaignId, [
    'recipient_count' => $added,
    'started_at' => $sendAction === 'now' ? now_iso() : null,
    'completed_at' => null,
]);

if ($sendAction === 'now') {
    $batch = process_campaign_batch($campaignId);
    if (!empty($batch['error']) && $batch['error'] === 'mail_disabled') {
        $_SESSION['flash_error'] = 'Campaign queued but mail is not configured. Check Admin → Email settings, then use Retry on the campaign page.';
        header('Location: /admin/campaign-view?id=' . $campaignId);
        exit;
    }
}

if ($sendAction === 'now') {
    $_SESSION['flash_success'] = 'Campaign started. Remaining emails will send automatically.';
} elseif ($editId && ($existing['status'] ?? '') === 'scheduled') {
    $_SESSION['flash_success'] = 'Scheduled campaign updated.';
} else {
    $_SESSION['flash_success'] = 'Campaign scheduled for ' . campaign_format_datetime($scheduledAt) . '.';
}

header('Location: /admin/campaign-view?id=' . $campaignId);
exit;
