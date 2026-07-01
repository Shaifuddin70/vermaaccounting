<?php

declare(strict_types=1);

final class EmailCampaignRepository
{
  private PDO $db;

  public function __construct()
  {
    $this->db = Database::instance()->pdo();
  }

  public function count(): int
  {
    return (int) $this->db->query('SELECT COUNT(*) FROM email_campaigns')->fetchColumn();
  }

  /** @return list<array<string, mixed>> */
  public function all(int $limit = 50, int $offset = 0): array
  {
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $stmt = $this->db->prepare('
      SELECT * FROM email_campaigns
      ORDER BY created_at DESC
      LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute();
    return $stmt->fetchAll();
  }

  public function find(int $id): ?array
  {
    $stmt = $this->db->prepare('SELECT * FROM email_campaigns WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
  }

  /** @param array<string, mixed> $data */
  public function create(array $data): int
  {
    $now = now_iso();
    $stmt = $this->db->prepare('
      INSERT INTO email_campaigns (
        name, subject, body_html, status, scheduled_at,
        recipient_count, sent_count, failed_count,
        created_by_user_id, created_by_name,
        started_at, completed_at, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
      (string) ($data['name'] ?? ''),
      (string) ($data['subject'] ?? ''),
      (string) ($data['body_html'] ?? ''),
      (string) ($data['status'] ?? 'draft'),
      $data['scheduled_at'] ?? null,
      (int) ($data['recipient_count'] ?? 0),
      (int) ($data['sent_count'] ?? 0),
      (int) ($data['failed_count'] ?? 0),
      $data['created_by_user_id'] ?? null,
      (string) ($data['created_by_name'] ?? ''),
      $data['started_at'] ?? null,
      $data['completed_at'] ?? null,
      $now,
      $now,
    ]);
    return (int) $this->db->lastInsertId();
  }

  /** @param array<string, mixed> $data */
  public function update(int $id, array $data): void
  {
    $fields = [];
    $params = [];
    $allowed = [
      'name', 'subject', 'body_html', 'status', 'scheduled_at',
      'recipient_count', 'sent_count', 'failed_count',
      'started_at', 'completed_at',
    ];
    foreach ($allowed as $key) {
      if (array_key_exists($key, $data)) {
        $fields[] = $key . ' = ?';
        $params[] = $data[$key];
      }
    }
    if ($fields === []) {
      return;
    }
    $fields[] = 'updated_at = ?';
    $params[] = now_iso();
    $params[] = $id;
    $stmt = $this->db->prepare('UPDATE email_campaigns SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);
  }

  public function delete(int $id): void
  {
    $stmt = $this->db->prepare('DELETE FROM email_campaigns WHERE id = ?');
    $stmt->execute([$id]);
  }

  public function clearRecipients(int $campaignId): void
  {
    $stmt = $this->db->prepare('DELETE FROM email_campaign_recipients WHERE campaign_id = ?');
    $stmt->execute([$campaignId]);
  }

  /** @param list<array<string, mixed>> $recipients */
  public function addRecipients(int $campaignId, array $recipients): int
  {
    if ($recipients === []) {
      return 0;
    }

    $stmt = $this->db->prepare('
      INSERT INTO email_campaign_recipients (campaign_id, client_id, email, client_name, status)
      VALUES (?, ?, ?, ?, \'pending\')
    ');
    $added = 0;
    foreach ($recipients as $recipient) {
      $email = strtolower(trim((string) ($recipient['email'] ?? '')));
      if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
      }
      $stmt->execute([
        $campaignId,
        $recipient['client_id'] ?? null,
        $email,
        (string) ($recipient['client_name'] ?? ''),
      ]);
      $added++;
    }
    return $added;
  }

  public function countRecipients(int $campaignId, ?string $status = null): int
  {
    $sql = 'SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id = ?';
    $params = [$campaignId];
    if ($status !== null) {
      $sql .= ' AND status = ?';
      $params[] = $status;
    }
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
  }

  /** @return list<array<string, mixed>> */
  public function recipients(int $campaignId, int $limit = 50, int $offset = 0, ?string $status = null): array
  {
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $sql = 'SELECT * FROM email_campaign_recipients WHERE campaign_id = ?';
    $params = [$campaignId];
    if ($status !== null) {
      $sql .= ' AND status = ?';
      $params[] = $status;
    }
    $sql .= ' ORDER BY id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  /** @return list<array<string, mixed>> */
  public function pendingRecipients(int $campaignId, int $limit): array
  {
    $limit = max(1, min(100, $limit));
    $stmt = $this->db->prepare('
      SELECT * FROM email_campaign_recipients
      WHERE campaign_id = ? AND status = \'pending\'
      ORDER BY id ASC
      LIMIT ' . $limit
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll();
  }

  public function markRecipientSent(int $recipientId): void
  {
    $stmt = $this->db->prepare('
      UPDATE email_campaign_recipients
      SET status = \'sent\', sent_at = ?, error_message = NULL
      WHERE id = ?
    ');
    $stmt->execute([now_iso(), $recipientId]);
  }

  public function markRecipientFailed(int $recipientId, string $error): void
  {
    $stmt = $this->db->prepare('
      UPDATE email_campaign_recipients
      SET status = \'failed\', error_message = ?
      WHERE id = ?
    ');
    $stmt->execute([mb_substr($error, 0, 500), $recipientId]);
  }

  public function refreshCounts(int $campaignId): void
  {
    $stmt = $this->db->prepare('
      SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) AS failed
      FROM email_campaign_recipients
      WHERE campaign_id = ?
    ');
    $stmt->execute([$campaignId]);
    $row = $stmt->fetch() ?: [];
    $this->update($campaignId, [
      'recipient_count' => (int) ($row['total'] ?? 0),
      'sent_count' => (int) ($row['sent'] ?? 0),
      'failed_count' => (int) ($row['failed'] ?? 0),
    ]);
  }

  /** @return list<array<string, mixed>> */
  public function campaignsDueForSending(int $limit = 5): array
  {
    $limit = max(1, min(20, $limit));
    $now = now_iso();
    $stmt = $this->db->prepare('
      SELECT * FROM email_campaigns
      WHERE (
        status = \'sending\'
        OR (status = \'scheduled\' AND scheduled_at IS NOT NULL AND scheduled_at <= ?)
      )
      ORDER BY COALESCE(scheduled_at, created_at) ASC
      LIMIT ' . $limit
    );
    $stmt->execute([$now]);
    return $stmt->fetchAll();
  }

  public function cancel(int $campaignId): bool
  {
    $campaign = $this->find($campaignId);
    if (!$campaign || !in_array($campaign['status'], ['draft', 'scheduled', 'sending'], true)) {
      return false;
    }
    $this->update($campaignId, [
      'status' => 'cancelled',
      'completed_at' => now_iso(),
    ]);
    return true;
  }

  /** @return array{sending: int, scheduled_due: int, scheduled_future: int, draft: int, failed: int, sent: int} */
  public function queueDiagnostics(): array
  {
    $now = now_iso();
    $count = function (string $sql, array $params = []): int {
      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
      return (int) $stmt->fetchColumn();
    };

    return [
      'sending' => $count('SELECT COUNT(*) FROM email_campaigns WHERE status = \'sending\''),
      'scheduled_due' => $count(
        'SELECT COUNT(*) FROM email_campaigns WHERE status = \'scheduled\' AND scheduled_at IS NOT NULL AND scheduled_at <= ?',
        [$now]
      ),
      'scheduled_future' => $count(
        'SELECT COUNT(*) FROM email_campaigns WHERE status = \'scheduled\' AND scheduled_at IS NOT NULL AND scheduled_at > ?',
        [$now]
      ),
      'draft' => $count('SELECT COUNT(*) FROM email_campaigns WHERE status = \'draft\''),
      'failed' => $count('SELECT COUNT(*) FROM email_campaigns WHERE status = \'failed\''),
      'sent' => $count('SELECT COUNT(*) FROM email_campaigns WHERE status = \'sent\''),
    ];
  }
}
