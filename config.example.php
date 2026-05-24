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
];
