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

/**
 * Compute Easter Sunday (Gregorian) for a year.
 *
 * @return array{month: int, day: int}
 */
function holiday_easter_sunday(int $year): array
{
    // Anonymous Gregorian algorithm
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return ['month' => $month, 'day' => $day];
}

/**
 * Nth weekday of a month. Weekday: 1=Mon .. 7=Sun. nth: 1-5 or -1 (last).
 *
 * @return array{month: int, day: int}
 */
function holiday_nth_weekday(int $year, int $month, int $weekday, int $nth): array
{
    $month = max(1, min(12, $month));
    $weekday = max(1, min(7, $weekday));
    $tz = new DateTimeZone('UTC');

    if ($nth === -1) {
        $dt = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-1', $year, $month), $tz)
            ?->modify('last day of this month');
        if ($dt === null) {
            return ['month' => $month, 'day' => 1];
        }
        $current = (int) $dt->format('N');
        $diff = ($current - $weekday + 7) % 7;
        if ($diff > 0) {
            $dt = $dt->modify('-' . $diff . ' days');
        }
        return ['month' => (int) $dt->format('n'), 'day' => (int) $dt->format('j')];
    }

    $nth = max(1, min(5, $nth));
    $first = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-1', $year, $month), $tz);
    if ($first === false) {
        return ['month' => $month, 'day' => 1];
    }
    $firstDow = (int) $first->format('N');
    $offset = ($weekday - $firstDow + 7) % 7;
    $day = 1 + $offset + (($nth - 1) * 7);
    $daysInMonth = (int) $first->format('t');
    if ($day > $daysInMonth) {
        $day -= 7;
    }
    return ['month' => $month, 'day' => $day];
}

/** Victoria Day: Monday on or before May 24. @return array{month: int, day: int} */
function holiday_victoria_day(int $year): array
{
    $may24 = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-5-24', $year), new DateTimeZone('UTC'));
    if ($may24 === false) {
        return ['month' => 5, 'day' => 24];
    }
    $dow = (int) $may24->format('N');
    if ($dow !== 1) {
        $may24 = $may24->modify('-' . ($dow - 1) . ' days');
    }
    return ['month' => 5, 'day' => (int) $may24->format('j')];
}

/**
 * Resolve the calendar date a holiday schedule falls on for a given year.
 *
 * @param array<string, mixed> $schedule
 * @return array{month: int, day: int}
 */
function holiday_resolve_date(array $schedule, int $year): array
{
    $rule = trim((string) ($schedule['date_rule'] ?? ''));
    $month = (int) ($schedule['holiday_month'] ?? 1);
    $day = (int) ($schedule['holiday_day'] ?? 1);

    if ($rule === '' || str_starts_with($rule, 'fixed')) {
        return holiday_effective_date($month, $day, $year);
    }

    if ($rule === 'victoria_day') {
        return holiday_victoria_day($year);
    }

    if (preg_match('/^easter:(-?\d+)$/', $rule, $m)) {
        $easter = holiday_easter_sunday($year);
        $base = DateTimeImmutable::createFromFormat(
            'Y-n-j',
            sprintf('%d-%d-%d', $year, $easter['month'], $easter['day']),
            new DateTimeZone('UTC')
        );
        if ($base === false) {
            return $easter;
        }
        $offset = (int) $m[1];
        if ($offset !== 0) {
            $base = $base->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        }
        return ['month' => (int) $base->format('n'), 'day' => (int) $base->format('j')];
    }

    if (preg_match('/^nth_weekday:([1-7]):([1-9]|1[0-2]):(-1|[1-5])$/', $rule, $m)) {
        return holiday_nth_weekday($year, (int) $m[2], (int) $m[1], (int) $m[3]);
    }

    return holiday_effective_date($month, $day, $year);
}

function holiday_rule_label(array $schedule): string
{
    $rule = trim((string) ($schedule['date_rule'] ?? ''));
    if ($rule === '' || str_starts_with($rule, 'fixed')) {
        return holiday_date_label((int) ($schedule['holiday_month'] ?? 1), (int) ($schedule['holiday_day'] ?? 1));
    }
    if ($rule === 'victoria_day') {
        return 'Monday on or before May 24';
    }
    if (preg_match('/^easter:(-?\d+)$/', $rule, $m)) {
        $offset = (int) $m[1];
        if ($offset === -2) {
            return 'Good Friday (Easter − 2 days)';
        }
        return 'Easter ' . ($offset >= 0 ? '+' : '') . $offset . ' days';
    }
    if (preg_match('/^nth_weekday:([1-7]):([1-9]|1[0-2]):(-1|[1-5])$/', $rule, $m)) {
        $names = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $nth = (int) $m[3];
        $nthLabel = $nth === -1 ? 'Last' : (['', '1st', '2nd', '3rd', '4th', '5th'][$nth] ?? $nth . 'th');
        return $nthLabel . ' ' . ($names[(int) $m[1]] ?? 'weekday') . ' of ' . holiday_month_name((int) $m[2]);
    }
    return holiday_date_label((int) ($schedule['holiday_month'] ?? 1), (int) ($schedule['holiday_day'] ?? 1));
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
    $sendTime = holiday_normalize_send_time((string) ($schedule['send_time'] ?? '09:00:00')) ?? '09:00:00';
    $year = (int) $from->format('Y');

    for ($offset = 0; $offset <= 2; $offset++) {
        $candidateYear = $year + $offset;
        $effective = holiday_resolve_date($schedule, $candidateYear);
        if (!holiday_is_valid_date($effective['month'], $effective['day'])
            && !($effective['month'] === 2 && $effective['day'] === 29)) {
            // Still allow resolved dates from rules
        }
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

    $effective = holiday_resolve_date($schedule, $year);

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
        'sin' => 'SAMPLE-SIN',
        'company' => 'Sample Company',
    ];
    [$emailSubject, $html, $text] = build_campaign_email($subject, $body, $client);
    $emailSubject = '[TEST] ' . $emailSubject;

    if (!$mailer->send($testEmail, $emailSubject, $html, $text, null, [
        'kind' => 'holiday_test',
    ])) {
        return ['ok' => false, 'error' => $mailer->getLastError() ?: 'Test send failed.'];
    }

    return ['ok' => true, 'to' => $testEmail];
}

/**
 * Group schedules by resolved day for a calendar month/year.
 *
 * @param list<array<string, mixed>> $schedules
 * @return array<int, list<array<string, mixed>>>
 */
function holiday_schedules_by_day_for_year(int $year, int $month, array $schedules): array
{
    $byDay = [];
    foreach ($schedules as $schedule) {
        $resolved = holiday_resolve_date($schedule, $year);
        if ($resolved['month'] !== $month) {
            continue;
        }
        $day = $resolved['day'];
        $byDay[$day][] = $schedule;
    }
    ksort($byDay);
    return $byDay;
}

/** @deprecated Use holiday_schedules_by_day_for_year() @return array<int, list<array<string, mixed>>> */
function holiday_schedules_by_day(int $month, array $schedules): array
{
    $year = (int) (new DateTimeImmutable('now', app_timezone()))->format('Y');
    return holiday_schedules_by_day_for_year($year, $month, $schedules);
}
