<?php

declare(strict_types=1);

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;
    private string $driver;

    private function __construct()
    {
        $config = app_config();
        $db = $config['database'] ?? [];
        $this->driver = strtolower((string) ($db['driver'] ?? 'mysql'));

        if ($this->driver === 'sqlite') {
            $this->pdo = $this->connectSqlite($db);
        } elseif ($this->driver === 'mysql') {
            $this->pdo = $this->connectMysql($db);
        } else {
            throw new RuntimeException('Unsupported database driver: ' . $this->driver);
        }
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    private function connectMysql(array $db): PDO
    {
        $host = $db['host'] ?? '127.0.0.1';
        $port = (int) ($db['port'] ?? 3306);
        $name = $db['name'] ?? '';
        $user = $db['user'] ?? '';
        $pass = $db['password'] ?? '';
        $charset = $db['charset'] ?? 'utf8mb4';

        if ($name === '' || $user === '') {
            throw new RuntimeException('MySQL database name and user are required in config.local.php');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function connectSqlite(array $db): PDO
    {
        $path = $db['path'] ?? (DATA_DIR . '/forms.sqlite');
        return new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function migrate(): void
    {
        if ($this->driver === 'mysql') {
            $this->migrateMysql();
            return;
        }
        $this->migrateSqlite();
    }

    private function migrateMysql(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS forms (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(191) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                schema_json LONGTEXT NOT NULL,
                status ENUM("draft", "published") NOT NULL DEFAULT "draft",
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uk_forms_slug (slug),
                KEY idx_forms_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS submissions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                form_id INT UNSIGNED NOT NULL,
                data_json LONGTEXT NOT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_submissions_form (form_id),
                CONSTRAINT fk_submissions_form
                    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS submission_files (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submission_id INT UNSIGNED NOT NULL,
                field_id VARCHAR(64) NOT NULL,
                stored_name VARCHAR(512) NOT NULL,
                original_name VARCHAR(512) NOT NULL,
                mime VARCHAR(128) DEFAULT NULL,
                size INT UNSIGNED DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_files_submission (submission_id),
                CONSTRAINT fk_files_submission
                    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function migrateSqlite(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS forms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT DEFAULT "",
                schema_json TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                form_id INTEGER NOT NULL,
                data_json TEXT NOT NULL,
                ip TEXT,
                user_agent TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS submission_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                field_id TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                original_name TEXT NOT NULL,
                mime TEXT,
                size INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
            );
        ');
    }
}
