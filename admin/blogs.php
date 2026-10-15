<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

header('Location: /admin/cms-content?type=article', true, 301);
exit;
