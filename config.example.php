<?php
/**
 * Copy to config.local.php and set your values.
 *
 * Admin password hash:
 *   php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
 *
 * MySQL: create a database (e.g. verma_forms) in phpMyAdmin, then set credentials below.
 * MAMP: website = port 8888, MySQL = port 8889 (MAMP → Preferences → Ports).
 * Default MAMP MySQL user/password is usually root / root.
 */
return [
    'admin_username' => 'admin',
    'admin_password_hash' => '$2y$10$REPLACE_WITH_password_hash_FROM_PHP_COMMAND',
    'session_name' => 'verma_admin_session',

    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'verma_forms',
        'user' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4',
    ],

    /** Total storage quota for client uploads (shown in admin file manager). */
    'uploads_quota_bytes' => 15 * 1024 * 1024 * 1024,

    'max_upload_bytes' => 10 * 1024 * 1024,
    'allowed_upload_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ],

    /** Public site URL (used in admin email links). */
    'site_url' => 'https://vermaaccounting.ca',

    /**
     * Submission email notifications (admin alert + client confirmation).
     *
     * If your domain MX points to Microsoft 365 / Outlook (common with Namecheap + M365):
     *   transport: smtp, host: smtp.office365.com, username: your M365 mailbox
     *   Enable "Authenticated SMTP" for that mailbox in Microsoft 365 admin.
     *
     * If email is on cPanel hosting only:
     *   Add DNS A record: mail.yourdomain.ca → your server IP
     *   transport: smtp, host: mail.yourdomain.ca (or serverXXX.web-hosting.com)
     *   Or use transport "mail" when PHP runs on the same hosting server.
     */
    'mail' => [
        'enabled' => true,
        'transport' => 'smtp', // mail | smtp
        'from_email' => 'info@vermaaccounting.ca',
        'from_name' => 'Verma Accounting',
        'admin_email' => 'info@vermaaccounting.ca',
        'admin_name' => 'Verma Accounting',
        'smtp' => [
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls', // tls | ssl | none
            'username' => 'info@vermaaccounting.ca',
            'password' => 'YOUR_MAILBOX_PASSWORD',
        ],
        'admin_notification' => [
            'enabled' => true,
            'subject' => 'New submission: {form_title} (#{submission_id})',
        ],
        'client_confirmation' => [
            'enabled' => true,
            'subject' => 'We received your submission — {form_title}',
        ],
    ],
];
