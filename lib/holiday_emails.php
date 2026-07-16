<?php

declare(strict_types=1);

/** @return array{month: int, day: int} */
function holiday_effective_date(int $month, int $day, int $year): array
{
    if ($month === 2 && $day === 29 && !checkdate(2, 29, $year)) {
        return ['month' => 2, 'day' => 28];
    }
    return ['month' => $month, 'day' => $day];
}

function holiday_is_valid_date(int $month, int $day): bool
{
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return false;
    }
    if ($month === 2 && $day === 29) {
        return true;
    }
    return checkdate($month, $day, 2000);
}

function holiday_normalize_send_time(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '09:00:00';
    }
    if (preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }
    if (!preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $raw, $m)) {
        return null;
    }
    $hour = (int) $m[1];
    $minute = (int) $m[2];
    $second = (int) $m[3];
    if ($hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }
    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function holiday_send_time_input_value(?string $stored): string
{
    $stored = holiday_normalize_send_time((string) $stored) ?? '09:00:00';
    return substr($stored, 0, 5);
}

function holiday_month_name(int $month): string
{
    return (new DateTimeImmutable(sprintf('2000-%02d-01', max(1, min(12, $month)))))->format('F');
}

function holiday_date_label(int $month, int $day): string
{
    return holiday_month_name($month) . ' ' . $day;
}

/** @param array<string, mixed> $schedule */
function holiday_next_send_at(array $schedule, ?DateTimeImmutable $from = null): ?DateTimeImmutable
{
    $tz = app_timezone();
    $from = $from ?? new DateTimeImmutable('now', $tz);
    $month = (int) ($schedule['holiday_month'] ?? 0);
    $day = (int) ($schedule['holiday_day'] ?? 0);
    $sendTime = holiday_normalize_send_time((string) ($schedule['send_time'] ?? '09:00:00')) ?? '09:00:00';

    if (!holiday_is_valid_date($month, $day)) {
        return null;
    }

    $year = (int) $from->format('Y');
    for ($offset = 0; $offset <= 2; $offset++) {
        $candidateYear = $year + $offset;
        $effective = holiday_effective_date($month, $day, $candidateYear);
        $at = DateTimeImmutable::createFromFormat(
            'Y-n-j H:i:s',
            sprintf('%d-%d-%d %s', $candidateYear, $effective['month'], $effective['day'], $sendTime),
            $tz
        );
        if ($at !== false && $at >= $from) {
            return $at;
        }
    }

    return null;
}

/** @param array<string, mixed> $schedule */
function holiday_format_next_send(array $schedule): string
{
    $next = holiday_next_send_at($schedule);
    if ($next === null) {
        return '—';
    }
    return $next->format('M j, Y g:i A') . ' · ' . app_timezone_label();
}

/** @param array<string, mixed> $schedule */
function holiday_is_due_now(array $schedule, ?DateTimeImmutable $now = null): bool
{
    if (empty($schedule['enabled'])) {
        return false;
    }

    $tz = app_timezone();
    $now = $now ?? new DateTimeImmutable('now', $tz);
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');
    $day = (int) $now->format('j');

    $scheduleMonth = (int) ($schedule['holiday_month'] ?? 0);
    $scheduleDay = (int) ($schedule['holiday_day'] ?? 0);
    $effective = holiday_effective_date($scheduleMonth, $scheduleDay, $year);

    if ($effective['month'] !== $month || $effective['day'] !== $day) {
        return false;
    }

    $lastSentYear = (int) ($schedule['last_sent_year'] ?? 0);
    if ($lastSentYear === $year) {
        return false;
    }

    $sendTime = holiday_normalize_send_time((string) ($schedule['send_time'] ?? '09:00:00')) ?? '09:00:00';
    $sendAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . $sendTime, $tz);
    if ($sendAt === false) {
        return false;
    }

    return $now >= $sendAt;
}

/**
 * Create and launch campaigns for holiday schedules due right now.
 *
 * @return array{triggered: int, campaigns: list<int>, errors: list<string>}
 */
function process_due_holiday_sends(): array
{
    $repo = new HolidayScheduleRepository();
    $now = new DateTimeImmutable('now', app_timezone());
    $result = ['triggered' => 0, 'campaigns' => [], 'errors' => []];

    foreach ($repo->all() as $schedule) {
        if (!holiday_is_due_now($schedule, $now)) {
            continue;
        }

        $id = (int) ($schedule['id'] ?? 0);
        $launch = holiday_launch_campaign($schedule, (int) $now->format('Y'));
        if (!$launch['ok']) {
            $result['errors'][] = (string) ($schedule['name'] ?? 'Holiday') . ': ' . ($launch['error'] ?? 'Send failed.');
            continue;
        }

        $repo->markSent($id, (int) $now->format('Y'), (int) $launch['campaign_id']);
        ActivityLog::record('holiday.sent', 'holiday_schedule', $id, [
            'year' => (int) $now->format('Y'),
            'campaign_id' => (int) $launch['campaign_id'],
        ]);
        $result['triggered']++;
        $result['campaigns'][] = (int) $launch['campaign_id'];
    }

    return $result;
}

/** @param array<string, mixed> $schedule @return array{ok: bool, campaign_id?: int, error?: string} */
function holiday_launch_campaign(array $schedule, int $year): array
{
    $user = Auth::currentUser();
    $campaignRepo = new EmailCampaignRepository();
    $name = trim((string) ($schedule['name'] ?? 'Holiday')) . ' ' . $year;

    $campaignId = $campaignRepo->create([
        'name' => $name,
        'subject' => (string) ($schedule['subject'] ?? ''),
        'body_html' => (string) ($schedule['body_html'] ?? ''),
        'status' => 'draft',
        'created_by_user_id' => $user['id'] ?? null,
        'created_by_name' => (string) ($user['name'] ?? 'Holiday automation'),
    ]);

    $launch = launch_campaign_send($campaignId, true);
    if (!$launch['ok']) {
        $campaignRepo->update($campaignId, ['status' => 'failed', 'completed_at' => now_iso()]);
        return ['ok' => false, 'error' => $launch['error'] ?? 'Could not start campaign.'];
    }

    return ['ok' => true, 'campaign_id' => $campaignId];
}

/** @param array<string, mixed> $schedule @return array{ok: bool, error?: string, to?: string} */
function holiday_send_test_email(array $schedule): array
{
    $subject = trim((string) ($schedule['subject'] ?? ''));
    $body = trim((string) ($schedule['body_html'] ?? ''));
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
        'cin' => 'SAMPLE-CIN',
        'company' => 'Sample Company',
    ];
    [$emailSubject, $html, $text] = build_campaign_email($subject, $body, $client);
    $emailSubject = '[TEST] ' . $emailSubject;

    if (!$mailer->send($testEmail, $emailSubject, $html, $text)) {
        return ['ok' => false, 'error' => $mailer->getLastError() ?: 'Test send failed.'];
    }

    return ['ok' => true, 'to' => $testEmail];
}

/** @return array<int, list<array<string, mixed>>> */
function holiday_schedules_by_day(int $month, array $schedules): array
{
    $byDay = [];
    foreach ($schedules as $schedule) {
        $day = (int) ($schedule['holiday_day'] ?? 0);
        if ($day < 1) {
            continue;
        }
        $byDay[$day][] = $schedule;
    }
    ksort($byDay);
    return $byDay;
}
