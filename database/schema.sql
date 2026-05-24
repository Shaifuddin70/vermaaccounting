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
