<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$repo = new BlogRepository();
$client = new UpliftAiClient();

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if (!in_array($statusFilter, ['published', 'draft', ''], true)) {
    $statusFilter = '';
}

$total = $repo->count($statusFilter !== '' ? $statusFilter : null);
$pagination = pagination_meta($total, $page, $perPage);
$blogs = $repo->all($statusFilter !== '' ? $statusFilter : null, $pagination['per_page'], $pagination['offset']);

$paginationPath = '/admin/blogs';
$paginationQuery = $statusFilter !== '' ? ['status' => $statusFilter] : [];
$paginationLabel = 'posts';
$paginationAriaLabel = 'Blog list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/blogs', $paginationQuery, $p, $pagination['per_page']);

$csrf = Auth::csrfToken();
$pageTitle = 'Blogs';
$activeNav = 'blogs';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Blogs</h1>
  <div class="admin-header-actions">
    <form method="post" action="/admin/blog-action" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <button type="submit" name="action" value="sync" class="admin-btn admin-btn-primary"
        <?= $client->isConfigured() ? '' : 'disabled title="' . e(app_is_local() ? 'Add uplift_ai.api_token in config.local.php' : 'Uplift AI is not configured.') . '"' ?>>
        Sync from Uplift AI
      </button>
    </form>
    <a href="https://app.upliftai.co/" target="_blank" rel="noopener" class="admin-btn admin-btn-secondary">Open Uplift AI</a>
  </div>
</div>

<div class="admin-card" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
  <span class="admin-field-hint" style="margin:0;">Filter:</span>
  <a href="/admin/blogs" class="admin-btn admin-btn-sm <?= $statusFilter === '' ? 'admin-btn-secondary' : 'admin-btn-secondary' ?>">All (<?= (int) $repo->count() ?>)</a>
  <a href="/admin/blogs?status=published" class="admin-btn admin-btn-sm admin-btn-secondary">Published (<?= (int) $repo->count('published') ?>)</a>
  <a href="/admin/blogs?status=draft" class="admin-btn admin-btn-sm admin-btn-secondary">Draft (<?= (int) $repo->count('draft') ?>)</a>
</div>

<?php if ($blogs === []): ?>
  <div class="admin-card admin-empty-state">
    <h2 class="admin-empty-state-title">No blogs synced yet</h2>
    <p class="admin-empty-state-text">Create posts in Uplift AI, then sync them here to publish on vermaaccounting.ca/blog.</p>
    <form method="post" action="/admin/blog-action">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <button type="submit" name="action" value="sync" class="admin-btn admin-btn-primary" <?= $client->isConfigured() ? '' : 'disabled' ?>>
        Sync from Uplift AI
      </button>
    </form>
  </div>
<?php else: ?>
  <div class="admin-card">
    <?php
      $paginationQuery = $statusFilter !== '' ? ['status' => $statusFilter] : [];
      $paginationShow = 'per_page';
      require __DIR__ . '/includes/pagination.php';
    ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Post</th>
            <th>Site</th>
            <th>Uplift</th>
            <th>Published</th>
            <th>Synced</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($blogs as $blog):
            $id = (int) $blog['id'];
            $local = (string) ($blog['local_status'] ?? 'draft');
            $source = (string) ($blog['source_status'] ?? '');
          ?>
            <tr>
              <td>
                <a href="/admin/blog?id=<?= $id ?>"><strong><?= e((string) $blog['title']) ?></strong></a>
                <div class="admin-table-sub">/blog/<?= e((string) $blog['slug']) ?></div>
              </td>
              <td>
                <span class="campaign-status-badge campaign-status-badge--<?= $local === 'published' ? 'sent' : 'draft' ?>">
                  <?= e(ucfirst($local)) ?>
                </span>
              </td>
              <td>
                <span class="campaign-status-badge campaign-status-badge--<?= strtoupper($source) === 'PUBLISH' ? 'scheduled' : 'draft' ?>">
                  <?= e($source !== '' ? $source : '—') ?>
                </span>
              </td>
              <td><?= e(blog_format_date($blog['publish_date'] ?? null) ?: '—') ?></td>
              <td class="admin-table-sub"><?= e((string) ($blog['last_synced_at'] ?? '—')) ?></td>
              <td>
                <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                  <a href="/admin/blog?id=<?= $id ?>" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                  <?php if ($local === 'published'): ?>
                    <a href="<?= e(blog_public_url($blog)) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-sm admin-btn-secondary">Open</a>
                    <form method="post" action="/admin/blog-action" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button type="submit" name="action" value="unpublish" class="admin-btn admin-btn-sm admin-btn-secondary">Unpublish</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="/admin/blog-action" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button type="submit" name="action" value="publish" class="admin-btn admin-btn-sm admin-btn-primary">Publish</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
