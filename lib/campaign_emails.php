<?php

declare(strict_types=1);

function campaign_batch_size(): int
{
    return 25;
}

function campaign_schedule_timezone(): DateTimeZone
{
    return app_timezone();
}

function campaign_timezone_label(): string
{
    return app_timezone_label();
}

function campaign_parse_scheduled_at(string $raw): ?string
{
    return app_parse_local_datetime($raw);
}

function campaign_format_datetime(?string $utc, bool $includeTimezone = true): string
{
    return app_format_datetime($utc, $includeTimezone);
}

/** Value for HTML datetime-local inputs in the app schedule timezone. */
function campaign_datetime_local_value(?string $utc): string
{
    return app_datetime_local_value($utc);
}

function campaign_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
        default => ucfirst($status),
    };
}

/** @param array<string, mixed>|null $campaign */
function campaign_is_editable(?array $campaign): bool
{
    if ($campaign === null) {
        return true;
    }
    return in_array((string) ($campaign['status'] ?? ''), ['draft', 'scheduled'], true);
}

/** @param array<string, mixed> $client */
function campaign_email_replace_tokens(string $text, array $client): string
{
    $replacements = [
        '{client_name}' => (string) ($client['client_name'] ?? $client['name'] ?? ''),
        '{client_email}' => (string) ($client['email'] ?? ''),
        '{sin}' => (string) ($client['sin'] ?? ''),
        '{cin}' => (string) ($client['sin'] ?? ''),
        '{company}' => (string) ($client['company'] ?? ''),
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

function campaign_email_body_html(string $body, array $client): string
{
    $body = campaign_email_replace_tokens($body, $client);
    if (str_contains($body, '<')) {
        return $body;
    }
    return '<p style="margin:0 0 16px;color:#334155;white-space:pre-wrap;">' . nl2br(e($body)) . '</p>';
}

/** @param array<string, mixed> $client @return array{0: string, 1: string, 2: string} */
function build_campaign_email(string $subject, string $body, array $client): array
{
    $subject = campaign_email_replace_tokens($subject, $client);
    $bodyHtml = campaign_email_body_html($body, $client);
    $html = submission_email_layout(
        $subject,
        $bodyHtml,
        'You received this message from Verma Accounting. Reply to this email if you have questions.'
    );

    $plainBody = campaign_email_replace_tokens($body, $client);
    $plainBody = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $plainBody)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = $subject . "\n\n" . trim($plainBody) . "\n\n— Verma Accounting";

    return [$subject, $html, $text];
}

/** Sample recipient used for previews and test sends. @return array{client_name: string, email: string, sin: string, company: string} */
function campaign_sample_client(?array $user = null): array
{
    $user ??= Auth::currentUser() ?? [];
    $mailCfg = mail_config();
    $email = trim((string) (($mailCfg['admin_emails'][0] ?? null) ?: ($mailCfg['admin_email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = trim((string) ($user['email'] ?? ''));
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'client@example.com';
    }

    return [
        'client_name' => (string) (($user['name'] ?? '') !== '' ? $user['name'] : 'Sample Client'),
        'email' => $email,
        'sin' => 'SAMPLE-SIN',
        'company' => 'Sample Company',
    ];
}

/**
 * Send a test campaign email to the admin notification address.
 *
 * @param array{subject?: string, body_html?: string, body?: string} $draft
 * @return array{ok: bool, error?: string, to?: string}
 */
function campaign_send_test_email(array $draft): array
{
    $subject = trim((string) ($draft['subject'] ?? ''));
    $body = trim((string) ($draft['body_html'] ?? $draft['body'] ?? ''));
    if ($subject === '' || $body === '') {
        return ['ok' => false, 'error' => 'Subject and message are required to send a test.'];
    }

    $mailCfg = mail_config();
    $testEmail = trim((string) (($mailCfg['admin_emails'][0] ?? null) ?: ($mailCfg['admin_email'] ?? '')));
    $user = Auth::currentUser() ?? [];
    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $testEmail = trim((string) ($user['email'] ?? ''));
    }
    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'No email address available for test sends. Add an admin email in Email settings.'];
    }

    $mailer = Mailer::fromAppConfig();
    if ($mailer === null) {
        return ['ok' => false, 'error' => 'Mail is disabled or not configured.'];
    }

    $client = campaign_sample_client($user);
    $client['email'] = $testEmail;
    [$emailSubject, $html, $text] = build_campaign_email($subject, $body, $client);
    $emailSubject = '[TEST] ' . $emailSubject;

    if (!$mailer->send($testEmail, $emailSubject, $html, $text)) {
        return ['ok' => false, 'error' => $mailer->getLastError() ?: 'Test send failed.'];
    }

    return ['ok' => true, 'to' => $testEmail];
}

/**
 * Send up to $limit pending emails for a campaign.
 *
 * @return array{processed: int, sent: int, failed: int, done: bool, error?: string}
 */
function process_campaign_batch(int $campaignId, int $limit = 0): array
{
    $limit = $limit > 0 ? min(100, $limit) : campaign_batch_size();
    $repo = new EmailCampaignRepository();
    $campaign = $repo->find($campaignId);
    if (!$campaign) {
      return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'done' => true];
    }

    if (!in_array($campaign['status'], ['scheduled', 'sending'], true)) {
      return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'done' => true];
    }

    $mailer = Mailer::fromAppConfig();
    if ($mailer === null) {
      $repo->update($campaignId, ['status' => 'failed', 'completed_at' => now_iso()]);
      return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'done' => true, 'error' => 'mail_disabled'];
    }

    $pendingTotal = $repo->countRecipients($campaignId, 'pending');
    if ($pendingTotal === 0) {
      $total = $repo->countRecipients($campaignId);
      $status = $total === 0 ? 'failed' : 'sent';
      $repo->update($campaignId, ['status' => $status, 'completed_at' => now_iso()]);
      return [
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
        'done' => true,
        'error' => $total === 0 ? 'no_recipients' : null,
      ];
    }

    if ($campaign['status'] === 'scheduled') {
      $repo->update($campaignId, [
        'status' => 'sending',
        'started_at' => now_iso(),
      ]);
    }

    $recipients = $repo->pendingRecipients($campaignId, $limit);
    $sent = 0;
    $failed = 0;

    foreach ($recipients as $recipient) {
      $client = [
        'client_name' => (string) ($recipient['client_name'] ?? ''),
        'email' => (string) ($recipient['email'] ?? ''),
        'sin' => '',
        'company' => '',
      ];
      if (!empty($recipient['client_id'])) {
        $clientRow = (new ClientRepository())->find((int) $recipient['client_id']);
        if ($clientRow) {
          $client['sin'] = (string) ($clientRow['sin'] ?? '');
          $client['company'] = (string) ($clientRow['company'] ?? '');
          if ($client['client_name'] === '') {
            $client['client_name'] = (string) ($clientRow['name'] ?? '');
          }
        }
      }

      [$subject, $html, $text] = build_campaign_email(
        (string) $campaign['subject'],
        (string) $campaign['body_html'],
        $client
      );

      if ($mailer->send((string) $recipient['email'], $subject, $html, $text)) {
        $repo->markRecipientSent((int) $recipient['id']);
        $sent++;
      } else {
        $repo->markRecipientFailed((int) $recipient['id'], $mailer->getLastError() ?: 'Send failed.');
        $failed++;
      }
    }

    $repo->refreshCounts($campaignId);
    $pendingLeft = $repo->countRecipients($campaignId, 'pending');
    $done = $pendingLeft === 0;
    if ($done) {
      $repo->update($campaignId, [
        'status' => 'sent',
        'completed_at' => now_iso(),
      ]);
    }

    return [
      'processed' => count($recipients),
      'sent' => $sent,
      'failed' => $failed,
      'done' => $done,
    ];
}

/** @return array{processed: int, sent: int, failed: int, campaigns: int} */
function process_due_campaign_batches(int $batchSize = 0): array
{
    $repo = new EmailCampaignRepository();
    $campaigns = $repo->campaignsDueForSending(5);
    $totals = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'campaigns' => 0];

    foreach ($campaigns as $campaign) {
      $result = process_campaign_batch((int) $campaign['id'], $batchSize);
      $totals['processed'] += $result['processed'];
      $totals['sent'] += $result['sent'];
      $totals['failed'] += $result['failed'];
      $totals['campaigns']++;
      if ($result['done']) {
        ActivityLog::record('campaign.completed', 'campaign', (int) $campaign['id'], [
          'sent' => $result['sent'],
          'failed' => $result['failed'],
        ]);
      }
    }

    return $totals;
}

/** @return array{sending: int, scheduled_due: int, scheduled_future: int, draft: int, failed: int, sent: int} */
function campaign_queue_diagnostics(): array
{
    return (new EmailCampaignRepository())->queueDiagnostics();
}

/**
 * Queue a campaign for sending and process the first batch.
 *
 * @return array{ok: bool, error?: string, batch?: array{processed: int, sent: int, failed: int, done: bool}}
 */
function launch_campaign_send(int $campaignId, bool $rebuildRecipients = false): array
{
    $repo = new EmailCampaignRepository();
    $clientRepo = new ClientRepository();
    $campaign = $repo->find($campaignId);
    if (!$campaign) {
        return ['ok' => false, 'error' => 'Campaign not found.'];
    }

    $status = (string) ($campaign['status'] ?? '');
    if (!in_array($status, ['draft', 'scheduled', 'sending', 'failed'], true)) {
        return ['ok' => false, 'error' => 'This campaign cannot be sent.'];
    }

    if ($rebuildRecipients || $repo->countRecipients($campaignId) === 0) {
        $repo->clearRecipients($campaignId);
        $recipients = $clientRepo->recipientsForCampaign();
        if ($recipients === []) {
            return ['ok' => false, 'error' => 'No clients with email addresses.'];
        }
        $added = $repo->addRecipients($campaignId, $recipients);
        $repo->update($campaignId, ['recipient_count' => $added]);
        $repo->refreshCounts($campaignId);
    }

    $repo->update($campaignId, [
        'status' => 'sending',
        'scheduled_at' => now_iso(),
        'started_at' => !empty($campaign['started_at']) ? $campaign['started_at'] : now_iso(),
    ]);

    $batch = process_campaign_batch($campaignId);
    if (!empty($batch['error']) && $batch['error'] === 'mail_disabled') {
        return ['ok' => false, 'error' => 'Mail is disabled or not configured. Check Admin → Email settings.'];
    }

    return ['ok' => true, 'batch' => $batch];
}
