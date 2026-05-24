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
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (Auth::attempt($user, $pass)) {
        header('Location: /admin/');
        exit;
    }
    $error = 'Invalid username or password. Check config.local.php.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Verma Form Builder</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/css/admin.css">
</head>
<body>
  <div class="admin-login-wrap">
    <div class="admin-login-card">
      <h1>Verma Form Builder</h1>
      <p style="color:var(--verma-muted);margin:0 0 1.25rem;">Sign in to manage custom forms</p>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="admin-field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required autocomplete="username">
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
