<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$uploadRepo = new UploadRepository();
$stats = $uploadRepo->storageStats();

$csrf = Auth::csrfToken();
$pageTitle = 'Files';
$activeNav = 'files';

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Files</h1>
  <div class="admin-header-actions">
    <button type="button" class="admin-btn" data-open-file-manager>Open file manager</button>
  </div>
</div>

<div class="files-storage-card admin-card">
  <div class="files-storage-head">
    <div>
      <h2 class="files-storage-title">Storage</h2>
      <p class="files-storage-sub">
        <?= e(format_file_size($stats['used_bytes'])) ?> used of <?= e(format_file_size($stats['quota_bytes'])) ?>
        &middot; <?= number_format($stats['file_count']) ?> file<?= $stats['file_count'] === 1 ? '' : 's' ?>
      </p>
    </div>
    <div class="files-storage-available">
      <span class="files-storage-available-value"><?= e(format_file_size($stats['available_bytes'])) ?></span>
      <span class="files-storage-available-label">available</span>
    </div>
  </div>
  <div class="files-storage-bar" role="progressbar"
    aria-valuenow="<?= (int) round($stats['percent_used']) ?>"
    aria-valuemin="0"
    aria-valuemax="100"
    aria-label="Storage used">
    <div class="files-storage-bar-fill <?= $stats['percent_used'] >= 90 ? 'files-storage-bar-fill--warn' : ($stats['percent_used'] >= 75 ? 'files-storage-bar-fill--caution' : '') ?>"
      style="width:<?= e((string) round($stats['percent_used'], 1)) ?>%"></div>
  </div>
  <div class="files-storage-meta">
    <span><?= e(number_format($stats['percent_used'], 1)) ?>% of quota used</span>
    <?php if ($stats['disk_free_bytes'] !== null): ?>
      <span><?= e(format_file_size($stats['disk_free_bytes'])) ?> free on disk</span>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card files-launch-card">
  <div class="files-launch-body">
    <div class="files-launch-icon" aria-hidden="true">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div>
      <h2 class="files-launch-title">Browse all uploaded files</h2>
      <p class="files-launch-text">
        View files in a grid, upload new files, rename, delete, select multiple items, and download as a ZIP archive.
      </p>
      <button type="button" class="admin-btn" data-open-file-manager>Open file manager</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/file-manager-modal.php'; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
