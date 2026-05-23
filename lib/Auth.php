<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(): void
    {
        $config = app_config();
        if (session_status() === PHP_SESSION_NONE) {
            session_name($config['session_name'] ?? 'verma_admin_session');
            session_start();
        }
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['admin_logged_in']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $config = app_config();
        $expectedUser = $config['admin_username'] ?? 'admin';
        $hash = $config['admin_password_hash'] ?? '';

        if ($hash === '' || str_contains($hash, 'REPLACE_WITH')) {
            return false;
        }

        if (!hash_equals($expectedUser, $username)) {
            return false;
        }

        if (!password_verify($password, $hash)) {
            return false;
        }

        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
