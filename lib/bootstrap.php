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
require_once __DIR__ . '/helpers.php';

ensure_data_dirs();
Database::instance()->migrate();
