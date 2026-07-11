<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';

require_once __DIR__ . '/flash-toasts.php';
$adminToastMessages = array_merge(
    admin_pull_toast_messages(),
    $adminToastMessages ?? []
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | Verma Accounting</title>
  <?php require __DIR__ . '/favicon.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/css/admin.css?v=59">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.36/dist/fancybox/fancybox.css">
  <?php if (!empty($loadEmailEditor)): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css">
  <?php endif; ?>
</head>
<body class="admin-body">
  <div class="admin-sidebar-overlay" id="admin-sidebar-overlay" aria-hidden="true"></div>
  <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
    <div class="admin-sidebar-head">
      <a href="/admin/" class="admin-brand">
        <img
          src="<?= asset('images/verma-accounting-logo.png') ?>"
          alt="Verma Accounting"
          class="admin-brand-logo"
          width="200"
          height="48" />
      </a>
      <button type="button" class="admin-sidebar-close" id="admin-sidebar-close" aria-label="Close menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <nav class="admin-nav" id="admin-nav" aria-label="Main">
      <span class="admin-nav-section">Overview</span>
      <a href="/admin/" class="admin-nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
        <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></span>
        <span class="admin-nav-label">Dashboard</span>
      </a>

      <span class="admin-nav-section">Work</span>
      <a href="/admin/reviewer-submissions" class="admin-nav-item <?= $activeNav === 'submissions' ? 'active' : '' ?>">
        <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg></span>
        <span class="admin-nav-label">Submissions</span>
      </a>
      <?php if (Auth::userRole() === 'admin'): ?>
        <a href="/admin/clients" class="admin-nav-item <?= $activeNav === 'clients' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
          <span class="admin-nav-label">Clients</span>
        </a>
      <?php endif; ?>

      <?php if (Auth::userRole() === 'admin'): ?>
        <span class="admin-nav-section">Content</span>
        <a href="/admin/forms" class="admin-nav-item <?= $activeNav === 'forms' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></span>
          <span class="admin-nav-label">All forms</span>
        </a>
        <a href="/admin/files" class="admin-nav-item <?= $activeNav === 'files' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg></span>
          <span class="admin-nav-label">Files</span>
        </a>

        <span class="admin-nav-section">Communications</span>
        <a href="/admin/campaigns" class="admin-nav-item <?= $activeNav === 'campaigns' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg></span>
          <span class="admin-nav-label">Campaigns</span>
        </a>
        <a href="/admin/holiday-calendar" class="admin-nav-item <?= $activeNav === 'holidays' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/></svg></span>
          <span class="admin-nav-label">Holiday emails</span>
        </a>
        <a href="/admin/email-settings" class="admin-nav-item <?= $activeNav === 'email' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg></span>
          <span class="admin-nav-label">Email settings</span>
        </a>

        <span class="admin-nav-section">Settings</span>
        <a href="/admin/users" class="admin-nav-item <?= $activeNav === 'users' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
          <span class="admin-nav-label">Team</span>
        </a>
        <a href="/admin/activity-log" class="admin-nav-item <?= $activeNav === 'activity' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg></span>
          <span class="admin-nav-label">Activity log</span>
        </a>
      <?php endif; ?>
    </nav>
  </aside>
  <main class="admin-main">
    <?php
      $__currentUser = Auth::currentUser();
      $__displayName = $__currentUser['name'] ?? 'Admin';
      $__initials = user_initials($__displayName);
      $__avatarUrl = user_avatar_url($__currentUser);
    ?>
    <header class="admin-page-topbar" id="admin-page-topbar">
      <button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="admin-sidebar">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <div class="admin-page-topbar-slot" id="admin-page-topbar-slot">
        <div class="admin-header admin-header--topbar" data-topbar-fallback>
          <h1><?= e($pageTitle) ?></h1>
        </div>
      </div>
      <div class="admin-topbar-user<?= $activeNav === 'profile' ? ' is-active' : '' ?>">
        <a href="/admin/profile" class="admin-topbar-user-main" title="Edit profile">
          <?php if ($__avatarUrl): ?>
            <img src="<?= e($__avatarUrl) ?>" alt="" class="admin-topbar-avatar admin-topbar-avatar--image">
          <?php else: ?>
            <span class="admin-topbar-avatar" aria-hidden="true"><?= e($__initials) ?></span>
          <?php endif; ?>
          <span class="admin-topbar-user-meta">
            <span class="admin-topbar-user-name"><?= e($__displayName) ?></span>
            <span class="admin-topbar-user-role"><?= e(ucfirst($__currentUser['role'] ?? 'admin')) ?></span>
          </span>
        </a>
        <div class="admin-topbar-user-tools">
          <a href="/" target="_blank" rel="noopener" class="admin-topbar-tool-btn" title="View website" aria-label="View website">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
          </a>
          <a href="/admin/logout" class="admin-topbar-tool-btn admin-topbar-tool-btn--logout" title="Log out" aria-label="Log out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
          </a>
        </div>
      </div>
    </header>
    <div class="admin-page">
