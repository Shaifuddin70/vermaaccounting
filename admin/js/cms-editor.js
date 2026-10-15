/* CMS content editor: TinyMCE / HTML mode, live SEO previews and checks, autosave, unsaved-change warning. */
(function () {
  'use strict';

  var form = document.getElementById('cms-editor-form');
  if (!form) return;

  var base = form.getAttribute('data-base-url') || '';
  var suffix = form.getAttribute('data-title-suffix') || '';
  var contentId = parseInt(form.getAttribute('data-id') || '0', 10);
  var readOnly = form.getAttribute('data-readonly') === '1';
  var type = form.getAttribute('data-type') || 'page';
  var bodyField = document.getElementById('f-body');
  var rawBox = document.getElementById('f-body-raw');
  var toggleBtn = document.getElementById('cms-toggle-html');
  var dirty = false;
  var submitting = false;

  function $(sel) { return form.querySelector(sel); }
  function val(name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? String(el.value || '') : '';
  }
  function text(el, value) { if (el) el.textContent = value; }

  // ---------- City page defaults ----------
  // Empty fields on a city page show the site-wide default filled in with this page's location fields.
  var cityDefaults = null;
  try { cityDefaults = JSON.parse(form.getAttribute('data-city-defaults') || 'null'); } catch (err) { cityDefaults = null; }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; });
  }

  function cityDefault(field) {
    if (!cityDefaults || !cityDefaults[field]) return '';
    var vars = {
      city: val('city_city').trim(), region: val('city_region').trim(), country: val('city_country').trim(),
      service: val('city_primary_service').trim() || cityDefaults.service || '',
      phone: val('city_phone').trim(), email: val('city_email').trim(), site_name: cityDefaults.site_name || ''
    };
    var tokens = /\{(city|region|country|service|phone|email|site_name)\}/g;
    if (field === 'body_html') {
      return cityDefaults[field].replace(tokens, function (m, key) { return escapeHtml(vars[key]); });
    }
    if (follows(field)) return '';
    var template = cityDefaults[field];
    Object.keys(vars).forEach(function (key) {
      if (vars[key] === '') template = template.split(new RegExp('\\s*,\\s*\\{' + key + '\\}|\\{' + key + '\\}\\s*,\\s*')).join('');
    });
    return template.replace(tokens, function (m, key) { return vars[key]; }).replace(/\s*,\s*(?=,|$|\s[|\-–]|\))/g, '').replace(/\(\s*\)/g, '').replace(/\s{2,}/g, ' ').replace(/^[\s,]+|[\s,]+$/g, '');
  }

  // The H1 and SEO title follow a custom title; the meta description follows a custom summary.
  function follows(field) {
    var source = { h1: 'title', seo_title: 'title', meta_description: 'excerpt' }[field];
    return !!source && val(source).trim() !== '';
  }

  function valOrDefault(name) {
    return val(name).trim() || cityDefault(name);
  }

  function bodyIsEmpty(html) {
    return !/<(img|iframe|video|embed)\b/i.test(html) && plain(html) === '';
  }

  if (readOnly) {
    form.querySelectorAll('input, select, textarea, button').forEach(function (el) {
      if (!el.closest('.admin-tabs')) el.disabled = true;
    });
  }

  // ---------- Body editor ----------
  function editor() {
    return window.tinymce ? window.tinymce.get('f-body') : null;
  }

  function bodyHtml() {
    var ed = editor();
    return ed ? ed.getContent() : (bodyField ? bodyField.value : '');
  }

  function uploadImage(blobInfo, progress) {
    var data = new FormData();
    data.append('action', 'upload');
    data.append('format', 'json');
    data.append('csrf_token', form.getAttribute('data-csrf'));
    data.append('file', blobInfo.blob(), blobInfo.filename());
    return fetch('/admin/cms-media', { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok || !d.location) throw new Error(d.error || 'Upload failed'); return d.location; }); });
  }

  function startEditor() {
    if (!bodyField || !window.tinymce || editor() || readOnly) return;
    if (rawBox && rawBox.checked) return;
    window.tinymce.init({
      target: bodyField,
      license_key: 'gpl',
      base_url: '/admin/vendor/tinymce',
      suffix: '.min',
      height: 620,
      menubar: 'edit view insert format table tools help',
      plugins: 'link lists image table code autolink fullscreen wordcount searchreplace visualblocks anchor charmap preview media help',
      toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link anchor | image media table | alignleft aligncenter alignright | blockquote charmap | removeformat visualblocks code fullscreen',
      block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Preformatted=pre',
      content_css: ['https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', '/css/styles.css', '/css/cms.css'],
      body_class: 'cms-body',
      content_style: 'body { padding: 1rem; max-width: 52rem; margin: 0 auto; }',
      relative_urls: false,
      remove_script_host: true,
      document_base_url: base + '/',
      convert_urls: true,
      link_default_protocol: 'https',
      link_assume_external_targets: 'https',
      link_target_list: [{ title: 'Same tab', value: '' }, { title: 'New tab', value: '_blank' }],
      rel_list: [{ title: 'None', value: '' }, { title: 'nofollow', value: 'nofollow' }, { title: 'sponsored', value: 'sponsored' }, { title: 'ugc', value: 'ugc' }],
      image_caption: true,
      image_advtab: false,
      image_dimensions: true,
      a11y_advanced_options: true,
      automatic_uploads: true,
      images_upload_handler: uploadImage,
      paste_data_images: true,
      file_picker_types: 'image',
      file_picker_callback: function (callback) {
        if (!window.CmsMediaPicker) return;
        window.CmsMediaPicker.open(function (item) { callback(item.url, { alt: item.alt || '', width: String(item.width || ''), height: String(item.height || '') }); });
      },
      entity_encoding: 'raw',
      promotion: false,
      branding: false,
      setup: function (ed) {
        ed.on('change input undo redo', function () { markDirty(); scheduleRefresh(); });
        ed.on('init', scheduleRefresh);
      }
    });
    if (toggleBtn) toggleBtn.textContent = 'Edit HTML';
  }

  function stopEditor() {
    var ed = editor();
    if (!ed) return;
    ed.save();
    ed.remove();
    if (toggleBtn) toggleBtn.textContent = 'Visual editor';
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      if (editor()) stopEditor(); else startEditor();
    });
  }
  if (rawBox) {
    rawBox.addEventListener('change', function () {
      if (rawBox.checked) {
        if (!window.confirm('Unfiltered HTML keeps scripts and any markup exactly as typed. Only use it for trusted code. Continue?')) {
          rawBox.checked = false;
          return;
        }
        stopEditor();
        if (toggleBtn) toggleBtn.disabled = true;
      } else if (toggleBtn) {
        toggleBtn.disabled = false;
      }
      markDirty();
    });
  }
  if (bodyField) {
    bodyField.addEventListener('input', function () { markDirty(); scheduleRefresh(); });
    if (!rawBox || !rawBox.checked) startEditor();
  }

  document.querySelectorAll('[data-media-insert]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!window.CmsMediaPicker) return;
      window.CmsMediaPicker.open(function (item) {
        var alt = item.alt || '';
        var html = '<img src="' + escapeAttr(item.url) + '" alt="' + escapeAttr(alt) + '"' +
          (item.width ? ' width="' + item.width + '" height="' + item.height + '"' : '') + '>';
        var ed = editor();
        if (ed) {
          ed.insertContent(html);
          markDirty();
          scheduleRefresh();
        } else if (bodyField) {
          var start = bodyField.selectionStart || bodyField.value.length;
          bodyField.value = bodyField.value.slice(0, start) + html + bodyField.value.slice(bodyField.selectionEnd || start);
          bodyField.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (!alt) window.alert('This image has no alt text. Add a description in the image settings so it is accessible and indexable.');
      });
    });
  });

  document.querySelectorAll('[data-city-default-body]').forEach(function (button) {
    button.addEventListener('click', function () {
      var html = cityDefault('body_html');
      if (!html) { window.alert('No default body is set yet. Add one under City pages → Default content.'); return; }
      var ed = editor();
      if (ed) ed.setContent(html); else if (bodyField) bodyField.value = html;
      markDirty();
      scheduleRefresh();
    });
  });

  document.querySelectorAll('[data-block-insert]').forEach(function (select) {
    select.addEventListener('change', function () {
      var token = select.value;
      select.value = '';
      if (!token) return;
      var ed = editor();
      if (ed) {
        ed.insertContent('<p>' + token + '</p>');
        markDirty();
        scheduleRefresh();
      } else if (bodyField) {
        var start = bodyField.selectionStart || bodyField.value.length;
        bodyField.value = bodyField.value.slice(0, start) + '\n' + token + '\n' + bodyField.value.slice(bodyField.selectionEnd || start);
        bodyField.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  });

  function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  // ---------- URL ----------
  function slugify(s) {
    s = String(s || '').trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    s = s.replace(/&/g, ' and ').replace(/\+/g, ' plus ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return s.slice(0, 120).replace(/-+$/, '');
  }

  var slugInput = document.getElementById('f-slug');
  var slugTouched = !!(slugInput && slugInput.value);
  if (slugInput) slugInput.addEventListener('input', function () { slugTouched = slugInput.value !== ''; });
  var urlEdit = document.getElementById('cms-url-edit');
  var changeUrl = document.getElementById('f-change-url');
  var originalPath = (document.getElementById('cms-url-path') || {}).textContent || '/';

  if (changeUrl && urlEdit) {
    changeUrl.addEventListener('change', function () { urlEdit.hidden = !changeUrl.checked; refresh(); });
  }

  function currentPath() {
    var changing = !changeUrl || changeUrl.checked;
    if (!changing) return originalPath;
    var mode = (form.querySelector('[name="url_mode"]:checked') || {}).value || 'auto';
    if (mode === 'custom') {
      var p = val('custom_path').trim().toLowerCase().replace(/\\/g, '/').replace(/\/+/g, '/').replace(/\/$/, '');
      return p === '' ? '/' : (p.charAt(0) === '/' ? p : '/' + p);
    }
    var folder = $('[name="folder_id"]');
    var option = folder && folder.options[folder.selectedIndex];
    var prefix = option ? (option.getAttribute('data-prefix') || '') : '';
    var slug = val('slug') || slugify(val('title') || (cityDefaults ? val('city_city') : ''));
    return (prefix.replace(/\/$/, '') + '/' + slug) || '/';
  }

  function syncUrlModes() {
    var mode = (form.querySelector('[name="url_mode"]:checked') || {}).value || 'auto';
    form.querySelectorAll('[data-url-mode]').forEach(function (el) { el.hidden = el.getAttribute('data-url-mode') !== mode; });
  }

  // ---------- Counters ----------
  function updateCounters() {
    form.querySelectorAll('[data-counter]').forEach(function (field) {
      var out = form.querySelector('[data-count-for="' + field.getAttribute('data-counter') + '"]');
      if (!out) return;
      var len = field.value.length;
      var min = parseInt(field.getAttribute('data-min') || '0', 10);
      var max = parseInt(field.getAttribute('data-max') || '0', 10);
      var cls = 'cms-count--ok';
      if (len === 0) cls = '';
      else if (max && len > max) cls = 'cms-count--bad';
      else if (min && len < min) cls = 'cms-count--warn';
      out.className = 'cms-count ' + cls;
      out.textContent = len + (max ? ' / ' + max : '') + ' characters.';
    });
  }

  // ---------- Previews and checks ----------
  function plain(html) {
    var div = document.createElement('div');
    div.innerHTML = html;
    div.querySelectorAll('script, style').forEach(function (n) { n.remove(); });
    return (div.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function truncate(s, n) {
    if (s.length <= n) return s;
    var cut = s.slice(0, n);
    var space = cut.lastIndexOf(' ');
    return (space > n * 0.6 ? cut.slice(0, space) : cut) + '…';
  }

  function effectiveTitle() {
    var custom = valOrDefault('seo_title');
    if (custom) return custom;
    var title = valOrDefault('title');
    var bare = suffix.replace(/^[\s|\-–]+|[\s|\-–]+$/g, '').toLowerCase();
    return bare && title.toLowerCase().indexOf(bare) !== -1 ? title : title + suffix;
  }

  function effectiveDescription(html) {
    return valOrDefault('meta_description') || valOrDefault('excerpt') || truncate(plain(html), 155);
  }

  function thumbUrl(field) {
    var img = form.querySelector('[data-media-field="' + field + '"] [data-media-thumb] img');
    return img ? img.getAttribute('src') : '';
  }

  function check(list, level, message) {
    var li = document.createElement('li');
    li.className = 'cms-check cms-check--' + level;
    li.textContent = message;
    list.appendChild(li);
  }

  function analyse(html) {
    var div = document.createElement('div');
    div.innerHTML = html;
    var words = plain(html).split(' ').filter(Boolean).length;
    var headings = Array.prototype.map.call(div.querySelectorAll('h1, h2, h3, h4, h5, h6'), function (h) { return parseInt(h.tagName.slice(1), 10); });
    var images = div.querySelectorAll('img');
    var missingAlt = Array.prototype.filter.call(images, function (i) { return !(i.getAttribute('alt') || '').trim(); }).length;
    var links = div.querySelectorAll('a[href]');
    var internal = Array.prototype.filter.call(links, function (a) {
      var href = a.getAttribute('href');
      return href.charAt(0) === '/' || (base && href.indexOf(base) === 0);
    }).length;
    var skipped = false;
    var last = 1;
    headings.forEach(function (lvl) { if (lvl > last + 1) skipped = true; last = lvl; });
    var vague = Array.prototype.filter.call(links, function (a) { return /^(click here|here|read more|learn more|more)$/i.test((a.textContent || '').trim()); }).length;
    return { words: words, headings: headings, images: images.length, missingAlt: missingAlt, links: links.length, internal: internal, skipped: skipped, vague: vague };
  }

  function syncCityDefaults(usingDefaultBody) {
    if (!cityDefaults) return;
    form.querySelectorAll('[data-city-default]').forEach(function (el) {
      if (!el.hasAttribute('data-placeholder-fallback')) el.setAttribute('data-placeholder-fallback', el.getAttribute('placeholder') || '');
      var field = el.getAttribute('data-city-default');
      var fallback = field === 'h1' ? 'Same as title' : (field === 'seo_title' ? (valOrDefault('title') + suffix) : '');
      el.setAttribute('placeholder', cityDefault(field) || fallback);
    });
    var note = document.getElementById('cms-city-default-note');
    if (note) note.hidden = !usingDefaultBody;
  }

  function refresh() {
    var html = bodyHtml();
    var usingDefaultBody = !!cityDefaults && bodyIsEmpty(html);
    if (usingDefaultBody) html = cityDefault('body_html');
    syncCityDefaults(usingDefaultBody);
    var path = currentPath();
    var title = effectiveTitle();
    var desc = effectiveDescription(html);

    text(document.getElementById('cms-url-path'), path);
    text(document.getElementById('serp-url'), base + path);
    text(document.getElementById('serp-title'), truncate(title, 62));
    text(document.getElementById('serp-desc'), truncate(desc, 160));
    text(document.getElementById('social-title'), val('og_title').trim() || title);
    text(document.getElementById('social-desc'), val('og_description').trim() || desc);
    var socialImg = document.getElementById('social-image');
    if (socialImg) {
      var img = thumbUrl('og') || val('og_image_url').trim() || thumbUrl('featured') || val('featured_image_url').trim();
      socialImg.style.backgroundImage = img ? 'url("' + img.replace(/"/g, '%22') + '")' : '';
    }

    var a = analyse(html);
    var stats = document.getElementById('cms-body-stats');
    if (stats) {
      stats.textContent = a.words + ' words · ' + a.headings.length + ' headings · ' + a.images + ' images · ' +
        a.links + ' links (' + a.internal + ' internal)';
    }

    var list = document.getElementById('cms-live-checks');
    if (!list) return;
    list.innerHTML = '';
    if (cityDefaults && !val('city_city').trim()) check(list, 'error', 'Add the city name on the Location tab.');
    else if (!valOrDefault('title')) check(list, 'error', 'Add a title.');
    if (usingDefaultBody) check(list, 'info', 'Uses the default city content. Add local details to make this page unique.');
    if (title.length > 60) check(list, 'warning', 'Search title is ' + title.length + ' characters; it may be cut off after about 60.');
    else if (title.length >= 30) check(list, 'ok', 'Search title length looks good.');
    else if (title.length > 0) check(list, 'warning', 'Search title is short; describe the page more specifically.');
    var metaLen = valOrDefault('meta_description').length;
    if (metaLen === 0) check(list, 'info', 'No meta description; the ' + (valOrDefault('excerpt') ? 'summary' : 'first paragraph') + ' is used.');
    else if (metaLen < 70) check(list, 'warning', 'Meta description is short (' + metaLen + ' characters).');
    else if (metaLen > 160) check(list, 'warning', 'Meta description is long (' + metaLen + ' characters) and may be cut off.');
    else check(list, 'ok', 'Meta description length looks good.');
    if (a.headings.indexOf(1) !== -1) check(list, 'warning', 'The body contains an H1. The page title is already the H1; use Heading 2 instead.');
    if (a.skipped) check(list, 'warning', 'Heading levels skip a step (for example H2 → H4).');
    if (a.missingAlt) check(list, 'warning', a.missingAlt + ' image(s) in the body have no alt text.');
    if (a.vague) check(list, 'warning', a.vague + ' link(s) use vague text like “click here”.');
    var minWords = type === 'page' ? 150 : 300;
    if (!usingDefaultBody) {
      if (a.words < minWords) check(list, 'warning', 'Only ' + a.words + ' words. ' + (type === 'city' ? 'City pages need substantial unique local content.' : 'Thin pages rarely rank.'));
      else check(list, 'ok', a.words + ' words of content.');
    }
    if (a.internal === 0 && a.words > 0) check(list, 'info', 'No internal links. Link to related services or articles.');
    if (val('robots_index') === 'noindex') check(list, 'warning', 'This page is set to noindex and will not appear in search results.');
    if (val('canonical_mode') === 'custom') check(list, 'info', 'Canonical points elsewhere; this page will be left out of the sitemap.');
    if (type === 'article' && !thumbUrl('featured') && !val('featured_image_url').trim()) check(list, 'info', 'Articles look better in search and social with a featured image.');
    if (path !== originalPath && changeUrl && changeUrl.checked) check(list, 'warning', 'You are changing the URL. Keep the 301 redirect ticked so existing links and rankings carry over.');
  }

  var refreshTimer;
  function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(function () { updateCounters(); refresh(); }, 200);
  }

  // ---------- Dirty tracking and autosave ----------
  function markDirty() {
    if (readOnly) return;
    dirty = true;
  }

  form.addEventListener('input', function () { markDirty(); scheduleRefresh(); });
  form.addEventListener('change', function (event) {
    if (event.target.name === 'url_mode') syncUrlModes();
    markDirty();
    scheduleRefresh();
  });
  form.addEventListener('submit', function () {
    if (window.tinymce) window.tinymce.triggerSave();
    submitting = true;
  });
  window.addEventListener('beforeunload', function (event) {
    if (dirty && !submitting) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  var status = document.getElementById('cms-autosave-status');
  function autosave() {
    if (!dirty || readOnly || contentId <= 0 || submitting) return;
    if (window.tinymce) window.tinymce.triggerSave();
    var data = new FormData(form);
    data.set('action', 'autosave');
    data.delete('workflow_action');
    text(status, 'Autosaving…');
    fetch('/admin/cms-action', { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) {
          dirty = false;
          text(status, 'Draft autosaved ' + d.saved_at + '. Save to apply your changes.');
        } else {
          text(status, d.error || 'Autosave failed.');
        }
      })
      .catch(function () { text(status, 'Autosave failed; check your connection.'); });
  }
  if (!readOnly && contentId > 0) {
    window.setInterval(autosave, 30000);
  } else if (!readOnly) {
    text(status, 'Autosave starts after the first save.');
  }

  syncUrlModes();
  updateCounters();
  refresh();
  document.addEventListener('cms:tab', scheduleRefresh);
})();
