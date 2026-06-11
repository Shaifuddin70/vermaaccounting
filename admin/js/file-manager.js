(function () {
  'use strict';

  var cfg = window.FILE_MANAGER_CONFIG || {};
  var csrf = cfg.csrf || '';
  var apiUrl = cfg.apiUrl || '/admin/files-api';

  var backdrop = document.getElementById('file-manager');
  if (!backdrop) return;

  var grid = document.getElementById('fm-grid');
  var emptyEl = document.getElementById('fm-empty');
  var storageEl = document.getElementById('fm-storage');
  var statusCount = document.getElementById('fm-status-count');
  var statusSelected = document.getElementById('fm-status-selected');
  var selectAll = document.getElementById('fm-select-all');
  var filterForm = document.getElementById('fm-filter-form');
  var searchInput = document.getElementById('fm-search');
  var sortSelect = document.getElementById('fm-sort');
  var fileInput = document.getElementById('fm-file-input');
  var zipForm = document.getElementById('fm-zip-form');
  var zipIds = document.getElementById('fm-zip-ids');
  var renameDialog = document.getElementById('fm-rename-dialog');
  var renameInput = document.getElementById('fm-rename-input');

  var btnUpload = document.getElementById('fm-btn-upload');
  var btnRename = document.getElementById('fm-btn-rename');
  var btnDelete = document.getElementById('fm-btn-delete');
  var btnDownload = document.getElementById('fm-btn-download');
  var btnClose = document.getElementById('fm-close');
  var btnEmptyUpload = document.getElementById('fm-empty-upload');
  var btnRenameCancel = document.getElementById('fm-rename-cancel');
  var btnRenameSave = document.getElementById('fm-rename-save');

  var files = [];
  var selected = new Set();
  var searchTimer = null;

  function openModal() {
    backdrop.setAttribute('aria-hidden', 'false');
    backdrop.classList.add('is-open');
    document.body.classList.add('fm-open');
    loadFiles();
  }

  function closeModal() {
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.classList.remove('is-open');
    document.body.classList.remove('fm-open');
    closeRenameDialog();
  }

  function apiQuery() {
    var params = new URLSearchParams();
    if (filterForm.value) params.set('form_id', filterForm.value);
    if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
    if (sortSelect.value) params.set('sort', sortSelect.value);
    return params.toString();
  }

  function loadFiles() {
    grid.innerHTML = '<div class="fm-loading">Loading files…</div>';
    fetch(apiUrl + '?' + apiQuery(), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Failed to load files');
        files = data.files || [];
        renderForms(data.forms || []);
        renderStorage(data.stats || {});
        renderGrid();
      })
      .catch(function (err) {
        grid.innerHTML = '<div class="fm-error">' + escapeHtml(err.message || 'Could not load files.') + '</div>';
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

  function renderStorage(stats) {
    if (!stats.quota_bytes) {
      storageEl.textContent = '';
      return;
    }
    storageEl.textContent = formatSize(stats.used_bytes) + ' / ' + formatSize(stats.quota_bytes);
  }

  function renderGrid() {
    selected.clear();
    updateToolbar();
    selectAll.checked = false;

    if (!files.length) {
      grid.hidden = true;
      emptyEl.hidden = false;
      statusCount.textContent = '0 items';
      statusSelected.textContent = '';
      return;
    }

    grid.hidden = false;
    emptyEl.hidden = true;
    grid.innerHTML = '';

    files.forEach(function (file) {
      var item = document.createElement('div');
      item.className = 'fm-item';
      item.setAttribute('role', 'option');
      item.dataset.id = String(file.id);
      item.tabIndex = 0;

      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'fm-item-check';
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

      grid.appendChild(item);
    });

    statusCount.textContent = files.length + ' item' + (files.length === 1 ? '' : 's');
  }

  function onItemCheck() {
    var id = Number(this.dataset.id);
    if (this.checked) selected.add(id);
    else selected.delete(id);

    var item = grid.querySelector('.fm-item[data-id="' + id + '"]');
    if (item) item.classList.toggle('is-selected', this.checked);

    selectAll.checked = files.length > 0 && selected.size === files.length;
    updateToolbar();
  }

  function updateToolbar() {
    var count = selected.size;
    btnRename.disabled = count !== 1;
    btnDelete.disabled = count === 0;
    btnDownload.disabled = count === 0;
    statusSelected.textContent = count ? count + ' selected' : '';
  }

  function postForm(data) {
    var body = new FormData();
    body.append('csrf_token', csrf);
    Object.keys(data).forEach(function (key) {
      var val = data[key];
      if (Array.isArray(val)) {
        val.forEach(function (v) { body.append(key + '[]', v); });
      } else {
        body.append(key, val);
      }
    });
    return fetch(apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) { return res.json(); });
  }

  function deleteSelected() {
    if (!selected.size) return;
    if (!confirm('Delete ' + selected.size + ' file(s) permanently? This cannot be undone.')) return;

    postForm({ action: 'delete', file_ids: Array.from(selected) })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Delete failed');
        loadFiles();
      })
      .catch(function (err) { alert(err.message); });
  }

  function downloadSelected() {
    if (!selected.size || !zipForm || !zipIds) return;
    zipIds.value = Array.from(selected).join(',');
    zipForm.submit();
  }

  function openRenameDialog() {
    if (selected.size !== 1) return;
    var id = Array.from(selected)[0];
    var file = files.find(function (f) { return f.id === id; });
    if (!file) return;
    renameInput.value = file.original_name;
    renameDialog.setAttribute('aria-hidden', 'false');
    renameDialog.classList.add('is-open');
    renameInput.focus();
    renameInput.select();
  }

  function closeRenameDialog() {
    renameDialog.setAttribute('aria-hidden', 'true');
    renameDialog.classList.remove('is-open');
  }

  function saveRename() {
    if (selected.size !== 1) return;
    var id = Array.from(selected)[0];
    var name = renameInput.value.trim();
    if (!name) return;

    postForm({ action: 'rename', file_id: String(id), name: name })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Rename failed');
        closeRenameDialog();
        loadFiles();
      })
      .catch(function (err) { alert(err.message); });
  }

  function uploadFiles(fileList) {
    if (!fileList || !fileList.length) return;
    var body = new FormData();
    body.append('csrf_token', csrf);
    body.append('action', 'upload');
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

  btnUpload.addEventListener('click', function () { fileInput.click(); });
  if (btnEmptyUpload) btnEmptyUpload.addEventListener('click', function () { fileInput.click(); });
  btnDelete.addEventListener('click', deleteSelected);
  btnDownload.addEventListener('click', downloadSelected);
  btnRename.addEventListener('click', openRenameDialog);
  btnClose.addEventListener('click', closeModal);
  btnRenameCancel.addEventListener('click', closeRenameDialog);
  btnRenameSave.addEventListener('click', saveRename);

  backdrop.addEventListener('click', function (e) {
    if (e.target === backdrop) closeModal();
  });

  selectAll.addEventListener('change', function () {
    var checks = grid.querySelectorAll('.fm-item-check');
    checks.forEach(function (cb) {
      cb.checked = selectAll.checked;
      var id = Number(cb.dataset.id);
      if (selectAll.checked) selected.add(id);
      else selected.delete(id);
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

  backdrop.addEventListener('dragover', function (e) { e.preventDefault(); });
  backdrop.addEventListener('drop', function (e) {
    e.preventDefault();
    uploadFiles(e.dataTransfer.files);
  });

  document.addEventListener('keydown', function (e) {
    if (!backdrop.classList.contains('is-open')) return;
    if (e.key === 'Escape') {
      if (renameDialog.classList.contains('is-open')) closeRenameDialog();
      else closeModal();
    }
  });

  document.querySelectorAll('[data-open-file-manager]').forEach(function (btn) {
    btn.addEventListener('click', openModal);
  });

  if (window.location.search.indexOf('manager=1') !== -1) {
    openModal();
  }
})();
