<?php

declare(strict_types=1);

final class ActivityLog
{
    /**
     * Record an event in the activity log.
     *
     * @param string   $action       e.g. "submission.status_changed", "submission.edited", "user.created"
     * @param string   $subjectType  e.g. "submission", "user", "form"
     * @param int|null $subjectId    The primary key of the subject record
     * @param array    $meta         Extra context stored as JSON
     */
    public static function record(
        string  $action,
        string  $subjectType = '',
        ?int    $subjectId   = null,
        array   $meta        = []
    ): void {
        try {
            $user     = Auth::currentUser();
            $userId   = $user['id'] ?? null;
            $userName = $user['name'] ?? 'System';
            $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

            $pdo = Database::instance()->pdo();
            $stmt = $pdo->prepare('
                INSERT INTO activity_log
                    (user_id, user_name, action, subject_type, subject_id, meta_json, ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                $userName,
                $action,
                $subjectType,
                $subjectId,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                $ip,
                now_iso(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break the application
        }
    }

    /**
     * Fetch log entries, newest first.
     *
     * @return array
     */
    public static function recent(
        int     $limit       = 50,
        ?int    $userId      = null,
        ?string $action      = null,
        ?string $subjectType = null,
        ?int    $subjectId   = null,
        int     $offset      = 0
    ): array {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        [$where, $params] = self::filterClause($userId, $action, $subjectType, $subjectId);

        $sql = 'SELECT * FROM activity_log';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        try {
            $pdo = Database::instance()->pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function count(
        ?int    $userId      = null,
        ?string $action      = null,
        ?string $subjectType = null,
        ?int    $subjectId   = null
    ): int {
        [$where, $params] = self::filterClause($userId, $action, $subjectType, $subjectId);
        $sql = 'SELECT COUNT(*) FROM activity_log';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        try {
            $pdo = Database::instance()->pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** @return array{0: string, 1: list<mixed>} */
    private static function filterClause(
        ?int    $userId,
        ?string $action,
        ?string $subjectType,
        ?int    $subjectId
    ): array {
        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($action !== null) {
            if (str_ends_with($action, '.')) {
                $where[] = 'action LIKE ?';
                $params[] = $action . '%';
            } else {
                $where[] = 'action = ?';
                $params[] = $action;
            }
        }
        if ($subjectType !== null) {
            $where[] = 'subject_type = ?';
            $params[] = $subjectType;
        }
        if ($subjectId !== null) {
            $where[] = 'subject_id = ?';
            $params[] = $subjectId;
        }

        return [implode(' AND ', $where), $params];
    }

    /** Human-readable label for a log action. */
    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'submission.status_changed' => 'Changed status',
            'submission.edited'         => 'Edited submission',
            'user.created'              => 'Created user',
            'user.updated'              => 'Updated user',
            'user.deactivated'          => 'Deactivated user',
            'user.activated'            => 'Activated user',
            'auth.login'                => 'Signed in',
            'auth.logout'               => 'Signed out',
            'file.deleted'              => 'Deleted file',
            'file.uploaded'             => 'Uploaded file',
            'file.renamed'              => 'Renamed file',
            'clients.synced'            => 'Synced clients',
            'clients.imported'          => 'Imported clients',
            default                     => ucwords(str_replace(['.', '_'], ' ', $action)),
        };
    }
}
