<?php

declare(strict_types=1);

/**
 * Public form anti-spam guards (honeypot, timing, tokens, rate limits, content filters).
 */

function form_spam_honeypot_field_name(): string
{
    return 'website_url';
}

function form_spam_timestamp_field_name(): string
{
    return '_form_ts';
}

function form_spam_token_field_name(): string
{
    return '_form_token';
}

function form_spam_client_ip(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
    ];
    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
        $first = trim(explode(',', $forwarded)[0] ?? '');
        if ($first !== '') {
            array_unshift($candidates, $first);
        }
    }
    foreach ($candidates as $candidate) {
        $ip = trim($candidate);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

function form_spam_rate_limit_dir(): string
{
    $dir = DATA_DIR . '/rate-limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $guard = $dir . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Require all denied\n");
    }
    return $dir;
}

/** @return array{limit: int, window_seconds: int, global_limit: int, global_window: int, min_seconds: int} */
function form_spam_config(): array
{
    $config = function_exists('app_config') ? app_config() : [];
    $spam = is_array($config['form_spam'] ?? null) ? $config['form_spam'] : [];

    return [
        'limit' => max(1, (int) ($spam['per_ip_limit'] ?? 2)),
        'window_seconds' => max(60, (int) ($spam['per_ip_window'] ?? 3600)),
        'global_limit' => max(1, (int) ($spam['global_limit'] ?? 20)),
        'global_window' => max(60, (int) ($spam['global_window'] ?? 600)),
        'min_seconds' => max(2, (int) ($spam['min_seconds'] ?? 3)),
    ];
}

function form_spam_secret(): string
{
    $config = function_exists('app_config') ? app_config() : [];
    $spam = is_array($config['form_spam'] ?? null) ? $config['form_spam'] : [];
    $secret = trim((string) ($spam['secret'] ?? ''));
    if ($secret !== '') {
        return $secret;
    }
    $hash = (string) ($config['admin_password_hash'] ?? '');
    $site = (string) ($config['site_url'] ?? 'vermaaccounting.ca');
    return hash('sha256', $hash . '|' . $site . '|form-spam');
}

function form_spam_make_token(int $timestamp): string
{
    // Timestamp-only HMAC so proxies/CDN IP changes do not break real visitors.
    return hash_hmac('sha256', 'form-ts:' . $timestamp, form_spam_secret());
}

function form_spam_verify_token(string $token, int $timestamp): bool
{
    if ($token === '' || $timestamp < 1) {
        return false;
    }
    $expected = form_spam_make_token($timestamp);
    return hash_equals($expected, $token);
}

/**
 * Reject bot submissions before validation/storage.
 */
function form_spam_reject_reason(array $post, string $slug = ''): ?string
{
    $generic = 'Unable to submit right now. Please try again later.';
    $cfg = form_spam_config();

    $honeypot = trim((string) ($post[form_spam_honeypot_field_name()] ?? ''));
    if ($honeypot !== '') {
        error_log('Form spam blocked (honeypot) from ' . form_spam_client_ip());
        return $generic;
    }

    $tsRaw = trim((string) ($post[form_spam_timestamp_field_name()] ?? ''));
    $token = trim((string) ($post[form_spam_token_field_name()] ?? ''));

    // Require page-generated timestamp + HMAC token (blocks raw API bots).
    if ($tsRaw === '' || !ctype_digit($tsRaw) || $token === '') {
        error_log('Form spam blocked (missing token/ts) from ' . form_spam_client_ip());
        return $generic;
    }

    $started = (int) $tsRaw;
    $elapsed = time() - $started;
    if ($elapsed < $cfg['min_seconds']) {
        error_log('Form spam blocked (too fast: ' . $elapsed . 's) from ' . form_spam_client_ip());
        return 'Please wait a moment and try again.';
    }
    if ($started > time() + 60 || $elapsed > 86400) {
        error_log('Form spam blocked (bad timestamp) from ' . form_spam_client_ip());
        return 'Unable to submit right now. Please refresh the page and try again.';
    }
    if (!form_spam_verify_token($token, $started)) {
        error_log('Form spam blocked (bad token) from ' . form_spam_client_ip());
        return $generic;
    }

    if (form_spam_ip_is_rate_limited()) {
        error_log('Form spam blocked (ip rate limit) from ' . form_spam_client_ip());
        return 'Too many submissions from your network. Please try again later.';
    }

    if (form_spam_global_is_rate_limited($slug)) {
        error_log('Form spam blocked (global rate limit) slug=' . $slug . ' from ' . form_spam_client_ip());
        return 'We are receiving too many messages right now. Please try again in a few minutes.';
    }

    $contentReason = form_spam_content_reject_reason($post, $slug);
    if ($contentReason !== null) {
        error_log('Form spam blocked (content) from ' . form_spam_client_ip() . ': ' . $contentReason);
        return $generic;
    }

    return null;
}

/**
 * Content heuristics for phishing / bot payloads (e.g. Coinbase graph.org spam).
 */
function form_spam_content_reject_reason(array $post, string $slug = ''): ?string
{
    $name = trim((string) ($post['name'] ?? ''));
    $message = trim((string) ($post['message'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    $phone = trim((string) ($post['phone'] ?? ''));
    $blob = $name . "\n" . $message . "\n" . $email . "\n" . $phone;

    if ($name !== '' && $message !== '' && mb_strtolower($name) === mb_strtolower($message)) {
        return 'name_equals_message';
    }

    // URLs / phishing markers anywhere in contact-like fields
    if (preg_match(
        '/https?:\/\/|www\.|\.org\/|\.com\/[^\s]|graph\.org|t\.me\/|bit\.ly|tinyurl|goo\.gl|coinbase|bitcoin|blockchain|crypto\b|nft\b|open\s*=>|transfer\s+from/iu',
        $blob
    )) {
        return 'url_or_phishing';
    }

    // Emoji in name (common phishing lure pattern)
    if ($name !== '' && preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $name)) {
        return 'emoji_in_name';
    }

    // Name should not look like a marketing blast
    if ($name !== '' && (mb_strlen($name) > 120 || preg_match('/@\w{3,}/', $name))) {
        return 'bad_name';
    }

    // Phone: reject very long digit-only international spam patterns
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits !== '' && (strlen($digits) > 15 || strlen($digits) < 7)) {
        return 'bad_phone';
    }

    // Contact form: require vaguely reasonable name (letters)
    if ($slug === 'contact' || $slug === '') {
        if ($name !== '' && !preg_match('/\p{L}/u', $name)) {
            return 'name_no_letters';
        }
    }

    return null;
}

function form_spam_hit_file(string $key): string
{
    return form_spam_rate_limit_dir() . '/' . hash('sha256', $key) . '.json';
}

/** @return list<int> */
function form_spam_read_hits(string $path, int $windowStart): array
{
    $hits = [];
    if (!is_file($path)) {
        return $hits;
    }
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return $hits;
    }
    foreach ($decoded as $ts) {
        $t = (int) $ts;
        if ($t >= $windowStart) {
            $hits[] = $t;
        }
    }
    return $hits;
}

function form_spam_write_hits(string $path, array $hits): void
{
    @file_put_contents($path, json_encode(array_values($hits)), LOCK_EX);
}

function form_spam_ip_hit_count(): int
{
    $cfg = form_spam_config();
    $path = form_spam_hit_file('ip:' . form_spam_client_ip());
    return count(form_spam_read_hits($path, time() - $cfg['window_seconds']));
}

function form_spam_ip_is_rate_limited(): bool
{
    $cfg = form_spam_config();
    return form_spam_ip_hit_count() >= $cfg['limit'];
}

function form_spam_global_is_rate_limited(string $slug): bool
{
    $cfg = form_spam_config();
    $key = 'global:' . ($slug !== '' ? $slug : 'all');
    $path = form_spam_hit_file($key);
    $hits = form_spam_read_hits($path, time() - $cfg['global_window']);
    return count($hits) >= $cfg['global_limit'];
}

function form_spam_record_ip_hit(): void
{
    $cfg = form_spam_config();
    $path = form_spam_hit_file('ip:' . form_spam_client_ip());
    $hits = form_spam_read_hits($path, time() - $cfg['window_seconds']);
    $hits[] = time();
    form_spam_write_hits($path, $hits);
}

function form_spam_record_global_hit(string $slug): void
{
    $cfg = form_spam_config();
    $key = 'global:' . ($slug !== '' ? $slug : 'all');
    $path = form_spam_hit_file($key);
    $hits = form_spam_read_hits($path, time() - $cfg['global_window']);
    $hits[] = time();
    form_spam_write_hits($path, $hits);
}

function form_spam_record_hit(string $slug = ''): void
{
    form_spam_record_ip_hit();
    form_spam_record_global_hit($slug);
}

/**
 * Hidden fields for public forms. Keep honeypot off-screen via CSS class.
 */
function form_spam_guard_fields_html(): string
{
    $hp = form_spam_honeypot_field_name();
    $tsName = form_spam_timestamp_field_name();
    $tokenName = form_spam_token_field_name();
    $now = time();
    $token = form_spam_make_token($now);

    return '<div class="form-spam-guard" aria-hidden="true">'
        . '<label for="' . e($hp) . '">Website</label>'
        . '<input type="text" id="' . e($hp) . '" name="' . e($hp) . '" value="" tabindex="-1" autocomplete="off">'
        . '<input type="hidden" name="' . e($tsName) . '" value="' . e((string) $now) . '">'
        . '<input type="hidden" name="' . e($tokenName) . '" value="' . e($token) . '">'
        . '</div>';
}
