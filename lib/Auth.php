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
            header('Location: ' . app_url('/admin/login'));
            exit;
        }
    }

    /**
     * Require the logged-in user to have at least one of the given roles.
     * Admins always pass. Redirects to dashboard with an error if role missing.
     */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        $current = self::userRole();
        // admin can do everything
        if ($current === 'admin') {
            return;
        }
        foreach ($roles as $role) {
            if ($current === $role) {
                return;
            }
        }
        $_SESSION['flash_error'] = 'You do not have permission to access that page.';
        header('Location: /admin/');
        exit;
    }

    /**
     * Attempt login. Tries config admin by username first, then DB users by email.
     * Returns true on success.
     */
    public static function attempt(string $login, string $password): bool
    {
        // 1. Config-file admin (login by username, not email)
        $config = app_config();
        $configUser = $config['admin_username'] ?? 'admin';
        $configHash = $config['admin_password_hash'] ?? '';

        if (
            $configHash !== ''
            && !str_contains($configHash, 'REPLACE_WITH')
            && hash_equals($configUser, $login)
            && password_verify($password, $configHash)
        ) {
            self::startSession();
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id']   = null;          // null = config admin
            $_SESSION['admin_username']  = $configUser;
            $_SESSION['admin_user_name'] = 'Admin';
            $_SESSION['admin_user_role'] = 'admin';
            $_SESSION['admin_user_email'] = '';
            $profile = config_admin_profile();
            if ($profile['name'] !== '') {
                $_SESSION['admin_user_name'] = $profile['name'];
            }
            if ($profile['email'] !== '') {
                $_SESSION['admin_user_email'] = $profile['email'];
            }
            $_SESSION['admin_user_avatar'] = $profile['avatar_path'] ?? '';
            return true;
        }

        // 2. DB user (login by email)
        try {
            $pdo = Database::instance()->pdo();
            $stmt = $pdo->prepare(
                "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1"
            );
            $stmt->execute([$login]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            // DB might not have users table yet on first boot — fail gracefully
            return false;
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in']  = true;
        $_SESSION['admin_user_id']    = (int) $user['id'];
        $_SESSION['admin_username']   = $user['email'];
        $_SESSION['admin_user_name']  = $user['name'];
        $_SESSION['admin_user_role']  = $user['role'];
        $_SESSION['admin_user_email'] = $user['email'];
        $_SESSION['admin_user_avatar'] = (string) ($user['avatar_path'] ?? '');
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

    /**
     * Return the currently logged-in user as an array.
     * Returns null if not logged in.
     */
    public static function currentUser(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id'    => $_SESSION['admin_user_id'] ?? null,
            'name'  => $_SESSION['admin_user_name'] ?? ($_SESSION['admin_username'] ?? 'Admin'),
            'email' => $_SESSION['admin_user_email'] ?? '',
            'role'  => $_SESSION['admin_user_role'] ?? 'admin',
            'avatar_path' => (string) ($_SESSION['admin_user_avatar'] ?? ''),
            'is_config_admin' => ($_SESSION['admin_user_id'] ?? null) === null,
        ];
    }

    /** @param array{name?: string, email?: string, avatar_path?: string} $profile */
    public static function syncProfileToSession(array $profile): void
    {
        self::startSession();
        if (array_key_exists('name', $profile)) {
            $_SESSION['admin_user_name'] = trim((string) $profile['name']);
        }
        if (array_key_exists('email', $profile)) {
            $_SESSION['admin_user_email'] = strtolower(trim((string) $profile['email']));
        }
        if (array_key_exists('avatar_path', $profile)) {
            $_SESSION['admin_user_avatar'] = trim((string) $profile['avatar_path']);
        }
    }

    /** Currently logged-in user ID (null for config admin). */
    public static function userId(): ?int
    {
        if (!self::check()) {
            return null;
        }
        $id = $_SESSION['admin_user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    /** Currently logged-in user display name. */
    public static function userName(): string
    {
        if (!self::check()) {
            return '';
        }
        return (string) ($_SESSION['admin_user_name'] ?? $_SESSION['admin_username'] ?? 'Admin');
    }

    /** Currently logged-in user role: admin, reviewer, or partner. */
    public static function userRole(): string
    {
        if (!self::check()) {
            return '';
        }
        return (string) ($_SESSION['admin_user_role'] ?? 'admin');
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
