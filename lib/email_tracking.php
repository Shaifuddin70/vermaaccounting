<?php

declare(strict_types=1);

/**
 * Create a tracked send row and return its public token.
 *
 * @param array{
 *   kind?: string,
 *   to_email: string,
 *   subject?: string,
 *   campaign_id?: int|null,
 *   recipient_id?: int|null,
 *   client_id?: int|null,
 *   ref_type?: string|null,
 *   ref_id?: int|null
 * } $meta
 */
function email_tracking_create(array $meta): string
{
    $token = bin2hex(random_bytes(16));
    $now = now_iso();
    $pdo = Database::instance()->pdo();
    $stmt = $pdo->prepare('
        INSERT INTO email_tracked_sends (
            token, kind, to_email, subject,
            campaign_id, recipient_id, client_id,
            ref_type, ref_id, sent_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $token,
        substr(trim((string) ($meta['kind'] ?? 'email')), 0, 40) ?: 'email',
        strtolower(trim((string) ($meta['to_email'] ?? ''))),
        substr(trim((string) ($meta['subject'] ?? '')), 0, 500),
        isset($meta['campaign_id']) && $meta['campaign_id'] !== null && (int) $meta['campaign_id'] > 0
            ? (int) $meta['campaign_id'] : null,
        isset($meta['recipient_id']) && $meta['recipient_id'] !== null && (int) $meta['recipient_id'] > 0
            ? (int) $meta['recipient_id'] : null,
        isset($meta['client_id']) && $meta['client_id'] !== null && (int) $meta['client_id'] > 0
            ? (int) $meta['client_id'] : null,
        ($meta['ref_type'] ?? null) !== null && trim((string) $meta['ref_type']) !== ''
            ? substr(trim((string) $meta['ref_type']), 0, 40) : null,
        isset($meta['ref_id']) && $meta['ref_id'] !== null && (int) $meta['ref_id'] > 0
            ? (int) $meta['ref_id'] : null,
        $now,
        $now,
    ]);

    return $token;
}

function email_tracking_pixel_url(string $token): string
{
    $base = rtrim(app_base_url() ?: 'https://vermaaccounting.ca', '/');

    return $base . '/email-open/' . rawurlencode($token) . '.gif';
}

function email_tracking_inject(string $html, string $token): string
{
    $token = preg_replace('/[^a-f0-9]/i', '', $token) ?? '';
    if (strlen($token) !== 32) {
        return $html;
    }

    $url = htmlspecialchars(email_tracking_pixel_url($token), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $pixel = '<img src="' . $url . '" width="1" height="1" alt="" '
        . 'style="display:block;width:1px;height:1px;border:0;outline:none;" '
        . 'data-email-open-pixel="1">';

    if (stripos($html, 'data-email-open-pixel') !== false) {
        return $html;
    }

    if (preg_match('/<\/body>/i', $html)) {
        return (string) preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1);
    }

    return $html . $pixel;
}

/**
 * Apply tracking to HTML before send. Returns updated HTML (unchanged on failure).
 *
 * @param array<string, mixed> $meta
 */
function email_tracking_prepare_html(string $html, array $meta): string
{
    try {
        $token = email_tracking_create($meta);
        return email_tracking_inject($html, $token);
    } catch (Throwable $e) {
        error_log('Email tracking prepare failed: ' . $e->getMessage());
        return $html;
    }
}

function email_tracking_find_by_token(string $token): ?array
{
    $token = strtolower(preg_replace('/[^a-f0-9]/i', '', $token) ?? '');
    if (strlen($token) !== 32) {
        return null;
    }

    $stmt = Database::instance()->pdo()->prepare('SELECT * FROM email_tracked_sends WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function email_tracking_record_open(string $token, ?string $userAgent = null): bool
{
    $row = email_tracking_find_by_token($token);
    if ($row === null) {
        return false;
    }

    $now = now_iso();
    $ua = substr(trim((string) ($userAgent ?? '')), 0, 500);
    $pdo = Database::instance()->pdo();

    if (empty($row['opened_at'])) {
        $stmt = $pdo->prepare('
            UPDATE email_tracked_sends
            SET opened_at = ?, open_count = 1, last_opened_at = ?, open_user_agent = ?
            WHERE id = ?
        ');
        $stmt->execute([$now, $now, $ua !== '' ? $ua : null, (int) $row['id']]);
    } else {
        $stmt = $pdo->prepare('
            UPDATE email_tracked_sends
            SET open_count = open_count + 1, last_opened_at = ?, open_user_agent = COALESCE(?, open_user_agent)
            WHERE id = ?
        ');
        $stmt->execute([$now, $ua !== '' ? $ua : null, (int) $row['id']]);
    }

    return true;
}

/** 1×1 transparent GIF bytes. */
function email_tracking_transparent_gif(): string
{
    return (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
}

/**
 * @return array{tracked: int, opened: int, open_rate: float}
 */
function email_tracking_stats_for_campaign(int $campaignId): array
{
    $stmt = Database::instance()->pdo()->prepare('
        SELECT
            COUNT(*) AS tracked,
            SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened
        FROM email_tracked_sends
        WHERE campaign_id = ?
    ');
    $stmt->execute([$campaignId]);
    $row = $stmt->fetch() ?: [];
    $tracked = (int) ($row['tracked'] ?? 0);
    $opened = (int) ($row['opened'] ?? 0);

    return [
        'tracked' => $tracked,
        'opened' => $opened,
        'open_rate' => $tracked > 0 ? round(($opened / $tracked) * 100, 1) : 0.0,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function email_tracking_opens_for_campaign(int $campaignId, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $stmt = Database::instance()->pdo()->prepare('
        SELECT *
        FROM email_tracked_sends
        WHERE campaign_id = ? AND opened_at IS NOT NULL
        ORDER BY opened_at DESC
        LIMIT ' . $limit
    );
    $stmt->execute([$campaignId]);

    return $stmt->fetchAll() ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function email_tracking_recent(int $limit = 50, int $offset = 0): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $stmt = Database::instance()->pdo()->prepare('
        SELECT *
        FROM email_tracked_sends
        ORDER BY sent_at DESC
        LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function email_tracking_count(): int
{
    return (int) Database::instance()->pdo()->query('SELECT COUNT(*) FROM email_tracked_sends')->fetchColumn();
}

/**
 * @return array{tracked: int, opened: int, open_rate: float}
 */
function email_tracking_overall_stats(): array
{
    $row = Database::instance()->pdo()->query('
        SELECT
            COUNT(*) AS tracked,
            SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened
        FROM email_tracked_sends
    ')->fetch() ?: [];
    $tracked = (int) ($row['tracked'] ?? 0);
    $opened = (int) ($row['opened'] ?? 0);

    return [
        'tracked' => $tracked,
        'opened' => $opened,
        'open_rate' => $tracked > 0 ? round(($opened / $tracked) * 100, 1) : 0.0,
    ];
}

function email_tracking_kind_label(string $kind): string
{
    return match ($kind) {
        'campaign' => 'Campaign',
        'campaign_test' => 'Campaign test',
        'holiday_test' => 'Holiday test',
        'birthday_test' => 'Birthday test',
        'invoice' => 'Invoice',
        'submission_admin' => 'Form (admin)',
        'submission_client' => 'Form (client)',
        'test' => 'Test email',
        default => ucfirst(str_replace('_', ' ', $kind)),
    };
}
