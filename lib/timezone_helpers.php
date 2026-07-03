<?php

declare(strict_types=1);

function app_timezone_default(): string
{
    return 'America/Toronto';
}

function app_timezone_is_valid(string $id): bool
{
    return in_array($id, timezone_identifiers_list(), true);
}

function app_timezone_id(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $stored = (new SettingsRepository())->get('app_timezone', null);
    if (is_string($stored) && app_timezone_is_valid($stored)) {
        $resolved = $stored;
        return $resolved;
    }

    $resolved = app_timezone_default();
    return $resolved;
}

function app_timezone(?string $id = null): DateTimeZone
{
    $id = $id ?? app_timezone_id();
    if (!app_timezone_is_valid($id)) {
        $id = app_timezone_default();
    }
    return new DateTimeZone($id);
}

/** @return array<string, string> */
function app_timezone_curated_labels(): array
{
    return [
        'America/St_Johns' => 'Newfoundland',
        'America/Halifax' => 'Atlantic',
        'America/Toronto' => 'Eastern (Canada)',
        'America/Winnipeg' => 'Central (Canada)',
        'America/Edmonton' => 'Mountain (Canada)',
        'America/Vancouver' => 'Pacific (Canada)',
        'America/New_York' => 'Eastern (US)',
        'America/Chicago' => 'Central (US)',
        'America/Denver' => 'Mountain (US)',
        'America/Los_Angeles' => 'Pacific (US)',
        'Asia/Dhaka' => 'Bangladesh',
        'Asia/Kolkata' => 'India',
        'Asia/Dubai' => 'Gulf',
        'Europe/London' => 'United Kingdom',
        'UTC' => 'UTC',
    ];
}

function app_timezone_display_name(string $id): string
{
    $labels = app_timezone_curated_labels();
    if (isset($labels[$id])) {
        return $labels[$id];
    }

    $parts = explode('/', $id);
    return str_replace('_', ' ', (string) end($parts));
}

function app_timezone_label(?string $id = null): string
{
    $id = $id ?? app_timezone_id();
    $name = app_timezone_display_name($id);

    try {
        $abbr = (new DateTime('now', new DateTimeZone($id)))->format('T');
        if ($abbr !== '' && $abbr !== $id) {
            return $name . ' (' . $abbr . ')';
        }
    } catch (Exception) {
        // ignore
    }

    return $name;
}

/** @return array<string, array<string, string>> */
function app_timezone_option_groups(): array
{
    $groups = [
        'Canada' => [
            'America/St_Johns',
            'America/Halifax',
            'America/Toronto',
            'America/Winnipeg',
            'America/Edmonton',
            'America/Vancouver',
        ],
        'United States' => [
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Phoenix',
            'America/Los_Angeles',
            'America/Anchorage',
            'Pacific/Honolulu',
        ],
        'Asia' => [
            'Asia/Dhaka',
            'Asia/Kolkata',
            'Asia/Dubai',
            'Asia/Singapore',
            'Asia/Tokyo',
        ],
        'Europe & other' => [
            'Europe/London',
            'Europe/Paris',
            'Australia/Sydney',
            'UTC',
        ],
    ];

    $labels = app_timezone_curated_labels();
    $used = [];
    $out = [];

    foreach ($groups as $group => $ids) {
        $out[$group] = [];
        foreach ($ids as $id) {
            if (!app_timezone_is_valid($id)) {
                continue;
            }
            $used[$id] = true;
            $out[$group][$id] = $labels[$id] ?? app_timezone_display_name($id);
        }
    }

    $more = [];
    foreach (timezone_identifiers_list() as $id) {
        if (isset($used[$id])) {
            continue;
        }
        $more[$id] = str_replace('_', ' ', $id);
    }
    if ($more !== []) {
        $out['All timezones'] = $more;
    }

    return $out;
}

/** Parse a datetime-local value in the app timezone; returns UTC for storage. */
function app_parse_local_datetime(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw, app_timezone());
    if (!$dt) {
        return null;
    }
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** Format a UTC timestamp for display in the app timezone. */
function app_format_datetime(?string $utc, bool $includeTimezone = true): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $dt = new DateTime($utc, new DateTimeZone('UTC'));
    $dt->setTimezone(app_timezone());
    $formatted = $dt->format('M j, Y g:i A');
    return $includeTimezone ? $formatted . ' · ' . app_timezone_label() : $formatted;
}

/** Value for HTML datetime-local inputs in the app timezone. */
function app_datetime_local_value(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $dt = new DateTime($utc, new DateTimeZone('UTC'));
    $dt->setTimezone(app_timezone());
    return $dt->format('Y-m-d\TH:i');
}

function app_now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function app_now_local_formatted(?string $id = null): string
{
    return (new DateTime('now', app_timezone($id)))->format('M j, Y g:i A');
}
