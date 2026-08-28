<?php

declare(strict_types=1);

require_once __DIR__ . '/config_env.php';

function verma_configure_error_handling(): void
{
    static $configured = false;
    if ($configured) {
        return;
    }
    $configured = true;

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    if (PHP_SAPI === 'cli') {
        return;
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        error_log(sprintf('[PHP %d] %s in %s:%d', $severity, $message, $file, $line));

        return true;
    });

    set_exception_handler(static function (Throwable $e): void {
        error_log(sprintf(
            'Uncaught %s: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo verma_public_error_message();
        exit(1);
    });

    register_shutdown_function(static function (): void {
        static $handled = false;
        if ($handled) {
            return;
        }

        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        $handled = true;
        error_log(sprintf(
            '[PHP fatal %d] %s in %s:%d',
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo verma_public_error_message();
    });
}

function verma_public_error_message(): string
{
    return 'Something went wrong. Please try again later.';
}
