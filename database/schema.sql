-- Verma Accounting — custom form builder (MySQL)
-- Create database first, then import this file in phpMyAdmin or:
--   mysql -u USER -p DATABASE_NAME < database/schema.sql

CREATE TABLE IF NOT EXISTS forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(191) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    schema_json LONGTEXT NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    is_site_cta TINYINT(1) NOT NULL DEFAULT 0,
    cta_label VARCHAR(191) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_forms_slug (slug),
    KEY idx_forms_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    data_json LONGTEXT NOT NULL,
    status ENUM('pending', 'complete') NOT NULL DEFAULT 'pending',
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

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    cin VARCHAR(64) DEFAULT NULL,
    email VARCHAR(191) DEFAULT NULL,
    phone VARCHAR(64) DEFAULT NULL,
    company VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    source ENUM('import', 'submission') NOT NULL DEFAULT 'submission',
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
