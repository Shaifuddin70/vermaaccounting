(function () {
  'use strict';

  var cfg = window.FILE_MANAGER_CONFIG || {};
  var csrf = cfg.csrf || '';
  var apiUrl = cfg.apiUrl || '/admin/files-api';

  var backdrop = document.getElementById('file-manager');
  if (!backdrop) return;

  var grid = document.getElementById('fm-grid');
  var emptyEl = document.getElementById('fm-empty');
  var bodyEl = document.getElementById('fm-body');
  var statusCount = document.getElementById('fm-status-count');
  var statusSelected = document.getElementById('fm-status-selected');
  var selectAll = document.getElementById('fm-select-all');
  var filterForm = document.getElementById('fm-filter-form');
  var searchInput = document.getElementById('fm-search');
  var sortSelect = document.getElementById('fm-sort');
  var fileInput = document.getElementById('fm-file-input');
  var zipForm = document.getElementById('fm-zip-form');
  var zipIds = document.getElementById('fm-zip-ids');
  var breadcrumbEl = document.getElementById('fm-breadcrumb');
  var renameDialog = document.getElementById('fm-rename-dialog');
  var renameInput = document.getElementById('fm-rename-input');
  var renameTitle = document.getElementById('fm-rename-title');
  var folderDialog = document.getElementById('fm-folder-dialog');
  var folderInput = document.getElementById('fm-folder-input');
  var moveDialog = document.getElementById('fm-move-dialog');
  var moveSelect = document.getElementById('fm-move-select');

  var btnNewFolder = document.getElementById('fm-btn-new-folder');
  var btnUpload = document.getElementById('fm-btn-upload');
  var btnMove = document.getElementById('fm-btn-move');
  var btnRename = document.getElementById('fm-btn-rename');
  var btnDelete = document.getElementById('fm-btn-delete');
  var btnDownload = document.getElementById('fm-btn-download');
  var btnEmptyUpload = document.getElementById('fm-empty-upload');
  var btnEmptyFolder = document.getElementById('fm-empty-folder');
  var btnRenameCancel = document.getElementById('fm-rename-cancel');
  var btnRenameSave = document.getElementById('fm-rename-save');
  var btnFolderCancel = document.getElementById('fm-folder-cancel');
  var btnFolderSave = document.getElementById('fm-folder-save');
  var btnMoveCancel = document.getElementById('fm-move-cancel');
  var btnMoveSave = document.getElementById('fm-move-save');

  var files = [];
  var folders = [];
  var folderOptions = [];
  var breadcrumb = [];
  var currentFolderId = null;
  var selectedFiles = new Set();
  var selectedFolders = new Set();
  var searchTimer = null;
  var renameTarget = null;

  function closeRenameDialog() {
    renameDialog.setAttribute('aria-hidden', 'true');
    renameDialog.classList.remove('is-open');
    renameTarget = null;
  }

  function closeFolderDialog() {
    folderDialog.setAttribute('aria-hidden', 'true');
    folderDialog.classList.remove('is-open');
  }

  function closeMoveDialog() {
    moveDialog.setAttribute('aria-hidden', 'true');
    moveDialog.classList.remove('is-open');
  }

  function navigateTo(folderId) {
    currentFolderId = folderId || null;
    selectedFiles.clear();
    selectedFolders.clear();
    loadFiles();
  }

  function apiQuery() {
    var params = new URLSearchParams();
    if (currentFolderId) params.set('folder_id', String(currentFolderId));
    if (filterForm.value) params.set('form_id', filterForm.value);
    if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
    if (sortSelect.value) params.set('sort', sortSelect.value);
    return params.toString();
  }

  function loadFiles() {
    grid.innerHTML = '<div class="fm-loading">Loading…</div>';
    fetch(apiUrl + '?' + apiQuery(), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Failed to load files');
        files = data.files || [];
        folders = data.folders || [];
        folderOptions = data.folder_options || [];
        breadcrumb = data.breadcrumb || [];
        currentFolderId = data.folder_id || null;
        renderBreadcrumb();
        renderForms(data.forms || []);
        renderGrid();
      })
      .catch(function (err) {
        grid.innerHTML = '<div class="fm-error">' + escapeHtml(err.message || 'Could not load files.') + '</div>';
      });
  }

  function renderBreadcrumb() {
    breadcrumbEl.innerHTML = '';
    breadcrumb.forEach(function (crumb, index) {
      if (index > 0) {
        var sep = document.createElement('span');
        sep.className = 'fm-breadcrumb-sep';
        sep.textContent = '/';
        sep.setAttribute('aria-hidden', 'true');
        breadcrumbEl.appendChild(sep);
      }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fm-breadcrumb-item' + (index === breadcrumb.length - 1 ? ' is-current' : '');
      btn.textContent = crumb.name;
      btn.dataset.folderId = crumb.id === null || crumb.id === undefined ? '' : String(crumb.id);
      btn.addEventListener('click', function () {
        navigateTo(btn.dataset.folderId ? Number(btn.dataset.folderId) : null);
      });
      breadcrumbEl.appendChild(btn);
    });
  }

  function renderForms(forms) {
    var current = filterForm.value;
    filterForm.innerHTML = '<option value="">All forms</option>';
    forms.forEach(function (form) {
      var opt = document.createElement('option');
      opt.value = String(form.id);
      opt.textContent = form.title;
      filterForm.appendChild(opt);
    });
    filterForm.value = current;
  }

  function totalItems() {
    return folders.length + files.length;
  }

  function renderGrid() {
    selectedFiles.clear();
    selectedFolders.clear();
    updateToolbar();
    selectAll.checked = false;

    if (!totalItems()) {
      grid.hidden = true;
      emptyEl.hidden = false;
      statusCount.textContent = '0 items';
      statusSelected.textContent = '';
      return;
    }

    grid.hidden = false;
    emptyEl.hidden = true;
    grid.innerHTML = '';

    folders.forEach(function (folder) {
      grid.appendChild(renderFolderItem(folder));
    });
    files.forEach(function (file) {
      grid.appendChild(renderFileItem(file));
    });

    var count = totalItems();
    statusCount.textContent = count + ' item' + (count === 1 ? '' : 's');
  }

  function renderFolderItem(folder) {
    var item = document.createElement('div');
    item.className = 'fm-item fm-item--folder';
    item.setAttribute('role', 'option');
    item.dataset.type = 'folder';
    item.dataset.id = String(folder.id);
    item.tabIndex = 0;

    var checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'fm-item-check';
    checkbox.dataset.type = 'folder';
    checkbox.dataset.id = String(folder.id);
    checkbox.addEventListener('change', onItemCheck);

    var thumb = document.createElement('div');
    thumb.className = 'fm-item-thumb fm-item-thumb--folder';
    thumb.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';

    var name = document.createElement('div');
    name.className = 'fm-item-name';
    name.title = folder.name;
    name.textContent = folder.name;

    var meta = document.createElement('div');
    meta.className = 'fm-item-meta';
    meta.textContent = folder.item_count + ' item' + (folder.item_count === 1 ? '' : 's');

    item.appendChild(checkbox);
    item.appendChild(thumb);
    item.appendChild(name);
    item.appendChild(meta);

    item.addEventListener('click', function (e) {
      if (e.target.classList.contains('fm-item-check')) return;
      checkbox.checked = !checkbox.checked;
      onItemCheck.call(checkbox);
    });

    item.addEventListener('dblclick', function () {
      navigateTo(folder.id);
    });

    return item;
  }

  function renderFileItem(file) {
    var item = document.createElement('div');
    item.className = 'fm-item';
    item.setAttribute('role', 'option');
    item.dataset.type = 'file';
    item.dataset.id = String(file.id);
    item.tabIndex = 0;

    var checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'fm-item-check';
    checkbox.dataset.type = 'file';
    checkbox.dataset.id = String(file.id);
    checkbox.addEventListener('change', onItemCheck);

    var thumb = document.createElement('div');
    thumb.className = 'fm-item-thumb';
    if (file.is_image) {
      var img = document.createElement('img');
      img.src = file.view_url;
      img.alt = '';
      img.loading = 'lazy';
      thumb.appendChild(img);
    } else {
      thumb.textContent = file.ext || 'FILE';
    }

    var name = document.createElement('div');
    name.className = 'fm-item-name';
    name.title = file.original_name;
    name.textContent = file.original_name;

    var meta = document.createElement('div');
    meta.className = 'fm-item-meta';
    meta.textContent = file.size_label + ' · ' + (file.form_title || 'Form');

    item.appendChild(checkbox);
    item.appendChild(thumb);
    item.appendChild(name);
    item.appendChild(meta);

    item.addEventListener('click', function (e) {
      if (e.target.classList.contains('fm-item-check')) return;
      checkbox.checked = !checkbox.checked;
      onItemCheck.call(checkbox);
    });

    item.addEventListener('dblclick', function () {
      window.open(file.download_url, '_blank');
    });

    return item;
  }

  function onItemCheck() {
    var id = Number(this.dataset.id);
    var type = this.dataset.type;
    if (type === 'folder') {
      if (this.checked) selectedFolders.add(id);
      else selectedFolders.delete(id);
    } else {
      if (this.checked) selectedFiles.add(id);
      else selectedFiles.delete(id);
    }

    var item = grid.querySelector('.fm-item[data-type="' + type + '"][data-id="' + id + '"]');
    if (item) item.classList.toggle('is-selected', this.checked);

    selectAll.checked = totalItems() > 0 && selectedFiles.size + selectedFolders.size === totalItems();
    updateToolbar();
  }

  function updateToolbar() {
    var fileCount = selectedFiles.size;
    var folderCount = selectedFolders.size;
    var totalSelected = fileCount + folderCount;

    btnRename.disabled = !((fileCount === 1 && folderCount === 0) || (folderCount === 1 && fileCount === 0));
    btnDelete.disabled = totalSelected === 0;
    btnDownload.disabled = fileCount === 0;
    btnMove.disabled = fileCount === 0;
    statusSelected.textContent = totalSelected ? totalSelected + ' selected' : '';
  }

  function postForm(data) {
    var body = new FormData();
    body.append('csrf_token', csrf);
    Object.keys(data).forEach(function (key) {
      var val = data[key];
      if (Array.isArray(val)) {
        val.forEach(function (v) { body.append(key + '[]', v); });
      } else if (val !== null && val !== undefined) {
        body.append(key, val);
      }
    });
    return fetch(apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) { return res.json(); });
  }

  function deleteSelected() {
    var fileCount = selectedFiles.size;
    var folderCount = selectedFolders.size;
    if (!fileCount && !folderCount) return;

    var message = 'Delete ';
    if (fileCount && folderCount) {
      message += fileCount + ' file(s) and ' + folderCount + ' folder(s)';
    } else if (fileCount) {
      message += fileCount + ' file(s)';
    } else {
      message += folderCount + ' folder(s)';
    }
    message += ' permanently? This cannot be undone.';
    if (!confirm(message)) return;

    var tasks = [];
    if (fileCount) {
      tasks.push(postForm({ action: 'delete', file_ids: Array.from(selectedFiles) }));
    }
    selectedFolders.forEach(function (folderId) {
      tasks.push(postForm({ action: 'delete_folder', folder_id: String(folderId) }));
    });

    Promise.all(tasks)
      .then(function (results) {
        var error = results.find(function (data) { return !data.ok; });
        if (error) throw new Error(error.error || 'Delete failed');
        loadFiles();
      })
      .catch(function (err) { alert(err.message); });
  }

  function downloadSelected() {
    if (!selectedFiles.size || !zipForm || !zipIds) return;
    zipIds.value = Array.from(selectedFiles).join(',');
    zipForm.submit();
  }

  function openRenameDialog() {
    if (selectedFiles.size === 1 && selectedFolders.size === 0) {
      var fileId = Array.from(selectedFiles)[0];
      var file = files.find(function (f) { return f.id === fileId; });
      if (!file) return;
      renameTarget = { type: 'file', id: fileId };
      renameTitle.textContent = 'Rename file';
      renameInput.value = file.original_name;
    } else if (selectedFolders.size === 1 && selectedFiles.size === 0) {
      var folderId = Array.from(selectedFolders)[0];
      var folder = folders.find(function (f) { return f.id === folderId; });
      if (!folder) return;
      renameTarget = { type: 'folder', id: folderId };
      renameTitle.textContent = 'Rename folder';
      renameInput.value = folder.name;
    } else {
      return;
    }

    renameDialog.setAttribute('aria-hidden', 'false');
    renameDialog.classList.add('is-open');
    renameInput.focus();
    renameInput.select();
  }

  function saveRename() {
    if (!renameTarget) return;
    var name = renameInput.value.trim();
    if (!name) return;

    var payload = renameTarget.type === 'folder'
      ? { action: 'rename_folder', folder_id: String(renameTarget.id), name: name }
      : { action: 'rename', file_id: String(renameTarget.id), name: name };

    postForm(payload)
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Rename failed');
        closeRenameDialog();
        loadFiles();
      })
      .catch(function (err) { alert(err.message); });
  }

  function openFolderDialog() {
    folderInput.value = '';
    folderDialog.setAttribute('aria-hidden', 'false');
    folderDialog.classList.add('is-open');
    folderInput.focus();
  }

  function saveFolder() {
    var name = folderInput.value.trim();
    if (!name) return;

    postForm({
      action: 'create_folder',
      name: name,
      parent_id: currentFolderId ? String(currentFolderId) : ''
    })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Could not create folder');
        closeFolderDialog();
        loadFiles();
      })
      .catch(function (err) { alert(err.message); });
  }

  function openMoveDialog() {
    if (!selectedFiles.size) return;
    moveSelect.innerHTML = '';
    var rootOpt = document.createElement('option');
    rootOpt.value = '';
    rootOpt.textContent = 'All files (root)';
    moveSelect.appendChild(rootOpt);

    folderOptions.forEach(function (folder) {
      if (currentFolderId && folder.id === currentFolderId) return;
      var opt = document.createElement('option');
      opt.value = String(folder.id);
      opt.textContent = folder.path;
      moveSelect.appendChild(opt);
    });

    moveDialog.setAttribute('aria-hidden', 'false');
    moveDialog.classList.add('is-open');
    moveSelect.focus();
  }

  function saveMove() {
    if (!selectedFiles.size) return;
    postForm({
      action: 'move_files',
      folder_id: moveSelect.value,
      file_ids: Array.from(selectedFiles)
    })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Move failed');
        closeMoveDialog();
        loadFiles();
      })
      .catch(function (err) { alert(err.message); });
  }

  function uploadFiles(fileList) {
    if (!fileList || !fileList.length) return;
    var body = new FormData();
    body.append('csrf_token', csrf);
    body.append('action', 'upload');
    if (currentFolderId) body.append('folder_id', String(currentFolderId));
    Array.from(fileList).forEach(function (file) {
      body.append('files[]', file);
    });

    grid.innerHTML = '<div class="fm-loading">Uploading…</div>';
    fetch(apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.errors && data.errors.length) {
          alert(data.errors.join('\n'));
        }
        loadFiles();
      })
      .catch(function () {
        alert('Upload failed.');
        loadFiles();
      });
  }

  function formatSize(bytes) {
    if (bytes < 1) return '0 B';
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    var value = bytes / Math.pow(1024, power);
    return value.toFixed(value >= 10 || power === 0 ? 0 : 1) + ' ' + units[power];
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  btnNewFolder.addEventListener('click', openFolderDialog);
  btnUpload.addEventListener('click', function () { fileInput.click(); });
  if (btnEmptyUpload) btnEmptyUpload.addEventListener('click', function () { fileInput.click(); });
  if (btnEmptyFolder) btnEmptyFolder.addEventListener('click', openFolderDialog);
  btnMove.addEventListener('click', openMoveDialog);
  btnDelete.addEventListener('click', deleteSelected);
  btnDownload.addEventListener('click', downloadSelected);
  btnRename.addEventListener('click', openRenameDialog);
  btnRenameCancel.addEventListener('click', closeRenameDialog);
  btnRenameSave.addEventListener('click', saveRename);
  btnFolderCancel.addEventListener('click', closeFolderDialog);
  btnFolderSave.addEventListener('click', saveFolder);
  btnMoveCancel.addEventListener('click', closeMoveDialog);
  btnMoveSave.addEventListener('click', saveMove);

  selectAll.addEventListener('change', function () {
    var checks = grid.querySelectorAll('.fm-item-check');
    selectedFiles.clear();
    selectedFolders.clear();
    checks.forEach(function (cb) {
      cb.checked = selectAll.checked;
      var id = Number(cb.dataset.id);
      if (selectAll.checked) {
        if (cb.dataset.type === 'folder') selectedFolders.add(id);
        else selectedFiles.add(id);
      }
      var item = cb.closest('.fm-item');
      if (item) item.classList.toggle('is-selected', selectAll.checked);
    });
    updateToolbar();
  });

  filterForm.addEventListener('change', loadFiles);
  sortSelect.addEventListener('change', loadFiles);
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadFiles, 300);
  });

  fileInput.addEventListener('change', function () {
    uploadFiles(fileInput.files);
    fileInput.value = '';
  });

  if (bodyEl) {
    bodyEl.addEventListener('dragover', function (e) { e.preventDefault(); });
    bodyEl.addEventListener('drop', function (e) {
      e.preventDefault();
      uploadFiles(e.dataTransfer.files);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (renameDialog.classList.contains('is-open')) closeRenameDialog();
    else if (folderDialog.classList.contains('is-open')) closeFolderDialog();
    else if (moveDialog.classList.contains('is-open')) closeMoveDialog();
  });

  loadFiles();
})();
