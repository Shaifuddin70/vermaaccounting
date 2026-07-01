<?php

declare(strict_types=1);

function campaign_batch_size(): int
{
    return 25;
}

function campaign_parse_scheduled_at(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw, new DateTimeZone('UTC'));
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
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

/** @param array<string, mixed> $client */
function campaign_email_replace_tokens(string $text, array $client): string
{
    $replacements = [
        '{client_name}' => (string) ($client['client_name'] ?? $client['name'] ?? ''),
        '{client_email}' => (string) ($client['email'] ?? ''),
        '{cin}' => (string) ($client['cin'] ?? ''),
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

/**
 * Send up to $limit pending emails for a campaign.
 *
 * @return array{processed: int, sent: int, failed: int, done: bool}
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
      return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'done' => true];
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
        'cin' => '',
        'company' => '',
      ];
      if (!empty($recipient['client_id'])) {
        $clientRow = (new ClientRepository())->find((int) $recipient['client_id']);
        if ($clientRow) {
          $client['cin'] = (string) ($clientRow['cin'] ?? '');
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
