<?php

declare(strict_types=1);

final class SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = Database::instance()->pdo()->prepare(
            'SELECT value_json FROM app_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }

        $decoded = json_decode((string) ($row['value_json'] ?? ''), true);
        return $decoded !== null ? $decoded : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Could not encode settings.');
        }

        $now = now_iso();
        $pdo = Database::instance()->pdo();

        if (Database::instance()->driver() === 'mysql') {
            $stmt = $pdo->prepare('
                INSERT INTO app_settings (setting_key, value_json, updated_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = VALUES(updated_at)
            ');
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO app_settings (setting_key, value_json, updated_at)
                VALUES (?, ?, ?)
                ON CONFLICT(setting_key) DO UPDATE SET
                    value_json = excluded.value_json,
                    updated_at = excluded.updated_at
            ');
        }

        $stmt->execute([$key, $json, $now]);
    }

    /** @return array<string, mixed> */
    public function getMailSettings(): array
    {
        $stored = $this->get('mail', []);
        return is_array($stored) ? $stored : [];
    }

    /** @param array<string, mixed> $settings */
    public function saveMailSettings(array $settings): void
    {
        $this->set('mail', $settings);
    }
}
