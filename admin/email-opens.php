<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$query = $_GET;
unset($query['view']);
$target = '/admin/email-tracker' . ($query !== [] ? '?' . http_build_query($query) : '');
header('Location: ' . $target, true, 301);
exit;
