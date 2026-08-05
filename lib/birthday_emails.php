<?php

declare(strict_types=1);

function birthday_email_default_html(): string
{
    return canada_holiday_email_html([
        'title' => 'Happy Birthday',
        'occasion' => 'your birthday',
        'message' => 'Happy Birthday! On your special day, we want to thank you for being a valued client. '
            . 'We hope your year ahead is filled with good health, happiness, and success.',
        'appreciation' => 'It is always our pleasure to serve you. Wishing you a wonderful celebration with family and friends.',
        'signoff' => 'Warm birthday wishes,',
        'image_name' => 'Birthday',
    ]);
}

/**
 * Detect bodies mangled by the old banner-replace regex (logo/greeting removed, banner left).
 */
function birthday_email_body_is_truncated(string $body): bool
{
    $body = trim($body);
    if ($body === '') {
        return false;
    }

    $hasBanner = str_contains($body, 'email-holidays')
        || str_contains($body, 'data-email-template-image');
    $hasLogo = str_contains($body, 'verma-accounting-logo');
    $hasGreeting = stripos($body, 'Dear') !== false && str_contains($body, '{client_name}');

    return $hasBanner && (!$hasLogo || !$hasGreeting);
}

/** @return array{enabled: bool, send_time: string, subject: string, body_html: string} */
function birthday_email_default_settings(): array
{
    return [
        'enabled' => true,
        'send_time' => '09:00:00',
        'subject' => 'Happy Birthday from Verma Accounting, {client_name}!',
        'body_html' => birthday_email_default_html(),
    ];
}

/** @return array{enabled: bool, send_time: string, subject: string, body_html: string} */
function birthday_email_settings(): array
{
    $defaults = birthday_email_default_settings();
    $stored = (new SettingsRepository())->get('birthday_emails', []);
    if (!is_array($stored)) {
        return $defaults;
    }

    $sendTime = holiday_normalize_send_time((string) ($stored['send_time'] ?? $defaults['send_time']))
        ?? $defaults['send_time'];
    $subject = trim((string) ($stored['subject'] ?? ''));
    $body = trim((string) ($stored['body_html'] ?? ''));
    $body = $body !== '' ? campaign_email_normalize_year_token($body) : $defaults['body_html'];

    // Repair templates gutted by the old cross-tag banner replace bug.
    if (birthday_email_body_is_truncated($body)) {
        $body = $defaults['body_html'];
        birthday_email_save_settings([
            'enabled' => array_key_exists('enabled', $stored) ? !empty($stored['enabled']) : true,
            'send_time' => $sendTime,
            'subject' => $subject !== '' ? $subject : $defaults['subject'],
            'body_html' => $body,
        ]);
    } else {
        // Older saved templates may not include the banner — keep birthday.jpg wired in.
        $body = canada_holiday_email_apply_template_image(
            $body,
            canada_holiday_email_image_url_for_name('Birthday')
        );
    }

    return [
        'enabled' => array_key_exists('enabled', $stored) ? !empty($stored['enabled']) : true,
        'send_time' => $sendTime,
        'subject' => $subject !== '' ? $subject : $defaults['subject'],
        'body_html' => $body,
    ];
}

/** @param array<string, mixed> $settings */
function birthday_email_save_settings(array $settings): void
{
    $defaults = birthday_email_default_settings();
    $sendTime = holiday_normalize_send_time((string) ($settings['send_time'] ?? '')) ?? $defaults['send_time'];
    $subject = trim((string) ($settings['subject'] ?? ''));
    $body = campaign_email_normalize_year_token(trim((string) ($settings['body_html'] ?? '')));
    if ($body === '') {
        $body = $defaults['body_html'];
    } else {
        $body = canada_holiday_email_apply_template_image(
            $body,
            canada_holiday_email_image_url_for_name('Birthday')
        );
    }

    (new SettingsRepository())->set('birthday_emails', [
        'enabled' => !empty($settings['enabled']),
        'send_time' => $sendTime,
        'subject' => $subject !== '' ? $subject : $defaults['subject'],
        'body_html' => $body,
    ]);
}

/**
 * @return array{ok: bool, error?: string, to?: string}
 */
function birthday_send_test_email(?array $settings = null): array
{
    $settings ??= birthday_email_settings();
    $subject = trim((string) ($settings['subject'] ?? ''));
    $body = trim((string) ($settings['body_html'] ?? ''));
    if ($subject === '' || $body === '') {
        return ['ok' => false, 'error' => 'Subject and message are required to send a test.'];
    }

    $mailCfg = mail_config();
    $testEmail = trim((string) (($mailCfg['admin_emails'][0] ?? null) ?: ($mailCfg['admin_email'] ?? '')));
    $user = Auth::currentUser();
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

    $client = [
        'client_name' => (string) ($user['name'] ?? 'Admin'),
        'email' => $testEmail,
        'sin' => 'SAMPLE-SIN',
        'company' => 'Sample Company',
    ];
    [$emailSubject, $html, $text] = build_campaign_email($subject, $body, $client);
    $emailSubject = '[TEST] ' . $emailSubject;

    if (!$mailer->send($testEmail, $emailSubject, $html, $text, null, [
        'kind' => 'birthday_test',
    ])) {
        return ['ok' => false, 'error' => $mailer->getLastError() ?: 'Test send failed.'];
    }

    return ['ok' => true, 'to' => $testEmail];
}

/**
 * Create and queue birthday campaigns for clients whose birthday is today.
 *
 * @return array{triggered: int, campaigns: list<int>, recipients: int, errors: list<string>}
 */
function process_due_birthday_sends(?DateTimeImmutable $now = null): array
{
    $result = ['triggered' => 0, 'campaigns' => [], 'recipients' => 0, 'errors' => []];
    $settings = birthday_email_settings();
    if (empty($settings['enabled'])) {
        return $result;
    }

    $tz = app_timezone();
    $now = $now ?? new DateTimeImmutable('now', $tz);
    $year = (int) $now->format('Y');
    $sendTime = holiday_normalize_send_time((string) $settings['send_time']) ?? '09:00:00';
    $sendAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . $sendTime, $tz);
    if ($sendAt === false || $now < $sendAt) {
        return $result;
    }

    $clientRepo = new ClientRepository();
    $due = $clientRepo->recipientsWithBirthdayOn($now);
    if ($due === []) {
        return $result;
    }

    $user = Auth::currentUser();
    $campaignRepo = new EmailCampaignRepository();
    $name = 'Birthday ' . $now->format('Y-m-d');
    $campaignId = $campaignRepo->create([
        'name' => $name,
        'subject' => (string) $settings['subject'],
        'body_html' => (string) $settings['body_html'],
        'status' => 'draft',
        'created_by_user_id' => $user['id'] ?? null,
        'created_by_name' => (string) ($user['name'] ?? 'Birthday automation'),
    ]);

    $launch = launch_campaign_send($campaignId, true, $due);
    if (!$launch['ok']) {
        $campaignRepo->update($campaignId, ['status' => 'failed', 'completed_at' => now_iso()]);
        $result['errors'][] = $launch['error'] ?? 'Could not start birthday campaign.';
        return $result;
    }

    foreach ($due as $recipient) {
        $clientId = (int) ($recipient['client_id'] ?? 0);
        if ($clientId > 0) {
            $clientRepo->markBirthdaySent($clientId, $year);
        }
    }

    ActivityLog::record('birthday.sent', 'birthday_emails', $campaignId, [
        'year' => $year,
        'recipients' => count($due),
        'campaign_id' => $campaignId,
    ]);

    $result['triggered'] = 1;
    $result['campaigns'][] = $campaignId;
    $result['recipients'] = count($due);
    return $result;
}
