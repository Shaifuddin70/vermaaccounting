<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
if (Auth::check()) {
    ActivityLog::record('auth.logout');
}
Auth::logout();
header('Location: /admin/login.php');
exit;
