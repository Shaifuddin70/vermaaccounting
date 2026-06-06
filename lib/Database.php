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
                status ENUM("pending", "complete") NOT NULL DEFAULT "pending",
                tax_year SMALLINT UNSIGNED DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                KEY idx_submissions_form (form_id),
                KEY idx_submissions_status (status),
                KEY idx_submissions_tax_year (tax_year),
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

            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM("admin", "reviewer") NOT NULL DEFAULT "reviewer",
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uk_users_email (email),
                KEY idx_users_role (role),
                KEY idx_users_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS activity_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                user_name VARCHAR(191) NOT NULL DEFAULT "",
                action VARCHAR(64) NOT NULL,
                subject_type VARCHAR(64) NOT NULL DEFAULT "",
                subject_id INT UNSIGNED NULL,
                meta_json TEXT,
                ip VARCHAR(45),
                created_at DATETIME NOT NULL,
                KEY idx_log_user (user_id),
                KEY idx_log_subject (subject_type, subject_id),
                KEY idx_log_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $this->ensureSubmissionStatusColumn();
        $this->ensureSubmissionTaxYearColumn();
        $this->ensureFormsSiteCtaColumns();
        $this->ensureClientsTables();
        $this->ensureClientsCinColumn();
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
                status TEXT NOT NULL DEFAULT "pending",
                tax_year INTEGER,
                ip TEXT,
                user_agent TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT,
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

            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "reviewer",
                status TEXT NOT NULL DEFAULT "active",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                user_name TEXT NOT NULL DEFAULT "",
                action TEXT NOT NULL,
                subject_type TEXT NOT NULL DEFAULT "",
                subject_id INTEGER NULL,
                meta_json TEXT,
                ip TEXT,
                created_at TEXT NOT NULL
            );
        ');
        $this->ensureSubmissionStatusColumn();
        $this->ensureSubmissionTaxYearColumn();
        $this->ensureFormsSiteCtaColumns();
        $this->ensureClientsTables();
        $this->ensureClientsCinColumn();
    }

    private function ensureClientsTables(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS clients (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    cin VARCHAR(64) DEFAULT NULL,
                    email VARCHAR(191) DEFAULT NULL,
                    phone VARCHAR(64) DEFAULT NULL,
                    company VARCHAR(255) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    source ENUM("import", "submission") NOT NULL DEFAULT "submission",
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uk_clients_cin (cin),
                    KEY idx_clients_email (email),
                    KEY idx_clients_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS client_submissions (
                    client_id INT UNSIGNED NOT NULL,
                    submission_id INT UNSIGNED NOT NULL,
                    PRIMARY KEY (client_id, submission_id),
                    KEY idx_cs_submission (submission_id),
                    CONSTRAINT fk_cs_client
                        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                    CONSTRAINT fk_cs_submission
                        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ');
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                cin TEXT,
                email TEXT,
                phone TEXT,
                company TEXT,
                notes TEXT,
                source TEXT NOT NULL DEFAULT "submission",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS client_submissions (
                client_id INTEGER NOT NULL,
                submission_id INTEGER NOT NULL,
                PRIMARY KEY (client_id, submission_id),
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
            );
        ');
    }

    private function ensureClientsCinColumn(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('clients', 'cin')) {
                $this->pdo->exec('ALTER TABLE clients ADD COLUMN cin VARCHAR(64) DEFAULT NULL AFTER name');
            }
            if (!$this->indexExists('clients', 'uk_clients_cin')) {
                $this->pdo->exec('ALTER TABLE clients ADD UNIQUE KEY uk_clients_cin (cin)');
            }
            return;
        }

        if (!$this->sqliteColumnExists('clients', 'cin')) {
            $this->pdo->exec('ALTER TABLE clients ADD COLUMN cin TEXT');
        }
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uk_clients_cin ON clients(cin)');
    }

    private function ensureFormsSiteCtaColumns(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('forms', 'is_site_cta')) {
                $this->pdo->exec('ALTER TABLE forms ADD COLUMN is_site_cta TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
            }
            if (!$this->columnExists('forms', 'cta_label')) {
                $this->pdo->exec('ALTER TABLE forms ADD COLUMN cta_label VARCHAR(191) DEFAULT NULL AFTER is_site_cta');
            }
            return;
        }

        if (!$this->sqliteColumnExists('forms', 'is_site_cta')) {
            $this->pdo->exec('ALTER TABLE forms ADD COLUMN is_site_cta INTEGER NOT NULL DEFAULT 0');
        }
        if (!$this->sqliteColumnExists('forms', 'cta_label')) {
            $this->pdo->exec('ALTER TABLE forms ADD COLUMN cta_label TEXT');
        }
    }

    private function ensureSubmissionTaxYearColumn(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('submissions', 'tax_year')) {
                $this->pdo->exec('ALTER TABLE submissions ADD COLUMN tax_year SMALLINT UNSIGNED DEFAULT NULL AFTER status');
                $this->pdo->exec('ALTER TABLE submissions ADD KEY idx_submissions_tax_year (tax_year)');
            }
            return;
        }

        if (!$this->sqliteColumnExists('submissions', 'tax_year')) {
            $this->pdo->exec('ALTER TABLE submissions ADD COLUMN tax_year INTEGER');
        }
    }

    private function ensureSubmissionStatusColumn(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('submissions', 'status')) {
                $this->pdo->exec("
                    ALTER TABLE submissions
                    ADD COLUMN status ENUM('pending', 'complete') NOT NULL DEFAULT 'pending' AFTER data_json,
                    ADD KEY idx_submissions_status (status)
                ");
            }
            if (!$this->columnExists('submissions', 'updated_at')) {
                $this->pdo->exec('ALTER TABLE submissions ADD COLUMN updated_at DATETIME NULL AFTER created_at');
                $this->pdo->exec('UPDATE submissions SET updated_at = created_at WHERE updated_at IS NULL');
            }
            return;
        }

        if (!$this->sqliteColumnExists('submissions', 'status')) {
            $this->pdo->exec('ALTER TABLE submissions ADD COLUMN status TEXT NOT NULL DEFAULT "pending"');
        }
        if (!$this->sqliteColumnExists('submissions', 'updated_at')) {
            $this->pdo->exec('ALTER TABLE submissions ADD COLUMN updated_at TEXT');
            $this->pdo->exec('UPDATE submissions SET updated_at = created_at WHERE updated_at IS NULL');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
            return false;
        }
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->pdo->quote($column));
        return (bool) $stmt->fetch();
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $index)) {
            return false;
        }
        $stmt = $this->pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->pdo->quote($index));
        return (bool) $stmt->fetch();
    }

    private function sqliteColumnExists(string $table, string $column): bool
    {
        $cols = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }
}
