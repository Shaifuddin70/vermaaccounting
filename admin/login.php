<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

if (Auth::check()) {
    header('Location: /admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? $_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (Auth::attempt($login, $pass)) {
        ActivityLog::record('auth.login');
        header('Location: /admin/');
        exit;
    }
    $error = 'Invalid credentials. Check your email/username and password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in | Verma Accounting</title>
  <?php require __DIR__ . '/includes/favicon.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/css/admin.css?v=12">
</head>
<body class="admin-body">
  <div class="admin-login-wrap">
    <div class="admin-login-card">
      <img
        src="<?= asset('images/verma-accounting-logo.png') ?>"
        alt="Verma Accounting"
        class="admin-login-logo"
        width="240"
        height="58" />
      <p style="color:var(--verma-muted);margin:0 0 1.25rem;">Sign in to manage custom forms</p>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="admin-field">
          <label for="login">Email or username</label>
          <input type="text" id="login" name="login" required autocomplete="username">
        </div>
        <div class="admin-field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="admin-btn" style="width:100%;justify-content:center;">Sign in</button>
      </form>
    </div>
  </div>
</body>
</html>
