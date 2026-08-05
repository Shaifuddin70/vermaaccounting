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

        $dsn = $host === 'localhost'
            ? sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

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
        $this->ensureClientsSinColumn();
        $this->ensureClientsBirthdayColumns();
        $this->ensurePartnerRole();
        $this->ensureSubmissionPartnersTable();
        $this->ensureUsersReferenceCodeColumn();
        $this->ensureUsersAvatarColumn();
        $this->ensureAppSettingsTable();
        $this->ensureEmailCampaignsTables();
        $this->ensureHolidaySchedulesTable();
        $this->ensureFileFoldersTable();
        $this->ensureBlogsTable();
        $this->ensureInvoiceTables();
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
        $this->ensureClientsSinColumn();
        $this->ensureClientsBirthdayColumns();
        $this->ensurePartnerRole();
        $this->ensureSubmissionPartnersTable();
        $this->ensureUsersReferenceCodeColumn();
        $this->ensureUsersAvatarColumn();
        $this->ensureAppSettingsTable();
        $this->ensureEmailCampaignsTables();
        $this->ensureHolidaySchedulesTable();
        $this->ensureFileFoldersTable();
        $this->ensureBlogsTable();
        $this->ensureInvoiceTables();
    }

    private function ensureInvoiceTables(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS invoice_services (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    KEY idx_invoice_services_active (is_active),
                    KEY idx_invoice_services_sort (sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS invoices (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_number VARCHAR(32) NOT NULL,
                    client_id INT UNSIGNED DEFAULT NULL,
                    bill_to_company VARCHAR(255) DEFAULT NULL,
                    bill_to_name VARCHAR(255) DEFAULT NULL,
                    bill_to_street VARCHAR(255) DEFAULT NULL,
                    bill_to_city VARCHAR(128) DEFAULT NULL,
                    bill_to_province VARCHAR(128) DEFAULT NULL,
                    bill_to_postal VARCHAR(32) DEFAULT NULL,
                    bill_to_country VARCHAR(128) DEFAULT NULL,
                    invoice_date DATE NOT NULL,
                    due_date DATE NOT NULL,
                    currency VARCHAR(8) NOT NULL DEFAULT "CAD",
                    discount_percent DECIMAL(8, 4) NOT NULL DEFAULT 0,
                    subtotal DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    notes TEXT,
                    status ENUM("draft", "sent", "paid", "void") NOT NULL DEFAULT "draft",
                    created_by_user_id INT UNSIGNED DEFAULT NULL,
                    created_by_name VARCHAR(191) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uk_invoices_number (invoice_number),
                    KEY idx_invoices_client (client_id),
                    KEY idx_invoices_status (status),
                    KEY idx_invoices_date (invoice_date),
                    CONSTRAINT fk_invoices_client
                        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS invoice_items (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_id INT UNSIGNED NOT NULL,
                    service_id INT UNSIGNED DEFAULT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    quantity DECIMAL(12, 2) NOT NULL DEFAULT 1.00,
                    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                    sort_order INT NOT NULL DEFAULT 0,
                    KEY idx_invoice_items_invoice (invoice_id),
                    CONSTRAINT fk_invoice_items_invoice
                        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                    CONSTRAINT fk_invoice_items_service
                        FOREIGN KEY (service_id) REFERENCES invoice_services(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ');
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS invoice_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                unit_price REAL NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number TEXT NOT NULL UNIQUE,
                client_id INTEGER,
                bill_to_company TEXT,
                bill_to_name TEXT,
                bill_to_street TEXT,
                bill_to_city TEXT,
                bill_to_province TEXT,
                bill_to_postal TEXT,
                bill_to_country TEXT,
                invoice_date TEXT NOT NULL,
                due_date TEXT NOT NULL,
                currency TEXT NOT NULL DEFAULT "CAD",
                discount_percent REAL NOT NULL DEFAULT 0,
                subtotal REAL NOT NULL DEFAULT 0,
                discount_amount REAL NOT NULL DEFAULT 0,
                total REAL NOT NULL DEFAULT 0,
                notes TEXT,
                status TEXT NOT NULL DEFAULT "draft",
                created_by_user_id INTEGER,
                created_by_name TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS invoice_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                service_id INTEGER,
                name TEXT NOT NULL,
                description TEXT,
                unit_price REAL NOT NULL DEFAULT 0,
                quantity REAL NOT NULL DEFAULT 1,
                amount REAL NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES invoice_services(id) ON DELETE SET NULL
            );
        ');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_invoice_services_active ON invoice_services(is_active)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items(invoice_id)');
    }

    private function ensurePartnerRole(): void
    {
        if ($this->driver !== 'mysql') {
            return;
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $col = $stmt->fetch();
        $type = (string) ($col['Type'] ?? '');
        if ($type !== '' && !str_contains($type, 'partner')) {
            $this->pdo->exec("ALTER TABLE users MODIFY role ENUM('admin', 'reviewer', 'partner') NOT NULL DEFAULT 'reviewer'");
        }
    }

    private function ensureSubmissionPartnersTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS submission_partners (
                    submission_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    PRIMARY KEY (submission_id, user_id),
                    KEY idx_sp_user (user_id),
                    CONSTRAINT fk_sp_submission
                        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
                    CONSTRAINT fk_sp_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS submission_partners (
                submission_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                PRIMARY KEY (submission_id, user_id),
                FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_sp_user ON submission_partners(user_id)');
    }

    private function ensureUsersReferenceCodeColumn(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('users', 'reference_code')) {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN reference_code VARCHAR(64) DEFAULT NULL AFTER email');
            }
            if (!$this->indexExists('users', 'uk_users_reference_code')) {
                $this->pdo->exec('ALTER TABLE users ADD UNIQUE KEY uk_users_reference_code (reference_code)');
            }
            return;
        }

        if (!$this->sqliteColumnExists('users', 'reference_code')) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN reference_code TEXT');
        }
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uk_users_reference_code ON users(reference_code)');
    }

    private function ensureUsersAvatarColumn(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('users', 'avatar_path')) {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER reference_code');
            }
            return;
        }

        if (!$this->sqliteColumnExists('users', 'avatar_path')) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN avatar_path TEXT');
        }
    }

    private function ensureClientsTables(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS clients (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    sin VARCHAR(64) DEFAULT NULL,
                    email VARCHAR(191) DEFAULT NULL,
                    phone VARCHAR(64) DEFAULT NULL,
                    company VARCHAR(255) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    source ENUM("import", "submission") NOT NULL DEFAULT "submission",
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uk_clients_sin (sin),
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
                sin TEXT,
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

    private function ensureClientsSinColumn(): void
    {
        if ($this->driver === 'mysql') {
            if ($this->columnExists('clients', 'cin') && !$this->columnExists('clients', 'sin')) {
                if ($this->indexExists('clients', 'uk_clients_cin')) {
                    $this->pdo->exec('ALTER TABLE clients DROP INDEX uk_clients_cin');
                }
                $this->pdo->exec('ALTER TABLE clients CHANGE COLUMN cin sin VARCHAR(64) DEFAULT NULL');
            }
            if (!$this->columnExists('clients', 'sin')) {
                $this->pdo->exec('ALTER TABLE clients ADD COLUMN sin VARCHAR(64) DEFAULT NULL AFTER name');
            }
            if ($this->indexExists('clients', 'uk_clients_cin') && $this->columnExists('clients', 'sin')) {
                $this->pdo->exec('ALTER TABLE clients DROP INDEX uk_clients_cin');
            }
            if (!$this->indexExists('clients', 'uk_clients_sin')) {
                $this->pdo->exec('ALTER TABLE clients ADD UNIQUE KEY uk_clients_sin (sin)');
            }
            return;
        }

        if ($this->sqliteColumnExists('clients', 'cin') && !$this->sqliteColumnExists('clients', 'sin')) {
            $this->pdo->exec('ALTER TABLE clients ADD COLUMN sin TEXT');
            $this->pdo->exec('UPDATE clients SET sin = cin WHERE sin IS NULL OR sin = ""');
        }
        if (!$this->sqliteColumnExists('clients', 'sin')) {
            $this->pdo->exec('ALTER TABLE clients ADD COLUMN sin TEXT');
        }
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uk_clients_sin ON clients(sin)');
    }

    private function ensureClientsBirthdayColumns(): void
    {
        if ($this->driver === 'mysql') {
            if (!$this->columnExists('clients', 'date_of_birth')) {
                $this->pdo->exec('ALTER TABLE clients ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER company');
            }
            if (!$this->columnExists('clients', 'birthday_last_sent_year')) {
                $this->pdo->exec('ALTER TABLE clients ADD COLUMN birthday_last_sent_year SMALLINT UNSIGNED DEFAULT NULL AFTER date_of_birth');
            }
            if (!$this->indexExists('clients', 'idx_clients_dob')) {
                $this->pdo->exec('ALTER TABLE clients ADD KEY idx_clients_dob (date_of_birth)');
            }
            return;
        }

        if (!$this->sqliteColumnExists('clients', 'date_of_birth')) {
            $this->pdo->exec('ALTER TABLE clients ADD COLUMN date_of_birth TEXT');
        }
        if (!$this->sqliteColumnExists('clients', 'birthday_last_sent_year')) {
            $this->pdo->exec('ALTER TABLE clients ADD COLUMN birthday_last_sent_year INTEGER');
        }
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_clients_dob ON clients(date_of_birth)');
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

    private function ensureEmailCampaignsTables(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS email_campaigns (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    subject VARCHAR(500) NOT NULL,
                    body_html LONGTEXT NOT NULL,
                    status ENUM("draft", "scheduled", "sending", "sent", "cancelled", "failed") NOT NULL DEFAULT "draft",
                    scheduled_at DATETIME NULL,
                    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
                    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
                    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
                    created_by_user_id INT UNSIGNED NULL,
                    created_by_name VARCHAR(191) NOT NULL DEFAULT "",
                    started_at DATETIME NULL,
                    completed_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    KEY idx_campaigns_status (status),
                    KEY idx_campaigns_scheduled (scheduled_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS email_campaign_recipients (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    campaign_id INT UNSIGNED NOT NULL,
                    client_id INT UNSIGNED NULL,
                    email VARCHAR(191) NOT NULL,
                    client_name VARCHAR(255) NOT NULL DEFAULT "",
                    status ENUM("pending", "sent", "failed", "skipped") NOT NULL DEFAULT "pending",
                    error_message VARCHAR(500) NULL,
                    sent_at DATETIME NULL,
                    KEY idx_recipients_campaign (campaign_id),
                    KEY idx_recipients_status (campaign_id, status),
                    CONSTRAINT fk_recipients_campaign
                        FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ');
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS email_campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                subject TEXT NOT NULL,
                body_html TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                scheduled_at TEXT NULL,
                recipient_count INTEGER NOT NULL DEFAULT 0,
                sent_count INTEGER NOT NULL DEFAULT 0,
                failed_count INTEGER NOT NULL DEFAULT 0,
                created_by_user_id INTEGER NULL,
                created_by_name TEXT NOT NULL DEFAULT "",
                started_at TEXT NULL,
                completed_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS email_campaign_recipients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                client_id INTEGER NULL,
                email TEXT NOT NULL,
                client_name TEXT NOT NULL DEFAULT "",
                status TEXT NOT NULL DEFAULT "pending",
                error_message TEXT NULL,
                sent_at TEXT NULL,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
            )
        ');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_campaigns_status ON email_campaigns(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_recipients_campaign ON email_campaign_recipients(campaign_id)');
    }

    private function ensureHolidaySchedulesTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS holiday_email_schedules (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    holiday_month TINYINT UNSIGNED NOT NULL,
                    holiday_day TINYINT UNSIGNED NOT NULL,
                    date_rule VARCHAR(64) NOT NULL DEFAULT "",
                    send_time TIME NOT NULL DEFAULT "09:00:00",
                    subject VARCHAR(500) NOT NULL,
                    body_html LONGTEXT NOT NULL,
                    enabled TINYINT(1) NOT NULL DEFAULT 1,
                    last_sent_year SMALLINT UNSIGNED NULL,
                    last_campaign_id INT UNSIGNED NULL,
                    created_by_user_id INT UNSIGNED NULL,
                    created_by_name VARCHAR(191) NOT NULL DEFAULT "",
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    KEY idx_holiday_date (holiday_month, holiday_day),
                    KEY idx_holiday_enabled (enabled)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            if (!$this->columnExists('holiday_email_schedules', 'date_rule')) {
                $this->pdo->exec('ALTER TABLE holiday_email_schedules ADD COLUMN date_rule VARCHAR(64) NOT NULL DEFAULT "" AFTER holiday_day');
            }
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS holiday_email_schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                holiday_month INTEGER NOT NULL,
                holiday_day INTEGER NOT NULL,
                date_rule TEXT NOT NULL DEFAULT "",
                send_time TEXT NOT NULL DEFAULT "09:00:00",
                subject TEXT NOT NULL,
                body_html TEXT NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                last_sent_year INTEGER NULL,
                last_campaign_id INTEGER NULL,
                created_by_user_id INTEGER NULL,
                created_by_name TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ');
        if (!$this->sqliteColumnExists('holiday_email_schedules', 'date_rule')) {
            $this->pdo->exec('ALTER TABLE holiday_email_schedules ADD COLUMN date_rule TEXT NOT NULL DEFAULT ""');
        }
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_holiday_date ON holiday_email_schedules(holiday_month, holiday_day)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_holiday_enabled ON holiday_email_schedules(enabled)');
    }

    private function ensureFileFoldersTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS file_folders (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    parent_id INT UNSIGNED NULL,
                    name VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    KEY idx_folders_parent (parent_id),
                    CONSTRAINT fk_folders_parent
                        FOREIGN KEY (parent_id) REFERENCES file_folders(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            if (!$this->columnExists('submission_files', 'folder_id')) {
                $this->pdo->exec('
                    ALTER TABLE submission_files
                    ADD COLUMN folder_id INT UNSIGNED NULL AFTER submission_id,
                    ADD KEY idx_files_folder (folder_id)
                ');
            }
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS file_folders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (parent_id) REFERENCES file_folders(id) ON DELETE CASCADE
            )
        ');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_folders_parent ON file_folders(parent_id)');
        if (!$this->sqliteColumnExists('submission_files', 'folder_id')) {
            $this->pdo->exec('ALTER TABLE submission_files ADD COLUMN folder_id INTEGER NULL');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_files_folder ON submission_files(folder_id)');
        }
    }

    private function ensureAppSettingsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
                    value_json LONGTEXT NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key TEXT NOT NULL PRIMARY KEY,
                value_json TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ');
    }

    private function ensureBlogsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS blogs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    uplift_id VARCHAR(64) NOT NULL,
                    title VARCHAR(500) NOT NULL,
                    slug VARCHAR(191) NOT NULL,
                    excerpt TEXT,
                    content_html LONGTEXT NOT NULL,
                    featured_image VARCHAR(1000) DEFAULT NULL,
                    source_status VARCHAR(32) NOT NULL DEFAULT "DRAFT",
                    local_status ENUM("draft", "published") NOT NULL DEFAULT "draft",
                    publish_date DATE DEFAULT NULL,
                    publish_time TIME DEFAULT NULL,
                    author_name VARCHAR(191) DEFAULT NULL,
                    author_url VARCHAR(500) DEFAULT NULL,
                    seo_title VARCHAR(500) DEFAULT NULL,
                    seo_description TEXT,
                    focus_keyword VARCHAR(255) DEFAULT NULL,
                    seo_score SMALLINT UNSIGNED DEFAULT 0,
                    categories_json LONGTEXT,
                    tags_json LONGTEXT,
                    meta_json LONGTEXT,
                    freshness_json LONGTEXT,
                    custom_fields_json LONGTEXT,
                    source_updated_at VARCHAR(64) DEFAULT NULL,
                    last_synced_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uk_blogs_uplift_id (uplift_id),
                    UNIQUE KEY uk_blogs_slug (slug),
                    KEY idx_blogs_local_status (local_status),
                    KEY idx_blogs_publish_date (publish_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            return;
        }

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS blogs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uplift_id TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                excerpt TEXT,
                content_html TEXT NOT NULL,
                featured_image TEXT,
                source_status TEXT NOT NULL DEFAULT "DRAFT",
                local_status TEXT NOT NULL DEFAULT "draft",
                publish_date TEXT,
                publish_time TEXT,
                author_name TEXT,
                author_url TEXT,
                seo_title TEXT,
                seo_description TEXT,
                focus_keyword TEXT,
                seo_score INTEGER DEFAULT 0,
                categories_json TEXT,
                tags_json TEXT,
                meta_json TEXT,
                freshness_json TEXT,
                custom_fields_json TEXT,
                source_updated_at TEXT,
                last_synced_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_blogs_local_status ON blogs(local_status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_blogs_publish_date ON blogs(publish_date)');
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
