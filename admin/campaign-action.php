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

if (!$campaign) {
    $_SESSION['flash_error'] = 'Campaign not found.';
    header('Location: /admin/campaigns');
    exit;
}

$redirect = '/admin/campaign-view?id=' . $campaignId;

if ($action === 'send_test') {
    $name = trim((string) ($_POST['name'] ?? $campaign['name'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? $campaign['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? $campaign['body_html'] ?? ''));
    if ($subject === '' || $body === '') {
        $_SESSION['campaign_edit_errors'] = ['Subject and message are required to send a test.'];
        header('Location: /admin/campaign-edit?id=' . $campaignId);
        exit;
    }

    $user = Auth::currentUser();
    $testEmail = trim((string) ($user['email'] ?? ''));
    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $mailCfg = mail_config();
        $testEmail = $mailCfg['admin_emails'][0] ?? trim((string) ($mailCfg['admin_email'] ?? ''));
    }
    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['campaign_edit_errors'] = ['No email address available for test sends. Add your email in Team or Email settings.'];
        header('Location: /admin/campaign-edit?id=' . $campaignId);
        exit;
    }

    $mailer = Mailer::fromAppConfig();
    if ($mailer === null) {
        $_SESSION['campaign_edit_errors'] = ['Mail is disabled or not configured.'];
        header('Location: /admin/campaign-edit?id=' . $campaignId);
        exit;
    }

    $client = [
        'client_name' => (string) ($user['name'] ?? 'Admin'),
        'email' => $testEmail,
        'cin' => 'SAMPLE-CIN',
        'company' => 'Sample Company',
    ];
    [$emailSubject, $html, $text] = build_campaign_email($subject, $body, $client);
    $emailSubject = '[TEST] ' . $emailSubject;

    if ($mailer->send($testEmail, $emailSubject, $html, $text)) {
        $_SESSION['flash_success'] = 'Test email sent to ' . $testEmail . '.';
    } else {
        $_SESSION['campaign_edit_errors'] = [$mailer->getLastError() ?: 'Test send failed.'];
        header('Location: /admin/campaign-edit?id=' . $campaignId);
        exit;
    }
    header('Location: /admin/campaign-edit?id=' . $campaignId);
    exit;
}

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
    if ($result['processed'] === 0 && !$result['done']) {
        $_SESSION['flash_error'] = 'No pending recipients to process.';
    } else {
        $_SESSION['flash_success'] = 'Processed ' . $result['processed'] . ' email(s): '
            . $result['sent'] . ' sent, ' . $result['failed'] . ' failed.'
            . ($result['done'] ? ' Campaign complete.' : '');
    }
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    if (($campaign['status'] ?? '') !== 'draft') {
        $_SESSION['flash_error'] = 'Only draft campaigns can be deleted.';
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
