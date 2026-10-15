<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$_SESSION['flash_error'] = 'Blog posts are now managed in Website → Blog articles.';
header('Location: /admin/cms-content?type=article', true, 303);
exit;
