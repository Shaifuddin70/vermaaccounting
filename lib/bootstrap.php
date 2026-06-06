<?php

declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/..';
const DATA_DIR = PROJECT_ROOT . '/data';
const UPLOADS_DIR = DATA_DIR . '/uploads';

function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = PROJECT_ROOT . '/config.local.php';
    $example = PROJECT_ROOT . '/config.example.php';
    if (is_file($local)) {
        $config = require $local;
    } elseif (is_file($example)) {
        $config = require $example;
    } else {
        throw new RuntimeException('Missing config.local.php — copy config.example.php and set admin credentials.');
    }

    return $config;
}

function ensure_data_dirs(): void
{
    foreach ([DATA_DIR, UPLOADS_DIR, DATA_DIR . '/forms'] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/FormRepository.php';
require_once __DIR__ . '/UploadRepository.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/embed.php';
require_once __DIR__ . '/submission_helpers.php';
require_once __DIR__ . '/client_helpers.php';
require_once __DIR__ . '/ClientRepository.php';

ensure_data_dirs();

try {
    Database::instance()->migrate();
} catch (PDOException $e) {
    $hint = $e->getMessage();
    $msg = 'Database connection failed. Check database settings in config.local.php.';
    if (str_contains($hint, '2002') || str_contains($hint, 'Connection refused')) {
        $msg .= ' (Cannot reach MySQL — use MAMP MySQL port 8889, not web port 8888.)';
    } elseif (str_contains($hint, '1049')) {
        $msg .= ' (Database does not exist — create verma_forms in phpMyAdmin.)';
    } elseif (str_contains($hint, '1045')) {
        $msg .= ' (Wrong username or password.)';
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . ' [' . $hint . ']' . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit($msg);
}
