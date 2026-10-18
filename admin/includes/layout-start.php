<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';

require_once __DIR__ . '/flash-toasts.php';
$adminToastMessages = array_merge(
    admin_pull_toast_messages(),
    $adminToastMessages ?? []
);
if (function_exists('app_begin_output_filter')) {
    app_begin_output_filter();
}
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
  <link rel="stylesheet" href="/admin/css/admin.css?v=127">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.36/dist/fancybox/fancybox.css">
  <?php if (!empty($loadCmsAdmin)): ?>
  <link rel="stylesheet" href="<?= e(asset_versioned('admin/css/cms-admin.css')) ?>">
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
      <?php if (Auth::can('reports.view')): ?>
        <a href="/admin/reports" class="admin-nav-item <?= $activeNav === 'reports' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg></span>
          <span class="admin-nav-label">Reports</span>
        </a>
      <?php endif; ?>

      <span class="admin-nav-section">Work</span>
      <?php if (Auth::can('submissions.view')): ?>
      <a href="/admin/reviewer-submissions" class="admin-nav-item <?= $activeNav === 'submissions' ? 'active' : '' ?>">
        <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg></span>
        <span class="admin-nav-label">Submissions</span>
      </a>
      <?php endif; ?>
      <?php if (Auth::can('clients.view')): ?>
        <a href="/admin/clients" class="admin-nav-item <?= $activeNav === 'clients' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
          <span class="admin-nav-label">Clients</span>
        </a>
      <?php endif; ?>
      <?php if (Auth::can('invoices.view')): ?>
        <a href="/admin/invoices" class="admin-nav-item <?= $activeNav === 'invoices' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10H7v-2h10v2zm0-4H7V7h10v2z"/></svg></span>
          <span class="admin-nav-label">Invoices</span>
        </a>
      <?php endif; ?>

      <?php if (Auth::can('cms.view')): ?>
        <?php
          $__cmsNav = [
              ['cms', '/admin/cms', 'Website overview', 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z', null],
              ['cms-page', '/admin/cms-content?type=page', 'Pages', 'M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z', null],
              ['cms-article', '/admin/cms-content?type=article', 'Blog articles', 'M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z', null],
              ['cms-blog-ai', '/admin/cms-blog-generate', 'AI blog', 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z', 'cms.edit'],
              ['cms-city', '/admin/cms-content?type=city', 'City pages', 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z', null],
              ['cms-navigation', '/admin/cms-navigation', 'Navigation', 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z', 'cms.publish'],
              ['cms-folders', '/admin/cms-folders', 'Folders & tags', 'M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z', null],
              ['cms-media', '/admin/cms-media', 'Media library', 'M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z', null],
              ['cms-redirects', '/admin/cms-redirects', 'Redirects', 'M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z', 'cms.redirects'],
              ['cms-seo', '/admin/cms-seo', 'SEO audit', 'M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z', null],
              ['cms-technical', '/admin/cms-technical', 'Sitemap & robots', 'M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z', 'cms.seo'],
              ['cms-site-config', '/admin/cms-site-config', 'Site configuration', 'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z', 'cms.code'],
              ['cms-blocks', '/admin/cms-blocks', 'Blocks', 'M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z', 'cms.code'],
              ['cms-stylesheets', '/admin/cms-stylesheets', 'Stylesheets', 'M12 3a9 9 0 0 0 0 18c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z', 'cms.code'],
              ['cms-migration', '/admin/cms-migration', 'Migration', 'M19 8l-4 4h3c0 3.31-2.69 6-6 6-1.01 0-1.97-.25-2.8-.7l-1.46 1.46C8.97 19.54 10.43 20 12 20c4.42 0 8-3.58 8-8h3l-4-4zM6 12c0-3.31 2.69-6 6-6 1.01 0 1.97.25 2.8.7l1.46-1.46C15.03 4.46 13.57 4 12 4c-4.42 0-8 3.58-8 8H1l4 4 4-4H6z', 'cms.redirects'],
              ['cms-integrations', '/admin/cms-integrations', 'Search data', 'M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z', null],
          ];
        ?>
        <span class="admin-nav-section">Website</span>
        <?php foreach ($__cmsNav as [$__key, $__href, $__label, $__icon, $__cap]): ?>
          <?php if ($__cap === null || Auth::can($__cap)): ?>
          <a href="<?= e($__href) ?>" class="admin-nav-item <?= $activeNav === $__key ? 'active' : '' ?>">
            <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="<?= e($__icon) ?>"/></svg></span>
            <span class="admin-nav-label"><?= e($__label) ?></span>
          </a>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (Auth::can('forms.view') || Auth::can('files.view')): ?>
        <span class="admin-nav-section">Content</span>
        <?php if (Auth::can('forms.view')): ?>
        <a href="/admin/forms" class="admin-nav-item <?= $activeNav === 'forms' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></span>
          <span class="admin-nav-label">All forms</span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('files.view')): ?>
        <a href="/admin/files" class="admin-nav-item <?= $activeNav === 'files' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg></span>
          <span class="admin-nav-label">Files</span>
        </a>
        <?php endif; ?>
      <?php endif; ?>

      <?php if (Auth::can('campaigns.view') || Auth::can('email.tracker') || Auth::can('email.holidays') || Auth::can('email.birthdays') || Auth::can('email.settings')): ?>
        <span class="admin-nav-section">Communications</span>
        <?php if (Auth::can('campaigns.view')): ?>
        <a href="/admin/campaigns" class="admin-nav-item <?= $activeNav === 'campaigns' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg></span>
          <span class="admin-nav-label">Campaigns</span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('email.tracker')): ?>
        <a href="/admin/email-tracker" class="admin-nav-item <?= $activeNav === 'email-tracker' || $activeNav === 'email-opens' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg></span>
          <span class="admin-nav-label">Email Tracker</span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('email.holidays')): ?>
        <a href="/admin/holiday-calendar" class="admin-nav-item <?= $activeNav === 'holidays' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/></svg></span>
          <span class="admin-nav-label">Holiday emails</span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('email.birthdays')): ?>
        <a href="/admin/birthday-emails" class="admin-nav-item <?= $activeNav === 'birthdays' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 6c1.11 0 2-.9 2-2 0-.38-.1-.73-.29-1.03L12 0l-1.71 2.97c-.19.3-.29.65-.29 1.03 0 1.1.9 2 2 2zm4.6 9.99l-1.07-1.07-1.08 1.07c-1.3 1.3-3.58 1.31-4.89 0l-1.07-1.07-1.09 1.07C6.29 17.21 5.55 18 4.62 18H1v2c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2v-2h-3.62c-.93 0-1.67-.79-2.78-1.01zM18 9h-5V7h-2v2H6c-1.66 0-3 1.34-3 3v2h2.12c.53 0 1.04.21 1.41.59l1.07 1.07 1.07-1.07c1.31-1.31 3.59-1.31 4.9 0l1.07 1.07 1.07-1.07c.37-.37.88-.59 1.41-.59H21v-2c0-1.66-1.34-3-3-3z"/></svg></span>
          <span class="admin-nav-label">Birthday emails</span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('email.settings')): ?>
        <a href="/admin/email-settings" class="admin-nav-item <?= $activeNav === 'email' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg></span>
          <span class="admin-nav-label">Email settings</span>
        </a>
        <?php endif; ?>
      <?php endif; ?>

      <?php if (Auth::can('team.manage') || Auth::can('activity.view')): ?>
        <span class="admin-nav-section">Settings</span>
        <?php if (Auth::can('team.manage')): ?>
        <a href="/admin/users" class="admin-nav-item <?= $activeNav === 'users' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
          <span class="admin-nav-label">Team</span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('activity.view')): ?>
        <a href="/admin/activity-log" class="admin-nav-item <?= $activeNav === 'activity' ? 'active' : '' ?>">
          <span class="admin-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg></span>
          <span class="admin-nav-label">Activity log</span>
        </a>
        <?php endif; ?>
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
      <?php
        $__clockTz = app_timezone_id();
        $__clockNow = new DateTimeImmutable('now', app_timezone($__clockTz));
        $__clockLabel = app_timezone_label($__clockTz);
      ?>
      <div
        class="admin-topbar-clock"
        id="admin-topbar-clock"
        data-timezone="<?= e($__clockTz) ?>"
        title="<?= e($__clockLabel) ?>"
      >
        <time
          class="admin-topbar-clock-value"
          datetime="<?= e($__clockNow->format(DateTimeInterface::ATOM)) ?>"
        ><?= e($__clockNow->format('D, M j, Y · g:i:s A')) ?></time>
        <span class="admin-topbar-clock-tz"><?= e($__clockLabel) ?></span>
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
            <span class="admin-topbar-user-role"><?= e(permission_role_label((string) ($__currentUser['role'] ?? ''))) ?></span>
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
