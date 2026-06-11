<?php
declare(strict_types=1);

/** @var array{page: int, per_page: int, total_pages: int, total: int} $pagination */
/** @var callable(int): string $paginationUrl */
/** @var string $paginationPath */
/** @var array<string, scalar> $paginationQuery */
/** @var string $paginationLabel */
/** @var string $paginationShow 'per_page'|'nav' */

$total = (int) ($pagination['total'] ?? 0);
if ($total < 1) {
    return;
}

$page = (int) $pagination['page'];
$totalPages = (int) $pagination['total_pages'];
$perPage = (int) ($pagination['per_page'] ?? pagination_default_per_page());
$label = $paginationLabel ?? 'items';
$query = $paginationQuery ?? [];
$show = $paginationShow ?? 'nav';
$fieldId = 'per-page-' . md5($paginationPath . serialize($query));

if ($show === 'per_page'): ?>
  <div class="admin-table-toolbar">
    <form method="get" action="<?= e($paginationPath) ?>" class="admin-pagination-per-page">
      <?php foreach ($query as $key => $value): ?>
        <?php if ($value !== '' && $value !== null): ?>
          <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <label for="<?= e($fieldId) ?>" class="admin-pagination-per-page-label">Show</label>
      <select
        id="<?= e($fieldId) ?>"
        name="per_page"
        class="admin-pagination-per-page-select"
        onchange="this.form.submit()"
      >
        <?php foreach (admin_per_page_options() as $option): ?>
          <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
      </select>
      <span class="admin-pagination-per-page-suffix">per page</span>
    </form>
  </div>
<?php
    return;
endif;

if ($show !== 'nav') {
    return;
}
?>
<div class="admin-pagination-bar admin-pagination-bar--nav">
  <?php if ($totalPages > 1): ?>
    <nav class="admin-pagination" aria-label="<?= e($paginationAriaLabel ?? 'Table pages') ?>">
      <?php if ($page > 1): ?>
        <a href="<?= e($paginationUrl($page - 1)) ?>" class="admin-pagination-btn admin-pagination-btn--nav">Back</a>
      <?php else: ?>
        <span class="admin-pagination-btn admin-pagination-btn--nav is-disabled" aria-disabled="true">Back</span>
      <?php endif; ?>

      <div class="admin-pagination-pages" role="list">
        <?php foreach (pagination_page_list($page, $totalPages) as $pageNum): ?>
          <?php if ($pageNum === 'ellipsis'): ?>
            <span class="admin-pagination-ellipsis" aria-hidden="true">…</span>
          <?php elseif ((int) $pageNum === $page): ?>
            <span class="admin-pagination-num is-current" aria-current="page"><?= (int) $pageNum ?></span>
          <?php else: ?>
            <a href="<?= e($paginationUrl((int) $pageNum)) ?>" class="admin-pagination-num"><?= (int) $pageNum ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($page < $totalPages): ?>
        <a href="<?= e($paginationUrl($page + 1)) ?>" class="admin-pagination-btn admin-pagination-btn--nav">Next</a>
      <?php else: ?>
        <span class="admin-pagination-btn admin-pagination-btn--nav is-disabled" aria-disabled="true">Next</span>
      <?php endif; ?>
    </nav>
    <span class="admin-pagination-info">
      <?= number_format($total) ?> <?= e($label) ?>
    </span>
  <?php else: ?>
    <span class="admin-pagination-info admin-pagination-info--solo">
      <?= number_format($total) ?> <?= e($label) ?>
    </span>
  <?php endif; ?>
</div>
