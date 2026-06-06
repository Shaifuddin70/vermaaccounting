<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$formRepo = new FormRepository();
$uploadRepo = new UploadRepository();

$formId = isset($_GET['form_id']) && $_GET['form_id'] !== '' ? (int) $_GET['form_id'] : null;
if ($formId !== null && $formId < 1) {
    $formId = null;
}

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$stats = $uploadRepo->storageStats();
$totalFiles = $uploadRepo->countFiles($formId, $search);
$files = $uploadRepo->listFiles($formId, $search, $perPage, $offset);
$forms = $formRepo->all();
$totalPages = max(1, (int) ceil($totalFiles / $perPage));

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = Auth::csrfToken();
$pageTitle = 'Files';
$activeNav = 'files';

function files_page_url(?int $formId, string $search, int $page = 1): string
{
    $params = [];
    if ($formId !== null && $formId > 0) {
        $params['form_id'] = $formId;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return '/admin/files.php' . ($params ? '?' . http_build_query($params) : '');
}

function files_return_qs(?int $formId, string $search, int $page): string
{
    $params = [];
    if ($formId !== null && $formId > 0) {
        $params['form_id'] = $formId;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return http_build_query($params);
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Files</h1>
</div>

<?php if ($flashSuccess): ?>
  <div class="admin-alert admin-alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="admin-alert admin-alert-error"><?= e($flashError) ?></div>
<?php endif; ?>

<div class="files-storage-card admin-card">
  <div class="files-storage-head">
    <div>
      <h2 class="files-storage-title">Storage</h2>
      <p class="files-storage-sub">
        <?= e(format_file_size($stats['used_bytes'])) ?> used of <?= e(format_file_size($stats['quota_bytes'])) ?> quota
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

<div class="admin-card files-filters-card">
  <form method="get" action="/admin/files.php" class="files-filter-form">
    <div class="admin-field files-filter-field">
      <label for="files-form">Form</label>
      <select id="files-form" name="form_id" onchange="this.form.submit()">
        <option value="">All forms</option>
        <?php foreach ($forms as $form): ?>
          <option value="<?= (int) $form['id'] ?>" <?= $formId === (int) $form['id'] ? 'selected' : '' ?>>
            <?= e($form['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="admin-field files-filter-field files-filter-field--search">
      <label for="files-search">Search</label>
      <input type="search" id="files-search" name="q" value="<?= e($search) ?>" placeholder="Filename or form name…">
    </div>
    <div class="files-filter-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
      <?php if ($formId || $search !== ''): ?>
        <a href="/admin/files.php" class="admin-btn admin-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card">
  <?php if (!$files): ?>
    <p class="files-empty">No uploaded files<?= ($formId || $search !== '') ? ' matching these filters' : '' ?>.</p>
  <?php else: ?>
    <table class="admin-table files-table">
      <thead>
        <tr>
          <th class="files-col-type">Type</th>
          <th>File</th>
          <th>Form</th>
          <th>Submission</th>
          <th>Size</th>
          <th>Uploaded</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($files as $file):
          $fileId = (int) $file['id'];
          $viewUrl = '/admin/view-file.php?file_id=' . $fileId;
          $downloadUrl = '/admin/download.php?file_id=' . $fileId;
          $submissionUrl = '/admin/submission.php?id=' . (int) $file['submission_id'] . '&form_id=' . (int) $file['form_id'];
          $isImage = is_image_mime($file['mime'] ?? null);
          $returnQs = files_return_qs($formId, $search, $page);
        ?>
          <tr>
            <td class="files-col-type">
              <?php if ($isImage): ?>
                <a href="<?= e($viewUrl) ?>" class="files-thumb-link" data-fancybox="admin-files" data-caption="<?= e($file['original_name']) ?>">
                  <img src="<?= e($viewUrl) ?>" alt="<?= e($file['original_name']) ?>" class="files-thumb" loading="lazy">
                </a>
              <?php else: ?>
                <span class="files-ext-badge"><?= e(file_extension_label($file['original_name'] ?? '', $file['mime'] ?? null)) ?></span>
              <?php endif; ?>
            </td>
            <td class="files-col-name">
              <a href="<?= e($downloadUrl) ?>" class="files-name"><?= e($file['original_name']) ?></a>
              <?php if (!empty($file['mime'])): ?>
                <span class="files-mime"><?= e($file['mime']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="/admin/submissions.php?form_id=<?= (int) $file['form_id'] ?>" class="files-form-link">
                <?= e($file['form_title']) ?>
              </a>
            </td>
            <td>
              <a href="<?= e($submissionUrl) ?>" class="files-submission-link">#<?= (int) $file['submission_id'] ?></a>
            </td>
            <td class="files-col-size"><?= e(format_file_size((int) ($file['size'] ?? 0))) ?></td>
            <td class="files-col-date"><?= e(substr((string) ($file['created_at'] ?? ''), 0, 10)) ?></td>
            <td class="admin-table-actions">
              <?php if ($isImage): ?>
                <a href="<?= e($viewUrl) ?>" class="admin-btn admin-btn-secondary admin-btn-sm" data-fancybox="admin-files" data-caption="<?= e($file['original_name']) ?>">View</a>
              <?php endif; ?>
              <a href="<?= e($downloadUrl) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Download</a>
              <form method="post" action="/admin/file-delete.php" class="inline-form"
                onsubmit="return confirm('Delete this file permanently? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="file_id" value="<?= $fileId ?>">
                <input type="hidden" name="return_qs" value="<?= e($returnQs) ?>">
                <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="files-pagination" aria-label="File list pages">
    <?php if ($page > 1): ?>
      <a href="<?= e(files_page_url($formId, $search, $page - 1)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">← Previous</a>
    <?php endif; ?>
    <span class="files-pagination-info">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalFiles) ?> files)</span>
    <?php if ($page < $totalPages): ?>
      <a href="<?= e(files_page_url($formId, $search, $page + 1)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Next →</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
