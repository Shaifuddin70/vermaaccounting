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
function email_tracking_overall_stats(?int $days = null): array
{
    $pdo = Database::instance()->pdo();
    $sql = '
        SELECT
            COUNT(*) AS tracked,
            SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened
        FROM email_tracked_sends
    ';
    $params = [];
    if ($days !== null && $days > 0) {
        $sql .= ' WHERE sent_at >= ?';
        $params[] = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    $tracked = (int) ($row['tracked'] ?? 0);
    $opened = (int) ($row['opened'] ?? 0);

    return [
        'tracked' => $tracked,
        'opened' => $opened,
        'open_rate' => $tracked > 0 ? round(($opened / $tracked) * 100, 1) : 0.0,
    ];
}

function email_tracking_sent_day_sql(string $alias = 's'): string
{
    $col = $alias . '.sent_at';
    if (Database::instance()->driver() === 'sqlite') {
        return 'DATE(' . $col . ')';
    }

    return 'DATE(' . $col . ')';
}

/**
 * Build filter SQL fragments for tracker list/detail queries.
 *
 * @param array{days?: int|null, kind?: string, q?: string, opened?: string} $filters
 * @return array{0: string, 1: list<mixed>} WHERE clause without leading WHERE, and params
 */
function email_tracking_filter_sql(array $filters, string $alias = 's'): array
{
    $where = [];
    $params = [];
    $days = isset($filters['days']) ? (int) $filters['days'] : 0;
    if ($days > 0) {
        $where[] = $alias . '.sent_at >= ?';
        $params[] = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
    }
    $kind = trim((string) ($filters['kind'] ?? ''));
    if ($kind !== '' && $kind !== 'all') {
        $where[] = $alias . '.kind = ?';
        $params[] = $kind;
    }
    $opened = trim((string) ($filters['opened'] ?? ''));
    if ($opened === 'yes') {
        $where[] = $alias . '.opened_at IS NOT NULL';
    } elseif ($opened === 'no') {
        $where[] = $alias . '.opened_at IS NULL';
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(' . $alias . '.to_email LIKE ? OR ' . $alias . '.subject LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    return [$where === [] ? '1=1' : implode(' AND ', $where), $params];
}

/**
 * Aggregated send batches for the tracker index (campaigns + other email types by day).
 *
 * @param array{days?: int|null, kind?: string} $filters
 * @return list<array<string, mixed>>
 */
function email_tracking_batches(array $filters = [], int $limit = 20, int $offset = 0): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    [$where, $params] = email_tracking_filter_sql($filters, 's');
    $dayExpr = email_tracking_sent_day_sql('s');
    $pdo = Database::instance()->pdo();

    // One row per campaign, plus one row per non-campaign kind+day+subject.
    $sql = "
        SELECT * FROM (
            SELECT
                'campaign' AS batch_type,
                s.campaign_id AS campaign_id,
                s.kind AS kind,
                COALESCE(MAX(c.name), MAX(s.subject), '') AS title,
                MAX(s.subject) AS subject,
                NULL AS send_day,
                COUNT(*) AS tracked,
                SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                MIN(s.sent_at) AS first_sent,
                MAX(s.sent_at) AS last_sent
            FROM email_tracked_sends s
            LEFT JOIN email_campaigns c ON c.id = s.campaign_id
            WHERE s.campaign_id IS NOT NULL AND ({$where})
            GROUP BY s.campaign_id, s.kind

            UNION ALL

            SELECT
                'other' AS batch_type,
                NULL AS campaign_id,
                s.kind AS kind,
                MAX(s.subject) AS title,
                s.subject AS subject,
                {$dayExpr} AS send_day,
                COUNT(*) AS tracked,
                SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                MIN(s.sent_at) AS first_sent,
                MAX(s.sent_at) AS last_sent
            FROM email_tracked_sends s
            WHERE s.campaign_id IS NULL AND ({$where})
            GROUP BY s.kind, {$dayExpr}, s.subject
        ) batches
        ORDER BY last_sent DESC
        LIMIT {$limit} OFFSET {$offset}";

    // Params used twice (campaign branch + other branch).
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $params));

    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $tracked = (int) ($row['tracked'] ?? 0);
        $opened = (int) ($row['opened'] ?? 0);
        $row['tracked'] = $tracked;
        $row['opened'] = $opened;
        $row['open_rate'] = $tracked > 0 ? round(($opened / $tracked) * 100, 1) : 0.0;
    }
    unset($row);

    return $rows;
}

/**
 * @param array{days?: int|null, kind?: string} $filters
 */
function email_tracking_batches_count(array $filters = []): int
{
    [$where, $params] = email_tracking_filter_sql($filters, 's');
    $dayExpr = email_tracking_sent_day_sql('s');
    $pdo = Database::instance()->pdo();
    $sql = '
        SELECT COUNT(*) FROM (
            SELECT s.campaign_id AS grp
            FROM email_tracked_sends s
            WHERE s.campaign_id IS NOT NULL AND (' . $where . ')
            GROUP BY s.campaign_id, s.kind

            UNION ALL

            SELECT 1 AS grp
            FROM email_tracked_sends s
            WHERE s.campaign_id IS NULL AND (' . $where . ')
            GROUP BY s.kind, ' . $dayExpr . ', s.subject
        ) batches
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $params));

    return (int) $stmt->fetchColumn();
}

/**
 * @param array{
 *   campaign_id?: int,
 *   kind?: string,
 *   day?: string,
 *   subject?: string,
 *   opened?: string,
 *   q?: string
 * } $filters
 * @return array{0: string, 1: list<mixed>}
 */
function email_tracking_batch_detail_filter_sql(array $filters): array
{
    $where = [];
    $params = [];
    $campaignId = (int) ($filters['campaign_id'] ?? 0);
    if ($campaignId > 0) {
        $where[] = 's.campaign_id = ?';
        $params[] = $campaignId;
    } else {
        $where[] = 's.campaign_id IS NULL';
        $kind = trim((string) ($filters['kind'] ?? ''));
        if ($kind !== '') {
            $where[] = 's.kind = ?';
            $params[] = $kind;
        }
        $day = trim((string) ($filters['day'] ?? ''));
        if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $where[] = email_tracking_sent_day_sql('s') . ' = ?';
            $params[] = $day;
        }
        if (array_key_exists('subject', $filters)) {
            $where[] = 's.subject = ?';
            $params[] = (string) $filters['subject'];
        }
    }

    $opened = trim((string) ($filters['opened'] ?? ''));
    if ($opened === 'yes') {
        $where[] = 's.opened_at IS NOT NULL';
    } elseif ($opened === 'no') {
        $where[] = 's.opened_at IS NULL';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(s.to_email LIKE ? OR s.subject LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    return [$where === [] ? '1=1' : implode(' AND ', $where), $params];
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function email_tracking_batch_sends(array $filters, int $limit = 50, int $offset = 0): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    [$where, $params] = email_tracking_batch_detail_filter_sql($filters);
    $stmt = Database::instance()->pdo()->prepare('
        SELECT s.*
        FROM email_tracked_sends s
        WHERE ' . $where . '
        ORDER BY s.sent_at DESC, s.id DESC
        LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

/**
 * @param array<string, mixed> $filters
 */
function email_tracking_batch_sends_count(array $filters): int
{
    [$where, $params] = email_tracking_batch_detail_filter_sql($filters);
    $stmt = Database::instance()->pdo()->prepare('
        SELECT COUNT(*) FROM email_tracked_sends s WHERE ' . $where
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * @param array<string, mixed> $filters
 * @return array{tracked: int, opened: int, open_rate: float}
 */
function email_tracking_batch_stats(array $filters): array
{
    [$where, $params] = email_tracking_batch_detail_filter_sql([
        'campaign_id' => $filters['campaign_id'] ?? null,
        'kind' => $filters['kind'] ?? null,
        'day' => $filters['day'] ?? null,
        'subject' => $filters['subject'] ?? null,
    ]);
    $stmt = Database::instance()->pdo()->prepare('
        SELECT
            COUNT(*) AS tracked,
            SUM(CASE WHEN s.opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened
        FROM email_tracked_sends s
        WHERE ' . $where
    );
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    $tracked = (int) ($row['tracked'] ?? 0);
    $opened = (int) ($row['opened'] ?? 0);

    return [
        'tracked' => $tracked,
        'opened' => $opened,
        'open_rate' => $tracked > 0 ? round(($opened / $tracked) * 100, 1) : 0.0,
    ];
}

/** @return list<string> */
function email_tracking_distinct_kinds(): array
{
    $stmt = Database::instance()->pdo()->query('
        SELECT DISTINCT kind FROM email_tracked_sends ORDER BY kind ASC
    ');
    $kinds = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $kind = trim((string) ($row['kind'] ?? ''));
        if ($kind !== '') {
            $kinds[] = $kind;
        }
    }

    return $kinds;
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

/**
 * @param array<string, scalar|null> $params
 */
function email_tracker_url(array $params = []): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '' || $value === 'all' || $value === 0 || $value === '0') {
            continue;
        }
        $clean[$key] = $value;
    }

    return '/admin/email-tracker' . ($clean !== [] ? '?' . http_build_query($clean) : '');
}
