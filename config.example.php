<?php
/**
 * Copy to config.local.php and set your admin password.
 * Generate hash: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
 */
return [
    'admin_username' => 'admin',
    'admin_password_hash' => '$2y$10$REPLACE_WITH_password_hash_FROM_PHP_COMMAND',
    'session_name' => 'verma_admin_session',
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
