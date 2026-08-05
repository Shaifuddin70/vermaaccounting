<?php

declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/..';
const DATA_DIR = PROJECT_ROOT . '/data';
const UPLOADS_DIR = DATA_DIR . '/uploads';
const EMAIL_ASSETS_DIR = DATA_DIR . '/email-assets';

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
    foreach ([DATA_DIR, UPLOADS_DIR, DATA_DIR . '/forms', UPLOADS_DIR . '/staging', UPLOADS_DIR . '/avatars', EMAIL_ASSETS_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    $stagingGuard = UPLOADS_DIR . '/staging/.htaccess';
    if (!is_file($stagingGuard)) {
        file_put_contents($stagingGuard, "Require all denied\n");
    }
    $emailAssetsGuard = EMAIL_ASSETS_DIR . '/.htaccess';
    if (!is_file($emailAssetsGuard)) {
        file_put_contents($emailAssetsGuard, "Require all denied\n");
    }
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/FormRepository.php';
require_once __DIR__ . '/UploadRepository.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/form_validation.php';
require_once __DIR__ . '/SettingsRepository.php';
require_once __DIR__ . '/timezone_helpers.php';
require_once __DIR__ . '/EmailCampaignRepository.php';
require_once __DIR__ . '/embed.php';
require_once __DIR__ . '/submission_helpers.php';
require_once __DIR__ . '/staging_uploads.php';
require_once __DIR__ . '/client_helpers.php';
require_once __DIR__ . '/ClientRepository.php';
require_once __DIR__ . '/pagination_helpers.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/submission_emails.php';
require_once __DIR__ . '/campaign_emails.php';
require_once __DIR__ . '/HolidayScheduleRepository.php';
require_once __DIR__ . '/holiday_emails.php';
require_once __DIR__ . '/canada_holidays.php';
require_once __DIR__ . '/birthday_emails.php';
require_once __DIR__ . '/FileFolderRepository.php';
require_once __DIR__ . '/profile_helpers.php';
require_once __DIR__ . '/partner_helpers.php';
require_once __DIR__ . '/email_assets.php';
require_once __DIR__ . '/UpliftAiClient.php';
require_once __DIR__ . '/BlogRepository.php';
require_once __DIR__ . '/blog_helpers.php';
require_once __DIR__ . '/InvoiceServiceRepository.php';
require_once __DIR__ . '/InvoiceRepository.php';
require_once __DIR__ . '/invoice_helpers.php';
require_once __DIR__ . '/InvoicePdf.php';
require_once __DIR__ . '/invoice_emails.php';

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
    } elseif (str_contains($hint, '2013') || str_contains($hint, 'handshake')) {
        $env = function_exists('app_environment') ? app_environment() : 'unknown';
        $msg .= ' (Cron may be using the wrong environment — current: ' . $env . '. On hosting, use VERMA_ENV=production in the cron command.)';
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . ' [' . $hint . ']' . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit($msg);
}
