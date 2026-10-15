/* Website (CMS) admin behaviours: confirmations, tabs, repeaters, conditional fields, media picker. */
(function () {
  'use strict';

  function csrfToken() {
    var form = document.querySelector('[data-csrf]');
    if (form) return form.getAttribute('data-csrf');
    var input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
  }

  // Confirmations and busy states
  document.addEventListener('click', function (event) {
    var el = event.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('submit', function (event) {
    var chosen = event.target.querySelector('select option[data-confirm]:checked');
    if (chosen && !window.confirm(chosen.getAttribute('data-confirm'))) {
      event.preventDefault();
      return;
    }
    var submitter = event.submitter;
    if (submitter && submitter.hasAttribute('data-busy')) {
      window.setTimeout(function () {
        submitter.disabled = true;
        submitter.textContent = submitter.getAttribute('data-busy');
      }, 0);
    }
  });

  document.addEventListener('change', function (event) {
    var all = event.target.closest('[data-check-all]');
    if (!all || !all.form) return;
    var name = all.getAttribute('data-check-all');
    Array.prototype.forEach.call(all.form.elements, function (box) {
      if (box.type === 'checkbox' && box.name === name) box.checked = all.checked;
    });
  });

  // Bulk actions on content lists (row checkboxes join the form through form="...")
  document.querySelectorAll('[data-bulk-form]').forEach(function (form) {
    var count = form.querySelector('[data-bulk-count]');
    var action = form.querySelector('[data-bulk-action]');
    var apply = form.querySelector('[data-bulk-apply]');
    var allBox = document.querySelector('[data-check-all][form="' + form.id + '"]');
    function selected() {
      return Array.prototype.filter.call(form.elements, function (el) { return el.type === 'checkbox' && el.name === 'ids[]' && el.checked; });
    }
    function sync() {
      var n = selected().length;
      var total = Array.prototype.filter.call(form.elements, function (el) { return el.type === 'checkbox' && el.name === 'ids[]'; }).length;
      count.textContent = n === 0 ? 'None selected' : n + ' selected';
      apply.disabled = n === 0 || !action.value;
      if (allBox) {
        allBox.checked = n > 0 && n === total;
        allBox.indeterminate = n > 0 && n < total;
      }
    }
    document.addEventListener('change', function (event) {
      if (event.target.form === form) sync();
    });
    form.addEventListener('submit', function (event) {
      var n = selected().length;
      var label = action.options[action.selectedIndex].text;
      var message = action.value === 'delete'
        ? 'Permanently delete ' + n + ' item(s) and their revisions? This cannot be undone. Live pages will return 404 unless you add redirects.'
        : label + ' ' + n + ' item(s)?' + (action.value === 'unpublish' || action.value === 'archive' ? ' Live pages will be taken offline.' : '');
      if (n === 0 || !action.value || !window.confirm(message)) {
        event.preventDefault();
        return;
      }
      apply.disabled = true;
      apply.textContent = 'Working…';
    });
    sync();
  });

  // Tabs
  function initTabs(nav) {
    var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-tab]'));
    if (!tabs.length) return;
    var scope = nav.parentElement;
    var panels = Array.prototype.slice.call(scope.querySelectorAll('[data-panel]'));

    function show(key, updateHash) {
      if (!tabs.some(function (t) { return t.getAttribute('data-tab') === key; })) key = tabs[0].getAttribute('data-tab');
      tabs.forEach(function (t) {
        var active = t.getAttribute('data-tab') === key;
        t.classList.toggle('is-active', active);
        t.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-panel') === key); });
      if (updateHash && window.history.replaceState) window.history.replaceState(null, '', '#tab-' + key);
      document.dispatchEvent(new CustomEvent('cms:tab', { detail: { tab: key } }));
    }

    tabs.forEach(function (t) {
      t.addEventListener('click', function (event) {
        event.preventDefault();
        show(t.getAttribute('data-tab'), true);
      });
    });

    // Reveal the tab holding an invalid field instead of failing silently.
    scope.addEventListener('invalid', function (event) {
      var panel = event.target.closest('[data-panel]');
      if (panel && !panel.classList.contains('is-active')) show(panel.getAttribute('data-panel'), true);
      tabs.forEach(function (t) {
        if (panel && t.getAttribute('data-tab') === panel.getAttribute('data-panel')) t.classList.add('has-error');
      });
    }, true);

    var hash = (window.location.hash || '').replace('#tab-', '');
    show(hash || tabs[0].getAttribute('data-tab'), false);
    scope.classList.add('cms-tabs-ready');
  }
  document.querySelectorAll('.cms-tabs').forEach(initTabs);

  // Repeaters
  document.addEventListener('click', function (event) {
    var add = event.target.closest('[data-repeat-add]');
    if (add) {
      var key = add.getAttribute('data-repeat-add');
      var list = document.querySelector('[data-repeat="' + key + '"]');
      var tpl = document.getElementById('tpl-repeat-' + key);
      if (list && tpl) {
        list.appendChild(tpl.content.cloneNode(true));
        var first = list.lastElementChild && list.lastElementChild.querySelector('input, textarea');
        if (first) first.focus();
        list.dispatchEvent(new Event('input', { bubbles: true }));
      }
      return;
    }
    var remove = event.target.closest('[data-repeat-remove]');
    if (remove) {
      var row = remove.closest('.cms-repeat-row');
      var parent = row && row.parentElement;
      if (!row || !parent) return;
      if (parent.querySelectorAll('.cms-repeat-row').length > 1) {
        row.remove();
      } else {
        row.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; });
      }
      parent.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  // Conditional fields: data-show-when="field=value"
  function syncShowWhen() {
    document.querySelectorAll('[data-show-when]').forEach(function (el) {
      var parts = el.getAttribute('data-show-when').split('=');
      var field = document.querySelector('[name="' + parts[0] + '"]:checked, select[name="' + parts[0] + '"], input[type="hidden"][name="' + parts[0] + '"]');
      el.hidden = !field || field.value !== parts[1];
    });
  }
  document.addEventListener('change', syncShowWhen);
  syncShowWhen();

  // Folder select pre-fills a prefix field: data-prefix-target="#selector"
  document.querySelectorAll('[data-prefix-target]').forEach(function (select) {
    select.addEventListener('change', function () {
      var target = document.querySelector(select.getAttribute('data-prefix-target'));
      var option = select.options[select.selectedIndex];
      if (target && option && option.getAttribute('data-prefix') !== null) target.value = option.getAttribute('data-prefix');
    });
  });

  // Structured data toggles -> hidden schema_disabled[] inputs
  var schemaBox = document.getElementById('cms-schema-disabled');
  if (schemaBox) {
    document.querySelectorAll('.cms-schema-toggle').forEach(function (box) {
      box.addEventListener('change', function () {
        schemaBox.innerHTML = '';
        document.querySelectorAll('.cms-schema-toggle').forEach(function (b) {
          if (b.checked) return;
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'schema_disabled[]';
          input.value = b.value;
          schemaBox.appendChild(input);
        });
        schemaBox.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  }

  // Folder tree: expand/collapse and drag-to-reorder among siblings
  document.querySelectorAll('[data-folder-tree]').forEach(function (table) {
    var tbody = table.tBodies[0];
    function rows() { return Array.prototype.slice.call(tbody.querySelectorAll('.cms-folder-row')); }
    function depth(row) { return parseInt(row.getAttribute('data-depth'), 10); }
    function block(row) {
      var list = rows();
      var start = list.indexOf(row);
      var out = [row];
      for (var i = start + 1; i < list.length && depth(list[i]) > depth(row); i++) out.push(list[i]);
      return out;
    }

    table.addEventListener('click', function (event) {
      var toggle = event.target.closest('[data-folder-toggle]');
      if (!toggle) return;
      var row = toggle.closest('.cms-folder-row');
      var expand = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', expand ? 'true' : 'false');
      toggle.textContent = expand ? '▾' : '▸';
      var hiddenBelow = null;
      block(row).slice(1).forEach(function (child) {
        if (!expand) { child.hidden = true; return; }
        // Re-show children, but keep grandchildren of collapsed subfolders hidden.
        if (hiddenBelow !== null && depth(child) > hiddenBelow) return;
        hiddenBelow = null;
        child.hidden = false;
        var t = child.querySelector('[data-folder-toggle]');
        if (t && t.getAttribute('aria-expanded') === 'false') hiddenBelow = depth(child);
      });
    });

    var dragging = null;
    tbody.addEventListener('dragstart', function (event) {
      var handle = event.target.closest('.cms-drag-handle');
      if (!handle) return;
      dragging = handle.closest('.cms-folder-row');
      dragging.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', dragging.getAttribute('data-id'));
    });
    tbody.addEventListener('dragover', function (event) {
      var target = event.target.closest('.cms-folder-row');
      if (!dragging || !target || target === dragging || target.getAttribute('data-parent') !== dragging.getAttribute('data-parent')) return;
      event.preventDefault();
      rows().forEach(function (r) { r.classList.toggle('is-drop-target', r === target); });
    });
    tbody.addEventListener('dragend', function () {
      if (dragging) dragging.classList.remove('is-dragging');
      rows().forEach(function (r) { r.classList.remove('is-drop-target'); });
      dragging = null;
    });
    tbody.addEventListener('drop', function (event) {
      var target = event.target.closest('.cms-folder-row');
      if (!dragging || !target || target === dragging || target.getAttribute('data-parent') !== dragging.getAttribute('data-parent')) return;
      event.preventDefault();
      var moving = block(dragging);
      var anchor = target;
      var list = rows();
      var after = list.indexOf(dragging) < list.indexOf(target);
      if (after) {
        var targetBlock = block(target);
        anchor = targetBlock[targetBlock.length - 1].nextElementSibling;
      }
      moving.forEach(function (r) { tbody.insertBefore(r, anchor); });
      var parent = dragging.getAttribute('data-parent');
      var order = rows().filter(function (r) { return r.getAttribute('data-parent') === parent; }).map(function (r) { return parseInt(r.getAttribute('data-id'), 10); });
      var body = new FormData();
      body.append('csrf_token', table.getAttribute('data-csrf'));
      body.append('action', 'reorder');
      body.append('order', JSON.stringify(order));
      fetch('/admin/cms-folders', { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (!d.ok) throw new Error(d.error || 'Could not save the new order.'); })
        .catch(function (err) { window.alert(err.message); window.location.reload(); });
    });
  });

  // Navigation editor: nested items serialized to menu_json on submit
  document.querySelectorAll('[data-nav-editor]').forEach(function (form) {
    var list = form.querySelector('[data-nav-list]');
    var dirtyNote = form.querySelector('[data-nav-dirty]');
    var dirty = false;
    var submitting = false;

    function markDirty() {
      dirty = true;
      if (dirtyNote) dirtyNote.hidden = false;
    }
    function field(node, name) {
      var scope = node.getAttribute('data-nav-node') === 'item' ? node.querySelector('.cms-nav-head') : node;
      return scope.querySelector('[data-f="' + name + '"]') || node.querySelector(':scope > [data-f="' + name + '"], :scope .cms-nav-dropdown-foot [data-f="' + name + '"]');
    }
    function children(item) {
      return Array.prototype.slice.call(item.querySelectorAll('[data-nav-node="child"]'));
    }
    function sync() {
      list.querySelectorAll('[data-nav-node]').forEach(function (node) {
        var box = field(node, 'visible');
        node.classList.toggle('is-off', box && !box.checked);
      });
      list.querySelectorAll('[data-nav-node="item"]').forEach(function (item) {
        var source = item.querySelector('.cms-nav-dropdown-foot [data-f="source"]');
        var isMenu = children(item).length > 0 || (source && source.value !== '');
        var kind = item.querySelector('[data-nav-kind]');
        if (kind) kind.textContent = isMenu ? 'Dropdown' : 'Link';
        item.classList.toggle('is-menu', isMenu);
        var url = field(item, 'url');
        if (url) url.placeholder = isMenu ? '"View all" URL (optional)' : '/page-url';
        var toggle = item.querySelector('[data-nav-toggle]');
        if (toggle) {
          var open = !item.classList.contains('is-collapsed');
          var n = children(item).length;
          var auto = source && source.value !== '';
          toggle.textContent = (n ? n + ' link' + (n === 1 ? '' : 's') + (auto ? ' + auto' : '') : (auto ? 'Automatic' : 'Add links')) + (open ? ' ▾' : ' ▸');
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
      });
      var items = list.querySelectorAll(':scope > [data-nav-node="item"]');
      Array.prototype.forEach.call(list.querySelectorAll('[data-nav-move]'), function (b) {
        var node = b.closest('[data-nav-node]');
        var step = parseInt(b.getAttribute('data-nav-move'), 10);
        b.disabled = step < 0 ? !node.previousElementSibling : !node.nextElementSibling;
      });
      if (items.length === 0) list.setAttribute('data-empty', 'No menu items yet.'); else list.removeAttribute('data-empty');
    }
    function add(tplId, target, values) {
      var tpl = document.getElementById(tplId);
      target.appendChild(tpl.content.cloneNode(true));
      var node = target.lastElementChild;
      Object.keys(values || {}).forEach(function (k) { var f = field(node, k); if (f) f.value = values[k]; });
      var first = field(node, 'label');
      if (first) first.focus();
      markDirty();
      sync();
      return node;
    }

    form.addEventListener('click', function (event) {
      var btn = event.target.closest('button');
      if (!btn || !form.contains(btn)) return;
      if (btn.hasAttribute('data-nav-toggle')) {
        btn.closest('[data-nav-node="item"]').classList.toggle('is-collapsed');
        sync();
      } else if (btn.hasAttribute('data-nav-add')) {
        add('tpl-nav-item', list);
      } else if (btn.hasAttribute('data-nav-add-child')) {
        add('tpl-nav-child', btn.closest('[data-nav-node="item"]').querySelector('[data-nav-children]'));
      } else if (btn.hasAttribute('data-nav-remove')) {
        var node = btn.closest('[data-nav-node]');
        var label = (field(node, 'label').value || 'this item').trim();
        var extra = node.getAttribute('data-nav-node') === 'item' && children(node).length ? ' and the ' + children(node).length + ' link(s) under it' : '';
        if (!window.confirm('Remove "' + label + '"' + extra + ' from the menu?')) return;
        node.remove();
        markDirty();
        sync();
      } else if (btn.hasAttribute('data-nav-move')) {
        var moving = btn.closest('[data-nav-node]');
        if (btn.getAttribute('data-nav-move') === '-1' && moving.previousElementSibling) {
          moving.parentNode.insertBefore(moving, moving.previousElementSibling);
        } else if (btn.getAttribute('data-nav-move') === '1' && moving.nextElementSibling) {
          moving.parentNode.insertBefore(moving.nextElementSibling, moving);
        }
        btn.focus();
        markDirty();
        sync();
      }
    });

    var addPage = form.querySelector('[data-nav-add-page]');
    if (addPage) {
      addPage.addEventListener('change', function () {
        var option = addPage.options[addPage.selectedIndex];
        if (!addPage.value) return;
        add('tpl-nav-item', list, { label: option.getAttribute('data-label') || '', url: addPage.value });
        addPage.value = '';
      });
    }

    // A chosen page fills an empty label with the page title.
    form.addEventListener('change', function (event) {
      var url = event.target.closest('[data-f="url"]');
      if (url) {
        var node = url.closest('[data-nav-node]');
        var label = field(node, 'label');
        var match = Array.prototype.find.call(document.querySelectorAll('#cms-nav-paths option'), function (o) { return o.value === url.value; });
        if (label && !label.value.trim() && match) label.value = match.textContent;
      }
      markDirty();
      sync();
    });
    form.addEventListener('input', function (event) {
      if (event.target.name !== 'menu_json') markDirty();
    });

    form.addEventListener('submit', function () {
      function read(node, names) {
        var out = {};
        names.forEach(function (n) {
          var f = field(node, n);
          if (f) out[n] = f.type === 'checkbox' ? f.checked : f.value;
        });
        return out;
      }
      var items = Array.prototype.map.call(list.querySelectorAll(':scope > [data-nav-node="item"]'), function (item) {
        var data = read(item, ['key', 'label', 'url', 'visible', 'new_tab', 'source', 'all_label']);
        data.children = children(item).map(function (c) { return read(c, ['label', 'url', 'visible', 'new_tab']); });
        return data;
      });
      form.querySelector('[name="menu_json"]').value = JSON.stringify(items);
      submitting = true;
    });
    window.addEventListener('beforeunload', function (event) {
      if (dirty && !submitting) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
    sync();
  });

  // Media picker
  var dialog = document.getElementById('cms-media-dialog');
  var MediaPicker = {
    callback: null,
    page: 1,
    query: '',
    open: function (callback) {
      if (!dialog) return;
      this.callback = callback;
      this.load(1, dialog.querySelector('[data-media-search]').value || '');
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    },
    close: function () {
      if (!dialog) return;
      if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
    },
    choose: function (item) {
      var cb = this.callback;
      this.close();
      if (cb) cb(item);
    },
    load: function (page, query) {
      var self = this;
      var grid = dialog.querySelector('[data-media-results]');
      var more = dialog.querySelector('[data-media-more]');
      self.page = page;
      self.query = query;
      if (page === 1) grid.innerHTML = '<p class="cms-media-dialog-empty">Loading…</p>';
      fetch('/admin/cms-media?format=json&page=' + page + '&q=' + encodeURIComponent(query), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (page === 1) grid.innerHTML = '';
          if (!data.items || !data.items.length) {
            if (page === 1) grid.innerHTML = '<p class="cms-media-dialog-empty">No images found. Upload one to get started.</p>';
          }
          (data.items || []).forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            var img = document.createElement('img');
            img.src = item.url;
            img.alt = '';
            img.loading = 'lazy';
            var label = document.createElement('span');
            label.textContent = item.alt || item.name || item.url;
            btn.appendChild(img);
            btn.appendChild(label);
            btn.addEventListener('click', function () { self.choose(item); });
            grid.appendChild(btn);
          });
          more.hidden = !(data.pages && page < data.pages);
        })
        .catch(function () {
          grid.innerHTML = '<p class="cms-media-dialog-empty">Could not load the media library.</p>';
        });
    },
    upload: function (file) {
      var self = this;
      var body = new FormData();
      body.append('action', 'upload');
      body.append('format', 'json');
      body.append('csrf_token', csrfToken());
      body.append('file', file);
      return fetch('/admin/cms-media', { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || 'Upload failed'); return d; }); })
        .then(function (data) { self.choose(data.item); return data.item; });
    }
  };
  window.CmsMediaPicker = MediaPicker;

  if (dialog) {
    var searchTimer;
    dialog.querySelector('[data-media-search]').addEventListener('input', function (event) {
      window.clearTimeout(searchTimer);
      var value = event.target.value;
      searchTimer = window.setTimeout(function () { MediaPicker.load(1, value); }, 250);
    });
    dialog.querySelector('[data-media-more]').addEventListener('click', function () {
      MediaPicker.load(MediaPicker.page + 1, MediaPicker.query);
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach(function (b) { b.addEventListener('click', function () { MediaPicker.close(); }); });
    var uploadInput = dialog.querySelector('[data-media-upload]');
    if (uploadInput) {
      uploadInput.addEventListener('change', function () {
        if (!uploadInput.files.length) return;
        MediaPicker.upload(uploadInput.files[0]).catch(function (err) { window.alert(err.message); });
        uploadInput.value = '';
      });
    }
  }

  document.addEventListener('click', function (event) {
    var pick = event.target.closest('[data-media-pick]');
    var clear = event.target.closest('[data-media-clear]');
    var field = (pick || clear) && (pick || clear).closest('[data-media-field]');
    if (!field) return;
    var idInput = field.querySelector('[data-media-id]');
    var thumb = field.querySelector('[data-media-thumb]');
    if (pick) {
      MediaPicker.open(function (item) {
        idInput.value = item.id;
        thumb.innerHTML = '';
        var img = document.createElement('img');
        img.src = item.url;
        img.alt = '';
        thumb.appendChild(img);
        idInput.dispatchEvent(new Event('input', { bubbles: true }));
      });
    } else {
      idInput.value = '0';
      thumb.innerHTML = '';
      idInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  // Block token follows the name until edited by hand: data-token-source="#token-input"
  document.querySelectorAll('[data-token-source]').forEach(function (nameInput) {
    var tokenInput = document.querySelector(nameInput.getAttribute('data-token-source'));
    var preview = document.querySelector('[data-token-preview]');
    if (!tokenInput) return;
    var touched = tokenInput.value !== '';
    function slug(v) { return v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64); }
    function show() { if (preview) preview.textContent = tokenInput.value || 'name'; }
    nameInput.addEventListener('input', function () { if (!touched) { tokenInput.value = slug(nameInput.value); show(); } });
    tokenInput.addEventListener('input', function () { touched = tokenInput.value !== ''; show(); });
  });

  // Plain-text code editor: data-code-editor on a textarea inside form[data-code-form]
  document.querySelectorAll('[data-code-editor]').forEach(function (area) {
    var form = area.form;
    var status = form.querySelector('[data-code-status]');
    var initial = area.value;
    var submitting = false;
    var indent = '  ';

    function lineStart(pos) { return area.value.lastIndexOf('\n', pos - 1) + 1; }
    function updateStatus() {
      if (!status) return;
      var pos = area.selectionStart;
      var line = area.value.slice(0, pos).split('\n').length;
      status.textContent = 'Line ' + line + ', column ' + (pos - lineStart(pos) + 1) + (area.value !== initial ? ' · unsaved changes' : '');
    }
    function replaceRange(start, end, text, selStart, selEnd) {
      area.setRangeText(text, start, end);
      area.setSelectionRange(selStart, selEnd);
      area.dispatchEvent(new Event('input', { bubbles: true }));
    }

    area.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        if (form.requestSubmit) form.requestSubmit(); else form.submit();
        return;
      }
      if (event.key !== 'Tab' || event.ctrlKey || event.metaKey || event.altKey) return;
      event.preventDefault();
      var start = area.selectionStart;
      var end = area.selectionEnd;
      var value = area.value;
      if (start === end && !event.shiftKey) {
        replaceRange(start, end, indent, start + indent.length, start + indent.length);
        return;
      }
      var from = lineStart(start);
      var block = value.slice(from, end);
      var lines = block.split('\n');
      var changed = event.shiftKey
        ? lines.map(function (l) { return l.replace(/^( {1,2}|\t)/, ''); })
        : lines.map(function (l) { return indent + l; });
      var text = changed.join('\n');
      var firstDelta = changed[0].length - lines[0].length;
      replaceRange(from, end, text, Math.max(from, start + firstDelta), from + text.length);
    });
    ['input', 'keyup', 'click'].forEach(function (type) { area.addEventListener(type, updateStatus); });
    form.addEventListener('submit', function () { submitting = true; });
    window.addEventListener('beforeunload', function (event) {
      if (!submitting && area.value !== initial) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
    if (area.hasAttribute('data-code-dirty')) initial = null;
  });

  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-goto-line]');
    var area = document.querySelector('[data-code-editor]');
    if (!link || !area) return;
    event.preventDefault();
    var target = parseInt(link.getAttribute('data-goto-line'), 10);
    var lines = area.value.split('\n');
    var pos = 0;
    for (var i = 0; i < target - 1 && i < lines.length; i++) pos += lines[i].length + 1;
    var end = pos + (lines[target - 1] || '').length;
    area.focus();
    area.setSelectionRange(pos, end);
    var lineHeight = parseFloat(getComputedStyle(area).lineHeight) || 18;
    area.scrollTop = Math.max(0, (target - 5) * lineHeight);
  });
})();
