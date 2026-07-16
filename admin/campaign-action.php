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
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/campaigns');
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$campaignId = (int) ($_POST['id'] ?? $_POST['campaign_id'] ?? 0);
$campaignRepo = new EmailCampaignRepository();
$campaign = $campaignId > 0 ? $campaignRepo->find($campaignId) : null;

$fromEditForm = array_key_exists('subject', $_POST) || array_key_exists('body', $_POST);
$editRedirect = $campaignId > 0
    ? '/admin/campaign-edit?id=' . $campaignId
    : '/admin/campaign-edit';

if ($action === 'send_test') {
    $subject = trim((string) ($_POST['subject'] ?? $campaign['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? $campaign['body_html'] ?? ''));
    $result = campaign_send_test_email([
        'subject' => $subject,
        'body_html' => $body,
    ]);

    if (!$result['ok']) {
        if ($fromEditForm || !$campaign) {
            $_SESSION['campaign_edit_errors'] = [$result['error'] ?? 'Test send failed.'];
            $_SESSION['campaign_edit_old'] = $_POST;
            header('Location: ' . $editRedirect);
            exit;
        }
        $_SESSION['flash_error'] = $result['error'] ?? 'Test send failed.';
        header('Location: /admin/campaign-view?id=' . $campaignId);
        exit;
    }

    $to = trim((string) ($result['to'] ?? ''));
    $_SESSION['flash_success'] = $to !== ''
        ? 'Test email sent to ' . $to . '.'
        : 'Test email sent.';

    if ($fromEditForm || !$campaign) {
        header('Location: ' . $editRedirect);
        exit;
    }
    header('Location: /admin/campaign-view?id=' . $campaignId);
    exit;
}

if (!$campaign) {
    $_SESSION['flash_error'] = 'Campaign not found.';
    header('Location: /admin/campaigns');
    exit;
}

$redirect = '/admin/campaign-view?id=' . $campaignId;

if ($action === 'cancel') {
    if ($campaignRepo->cancel($campaignId)) {
        ActivityLog::record('campaign.cancelled', 'campaign', $campaignId);
        $_SESSION['flash_success'] = 'Campaign cancelled.';
    } else {
        $_SESSION['flash_error'] = 'This campaign cannot be cancelled.';
    }
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'process_batch') {
    if (!in_array($campaign['status'], ['scheduled', 'sending'], true)) {
        $_SESSION['flash_error'] = 'This campaign is not in the send queue.';
        header('Location: ' . $redirect);
        exit;
    }
    $result = process_campaign_batch($campaignId);
    if (!empty($result['error']) && $result['error'] === 'mail_disabled') {
        $_SESSION['flash_error'] = 'Mail is disabled or not configured. Check Admin → Email settings.';
    } elseif ($result['processed'] === 0 && !$result['done']) {
        $_SESSION['flash_error'] = 'No pending recipients to process.';
    } else {
        $_SESSION['flash_success'] = 'Processed ' . $result['processed'] . ' email(s): '
            . $result['sent'] . ' sent, ' . $result['failed'] . ' failed.'
            . ($result['done'] ? ' Campaign complete.' : '');
    }
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'launch' || $action === 'send_now') {
    $rebuild = $action === 'send_now' || in_array($campaign['status'], ['draft', 'failed'], true);
    $launch = launch_campaign_send($campaignId, $rebuild);
    if (!$launch['ok']) {
        $_SESSION['flash_error'] = $launch['error'] ?? 'Could not start campaign.';
        header('Location: ' . $redirect);
        exit;
    }
    $batch = $launch['batch'] ?? ['processed' => 0, 'sent' => 0, 'failed' => 0, 'done' => false];
    ActivityLog::record('campaign.launched', 'campaign', $campaignId, [
        'sent' => $batch['sent'] ?? 0,
        'failed' => $batch['failed'] ?? 0,
    ]);
    $_SESSION['flash_success'] = 'Campaign started. Processed ' . ($batch['processed'] ?? 0) . ' email(s): '
        . ($batch['sent'] ?? 0) . ' sent, ' . ($batch['failed'] ?? 0) . ' failed.'
        . (!empty($batch['done']) ? ' Campaign complete.' : ' Remaining emails will send automatically.');
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $status = (string) ($campaign['status'] ?? '');
    if (in_array($status, ['sending', 'sent'], true)) {
        $_SESSION['flash_error'] = 'Cannot delete a campaign that is sending or has already been sent.';
        header('Location: ' . $redirect);
        exit;
    }
    $campaignRepo->delete($campaignId);
    ActivityLog::record('campaign.deleted', 'campaign', $campaignId);
    $_SESSION['flash_success'] = 'Campaign deleted.';
    header('Location: /admin/campaigns');
    exit;
}

$_SESSION['flash_error'] = 'Unknown action.';
header('Location: ' . $redirect);
exit;
