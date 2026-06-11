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
  <link rel="stylesheet" href="/admin/css/admin.css?v=33">
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
      <p class="admin-login-sub">Sign in to your admin dashboard</p>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="admin-field">
          <label for="login">Email or username</label>
          <input type="text" id="login" name="login" required autocomplete="username"
            placeholder="you@example.com" autofocus
            value="<?= e(trim($_POST['login'] ?? '')) ?>">
        </div>
        <div class="admin-field">
          <label for="password">Password</label>
          <div class="umodal-pw-wrap">
            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Your password">
            <button type="button" class="umodal-pw-toggle" id="login-pw-toggle" aria-label="Toggle password visibility">
              <svg id="login-eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="login-eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="admin-btn" style="width:100%;justify-content:center;">Sign in</button>
      </form>
      <p class="admin-login-foot">
        <a href="/">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
          Back to website
        </a>
      </p>
    </div>
  </div>
  <script>
  (function () {
    var input = document.getElementById('password');
    var toggle = document.getElementById('login-pw-toggle');
    if (!input || !toggle) return;
    toggle.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      document.getElementById('login-eye-show').style.display = show ? 'none' : '';
      document.getElementById('login-eye-hide').style.display = show ? '' : 'none';
    });
  })();
  </script>
</body>
</html>
