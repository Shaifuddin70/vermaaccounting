<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$repo = new BlogRepository();
$blog = $id > 0 ? $repo->find($id) : null;
if ($blog === null) {
    $_SESSION['flash_error'] = 'Blog post not found.';
    header('Location: /admin/blogs');
    exit;
}

$csrf = Auth::csrfToken();
$local = (string) ($blog['local_status'] ?? 'draft');
$pageTitle = (string) $blog['title'];
$activeNav = 'blogs';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e((string) $blog['title']) ?></h1>
  <div class="admin-header-actions">
    <a href="/admin/blogs" class="admin-btn admin-btn-secondary">← All blogs</a>
    <?php if ($local === 'published'): ?>
      <a href="<?= e(blog_public_url($blog)) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-secondary">View on site</a>
      <form method="post" action="/admin/blog-action" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $blog['id'] ?>">
        <button type="submit" name="action" value="unpublish" class="admin-btn admin-btn-secondary">Unpublish</button>
      </form>
    <?php else: ?>
      <form method="post" action="/admin/blog-action" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $blog['id'] ?>">
        <button type="submit" name="action" value="publish" class="admin-btn admin-btn-primary">Publish</button>
      </form>
    <?php endif; ?>
    <form method="post" action="/admin/blog-action" style="display:inline;" onsubmit="return confirm('Remove this post from the site? The Uplift copy stays.');">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $blog['id'] ?>">
      <button type="submit" name="action" value="delete" class="admin-btn admin-btn-secondary" style="color:var(--admin-danger);">Delete</button>
    </form>
  </div>
</div>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2 style="margin-top:0;font-size:1.05rem;">Details</h2>
    <dl class="admin-dl" style="margin:0;display:grid;gap:0.65rem;">
      <div><dt class="admin-field-hint" style="margin:0;">Slug</dt><dd style="margin:0;"><code>/blog/<?= e((string) $blog['slug']) ?></code></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Site status</dt><dd style="margin:0;"><?= e(ucfirst($local)) ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Uplift status</dt><dd style="margin:0;"><?= e((string) ($blog['source_status'] ?? '—')) ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Publish date</dt><dd style="margin:0;"><?= e(blog_format_date($blog['publish_date'] ?? null) ?: '—') ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Author</dt><dd style="margin:0;"><?= e((string) ($blog['author_name'] ?? '—')) ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">SEO score</dt><dd style="margin:0;"><?= (int) ($blog['seo_score'] ?? 0) ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Focus keyword</dt><dd style="margin:0;"><?= e((string) ($blog['focus_keyword'] ?? '—')) ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Last synced</dt><dd style="margin:0;"><?= e((string) ($blog['last_synced_at'] ?? '—')) ?></dd></div>
      <div><dt class="admin-field-hint" style="margin:0;">Uplift ID</dt><dd style="margin:0;"><code><?= e((string) $blog['uplift_id']) ?></code></dd></div>
    </dl>
  </div>
  <div class="admin-card">
    <h2 style="margin-top:0;font-size:1.05rem;">SEO</h2>
    <p style="margin:0 0 0.5rem;"><strong><?= e((string) ($blog['seo_title'] ?: $blog['title'])) ?></strong></p>
    <p class="admin-field-hint" style="margin:0;"><?= e((string) ($blog['seo_description'] ?: $blog['excerpt'])) ?></p>
    <?php if (!empty($blog['categories'])): ?>
      <p style="margin:1rem 0 0;"><strong>Categories:</strong> <?= e(implode(', ', $blog['categories'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($blog['tags'])): ?>
      <p style="margin:0.5rem 0 0;"><strong>Tags:</strong> <?= e(implode(', ', array_slice($blog['tags'], 0, 12))) ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card" style="margin-top:1rem;">
  <h2 style="margin-top:0;font-size:1.05rem;">Content preview</h2>
  <?php if (!empty($blog['featured_image'])): ?>
    <p><img src="<?= e((string) $blog['featured_image']) ?>" alt="" style="max-width:100%;height:auto;border-radius:8px;"></p>
  <?php endif; ?>
  <p class="admin-field-hint"><?= e((string) ($blog['excerpt'] ?? '')) ?></p>
  <div class="blog-admin-preview" style="border-top:1px solid var(--admin-border);padding-top:1rem;line-height:1.6;">
    <?= $blog['content_html'] ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
