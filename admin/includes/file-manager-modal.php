<?php
declare(strict_types=1);
/** @var string $csrf */
?>
<div id="file-manager" class="fm-backdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="fm-title">
  <div class="fm-window">
    <header class="fm-toolbar">
      <div class="fm-toolbar-main">
        <h2 id="fm-title" class="fm-title">File manager</h2>
        <div class="fm-toolbar-actions">
          <button type="button" class="fm-btn" id="fm-btn-upload" title="Upload files">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload
          </button>
          <button type="button" class="fm-btn" id="fm-btn-rename" disabled title="Rename selected file">Rename</button>
          <button type="button" class="fm-btn fm-btn-danger" id="fm-btn-delete" disabled title="Delete selected files">Delete</button>
          <button type="button" class="fm-btn fm-btn-primary" id="fm-btn-download" disabled title="Download selected files as ZIP">Download ZIP</button>
        </div>
      </div>
      <button type="button" class="fm-close" id="fm-close" aria-label="Close file manager">&times;</button>
    </header>

    <div class="fm-subtoolbar">
      <label class="fm-select-all">
        <input type="checkbox" id="fm-select-all">
        <span>Select all</span>
      </label>
      <div class="fm-filters">
        <select id="fm-filter-form" class="fm-select" aria-label="Filter by form">
          <option value="">All forms</option>
        </select>
        <input type="search" id="fm-search" class="fm-search" placeholder="Search files…" aria-label="Search files">
        <select id="fm-sort" class="fm-select" aria-label="Sort files">
          <option value="date">Newest first</option>
          <option value="name">Name</option>
          <option value="size">Size</option>
        </select>
      </div>
      <div class="fm-storage" id="fm-storage" aria-live="polite"></div>
    </div>

    <div class="fm-body" id="fm-body">
      <div class="fm-empty" id="fm-empty" hidden>
        <p>No files found.</p>
        <button type="button" class="fm-btn fm-btn-primary" id="fm-empty-upload">Upload files</button>
      </div>
      <div class="fm-grid" id="fm-grid" role="listbox" aria-multiselectable="true"></div>
    </div>

    <footer class="fm-statusbar">
      <span id="fm-status-count">0 items</span>
      <span id="fm-status-selected"></span>
    </footer>
  </div>

  <input type="file" id="fm-file-input" multiple hidden>

  <form id="fm-zip-form" method="post" action="/admin/files-zip" target="_blank" hidden>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="file_ids" id="fm-zip-ids" value="">
  </form>
</div>

<div id="fm-rename-dialog" class="fm-dialog-backdrop" aria-hidden="true" role="dialog" aria-labelledby="fm-rename-title">
  <div class="fm-dialog">
    <h3 id="fm-rename-title" class="fm-dialog-title">Rename file</h3>
    <input type="text" id="fm-rename-input" class="fm-dialog-input" autocomplete="off">
    <div class="fm-dialog-actions">
      <button type="button" class="fm-btn" id="fm-rename-cancel">Cancel</button>
      <button type="button" class="fm-btn fm-btn-primary" id="fm-rename-save">Save</button>
    </div>
  </div>
</div>

<script>
window.FILE_MANAGER_CONFIG = {
  csrf: <?= json_encode($csrf, JSON_THROW_ON_ERROR) ?>,
  apiUrl: '/admin/files-api'
};
</script>
<script src="/admin/js/file-manager.js?v=2" defer></script>
