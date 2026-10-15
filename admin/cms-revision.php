<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$repo = new CmsContentRepository();
$rev = $repo->revision((int) ($_GET['id'] ?? 0));
$content = $rev ? $repo->find((int) $rev['content_id']) : null;
if ($rev === null || $content === null) {
    cms_admin_back('/admin/cms-content', null, 'Revision not found.');
}
$id = (int) $content['id'];
$snap = $rev['snapshot'];
$isAutosave = !isset($snap['content']);
$current = (new CmsContentService())->snapshot($id);

/** Split HTML into comparable lines. */
$toLines = static function (string $html): array {
    $html = preg_replace('/>\s*</', ">\n<", trim($html)) ?? $html;
    return $html === '' ? [] : explode("\n", $html);
};

$fieldLabels = [
    'content.title' => 'Title', 'content.h1' => 'H1', 'content.internal_name' => 'Internal name', 'content.excerpt' => 'Excerpt',
    'content.template' => 'Template', 'content.body_mode' => 'Body mode', 'content.featured_media_id' => 'Featured image',
    'content.featured_alt' => 'Featured image alt', 'content.author_user_id' => 'Author', 'content.author_name' => 'Author name',
    'content.folder_id' => 'Folder', 'content.language' => 'Language', 'content.internal_notes' => 'Internal notes',
    'content.faqs_json' => 'FAQs',
    'seo.seo_title' => 'SEO title', 'seo.meta_description' => 'Meta description', 'seo.canonical_mode' => 'Canonical mode',
    'seo.canonical_url' => 'Canonical URL', 'seo.robots_index' => 'Index', 'seo.robots_follow' => 'Follow',
    'seo.robots_extra' => 'Extra robots directives', 'seo.og_title' => 'Social title', 'seo.og_description' => 'Social description',
    'seo.og_image_id' => 'Social image', 'seo.sitemap_include' => 'In sitemap',
];
$changes = [];
if (!$isAutosave) {
    foreach ($fieldLabels as $key => $label) {
        [$group, $field] = explode('.', $key);
        $old = (string) ($snap[$group][$field] ?? '');
        $new = (string) ($current[$group][$field] ?? '');
        if ($old !== $new) {
            $changes[] = [$label, $old, $new];
        }
    }
    foreach (array_unique(array_merge(array_keys((array) ($snap['city'] ?? [])), array_keys((array) ($current['city'] ?? [])))) as $field) {
        $old = is_scalar($snap['city'][$field] ?? '') ? (string) ($snap['city'][$field] ?? '') : json_encode($snap['city'][$field]);
        $new = is_scalar($current['city'][$field] ?? '') ? (string) ($current['city'][$field] ?? '') : json_encode($current['city'][$field]);
        if ($old !== $new && $field !== 'updated_at') {
            $changes[] = ['Location: ' . str_replace('_', ' ', $field), $old, $new];
        }
    }
    $bodyDiff = CmsDiff::lines($toLines((string) ($snap['content']['body_html'] ?? '')), $toLines((string) ($current['content']['body_html'] ?? '')));
}

$canRestore = Auth::can('cms.edit') && !$isAutosave;
$csrf = Auth::csrfToken();
$pageTitle = 'Revision #' . (int) $rev['id'];
$activeNav = cms_admin_type_nav((string) $content['type']);
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Revision from <?= e(cms_admin_date((string) $rev['created_at'])) ?></h1>
  <div class="admin-header-actions">
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-edit?id=<?= $id ?>#tab-revisions">Back to editor</a>
  </div>
</div>

<div class="admin-card">
  <p>
    <strong><?= e(CmsCityDefaults::label($content)) ?></strong> <code><?= e((string) $content['path']) ?></code><br>
    <?= e((string) ($rev['comment'] ?: 'No comment')) ?> · by <?= e((string) ($rev['user_name'] ?: 'unknown')) ?>
    <?php if ((int) $rev['is_autosave'] === 1): ?><span class="admin-badge admin-badge-muted">Autosave</span><?php endif; ?>
  </p>
  <?php if ($isAutosave): ?>
    <p>Autosaves hold unsaved form input. Open it in the editor, review, then save.</p>
    <a class="admin-btn" href="/admin/cms-edit?id=<?= $id ?>&amp;autosave=<?= (int) $rev['id'] ?>">Open autosave in editor</a>
  <?php elseif ($canRestore): ?>
    <form method="post" action="/admin/cms-action">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="restore_revision">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="revision_id" value="<?= (int) $rev['id'] ?>">
      <button type="submit" class="admin-btn" data-confirm="Replace the current content with this revision? The current version is kept as a revision.">Restore this revision</button>
      <span class="admin-field-hint">The URL and publishing status stay as they are now.</span>
    </form>
  <?php endif; ?>
</div>

<?php if (!$isAutosave): ?>
<div class="admin-card">
  <h2 class="admin-card-title">Field changes (revision → current)</h2>
  <?php if ($changes === []): ?>
    <p class="admin-field-hint">No differences in fields.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Field</th><th>In this revision</th><th>Current</th></tr></thead>
      <tbody>
      <?php foreach ($changes as [$label, $old, $new]): ?>
        <tr><td><?= e($label) ?></td><td class="cms-diff-old"><?= e($old) ?></td><td class="cms-diff-new"><?= e($new) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2 class="admin-card-title">Body changes</h2>
  <?php
  $hasBodyChange = array_filter($bodyDiff, fn ($r) => $r[0] !== ' ') !== [];
  $rows = CmsDiff::sideBySide($bodyDiff);
  ?>
  <?php if (!$hasBodyChange): ?>
    <p class="admin-field-hint">The body is identical.</p>
  <?php else: ?>
    <div class="cms-table-scroll">
      <table class="cms-diff-table">
        <thead><tr><th>This revision</th><th>Current</th></tr></thead>
        <tbody>
        <?php foreach ($rows as [$left, $right, $kind]): ?>
          <tr class="cms-diff-row--<?= $kind ?>">
            <td class="<?= $kind === 'change' && $left !== null ? 'cms-diff-del' : '' ?>"><?= $left !== null ? e($left) : '' ?></td>
            <td class="<?= $kind === 'change' && $right !== null ? 'cms-diff-add' : '' ?>"><?= $right !== null ? e($right) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
