<?php
declare(strict_types=1);
/** @var string $csrf */
?>
<div id="file-manager" class="fm-embedded admin-card" aria-labelledby="fm-title">
  <div class="fm-window">
    <header class="fm-toolbar">
      <div class="fm-toolbar-main">
        <h2 id="fm-title" class="fm-title">All files</h2>
        <div class="fm-toolbar-actions">
          <button type="button" class="fm-btn" id="fm-btn-new-folder" title="Create a new folder">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
            New folder
          </button>
          <button type="button" class="fm-btn" id="fm-btn-upload" title="Upload files">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload
          </button>
          <button type="button" class="fm-btn" id="fm-btn-move" disabled title="Move selected files to another folder">Move</button>
          <button type="button" class="fm-btn" id="fm-btn-rename" disabled title="Rename selected item">Rename</button>
          <button type="button" class="fm-btn fm-btn-danger" id="fm-btn-delete" disabled title="Delete selected items">Delete</button>
          <button type="button" class="fm-btn fm-btn-primary" id="fm-btn-download" disabled title="Download selected files as ZIP">Download ZIP</button>
        </div>
      </div>
    </header>

    <nav class="fm-breadcrumb" id="fm-breadcrumb" aria-label="Folder location"></nav>

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
    </div>

    <div class="fm-body" id="fm-body">
      <div class="fm-empty" id="fm-empty" hidden>
        <p>This folder is empty.</p>
        <div class="fm-empty-actions">
          <button type="button" class="fm-btn" id="fm-empty-folder">New folder</button>
          <button type="button" class="fm-btn fm-btn-primary" id="fm-empty-upload">Upload files</button>
        </div>
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
    <h3 id="fm-rename-title" class="fm-dialog-title">Rename</h3>
    <input type="text" id="fm-rename-input" class="fm-dialog-input" autocomplete="off">
    <div class="fm-dialog-actions">
      <button type="button" class="fm-btn" id="fm-rename-cancel">Cancel</button>
      <button type="button" class="fm-btn fm-btn-primary" id="fm-rename-save">Save</button>
    </div>
  </div>
</div>

<div id="fm-folder-dialog" class="fm-dialog-backdrop" aria-hidden="true" role="dialog" aria-labelledby="fm-folder-title">
  <div class="fm-dialog">
    <h3 id="fm-folder-title" class="fm-dialog-title">New folder</h3>
    <input type="text" id="fm-folder-input" class="fm-dialog-input" placeholder="Folder name" autocomplete="off">
    <div class="fm-dialog-actions">
      <button type="button" class="fm-btn" id="fm-folder-cancel">Cancel</button>
      <button type="button" class="fm-btn fm-btn-primary" id="fm-folder-save">Create</button>
    </div>
  </div>
</div>

<div id="fm-move-dialog" class="fm-dialog-backdrop" aria-hidden="true" role="dialog" aria-labelledby="fm-move-title">
  <div class="fm-dialog">
    <h3 id="fm-move-title" class="fm-dialog-title">Move files to</h3>
    <select id="fm-move-select" class="fm-dialog-input"></select>
    <div class="fm-dialog-actions">
      <button type="button" class="fm-btn" id="fm-move-cancel">Cancel</button>
      <button type="button" class="fm-btn fm-btn-primary" id="fm-move-save">Move</button>
    </div>
  </div>
</div>

<script>
window.FILE_MANAGER_CONFIG = {
  csrf: <?= json_encode($csrf, JSON_THROW_ON_ERROR) ?>,
  apiUrl: '/admin/files-api'
};
</script>
<script src="/admin/js/file-manager.js?v=4" defer></script>
