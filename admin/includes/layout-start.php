<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | Verma Form Builder</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/css/admin.css">
</head>
<body class="admin-body">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <a href="/admin/">Verma Forms</a>
      <span class="admin-brand-sub">Form Builder</span>
    </div>
    <nav class="admin-nav">
      <a href="/admin/" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="/admin/forms.php" class="<?= $activeNav === 'forms' ? 'active' : '' ?>">All forms</a>
      <a href="/admin/form-builder.php" class="<?= $activeNav === 'builder' ? 'active' : '' ?>">+ New form</a>
    </nav>
    <div class="admin-sidebar-foot">
      <a href="/" target="_blank" rel="noopener">View website</a>
      <a href="/admin/logout.php">Log out</a>
    </div>
  </aside>
  <main class="admin-main">
