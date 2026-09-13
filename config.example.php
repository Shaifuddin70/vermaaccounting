<?php
/**
 * Copy to config.local.php and set your values.
 *
 * config.local.php supports local (MAMP) + production (hosting) in one file.
 * See the template at the bottom of this file, or copy config.local.php from a teammate.
 *
 * Admin password hash:
 *   php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
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
        'application/zip',
        'application/x-zip-compressed',
        'application/x-zip',
        'application/x-pdf',
    ],

    'site_url' => 'https://vermaaccounting.ca',

    // Uplift AI Custom API token (Website Integration → Custom API)
    'uplift_ai' => [
        'api_token' => 'uai_YOUR_TOKEN_HERE',
    ],

    'mail' => [
        'enabled' => true,
        'transport' => 'smtp',
        'from_email' => 'info@vermaaccounting.ca',
        'from_name' => 'Verma Accounting',
        'admin_email' => 'info@vermaaccounting.ca',
        // Optional: multiple admin notification recipients (overridden by Admin → Email settings)
        'admin_emails' => ['info@vermaaccounting.ca'],
        'admin_name' => 'Verma Accounting',
        'smtp' => [
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
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

    // Public form anti-spam (honeypot + timing + HMAC token + IP/global rate limits)
    'form_spam' => [
        'per_ip_limit' => 2,
        'per_ip_window' => 3600,
        'global_limit' => 15,
        'global_window' => 600,
        'min_seconds' => 3,
        // Only enable if Apache/nginx strips client-supplied X-Forwarded-For / X-Real-IP.
        // Cloudflare CF-Connecting-IP is always preferred when present.
        'trust_proxy_headers' => false,
        // Abandoned staged uploads older than this are removed opportunistically.
        'staging_ttl_seconds' => 21600,
        // Optional: set a long random string in config.local.php
        // 'secret' => 'change-me-to-a-long-random-string',
    ],
];

/*
 * ── Dual environment config.local.php pattern ─────────────────────────────
 *
 * Set $environmentMode = 'auto' to switch automatically:
 *   localhost:8888 / 127.0.0.1  →  MAMP database, mail disabled
 *   vermaaccounting.ca          →  hosting database, mail enabled
 *
 * Force one environment: $environmentMode = 'local' or 'production'
 * CLI scripts: VERMA_ENV=production php your-script.php
 */
