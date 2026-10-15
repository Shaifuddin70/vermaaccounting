<?php
declare(strict_types=1);
/** Media library picker dialog used by the CMS editor (driven by admin/js/cms-admin.js). */
?>
<dialog class="cms-media-dialog" id="cms-media-dialog" aria-labelledby="cms-media-dialog-title">
  <div class="cms-media-dialog-head">
    <h2 id="cms-media-dialog-title">Choose an image</h2>
    <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-dialog-close aria-label="Close">×</button>
  </div>
  <div class="cms-media-dialog-tools">
    <input type="search" placeholder="Search images" data-media-search aria-label="Search images">
    <?php if (Auth::can('cms.edit')): ?>
      <label class="admin-btn admin-btn-sm admin-btn-secondary cms-upload-label">
        Upload new
        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-media-upload hidden>
      </label>
    <?php endif; ?>
  </div>
  <div class="cms-media-dialog-grid" data-media-results aria-live="polite"></div>
  <div class="cms-media-dialog-foot">
    <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-media-more hidden>Load more</button>
  </div>
</dialog>
