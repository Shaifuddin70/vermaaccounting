<?php

declare(strict_types=1);

/**
 * CMS tables. Column types are abstract so one definition produces both MySQL and SQLite DDL.
 * Bump VERSION whenever a table or index is added so existing installs re-run ensure().
 */
final class CmsSchema
{
    public const VERSION = 5;

    /** @return array<string, array{columns: array<string, string>, primary?: list<string>, unique?: array<string, list<string>>, index?: array<string, list<string>>}> */
    public static function tables(): array
    {
        return [
            'cms_folders' => [
                'columns' => [
                    'id' => 'id',
                    'parent_id' => 'int_null',
                    'name' => 'str:191',
                    'slug' => 'str:191',
                    'url_prefix' => 'str:500',
                    'sort_order' => 'int',
                    'status' => 'str:16:active',
                    'landing_content_id' => 'int_null',
                    'default_type' => 'str:16:page',
                    'seo_defaults_json' => 'longtext',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ],
                'index' => ['idx_cms_folders_parent' => ['parent_id', 'sort_order']],
            ],
            'cms_contents' => [
                'columns' => [
                    'id' => 'id',
                    'type' => 'str:16:page',
                    'folder_id' => 'int_null',
                    'internal_name' => 'str:255',
                    'title' => 'str:500',
                    'h1' => 'str:500',
                    'slug' => 'str:191',
                    'path' => 'str:500',
                    'body_html' => 'longtext',
                    'body_mode' => 'str:16:sanitized',
                    'excerpt' => 'text',
                    'featured_media_id' => 'int_null',
                    'featured_image_url' => 'str:1000',
                    'featured_alt' => 'str:500',
                    'author_user_id' => 'int_null',
                    'author_name' => 'str:191',
                    'template' => 'str:64:full-width',
                    'language' => 'str:16:en-CA',
                    'status' => 'str:16:draft',
                    'publish_at' => 'datetime_null',
                    'expire_at' => 'datetime_null',
                    'published_at' => 'datetime_null',
                    'preview_token' => 'str_null:64',
                    'internal_notes' => 'text',
                    'faqs_json' => 'longtext',
                    'sort_order' => 'int',
                    'created_by' => 'int_null',
                    'updated_by' => 'int_null',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ],
                'unique' => ['uk_cms_contents_path' => ['path']],
                'index' => [
                    'idx_cms_contents_type_status' => ['type', 'status'],
                    'idx_cms_contents_status_publish' => ['status', 'publish_at'],
                    'idx_cms_contents_expire' => ['expire_at'],
                    'idx_cms_contents_folder' => ['folder_id', 'sort_order'],
                    'idx_cms_contents_published' => ['published_at'],
                ],
            ],
            'cms_seo' => [
                'columns' => [
                    'content_id' => 'int',
                    'seo_title' => 'str:500',
                    'meta_description' => 'text',
                    'robots_index' => 'str:16:inherit',
                    'robots_follow' => 'str:16:inherit',
                    'max_snippet' => 'sint_null',
                    'max_image_preview' => 'str:16',
                    'max_video_preview' => 'sint_null',
                    'robots_extra' => 'str:255',
                    'sitemap_include' => 'int:1',
                    'canonical_mode' => 'str:16:auto',
                    'canonical_url' => 'str:1000',
                    'og_title' => 'str:500',
                    'og_description' => 'text',
                    'og_image_id' => 'int_null',
                    'og_image_url' => 'str:1000',
                    'og_image_alt' => 'str:500',
                    'og_type' => 'str:32',
                    'twitter_card' => 'str:32',
                    'schema_json' => 'longtext',
                    'updated_at' => 'datetime',
                ],
                'primary' => ['content_id'],
            ],
            'cms_revisions' => [
                'columns' => [
                    'id' => 'id',
                    'content_id' => 'int',
                    'snapshot_json' => 'longtext',
                    'comment' => 'str:255',
                    'is_autosave' => 'int:0',
                    'user_id' => 'int_null',
                    'user_name' => 'str:191',
                    'created_at' => 'datetime',
                ],
                'index' => ['idx_cms_revisions_content' => ['content_id', 'created_at']],
            ],
            'cms_terms' => [
                'columns' => [
                    'id' => 'id',
                    'kind' => 'str:16:category',
                    'name' => 'str:191',
                    'slug' => 'str:191',
                    'description' => 'text',
                    'created_at' => 'datetime',
                ],
                'unique' => ['uk_cms_terms_kind_slug' => ['kind', 'slug']],
            ],
            'cms_content_terms' => [
                'columns' => [
                    'content_id' => 'int',
                    'term_id' => 'int',
                ],
                'primary' => ['content_id', 'term_id'],
                'index' => ['idx_cms_content_terms_term' => ['term_id']],
            ],
            'cms_media' => [
                'columns' => [
                    'id' => 'id',
                    'path' => 'str:500',
                    'original_name' => 'str:255',
                    'mime' => 'str:100',
                    'width' => 'int',
                    'height' => 'int',
                    'bytes' => 'int',
                    'alt' => 'str:500',
                    'caption' => 'str:500',
                    'credit' => 'str:255',
                    'created_by' => 'int_null',
                    'created_at' => 'datetime',
                ],
                'index' => ['idx_cms_media_created' => ['created_at']],
            ],
            'cms_city_data' => [
                'columns' => [
                    'content_id' => 'int',
                    'city' => 'str:191',
                    'region' => 'str:191',
                    'country' => 'str:100',
                    'primary_service' => 'str:191',
                    'related_services_json' => 'longtext',
                    'phone' => 'str:64',
                    'email' => 'str:191',
                    'address' => 'str:500',
                    'hours_json' => 'longtext',
                    'map_url' => 'str:1000',
                    'map_embed_url' => 'str:1000',
                    'nearby_json' => 'longtext',
                    'cta_json' => 'longtext',
                    'local_business' => 'int:0',
                ],
                'primary' => ['content_id'],
                'index' => ['idx_cms_city_city' => ['city']],
            ],
            'cms_redirects' => [
                'columns' => [
                    'id' => 'id',
                    'source_path' => 'str:500',
                    'target' => 'str:1000',
                    'status_code' => 'int:301',
                    'origin' => 'str:16:manual',
                    'hits' => 'int',
                    'last_hit_at' => 'datetime_null',
                    'note' => 'str:255',
                    'created_by' => 'int_null',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ],
                'unique' => ['uk_cms_redirects_source' => ['source_path']],
            ],
            'cms_url_history' => [
                'columns' => [
                    'id' => 'id',
                    'content_id' => 'int',
                    'old_path' => 'str:500',
                    'new_path' => 'str:500',
                    'user_id' => 'int_null',
                    'changed_at' => 'datetime',
                ],
                'index' => [
                    'idx_cms_url_history_content' => ['content_id'],
                    'idx_cms_url_history_old' => ['old_path'],
                ],
            ],
            'cms_site_config_revisions' => [
                'columns' => [
                    'id' => 'id',
                    'config_json' => 'longtext',
                    'comment' => 'str:255',
                    'user_id' => 'int_null',
                    'user_name' => 'str:191',
                    'created_at' => 'datetime',
                ],
            ],
            'cms_stylesheet_revisions' => [
                'columns' => [
                    'id' => 'id',
                    'file' => 'str:191',
                    'css' => 'longtext',
                    'hash' => 'str:64',
                    'comment' => 'str:255',
                    'user_id' => 'int_null',
                    'user_name' => 'str:191',
                    'created_at' => 'datetime',
                ],
                'index' => ['idx_cms_stylesheet_revisions_file' => ['file', 'created_at']],
            ],
            'cms_blocks' => [
                'columns' => [
                    'id' => 'id',
                    'token' => 'str:64',
                    'name' => 'str:191',
                    'description' => 'str:500',
                    'code' => 'longtext',
                    'status' => 'str:16:active',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                    'updated_by_name' => 'str:191',
                ],
                'unique' => ['uk_cms_blocks_token' => ['token']],
            ],
            'cms_block_revisions' => [
                'columns' => [
                    'id' => 'id',
                    'block_id' => 'int',
                    'name' => 'str:191',
                    'code' => 'longtext',
                    'status' => 'str:16:active',
                    'comment' => 'str:255',
                    'user_id' => 'int_null',
                    'user_name' => 'str:191',
                    'created_at' => 'datetime',
                ],
                'index' => ['idx_cms_block_revisions_block' => ['block_id', 'created_at']],
            ],
            'cms_content_links' => [
                'columns' => [
                    'id' => 'id',
                    'content_id' => 'int',
                    'target' => 'str:1000',
                    'target_path' => 'str:500',
                    'is_internal' => 'int:0',
                    'kind' => 'str:8:a',
                    'anchor' => 'str:500',
                ],
                'index' => [
                    'idx_cms_links_content' => ['content_id'],
                    'idx_cms_links_target' => ['target_path'],
                ],
            ],
            'cms_migration_items' => [
                'columns' => [
                    'id' => 'id',
                    'source_url' => 'str:1000',
                    'source_path' => 'str:500',
                    'origin' => 'str:16:crawl',
                    'http_status' => 'int',
                    'title' => 'str:500',
                    'description' => 'text',
                    'canonical' => 'str:1000',
                    'robots' => 'str:255',
                    'h1' => 'str:500',
                    'body_html' => 'longtext',
                    'links_json' => 'longtext',
                    'images_json' => 'longtext',
                    'word_count' => 'int',
                    'content_type' => 'str:16:page',
                    'folder_id' => 'int_null',
                    'target_path' => 'str:500',
                    'action' => 'str:16:pending',
                    'content_id' => 'int_null',
                    'notes' => 'text',
                    'crawled_at' => 'datetime_null',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ],
                'unique' => ['uk_cms_migration_source' => ['source_path']],
            ],
        ];
    }

    public static function ensure(PDO $pdo, string $driver): void
    {
        $current = 0;
        try {
            $raw = $pdo->query("SELECT value_json FROM app_settings WHERE setting_key = 'cms_schema_version'")->fetchColumn();
            $current = $raw === false ? 0 : (int) json_decode((string) $raw, true);
        } catch (Throwable) {
            $current = 0;
        }
        if ($current >= self::VERSION) {
            return;
        }

        foreach (self::tables() as $table => $def) {
            foreach (self::createStatements($table, $def, $driver) as $sql) {
                $pdo->exec($sql);
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = VALUES(updated_at)');
        } else {
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)
                ON CONFLICT(setting_key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at');
        }
        $stmt->execute(['cms_schema_version', (string) self::VERSION, $now]);
    }

    /** @return list<string> */
    public static function createStatements(string $table, array $def, string $driver): array
    {
        $mysql = $driver === 'mysql';
        $lines = [];
        foreach ($def['columns'] as $name => $type) {
            $lines[] = $name . ' ' . self::columnSql($type, $mysql);
        }
        if (!empty($def['primary'])) {
            $lines[] = 'PRIMARY KEY (' . implode(', ', $def['primary']) . ')';
        }

        $out = [];
        if ($mysql) {
            foreach ($def['unique'] ?? [] as $name => $cols) {
                $lines[] = 'UNIQUE KEY ' . $name . ' (' . self::mysqlIndexCols($def['columns'], $cols) . ')';
            }
            foreach ($def['index'] ?? [] as $name => $cols) {
                $lines[] = 'KEY ' . $name . ' (' . self::mysqlIndexCols($def['columns'], $cols) . ')';
            }
            $out[] = 'CREATE TABLE IF NOT EXISTS ' . $table . " (\n  " . implode(",\n  ", $lines)
                . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            return $out;
        }

        $out[] = 'CREATE TABLE IF NOT EXISTS ' . $table . " (\n  " . implode(",\n  ", $lines) . "\n)";
        foreach ($def['unique'] ?? [] as $name => $cols) {
            $out[] = 'CREATE UNIQUE INDEX IF NOT EXISTS ' . $name . ' ON ' . $table . ' (' . implode(', ', $cols) . ')';
        }
        foreach ($def['index'] ?? [] as $name => $cols) {
            $out[] = 'CREATE INDEX IF NOT EXISTS ' . $name . ' ON ' . $table . ' (' . implode(', ', $cols) . ')';
        }
        return $out;
    }

    /** Long VARCHARs need a prefix length when combined in a MySQL index. */
    private static function mysqlIndexCols(array $columns, array $cols): string
    {
        $parts = [];
        foreach ($cols as $col) {
            $type = $columns[$col] ?? '';
            if (preg_match('/^str(?:_null)?:(\d+)/', $type, $m) && (int) $m[1] > 500 && count($cols) > 1) {
                $parts[] = $col . '(191)';
            } elseif (preg_match('/^str(?:_null)?:(\d+)/', $type, $m) && (int) $m[1] > 768) {
                $parts[] = $col . '(500)';
            } else {
                $parts[] = $col;
            }
        }
        return implode(', ', $parts);
    }

    private static function columnSql(string $type, bool $mysql): string
    {
        $parts = explode(':', $type, 3);
        $kind = $parts[0];
        switch ($kind) {
            case 'id':
                return $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
            case 'int':
                $default = isset($parts[1]) ? (int) $parts[1] : 0;
                return ($mysql ? 'INT' : 'INTEGER') . ' NOT NULL DEFAULT ' . $default;
            case 'int_null':
                return ($mysql ? 'INT UNSIGNED' : 'INTEGER') . ' DEFAULT NULL';
            case 'sint_null':
                return ($mysql ? 'INT' : 'INTEGER') . ' DEFAULT NULL';
            case 'str':
                $len = (int) ($parts[1] ?? 255);
                $default = $parts[2] ?? '';
                return ($mysql ? 'VARCHAR(' . $len . ')' : 'TEXT') . " NOT NULL DEFAULT '" . str_replace("'", "''", $default) . "'";
            case 'str_null':
                $len = (int) ($parts[1] ?? 255);
                return ($mysql ? 'VARCHAR(' . $len . ')' : 'TEXT') . ' DEFAULT NULL';
            case 'text':
                return 'TEXT';
            case 'longtext':
                return $mysql ? 'LONGTEXT' : 'TEXT';
            case 'datetime':
                return ($mysql ? 'DATETIME' : 'TEXT') . ' NOT NULL';
            case 'datetime_null':
                return ($mysql ? 'DATETIME' : 'TEXT') . ' DEFAULT NULL';
        }
        throw new InvalidArgumentException('Unknown CMS column type ' . $type);
    }
}
