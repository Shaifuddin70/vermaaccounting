<?php

declare(strict_types=1);

/**
 * Public form anti-spam guards (honeypot, timing, IP rate limit).
 */

function form_spam_honeypot_field_name(): string
{
    return 'website_url';
}

function form_spam_timestamp_field_name(): string
{
    return '_form_ts';
}

function form_spam_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

function form_spam_rate_limit_dir(): string
{
    $dir = DATA_DIR . '/rate-limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Max submissions allowed per IP within the window.
 *
 * @return array{limit: int, window_seconds: int}
 */
function form_spam_rate_limit_config(): array
{
    $config = function_exists('app_config') ? app_config() : [];
    $spam = is_array($config['form_spam'] ?? null) ? $config['form_spam'] : [];

    return [
        'limit' => max(1, (int) ($spam['per_ip_limit'] ?? 5)),
        'window_seconds' => max(60, (int) ($spam['per_ip_window'] ?? 3600)),
    ];
}

function form_spam_min_seconds(): int
{
    $config = function_exists('app_config') ? app_config() : [];
    $spam = is_array($config['form_spam'] ?? null) ? $config['form_spam'] : [];
    return max(1, (int) ($spam['min_seconds'] ?? 2));
}

/**
 * Reject bot submissions before validation/storage.
 * Returns a public error message, or null when the request looks human.
 */
function form_spam_reject_reason(array $post): ?string
{
    $honeypot = trim((string) ($post[form_spam_honeypot_field_name()] ?? ''));
    if ($honeypot !== '') {
        error_log('Form spam blocked (honeypot) from ' . form_spam_client_ip());
        return 'Unable to submit right now. Please try again later.';
    }

    $tsRaw = trim((string) ($post[form_spam_timestamp_field_name()] ?? ''));
    if ($tsRaw !== '' && ctype_digit($tsRaw)) {
        $started = (int) $tsRaw;
        $elapsed = time() - $started;
        if ($started > 0 && $elapsed >= 0 && $elapsed < form_spam_min_seconds()) {
            error_log('Form spam blocked (too fast: ' . $elapsed . 's) from ' . form_spam_client_ip());
            return 'Please wait a moment and try again.';
        }
        // Reject absurd future timestamps or stamps older than 1 day (replay).
        if ($started > time() + 60 || $elapsed > 86400) {
            error_log('Form spam blocked (bad timestamp) from ' . form_spam_client_ip());
            return 'Unable to submit right now. Please refresh the page and try again.';
        }
    }

    if (form_spam_ip_is_rate_limited()) {
        error_log('Form spam blocked (rate limit) from ' . form_spam_client_ip());
        return 'Too many submissions from your network. Please try again later.';
    }

    return null;
}

function form_spam_ip_hit_count(): int
{
    $cfg = form_spam_rate_limit_config();
    $ip = form_spam_client_ip();
    $path = form_spam_rate_limit_dir() . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowStart = $now - $cfg['window_seconds'];

    $hits = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            foreach ($decoded as $ts) {
                $t = (int) $ts;
                if ($t >= $windowStart) {
                    $hits[] = $t;
                }
            }
        }
    }

    return count($hits);
}

function form_spam_ip_is_rate_limited(): bool
{
    $cfg = form_spam_rate_limit_config();
    return form_spam_ip_hit_count() >= $cfg['limit'];
}

function form_spam_record_ip_hit(): void
{
    $cfg = form_spam_rate_limit_config();
    $ip = form_spam_client_ip();
    $path = form_spam_rate_limit_dir() . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowStart = $now - $cfg['window_seconds'];

    $hits = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            foreach ($decoded as $ts) {
                $t = (int) $ts;
                if ($t >= $windowStart) {
                    $hits[] = $t;
                }
            }
        }
    }

    $hits[] = $now;
    @file_put_contents($path, json_encode($hits), LOCK_EX);
}

/**
 * Hidden fields for public forms. Keep honeypot off-screen via CSS class.
 */
function form_spam_guard_fields_html(): string
{
    $hp = form_spam_honeypot_field_name();
    $ts = form_spam_timestamp_field_name();
    $now = (string) time();

    return '<div class="form-spam-guard" aria-hidden="true">'
        . '<label for="' . e($hp) . '">Website</label>'
        . '<input type="text" id="' . e($hp) . '" name="' . e($hp) . '" value="" tabindex="-1" autocomplete="off">'
        . '<input type="hidden" name="' . e($ts) . '" value="' . e($now) . '">'
        . '</div>';
}
