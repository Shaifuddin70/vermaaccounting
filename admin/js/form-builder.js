(function () {
  const config = window.FORM_BUILDER_CONFIG;
  let state = structuredClone(config.initial);

  const el = (id) => document.getElementById(id);
  const fieldList = el('field-list');
  const fieldEditor = el('field-editor');
  const noFieldSelected = el('no-field-selected');
  let selectedFieldId = state.schema.fields[0]?.id || null;
  let activePageIndex = 0;

  function ensureSchemaSettings() {
    if (!state.schema) {
      state.schema = { version: 1, fields: [], settings: {} };
    }
    if (!state.schema.settings || typeof state.schema.settings !== 'object') {
      state.schema.settings = {};
    }
    if (!state.schema.fields) {
      state.schema.fields = [];
    }
  }

  ensureSchemaSettings();

  function uid() {
    return 'f_' + Math.random().toString(16).slice(2, 10);
  }

  function slugify(text) {
    return text
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'form';
  }

  function showStatus(msg, ok) {
    if (window.AdminToast) {
      if (ok) {
        window.AdminToast.success(msg);
      } else {
        window.AdminToast.error(msg);
      }
      return;
    }
    const box = el('save-status');
    if (!box) return;
    box.style.display = 'block';
    box.className = 'admin-alert ' + (ok ? 'admin-alert-success' : 'admin-alert-error');
    box.textContent = msg;
  }

  /** @returns {{title: string, fields: array, breakField?: object|null}[]} */
  function getPages() {
    ensureSchemaSettings();
    const pages = [];
    let current = {
      title: String(state.schema.settings.firstPageTitle || '').trim() || 'Page 1',
      fields: [],
      breakField: null,
    };
    state.schema.fields.forEach((field) => {
      if (field.type === 'page_break') {
        pages.push(current);
        current = {
          title: String(field.label || '').trim() || 'Page ' + (pages.length + 1),
          fields: [],
          breakField: field,
        };
        return;
      }
      current.fields.push(field);
    });
    pages.push(current);
    return pages;
  }

  function setPages(pages) {
    ensureSchemaSettings();
    if (!pages.length) {
      pages = [{ title: 'Page 1', fields: [], breakField: null }];
    }
    state.schema.settings.firstPageTitle = String(pages[0].title || '').trim() || 'Page 1';
    const flat = [];
    pages.forEach((page, index) => {
      if (index > 0) {
        const br = page.breakField && page.breakField.type === 'page_break'
          ? { ...page.breakField }
          : defaultFieldFromType('page_break');
        br.type = 'page_break';
        br.label = String(page.title || '').trim() || 'Page ' + (index + 1);
        br.required = false;
        br.conditions = [];
        flat.push(br);
      }
      (page.fields || []).forEach((field) => {
        if (field && field.type !== 'page_break') flat.push(field);
      });
    });
    state.schema.fields = flat;
  }

  function findPageIndexForField(fieldId) {
    const pages = getPages();
    for (let i = 0; i < pages.length; i++) {
      if (pages[i].fields.some((f) => f.id === fieldId)) return i;
    }
    return 0;
  }

  function syncActivePageSelection() {
    const pages = getPages();
    if (activePageIndex >= pages.length) activePageIndex = Math.max(0, pages.length - 1);
    if (selectedFieldId) {
      const onPage = pages[activePageIndex]?.fields.some((f) => f.id === selectedFieldId);
      if (!onPage) {
        selectedFieldId = pages[activePageIndex]?.fields[0]?.id || null;
      }
    } else {
      selectedFieldId = pages[activePageIndex]?.fields[0]?.id || null;
    }
  }

  function renderPageTabs() {
    const tabs = el('fb-page-tabs');
    const titleInput = el('fb-page-title');
    const deleteBtn = el('fb-page-delete');
    if (!tabs) return;

    const pages = getPages();
    if (activePageIndex >= pages.length) activePageIndex = Math.max(0, pages.length - 1);

    tabs.innerHTML = pages
      .map((page, index) => {
        const label = escapeHtml(page.title || 'Page ' + (index + 1));
        const count = page.fields.length;
        return (
          '<button type="button" class="fb-page-tab' +
          (index === activePageIndex ? ' is-active' : '') +
          '" role="tab" aria-selected="' +
          (index === activePageIndex ? 'true' : 'false') +
          '" data-page-index="' +
          index +
          '">' +
          '<span class="fb-page-tab-label">' +
          label +
          '</span>' +
          '<span class="fb-page-tab-count">' +
          count +
          '</span>' +
          '</button>'
        );
      })
      .join('');

    tabs.querySelectorAll('[data-page-index]').forEach((btn) => {
      btn.addEventListener('click', () => {
        activePageIndex = Number(btn.getAttribute('data-page-index')) || 0;
        syncActivePageSelection();
        renderPageTabs();
        renderFieldList();
        renderFieldEditor();
      });
    });

    if (titleInput && document.activeElement !== titleInput) {
      titleInput.value = pages[activePageIndex]?.title || '';
    }
    if (deleteBtn) {
      deleteBtn.hidden = pages.length < 2;
    }
  }

  function addPage() {
    const pages = getPages();
    pages.push({
      title: 'Page ' + (pages.length + 1),
      fields: [],
      breakField: defaultFieldFromType('page_break'),
    });
    setPages(pages);
    activePageIndex = pages.length - 1;
    selectedFieldId = null;
    renderPageTabs();
    renderFieldList();
    renderFieldEditor();
    el('fb-page-title')?.focus();
  }

  function deleteActivePage() {
    const pages = getPages();
    if (pages.length < 2) return;
    if (!confirm('Delete this page? Its fields will move to the previous page.')) return;
    const removing = pages[activePageIndex];
    const targetIndex = Math.max(0, activePageIndex - 1);
    pages[targetIndex].fields = pages[targetIndex].fields.concat(removing.fields || []);
    pages.splice(activePageIndex, 1);
    setPages(pages);
    activePageIndex = Math.min(targetIndex, pages.length - 1);
    syncActivePageSelection();
    renderPageTabs();
    renderFieldList();
    renderFieldEditor();
  }

  function updateActivePageTitle(title, { finalize = false } = {}) {
    const pages = getPages();
    if (!pages[activePageIndex]) return;
    let next = String(title ?? '');
    if (finalize) {
      next = next.trim() || 'Page ' + (activePageIndex + 1);
    }
    pages[activePageIndex].title = next;
    setPages(pages);
    renderPageTabs();
  }

  function moveFieldToPage(fieldId, toPageIndex) {
    const pages = getPages();
    let field = null;
    let fromIndex = -1;
    pages.forEach((page, index) => {
      const i = page.fields.findIndex((f) => f.id === fieldId);
      if (i >= 0) {
        field = page.fields.splice(i, 1)[0];
        fromIndex = index;
      }
    });
    if (!field) return;
    const dest = Math.max(0, Math.min(toPageIndex, pages.length - 1));
    pages[dest].fields.push(field);
    setPages(pages);
    activePageIndex = dest;
    selectedFieldId = fieldId;
    renderPageTabs();
    renderFieldList();
    renderFieldEditor();
  }

  let dragFromIndex = null;

  function clearDropIndicators() {
    fieldList.querySelectorAll('.builder-field-item').forEach((node) => {
      node.classList.remove('drop-before', 'drop-after', 'is-dragging');
    });
  }

  function reorderFieldsOnActivePage(fromLocalIndex, toLocalIndex, insertAfter) {
    if (fromLocalIndex === null) return;
    const pages = getPages();
    const page = pages[activePageIndex];
    if (!page) return;
    const fields = page.fields;
    const [moved] = fields.splice(fromLocalIndex, 1);
    let insertAt = toLocalIndex;
    if (insertAfter) insertAt++;
    if (fromLocalIndex < insertAt) insertAt--;
    insertAt = Math.max(0, Math.min(insertAt, fields.length));
    fields.splice(insertAt, 0, moved);
    setPages(pages);
    renderFieldList();
    renderFieldEditor();
  }

  function renderFieldList() {
    const emptyEl = el('field-list-empty');
    const pages = getPages();
    const page = pages[activePageIndex] || { fields: [] };
    const pageFields = page.fields || [];

    if (emptyEl) {
      emptyEl.hidden = pageFields.length > 0;
      emptyEl.textContent =
        pages.length > 1
          ? 'No fields on this page yet. Add a field or switch tabs.'
          : 'Add fields below or use the toolbar to get started.';
    }

    fieldList.innerHTML = '';
    pageFields.forEach((field, localIndex) => {
      const item = document.createElement('div');
      item.className =
        'builder-field-item' + (field.id === selectedFieldId ? ' is-selected' : '');
      item.dataset.id = field.id;
      item.dataset.localIndex = String(localIndex);

      item.innerHTML =
        '<span class="builder-drag-handle" title="Drag to reorder" aria-hidden="true">⠿</span>' +
        '<div class="builder-field-body">' +
        '<span class="field-type">' +
        (config.fieldTypes[field.type] || field.type) +
        '</span>' +
        '<strong>' +
        escapeHtml(field.label) +
        '</strong>' +
        (field.required ? ' <span class="builder-field-required" aria-label="Required">*</span>' : '') +
        '</div>';

      const handle = item.querySelector('.builder-drag-handle');
      const body = item.querySelector('.builder-field-body');

      handle.draggable = true;
      handle.addEventListener('dragstart', (e) => {
        dragFromIndex = localIndex;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(localIndex));
        item.classList.add('is-dragging');
      });

      handle.addEventListener('dragend', () => {
        dragFromIndex = null;
        clearDropIndicators();
      });

      item.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (dragFromIndex === null) return;
        fieldList.querySelectorAll('.builder-field-item').forEach((n) => {
          n.classList.remove('drop-before', 'drop-after');
        });
        const rect = item.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        item.classList.add(e.clientY < mid ? 'drop-before' : 'drop-after');
      });

      item.addEventListener('dragleave', (e) => {
        if (!item.contains(e.relatedTarget)) {
          item.classList.remove('drop-before', 'drop-after');
        }
      });

      item.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const from = dragFromIndex ?? Number(e.dataTransfer.getData('text/plain'));
        const insertAfter = item.classList.contains('drop-after');
        clearDropIndicators();
        reorderFieldsOnActivePage(from, localIndex, insertAfter);
      });

      body.addEventListener('click', () => selectField(field.id));

      fieldList.appendChild(item);
    });

    renderDataMatchFields();
    renderPageTabs();
  }

  const MATCHABLE_TYPES = ['text', 'email', 'tel', 'number', 'date', 'select', 'radio', 'yes_no'];

  function renderDataMatchFields() {
    const container = el('data-match-fields');
    if (!container) return;
    ensureSchemaSettings();
    if (!state.schema.settings.dataMatch) {
      state.schema.settings.dataMatch = {
        enabled: false,
        fieldIds: [],
        title: '',
        message: '',
        confirmLabel: '',
        declineLabel: '',
      };
    }
    const selected = new Set(state.schema.settings.dataMatch.fieldIds || []);
    const fields = state.schema.fields.filter((f) => MATCHABLE_TYPES.includes(f.type));
    if (fields.length < 2) {
      container.innerHTML =
        '<p style="color:#64748b;font-size:0.875rem;margin:0;">Add at least two text-like fields (e.g. email and phone) to use matching.</p>';
      return;
    }
    container.innerHTML = fields
      .map(
        (f) =>
          '<label class="data-match-field-option">' +
          '<input type="checkbox" data-match-field-id="' +
          escapeHtml(f.id) +
          '" ' +
          (selected.has(f.id) ? 'checked' : '') +
          '> ' +
          escapeHtml(f.label) +
          ' <span style="color:#64748b;font-size:0.8rem;">(' +
          escapeHtml(config.fieldTypes[f.type] || f.type) +
          ')</span></label>'
      )
      .join('');
    container.querySelectorAll('input[data-match-field-id]').forEach((cb) => {
      cb.addEventListener('change', syncDataMatchSettings);
    });
  }

  function syncDataMatchSettings() {
    ensureSchemaSettings();
    const container = el('data-match-fields');
    const fieldIds = [];
    if (container) {
      container.querySelectorAll('input[data-match-field-id]:checked').forEach((cb) => {
        fieldIds.push(cb.getAttribute('data-match-field-id'));
      });
    }
    state.schema.settings.dataMatch = {
      enabled: !!el('data-match-enabled')?.checked,
      fieldIds,
      title: (el('data-match-title')?.value || '').trim() || 'We found your information',
      message:
        (el('data-match-message')?.value || '').trim() ||
        'A previous submission matches what you entered. Would you like to fill this form with that saved information?',
      confirmLabel: (el('data-match-confirm')?.value || '').trim() || 'Yes, fill the form',
      declineLabel: (el('data-match-decline')?.value || '').trim() || 'No, start fresh',
    };
    return state.schema.settings.dataMatch;
  }

  function toggleDataMatchSettingsUi() {
    const on = el('data-match-enabled')?.checked ?? false;
    const panel = el('data-match-settings');
    if (panel) panel.style.display = on ? '' : 'none';
    const section = el('fb-section-autofill');
    if (section && on) {
      section.open = true;
    }
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function selectField(id) {
    selectedFieldId = id;
    if (id) {
      activePageIndex = findPageIndexForField(id);
    }
    renderFieldList();
    renderFieldEditor();
  }

  function getSelectedField() {
    const field = state.schema.fields.find((f) => f.id === selectedFieldId);
    if (field && field.type === 'page_break') return null;
    return field || null;
  }

  function renderFieldEditor() {
    const field = getSelectedField();
    if (!field) {
      fieldEditor.style.display = 'none';
      noFieldSelected.style.display = 'block';
      return;
    }
    noFieldSelected.style.display = 'none';
    fieldEditor.style.display = 'block';

    const otherFields = state.schema.fields.filter(
      (f) => f.id !== field.id && !['heading', 'paragraph', 'page_break'].includes(f.type)
    );

    const optionsHtml =
      field.options
        ?.map(
          (opt, i) => `
        <div class="condition-row" style="grid-template-columns:1fr 1fr auto;">
          <input type="text" data-opt-label="${i}" value="${escapeAttr(opt.label)}" placeholder="Label">
          <input type="text" data-opt-value="${i}" value="${escapeAttr(opt.value)}" placeholder="Value">
          <button type="button" class="admin-btn admin-btn-danger" data-remove-opt="${i}">×</button>
        </div>`
        )
        .join('') || '';

    const conditionsHtml = renderConditionsEditor(field, otherFields);

    const placeholderRow =
      ['text', 'textarea', 'email', 'tel', 'number'].includes(field.type) &&
      ['text', 'textarea'].includes(field.type)
        ? `<div class="admin-fields-2col">
            <div class="admin-field"><label>Placeholder</label><input type="text" id="fe-placeholder" value="${escapeAttr(field.placeholder || '')}"></div>
            <div class="admin-field"><label>Help text</label><input type="text" id="fe-help" value="${escapeAttr(field.helpText || '')}"></div>
          </div>`
        : ['text', 'textarea', 'email', 'tel', 'number'].includes(field.type)
          ? `<div class="admin-field admin-field--full"><label>Placeholder</label><input type="text" id="fe-placeholder" value="${escapeAttr(field.placeholder || '')}"></div>`
          : ['text', 'textarea'].includes(field.type)
            ? `<div class="admin-field admin-field--full"><label>Help text</label><input type="text" id="fe-help" value="${escapeAttr(field.helpText || '')}"></div>`
            : field.type === 'partners' && ['text', 'number'].includes(field.partnerInput || 'select')
              ? `<div class="admin-fields-2col">
                  <div class="admin-field"><label>Placeholder</label><input type="text" id="fe-placeholder" value="${escapeAttr(field.placeholder || '')}"></div>
                  <div class="admin-field"><label>Help text</label><input type="text" id="fe-help" value="${escapeAttr(field.helpText || '')}"></div>
                </div>`
              : field.type === 'partners'
                ? `<div class="admin-field admin-field--full"><label>Help text</label><input type="text" id="fe-help" value="${escapeAttr(field.helpText || '')}"></div>`
                : '';

    const pages = getPages();
    const currentPageIndex = findPageIndexForField(field.id);
    const pageOptions = pages
      .map(
        (page, index) =>
          `<option value="${index}" ${index === currentPageIndex ? 'selected' : ''}>${escapeHtml(
            page.title || 'Page ' + (index + 1)
          )}</option>`
      )
      .join('');

    fieldEditor.innerHTML = `
      <div class="admin-fields-2col">
        <div class="admin-field admin-field--full">
          <label>Field type</label>
          <select id="fe-type">
            ${Object.entries(config.fieldTypes)
              .filter(([t]) => t !== 'page_break')
              .map(([t, l]) => `<option value="${t}" ${field.type === t ? 'selected' : ''}>${l}</option>`)
              .join('')}
          </select>
        </div>
        <div class="admin-field">
          <label>Label</label>
          <input type="text" id="fe-label" value="${escapeAttr(field.label)}">
        </div>
        <div class="admin-field">
          <label>Field name (for data)</label>
          <input type="text" id="fe-name" value="${escapeAttr(field.name)}">
        </div>
        ${
          pages.length > 1
            ? `<div class="admin-field admin-field--full">
                <label for="fe-page">Page</label>
                <select id="fe-page">${pageOptions}</select>
                <small class="admin-field-hint">Move this field to another form page/tab.</small>
              </div>`
            : ''
        }
        ${
          !['heading', 'paragraph'].includes(field.type)
            ? `<div class="admin-field admin-field--full"><label class="admin-checkbox-label"><input type="checkbox" id="fe-required" ${field.required ? 'checked' : ''}><span>Required</span></label></div>`
            : ''
        }
      </div>
      ${placeholderRow}
      ${
        field.type === 'number'
          ? (() => {
              const fmt = field.numberFormat || '';
              const presets = {
                '': 'None (plain number)',
                '###-###-###': 'SIN (Social Insurance Number) — ###-###-###',
                '#####': 'Postal (digits) — #####',
              };
              const presetKeys = Object.keys(presets);
              const isPreset = presetKeys.includes(fmt);
              return `
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="fe-number-format-preset">Number format</label>
          <select id="fe-number-format-preset">
            ${presetKeys
              .map(
                (key) =>
                  `<option value="${escapeAttr(key)}" ${isPreset && fmt === key ? 'selected' : ''}>${presets[key]}</option>`
              )
              .join('')}
            <option value="__custom" ${!isPreset && fmt ? 'selected' : ''}>Custom…</option>
          </select>
        </div>
        <div class="admin-field">
          <label for="fe-number-format">Format pattern</label>
          <input type="text" id="fe-number-format" value="${escapeAttr(fmt)}" placeholder="e.g. ###-###-###">
          <small class="admin-field-hint">Use <code>#</code> for each digit. Example: <code>###-###-###</code> → 123-456-789</small>
        </div>
      </div>`;
            })()
          : ''
      }
      ${
        field.type === 'tel'
          ? (() => {
              const countries = {
                CA: { name: 'Canada', dial: '+1', format: '(###) ###-####' },
                US: { name: 'United States', dial: '+1', format: '(###) ###-####' },
                GB: { name: 'United Kingdom', dial: '+44', format: '#### ######' },
                AU: { name: 'Australia', dial: '+61', format: '### ### ###' },
                IN: { name: 'India', dial: '+91', format: '##### #####' },
                BD: { name: 'Bangladesh', dial: '+880', format: '####-######' },
                PK: { name: 'Pakistan', dial: '+92', format: '### #######' },
                AE: { name: 'United Arab Emirates', dial: '+971', format: '## ### ####' },
                SA: { name: 'Saudi Arabia', dial: '+966', format: '## ### ####' },
                DE: { name: 'Germany', dial: '+49', format: '### #######' },
                FR: { name: 'France', dial: '+33', format: '# ## ## ## ##' },
                IT: { name: 'Italy', dial: '+39', format: '### ### ####' },
                ES: { name: 'Spain', dial: '+34', format: '### ## ## ##' },
                NL: { name: 'Netherlands', dial: '+31', format: '# ########' },
                IE: { name: 'Ireland', dial: '+353', format: '## ### ####' },
                NZ: { name: 'New Zealand', dial: '+64', format: '## ### ####' },
                SG: { name: 'Singapore', dial: '+65', format: '#### ####' },
                PH: { name: 'Philippines', dial: '+63', format: '### ### ####' },
                NG: { name: 'Nigeria', dial: '+234', format: '### ### ####' },
                ZA: { name: 'South Africa', dial: '+27', format: '## ### ####' },
                BR: { name: 'Brazil', dial: '+55', format: '## #####-####' },
                MX: { name: 'Mexico', dial: '+52', format: '## #### ####' },
                CN: { name: 'China', dial: '+86', format: '### #### ####' },
                JP: { name: 'Japan', dial: '+81', format: '##-####-####' },
                KR: { name: 'South Korea', dial: '+82', format: '##-####-####' },
                OTHER: { name: 'Other', dial: '+', format: '##############' },
              };
              const country = countries[field.phoneCountry] ? field.phoneCountry : 'CA';
              const fmt = field.phoneFormat || countries[country].format;
              const allowSelect = field.phoneAllowCountrySelect !== false;
              return `
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="fe-phone-country">Default country</label>
          <select id="fe-phone-country">
            ${Object.keys(countries)
              .map(
                (code) =>
                  `<option value="${code}" ${code === country ? 'selected' : ''}>${countries[code].name} (${countries[code].dial})</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="admin-field">
          <label for="fe-phone-format">Number format</label>
          <input type="text" id="fe-phone-format" value="${escapeAttr(fmt)}" placeholder="(###) ###-####">
          <small class="admin-field-hint">Use <code>#</code> for each digit. Changes with country unless you customize.</small>
        </div>
        <div class="admin-field admin-field--full">
          <label class="admin-checkbox-label">
            <input type="checkbox" id="fe-phone-allow-cc" ${allowSelect ? 'checked' : ''}>
            <span>Allow visitors to change country code</span>
          </label>
        </div>
      </div>`;
            })()
          : ''
      }
      ${
        field.type === 'date'
          ? `<div class="admin-field admin-field--full">
              <label for="fe-min-age">Minimum age (years)</label>
              <input type="number" id="fe-min-age" min="0" max="120" step="1" value="${Number(field.minAge) > 0 ? Number(field.minAge) : ''}" placeholder="e.g. 18">
              <small class="admin-field-hint">For date of birth: clients cannot pick a date that makes them younger than this age. Leave blank for no limit.</small>
            </div>`
          : ''
      }
      ${field.type === 'partners' ? renderPartnerFieldEditor(field) : ''}
      ${
        ['select', 'radio', 'checkbox'].includes(field.type)
          ? `<div class="admin-field admin-field--full"><label>Options</label>${optionsHtml}<button type="button" class="admin-btn admin-btn-secondary" id="fe-add-opt">+ Option</button></div>`
          : ''
      }
      ${
        field.type === 'yes_no'
          ? (() => {
              if (!Array.isArray(field.followUps)) {
                field.followUps = [];
                if (field.reasonWhen === 'yes' || field.reasonWhen === 'no') {
                  field.followUps.push({
                    id: 'legacy_reason',
                    when: field.reasonWhen,
                    type: field.reasonType || 'textarea',
                    label: field.reasonLabel || 'Please explain your answer',
                    placeholder: field.reasonPlaceholder || '',
                    required: field.reasonRequired !== false,
                    legacy: true,
                  });
                }
              }
              const typeOpts = [
                ['textarea', 'Long text'],
                ['text', 'Short text'],
                ['email', 'Email'],
                ['tel', 'Phone'],
                ['number', 'Number'],
                ['date', 'Date'],
              ];
              const rows = (field.followUps || [])
                .map((fu, i) => {
                  const typeOptions = typeOpts
                    .map(
                      ([val, lab]) =>
                        `<option value="${val}" ${fu.type === val || (!fu.type && val === 'textarea') ? 'selected' : ''}>${lab}</option>`
                    )
                    .join('');
                  return `
        <div class="yes-no-followup-row" data-followup-index="${i}">
          <div class="admin-fields-2col">
            <div class="admin-field">
              <label>Show when answer is</label>
              <select data-fu-when="${i}">
                <option value="no" ${fu.when === 'no' ? 'selected' : ''}>No</option>
                <option value="yes" ${fu.when === 'yes' ? 'selected' : ''}>Yes</option>
              </select>
            </div>
            <div class="admin-field">
              <label>Field type</label>
              <select data-fu-type="${i}">${typeOptions}</select>
            </div>
            <div class="admin-field">
              <label>Label</label>
              <input type="text" data-fu-label="${i}" value="${escapeAttr(fu.label || '')}" placeholder="Please explain">
            </div>
            <div class="admin-field">
              <label>Placeholder</label>
              <input type="text" data-fu-placeholder="${i}" value="${escapeAttr(fu.placeholder || '')}">
            </div>
            <div class="admin-field admin-field--full" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">
              <label class="admin-checkbox-label">
                <input type="checkbox" data-fu-required="${i}" ${fu.required !== false ? 'checked' : ''}>
                <span>Required when shown</span>
              </label>
              <button type="button" class="admin-btn admin-btn-danger admin-btn-sm" data-fu-remove="${i}">Remove</button>
            </div>
          </div>
        </div>`;
                })
                .join('');
              return `
      <hr style="border:none;border-top:1px solid #dbe3f0;margin:1rem 0;">
      <h3 style="margin:0 0 0.75rem;font-size:0.95rem;">Follow-up fields</h3>
      <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.75rem;">Add one or more fields that appear when the user picks Yes or No.</p>
      <div id="fe-followups">${rows || '<p style="font-size:0.85rem;color:#94a3b8;margin:0 0 0.75rem;">No follow-up fields yet.</p>'}</div>
      <button type="button" class="admin-btn admin-btn-secondary" id="fe-add-followup">+ Add follow-up field</button>`;
            })()
          : ''
      }
      ${
        ['file', 'image'].includes(field.type)
          ? `<div class="admin-fields-2col">
             <div class="admin-field"><label>Accept (optional)</label><input type="text" id="fe-accept" value="${escapeAttr(field.accept || (field.type === 'image' ? 'image/*' : 'image/*,.pdf,.zip,application/pdf,application/zip'))}" placeholder="e.g. image/*,.pdf,.zip"><small class="admin-field-hint">Leave blank for images, PDF, ZIP, and Office docs.</small></div>
             <div class="admin-field"><label>Max files</label><input type="number" id="fe-max-files" min="1" max="10" value="${field.maxFiles || 1}"></div>
           </div>`
          : ''
      }
      ${
        field.type !== 'page_break'
          ? `<hr style="border:none;border-top:1px solid #dbe3f0;margin:1rem 0;">
      <h3 style="margin:0 0 0.75rem;font-size:0.95rem;">Conditional logic</h3>
      <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.75rem;">Show or hide this field based on answers to other fields.</p>
      ${conditionsHtml}`
          : ''
      }
      <div style="margin-top:1rem;display:flex;gap:0.5rem;">
        <button type="button" class="admin-btn admin-btn-danger" id="fe-delete">Delete field</button>
        <button type="button" class="admin-btn admin-btn-secondary" id="fe-duplicate">Duplicate</button>
      </div>
    `;

    bindFieldEditorEvents(field, otherFields);
  }

  function escapeAttr(s) {
    return String(s).replace(/"/g, '&quot;');
  }

  function renderConditionsEditor(field, otherFields) {
    if (!field.conditions) field.conditions = [];
    const block = field.conditions[0] || {
      action: 'show',
      logic: 'all',
      rules: [],
    };

    const rulesHtml =
      block.rules
        .map((rule, i) => {
          const fieldOpts = otherFields
            .map(
              (f) =>
                `<option value="${f.id}" ${rule.field === f.id ? 'selected' : ''}>${escapeHtml(f.label)}</option>`
            )
            .join('');
          return `
          <div class="condition-row" data-rule="${i}">
            <select data-rule-field>${fieldOpts || '<option value="">No fields</option>'}</select>
            <select data-rule-op>
              <option value="equals" ${rule.operator === 'equals' ? 'selected' : ''}>equals</option>
              <option value="not_equals" ${rule.operator === 'not_equals' ? 'selected' : ''}>not equals</option>
              <option value="contains" ${rule.operator === 'contains' ? 'selected' : ''}>contains</option>
              <option value="empty" ${rule.operator === 'empty' ? 'selected' : ''}>is empty</option>
              <option value="not_empty" ${rule.operator === 'not_empty' ? 'selected' : ''}>is not empty</option>
            </select>
            <input type="text" data-rule-value value="${escapeAttr(rule.value || '')}" placeholder="Value">
            <button type="button" class="admin-btn admin-btn-danger" data-remove-rule="${i}">×</button>
          </div>`;
        })
        .join('');

    return `
      <div class="admin-field">
        <label class="admin-checkbox-label"><input type="checkbox" id="fe-cond-enabled" ${field.conditions.length ? 'checked' : ''}><span>Enable conditions</span></label>
      </div>
      <div id="fe-cond-panel" style="${field.conditions.length ? '' : 'display:none'}">
        <div class="admin-field">
          <label>Action</label>
          <select id="fe-cond-action">
            <option value="show" ${block.action === 'show' ? 'selected' : ''}>Show field when…</option>
            <option value="hide" ${block.action === 'hide' ? 'selected' : ''}>Hide field when…</option>
          </select>
        </div>
        <div class="admin-field">
          <label>Match</label>
          <select id="fe-cond-logic">
            <option value="all" ${block.logic === 'all' ? 'selected' : ''}>All rules</option>
            <option value="any" ${block.logic === 'any' ? 'selected' : ''}>Any rule</option>
          </select>
        </div>
        <div id="fe-rules">${rulesHtml}</div>
        <button type="button" class="admin-btn admin-btn-secondary" id="fe-add-rule">+ Add rule</button>
      </div>
    `;
  }

  function renderPartnerFieldEditor(field) {
    const partners = config.activePartners || [];
    const selected = new Set((field.partnerIds || []).map(String));
    const input = field.partnerInput || 'select';
    const partnerChecks = partners.length
      ? partners
          .map(
            (p) =>
              `<label class="admin-checkbox-label" style="display:flex;margin:0.35rem 0;">
                <input type="checkbox" data-partner-id="${p.id}" ${selected.has(String(p.id)) ? 'checked' : ''}>
                <span>${escapeHtml(p.name)}${
                p.reference_code
                  ? ` <span class="fb-optional">(${escapeHtml(p.reference_code)})</span>`
                  : ''
              }</span>
              </label>`
          )
          .join('')
      : '<p class="admin-note">No active partners yet. Add partners under Team and assign each a reference code.</p>';

    return `
      <hr style="border:none;border-top:1px solid #dbe3f0;margin:1rem 0;">
      <h3 style="margin:0 0 0.75rem;font-size:0.95rem;">Partner options</h3>
      <div class="admin-field admin-field--full">
        <label for="fe-partner-input">How clients enter the reference</label>
        <select id="fe-partner-input">
          <option value="select" ${input === 'select' ? 'selected' : ''}>Dropdown — choose a partner</option>
          <option value="text" ${input === 'text' ? 'selected' : ''}>Text — type reference code</option>
          <option value="number" ${input === 'number' ? 'selected' : ''}>Number — type reference code</option>
        </select>
      </div>
      <div class="admin-field admin-field--full">
        <label>Partners to include</label>
        <div class="data-match-fields">${partnerChecks}</div>
        <small class="admin-field-hint">Select which partners appear in the dropdown, or whose codes are accepted for text/number entry.</small>
      </div>`;
  }

  function bindFieldEditorEvents(field) {
    el('fe-type')?.addEventListener('change', (e) => {
      const newType = e.target.value === 'page_break' ? 'text' : e.target.value;
      const idx = state.schema.fields.findIndex((f) => f.id === field.id);
      const fresh = defaultFieldFromType(newType);
      fresh.id = field.id;
      fresh.label = field.label;
      fresh.name = field.name;
      state.schema.fields[idx] = fresh;
      renderFieldList();
      renderFieldEditor();
    });

    el('fe-page')?.addEventListener('change', (e) => {
      const to = Number(e.target.value);
      if (!Number.isNaN(to)) moveFieldToPage(field.id, to);
    });

    const sync = () => {
      field.label = el('fe-label').value;
      field.name = el('fe-name').value.replace(/[^a-zA-Z0-9_]/g, '_');
      if (el('fe-required')) field.required = el('fe-required').checked;
      if (el('fe-placeholder')) field.placeholder = el('fe-placeholder').value;
      if (el('fe-help')) field.helpText = el('fe-help').value;
      if (el('fe-accept')) field.accept = el('fe-accept').value;
      if (el('fe-max-files')) field.maxFiles = Number(el('fe-max-files').value) || 1;
      if (el('fe-number-format')) {
        field.numberFormat = String(el('fe-number-format').value || '').trim();
      }
      if (el('fe-phone-country')) {
        field.phoneCountry = String(el('fe-phone-country').value || 'CA');
      }
      if (el('fe-phone-format')) {
        field.phoneFormat = String(el('fe-phone-format').value || '').trim();
      }
      if (el('fe-phone-allow-cc')) {
        field.phoneAllowCountrySelect = el('fe-phone-allow-cc').checked;
      }
      if (el('fe-min-age')) {
        const raw = String(el('fe-min-age').value || '').trim();
        const age = raw === '' ? 0 : parseInt(raw, 10);
        field.minAge = Number.isFinite(age) ? Math.max(0, Math.min(120, age)) : 0;
      }
      renderFieldList();
    };

    ['fe-label', 'fe-name', 'fe-required', 'fe-placeholder', 'fe-help', 'fe-accept', 'fe-max-files', 'fe-number-format', 'fe-phone-format', 'fe-min-age'].forEach((id) => {
      const node = el(id);
      if (node) node.addEventListener('input', sync);
      if (node && node.type === 'checkbox') node.addEventListener('change', sync);
    });

    el('fe-phone-allow-cc')?.addEventListener('change', sync);

    const PHONE_FORMATS = {
      CA: '(###) ###-####',
      US: '(###) ###-####',
      GB: '#### ######',
      AU: '### ### ###',
      IN: '##### #####',
      BD: '####-######',
      PK: '### #######',
      AE: '## ### ####',
      SA: '## ### ####',
      DE: '### #######',
      FR: '# ## ## ## ##',
      IT: '### ### ####',
      ES: '### ## ## ##',
      NL: '# ########',
      IE: '## ### ####',
      NZ: '## ### ####',
      SG: '#### ####',
      PH: '### ### ####',
      NG: '### ### ####',
      ZA: '## ### ####',
      BR: '## #####-####',
      MX: '## #### ####',
      CN: '### #### ####',
      JP: '##-####-####',
      KR: '##-####-####',
      OTHER: '##############',
    };

    el('fe-phone-country')?.addEventListener('change', (e) => {
      const code = e.target.value;
      const nextFmt = PHONE_FORMATS[code] || '(###) ###-####';
      if (el('fe-phone-format')) {
        el('fe-phone-format').value = nextFmt;
      }
      field.phoneCountry = code;
      field.phoneFormat = nextFmt;
      renderFieldList();
    });

    el('fe-number-format-preset')?.addEventListener('change', (e) => {
      const val = e.target.value;
      if (val === '__custom') {
        el('fe-number-format')?.focus();
        return;
      }
      if (el('fe-number-format')) {
        el('fe-number-format').value = val;
      }
      field.numberFormat = val;
      renderFieldList();
    });

    el('fe-number-format')?.addEventListener('input', () => {
      const fmt = String(el('fe-number-format').value || '').trim();
      const preset = el('fe-number-format-preset');
      if (!preset) return;
      const known = ['', '###-###-###', '#####'];
      preset.value = known.includes(fmt) ? fmt : '__custom';
    });

    const syncYesNoFollowUps = () => {
      if (field.type !== 'yes_no') return;
      if (!Array.isArray(field.followUps)) field.followUps = [];
      field.followUps.forEach((fu, i) => {
        const whenEl = fieldEditor.querySelector(`[data-fu-when="${i}"]`);
        const typeEl = fieldEditor.querySelector(`[data-fu-type="${i}"]`);
        const labelEl = fieldEditor.querySelector(`[data-fu-label="${i}"]`);
        const phEl = fieldEditor.querySelector(`[data-fu-placeholder="${i}"]`);
        const reqEl = fieldEditor.querySelector(`[data-fu-required="${i}"]`);
        if (whenEl) fu.when = whenEl.value === 'yes' ? 'yes' : 'no';
        if (typeEl) fu.type = typeEl.value || 'textarea';
        if (labelEl) fu.label = labelEl.value || 'Please explain your answer';
        if (phEl) fu.placeholder = phEl.value || '';
        if (reqEl) fu.required = reqEl.checked;
      });
      const first = field.followUps[0];
      if (first) {
        field.reasonWhen = first.when;
        field.reasonType = first.type;
        field.reasonLabel = first.label;
        field.reasonPlaceholder = first.placeholder;
        field.reasonRequired = first.required;
      } else {
        field.reasonWhen = '';
      }
    };

    fieldEditor.querySelectorAll('[data-fu-when], [data-fu-type], [data-fu-label], [data-fu-placeholder], [data-fu-required]').forEach((node) => {
      node.addEventListener('input', syncYesNoFollowUps);
      node.addEventListener('change', syncYesNoFollowUps);
    });

    el('fe-add-followup')?.addEventListener('click', () => {
      if (!Array.isArray(field.followUps)) field.followUps = [];
      field.followUps.push({
        id: 'fu_' + Math.random().toString(36).slice(2, 8),
        when: 'no',
        type: 'textarea',
        label: 'Please explain why',
        placeholder: '',
        required: true,
        legacy: false,
      });
      renderFieldEditor();
    });

    fieldEditor.querySelectorAll('[data-fu-remove]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const i = Number(btn.getAttribute('data-fu-remove'));
        if (!Array.isArray(field.followUps)) return;
        field.followUps.splice(i, 1);
        syncYesNoFollowUps();
        renderFieldEditor();
      });
    });

    el('fe-partner-input')?.addEventListener('change', (e) => {
      field.partnerInput = e.target.value;
      if (!field.partnerIds) field.partnerIds = [];
      renderFieldEditor();
    });
    fieldEditor.querySelectorAll('[data-partner-id]').forEach((cb) => {
      cb.addEventListener('change', () => {
        field.partnerIds = [...fieldEditor.querySelectorAll('[data-partner-id]:checked')].map((node) =>
          Number(node.getAttribute('data-partner-id'))
        );
        renderFieldList();
      });
    });

    fieldEditor.querySelectorAll('[data-opt-label]').forEach((input) => {
      input.addEventListener('input', () => {
        const i = Number(input.dataset.optLabel);
        field.options[i].label = input.value;
        field.options[i].value = slugify(input.value) || field.options[i].value;
        renderFieldList();
      });
    });
    fieldEditor.querySelectorAll('[data-opt-value]').forEach((input) => {
      input.addEventListener('input', () => {
        field.options[Number(input.dataset.optValue)].value = input.value;
      });
    });
    fieldEditor.querySelectorAll('[data-remove-opt]').forEach((btn) => {
      btn.addEventListener('click', () => {
        field.options.splice(Number(btn.dataset.removeOpt), 1);
        renderFieldEditor();
      });
    });
    el('fe-add-opt')?.addEventListener('click', () => {
      field.options = field.options || [];
      field.options.push({ label: 'New option', value: 'new_option_' + field.options.length });
      renderFieldEditor();
    });

    bindConditions(field);

    el('fe-delete')?.addEventListener('click', () => {
      if (!confirm('Delete this field?')) return;
      state.schema.fields = state.schema.fields.filter((f) => f.id !== field.id);
      selectedFieldId = state.schema.fields[0]?.id || null;
      renderFieldList();
      renderFieldEditor();
    });

    el('fe-duplicate')?.addEventListener('click', () => {
      const copy = structuredClone(field);
      copy.id = uid();
      copy.name = copy.name + '_copy';
      copy.label = copy.label + ' (copy)';
      state.schema.fields.push(copy);
      selectField(copy.id);
    });
  }

  function defaultFieldFromType(type) {
    const defaults = {
      text: { type: 'text', label: 'Short text', required: true, options: [], conditions: [] },
      textarea: { type: 'textarea', label: 'Long text', required: true, options: [], conditions: [] },
      email: { type: 'email', label: 'Email', required: true, options: [], conditions: [] },
      tel: {
        type: 'tel',
        label: 'Phone',
        required: true,
        phoneCountry: 'CA',
        phoneFormat: '(###) ###-####',
        phoneAllowCountrySelect: true,
        options: [],
        conditions: [],
      },
      number: { type: 'number', label: 'Number', required: false, numberFormat: '', options: [], conditions: [] },
      date: { type: 'date', label: 'Date', required: false, minAge: 0, options: [], conditions: [] },
      select: {
        type: 'select',
        label: 'Dropdown',
        required: true,
        options: [
          { label: 'Option 1', value: 'option_1' },
          { label: 'Option 2', value: 'option_2' },
        ],
        conditions: [],
      },
      radio: {
        type: 'radio',
        label: 'Single choice',
        required: true,
        options: [
          { label: 'Option 1', value: 'option_1' },
          { label: 'Option 2', value: 'option_2' },
        ],
        conditions: [],
      },
      checkbox: {
        type: 'checkbox',
        label: 'Multiple choice',
        required: false,
        options: [
          { label: 'Option 1', value: 'option_1' },
          { label: 'Option 2', value: 'option_2' },
        ],
        conditions: [],
      },
      partners: {
        type: 'partners',
        label: 'Partner reference',
        required: false,
        partnerInput: 'select',
        partnerIds: [],
        options: [],
        conditions: [],
      },
      yes_no: {
        type: 'yes_no',
        label: 'Yes / No',
        required: true,
        options: [
          { label: 'Yes', value: 'yes' },
          { label: 'No', value: 'no' },
        ],
        followUps: [
          {
            id: 'fu_reason',
            when: 'no',
            type: 'textarea',
            label: 'Please explain why',
            placeholder: '',
            required: true,
            legacy: false,
          },
        ],
        reasonWhen: 'no',
        reasonLabel: 'Please explain why',
        reasonPlaceholder: '',
        reasonRequired: true,
        reasonType: 'textarea',
        conditions: [],
      },
      file: { type: 'file', label: 'File upload', required: false, accept: 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,application/pdf,application/zip,application/x-zip-compressed', maxFiles: 5, options: [], conditions: [] },
      image: { type: 'image', label: 'Image upload', required: false, accept: 'image/*', maxFiles: 5, options: [], conditions: [] },
      heading: { type: 'heading', label: 'Section title', required: false, options: [], conditions: [] },
      paragraph: { type: 'paragraph', label: 'Instructions…', required: false, options: [], conditions: [] },
      page_break: { type: 'page_break', label: 'Next page', required: false, options: [], conditions: [] },
    };
    const base = defaults[type] || defaults.text;
    return { id: uid(), name: uid(), placeholder: '', helpText: '', ...base };
  }

  function bindConditions(field) {
    const enabled = el('fe-cond-enabled');
    const panel = el('fe-cond-panel');
    if (!enabled) return;

    const readRules = () => {
      const rules = [];
      el('fe-rules')?.querySelectorAll('[data-rule]').forEach((row) => {
        rules.push({
          field: row.querySelector('[data-rule-field]')?.value || '',
          operator: row.querySelector('[data-rule-op]')?.value || 'equals',
          value: row.querySelector('[data-rule-value]')?.value || '',
        });
      });
      return rules;
    };

    const saveConditions = () => {
      if (!enabled.checked) {
        field.conditions = [];
        return;
      }
      field.conditions = [
        {
          action: el('fe-cond-action')?.value || 'show',
          logic: el('fe-cond-logic')?.value || 'all',
          rules: readRules(),
        },
      ];
    };

    enabled.addEventListener('change', () => {
      panel.style.display = enabled.checked ? '' : 'none';
      if (enabled.checked && !field.conditions.length) {
        field.conditions = [{ action: 'show', logic: 'all', rules: [] }];
        renderFieldEditor();
      } else if (!enabled.checked) {
        field.conditions = [];
      }
    });

    ['fe-cond-action', 'fe-cond-logic'].forEach((id) => {
      el(id)?.addEventListener('change', saveConditions);
    });

    el('fe-add-rule')?.addEventListener('click', () => {
      if (!field.conditions.length) field.conditions = [{ action: 'show', logic: 'all', rules: [] }];
      field.conditions[0].rules.push({ field: '', operator: 'equals', value: '' });
      renderFieldEditor();
    });

    fieldEditor.querySelectorAll('[data-remove-rule]').forEach((btn) => {
      btn.addEventListener('click', () => {
        field.conditions[0].rules.splice(Number(btn.dataset.removeRule), 1);
        renderFieldEditor();
      });
    });

    fieldEditor.querySelectorAll('[data-rule-field], [data-rule-op], [data-rule-value]').forEach((node) => {
      node.addEventListener('change', saveConditions);
      node.addEventListener('input', saveConditions);
    });
  }

  el('add-field-btn')?.addEventListener('click', () => {
    let type = el('add-field-type').value;
    if (type === 'page_break') type = 'text';
    const field = defaultFieldFromType(type);
    const pages = getPages();
    if (!pages[activePageIndex]) {
      pages.push({ title: 'Page 1', fields: [], breakField: null });
      activePageIndex = 0;
    }
    pages[activePageIndex].fields.push(field);
    setPages(pages);
    selectField(field.id);
  });

  el('fb-page-add')?.addEventListener('click', addPage);
  el('fb-page-delete')?.addEventListener('click', deleteActivePage);
  el('fb-page-title')?.addEventListener('input', (e) => {
    updateActivePageTitle(e.target.value);
  });
  el('fb-page-title')?.addEventListener('blur', (e) => {
    updateActivePageTitle(e.target.value, { finalize: true });
  });

  fieldList.addEventListener('dragover', (e) => {
    if (dragFromIndex === null) return;
    e.preventDefault();
    if (e.target === fieldList) {
      clearDropIndicators();
      const last = fieldList.lastElementChild;
      if (last) last.classList.add('drop-after');
    }
  });
  fieldList.addEventListener('drop', (e) => {
    if (e.target !== fieldList) return;
    if (dragFromIndex === null) return;
    e.preventDefault();
    const pages = getPages();
    const page = pages[activePageIndex];
    if (!page || !page.fields.length) return;
    const toIndex = Math.max(0, page.fields.length - 1);
    clearDropIndicators();
    reorderFieldsOnActivePage(dragFromIndex, toIndex, true);
  });

  el('form-title')?.addEventListener('input', (e) => {
    state.title = e.target.value;
    if (!state.id && !el('form-slug').dataset.touched) {
      el('form-slug').value = slugify(state.title);
      el('slug-preview').textContent = el('form-slug').value;
    }
  });

  el('form-slug')?.addEventListener('input', (e) => {
    e.target.dataset.touched = '1';
    state.slug = slugify(e.target.value);
    e.target.value = state.slug;
    el('slug-preview').textContent = state.slug || 'your-slug';
  });

  function syncTaxYearSettings() {
    ensureSchemaSettings();
    const enabledEl = el('tax-year-enabled');
    const enabled = enabledEl ? enabledEl.checked : false;
    const yearsRaw = (el('tax-year-years')?.value || '').trim();
    const years = yearsRaw
      .split(/[\s,;]+/)
      .map((y) => parseInt(y, 10))
      .filter((y) => !Number.isNaN(y) && y >= 1990 && y <= 2100);

    state.schema.settings.taxYear = {
      enabled: !!enabled,
      label: (el('tax-year-label')?.value || '').trim() || 'Which tax year are you filing for?',
      prompt:
        (el('tax-year-prompt')?.value || '').trim() ||
        'Select a year to continue to the form for that tax period.',
      years,
    };
    return state.schema.settings.taxYear;
  }

  function hydrateTaxYearFromDom() {
    syncTaxYearSettings();
    toggleTaxYearSettingsUi();
  }

  function toggleTaxYearSettingsUi() {
    const on = el('tax-year-enabled')?.checked ?? false;
    document.querySelectorAll('.tax-year-settings').forEach((node) => {
      node.style.display = on ? '' : 'none';
    });
    const section = el('fb-section-tax-year');
    if (section && on) {
      section.open = true;
    }
  }

  function toggleCtaLabelWrap() {
    const wrap = el('form-cta-label-wrap');
    const on = el('form-site-cta')?.checked ?? false;
    if (wrap) {
      wrap.style.display = on ? '' : 'none';
    }
  }

  el('form-site-cta')?.addEventListener('change', toggleCtaLabelWrap);
  toggleCtaLabelWrap();

  el('tax-year-enabled')?.addEventListener('change', () => {
    syncTaxYearSettings();
    toggleTaxYearSettingsUi();
  });
  el('tax-year-label')?.addEventListener('input', syncTaxYearSettings);
  el('tax-year-prompt')?.addEventListener('input', syncTaxYearSettings);
  el('tax-year-years')?.addEventListener('input', syncTaxYearSettings);
  hydrateTaxYearFromDom();

  el('data-match-enabled')?.addEventListener('change', () => {
    syncDataMatchSettings();
    toggleDataMatchSettingsUi();
  });
  ['data-match-title', 'data-match-message', 'data-match-confirm', 'data-match-decline'].forEach(
    (id) => el(id)?.addEventListener('input', syncDataMatchSettings)
  );
  toggleDataMatchSettingsUi();
  renderDataMatchFields();

  el('save-form-btn')?.addEventListener('click', async () => {
    ensureSchemaSettings();
    state.title = el('form-title').value.trim() || 'Untitled form';
    state.slug = slugify(el('form-slug').value || state.title);
    state.description = el('form-description').value.trim();
    state.status = el('form-status').value;
    state.schema.settings.submitLabel = el('submit-label').value.trim() || 'Submit';
    state.schema.settings.successMessage =
      el('success-message').value.trim() || 'Thank you! Your response has been received.';
    const taxYear = syncTaxYearSettings();
    syncDataMatchSettings();

    const getSelected = getSelectedField();
    if (getSelected) {
      /* sync conditions from open editor */
    }

    try {
      const res = await fetch('/api/forms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf_token: config.csrfToken,
          id: state.id,
          slug: state.slug,
          title: state.title,
          description: state.description,
          status: state.status,
          schema: state.schema,
          is_site_cta: !!el('form-site-cta')?.checked,
          cta_label: (el('form-cta-label')?.value || '').trim(),
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Save failed');
      state.id = data.id;
      state.slug = data.slug;
      if (!window.location.search.includes('id=')) {
        history.replaceState({}, '', '?id=' + data.id);
      }
      let msg = 'Form saved successfully.';
      if (taxYear.enabled) {
        const yrCount = taxYear.years.length;
        msg += yrCount
          ? ' Tax years: ' + taxYear.years.join(', ') + '.'
          : ' Tax year step on (default year range will be used on the public form).';
      }
      if (el('form-site-cta')?.checked && state.status === 'published') {
        msg += ' This form is linked on the homepage and header.';
      } else if (el('form-site-cta')?.checked && state.status !== 'published') {
        msg += ' Publish the form to show it on the homepage and header.';
      }
      showStatus(msg, true);
    } catch (err) {
      showStatus(err.message, false);
    }
  });

  if (selectedFieldId) {
    activePageIndex = findPageIndexForField(selectedFieldId);
  }
  renderPageTabs();
  renderFieldList();
  renderFieldEditor();
})();
