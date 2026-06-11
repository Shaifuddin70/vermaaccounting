(function () {
  const config = window.FORM_BUILDER_CONFIG;
  let state = structuredClone(config.initial);

  const el = (id) => document.getElementById(id);
  const fieldList = el('field-list');
  const fieldEditor = el('field-editor');
  const noFieldSelected = el('no-field-selected');
  let selectedFieldId = state.schema.fields[0]?.id || null;

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
    const box = el('save-status');
    box.style.display = 'block';
    box.className = 'admin-alert ' + (ok ? 'admin-alert-success' : 'admin-alert-error');
    box.textContent = msg;
  }

  let dragFromIndex = null;

  function clearDropIndicators() {
    fieldList.querySelectorAll('.builder-field-item').forEach((node) => {
      node.classList.remove('drop-before', 'drop-after', 'is-dragging');
    });
  }

  function reorderFields(fromIndex, toIndex, insertAfter) {
    if (fromIndex === null) return;
    const fields = state.schema.fields;
    const [moved] = fields.splice(fromIndex, 1);
    let insertAt = toIndex;
    if (insertAfter) insertAt++;
    if (fromIndex < insertAt) insertAt--;
    insertAt = Math.max(0, Math.min(insertAt, fields.length));
    fields.splice(insertAt, 0, moved);
    renderFieldList();
    renderFieldEditor();
  }

  function renderFieldList() {
    fieldList.innerHTML = '';
    state.schema.fields.forEach((field, index) => {
      const item = document.createElement('div');
      item.className =
        'builder-field-item' + (field.id === selectedFieldId ? ' is-selected' : '');
      item.dataset.id = field.id;
      item.dataset.index = String(index);

      item.innerHTML =
        '<span class="builder-drag-handle" title="Drag to reorder" aria-hidden="true">⠿</span>' +
        '<div class="builder-field-body">' +
        '<div class="field-type">' +
        (config.fieldTypes[field.type] || field.type) +
        '</div>' +
        '<strong>' +
        escapeHtml(field.label) +
        '</strong>' +
        (field.required ? ' <span style="color:#dc2626">*</span>' : '') +
        '</div>';

      const handle = item.querySelector('.builder-drag-handle');
      const body = item.querySelector('.builder-field-body');

      handle.draggable = true;
      handle.addEventListener('dragstart', (e) => {
        dragFromIndex = index;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(index));
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
        const from = dragFromIndex ?? Number(e.dataTransfer.getData('text/plain'));
        const insertAfter = item.classList.contains('drop-after');
        clearDropIndicators();
        reorderFields(from, index, insertAfter);
      });

      body.addEventListener('click', () => selectField(field.id));

      fieldList.appendChild(item);
    });

    fieldList.addEventListener(
      'dragover',
      (e) => {
        if (dragFromIndex === null) return;
        e.preventDefault();
        if (e.target === fieldList && state.schema.fields.length) {
          clearDropIndicators();
          const last = fieldList.lastElementChild;
          if (last) last.classList.add('drop-after');
        }
      },
      { once: false }
    );
    renderDataMatchFields();
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
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function selectField(id) {
    selectedFieldId = id;
    renderFieldList();
    renderFieldEditor();
  }

  function getSelectedField() {
    return state.schema.fields.find((f) => f.id === selectedFieldId);
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
      (f) => f.id !== field.id && !['heading', 'paragraph'].includes(f.type)
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
            : '';

    fieldEditor.innerHTML = `
      <div class="admin-fields-2col">
        <div class="admin-field admin-field--full">
          <label>Field type</label>
          <select id="fe-type">
            ${Object.entries(config.fieldTypes)
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
          !['heading', 'paragraph'].includes(field.type)
            ? `<div class="admin-field admin-field--full"><label><input type="checkbox" id="fe-required" ${field.required ? 'checked' : ''}> Required</label></div>`
            : ''
        }
      </div>
      ${placeholderRow}
      ${
        ['select', 'radio', 'checkbox'].includes(field.type)
          ? `<div class="admin-field admin-field--full"><label>Options</label>${optionsHtml}<button type="button" class="admin-btn admin-btn-secondary" id="fe-add-opt">+ Option</button></div>`
          : ''
      }
      ${
        field.type === 'yes_no'
          ? `
      <hr style="border:none;border-top:1px solid #dbe3f0;margin:1rem 0;">
      <h3 style="margin:0 0 0.75rem;font-size:0.95rem;">Follow-up reason</h3>
      <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.75rem;">Show a text field when the user picks Yes or No (e.g. ask why they answered No).</p>
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label>Ask for reason when answer is</label>
          <select id="fe-reason-when">
            <option value="" ${!(field.reasonWhen) ? 'selected' : ''}>Never</option>
            <option value="no" ${field.reasonWhen === 'no' ? 'selected' : ''}>No</option>
            <option value="yes" ${field.reasonWhen === 'yes' ? 'selected' : ''}>Yes</option>
          </select>
        </div>
        <div class="admin-field" style="display:flex;align-items:flex-end;padding-bottom:0.15rem;">
          <label><input type="checkbox" id="fe-reason-required" ${field.reasonRequired !== false ? 'checked' : ''}> Reason required when shown</label>
        </div>
        <div class="admin-field">
          <label>Reason field label</label>
          <input type="text" id="fe-reason-label" value="${escapeAttr(field.reasonLabel || 'Please explain your answer')}">
        </div>
        <div class="admin-field">
          <label>Reason placeholder</label>
          <input type="text" id="fe-reason-placeholder" value="${escapeAttr(field.reasonPlaceholder || '')}">
        </div>
      </div>`
          : ''
      }
      ${
        ['file', 'image'].includes(field.type)
          ? `<div class="admin-fields-2col">
             <div class="admin-field"><label>Accept (optional)</label><input type="text" id="fe-accept" value="${escapeAttr(field.accept || (field.type === 'image' ? 'image/*' : ''))}"></div>
             <div class="admin-field"><label>Max files</label><input type="number" id="fe-max-files" min="1" max="10" value="${field.maxFiles || 1}"></div>
           </div>`
          : ''
      }
      <hr style="border:none;border-top:1px solid #dbe3f0;margin:1rem 0;">
      <h3 style="margin:0 0 0.75rem;font-size:0.95rem;">Conditional logic</h3>
      <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.75rem;">Show or hide this field based on answers to other fields.</p>
      ${conditionsHtml}
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
        <label><input type="checkbox" id="fe-cond-enabled" ${field.conditions.length ? 'checked' : ''}> Enable conditions</label>
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

  function bindFieldEditorEvents(field) {
    el('fe-type')?.addEventListener('change', (e) => {
      const newType = e.target.value;
      const idx = state.schema.fields.findIndex((f) => f.id === field.id);
      const fresh = defaultFieldFromType(newType);
      fresh.id = field.id;
      fresh.label = field.label;
      fresh.name = field.name;
      state.schema.fields[idx] = fresh;
      renderFieldList();
      renderFieldEditor();
    });

    const sync = () => {
      field.label = el('fe-label').value;
      field.name = el('fe-name').value.replace(/[^a-zA-Z0-9_]/g, '_');
      if (el('fe-required')) field.required = el('fe-required').checked;
      if (el('fe-placeholder')) field.placeholder = el('fe-placeholder').value;
      if (el('fe-help')) field.helpText = el('fe-help').value;
      if (el('fe-accept')) field.accept = el('fe-accept').value;
      if (el('fe-max-files')) field.maxFiles = Number(el('fe-max-files').value) || 1;
      renderFieldList();
    };

    ['fe-label', 'fe-name', 'fe-required', 'fe-placeholder', 'fe-help', 'fe-accept', 'fe-max-files'].forEach((id) => {
      const node = el(id);
      if (node) node.addEventListener('input', sync);
      if (node && node.type === 'checkbox') node.addEventListener('change', sync);
    });

    const syncYesNoReason = () => {
      if (field.type !== 'yes_no') return;
      field.reasonWhen = el('fe-reason-when')?.value || '';
      field.reasonLabel = el('fe-reason-label')?.value || 'Please explain your answer';
      field.reasonPlaceholder = el('fe-reason-placeholder')?.value || '';
      field.reasonRequired = el('fe-reason-required')?.checked ?? true;
    };
    ['fe-reason-when', 'fe-reason-label', 'fe-reason-placeholder', 'fe-reason-required'].forEach((id) => {
      const node = el(id);
      if (node) {
        node.addEventListener('input', syncYesNoReason);
        node.addEventListener('change', syncYesNoReason);
      }
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
      tel: { type: 'tel', label: 'Phone', required: true, options: [], conditions: [] },
      number: { type: 'number', label: 'Number', required: false, options: [], conditions: [] },
      date: { type: 'date', label: 'Date', required: false, options: [], conditions: [] },
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
      yes_no: {
        type: 'yes_no',
        label: 'Yes / No',
        required: true,
        options: [
          { label: 'Yes', value: 'yes' },
          { label: 'No', value: 'no' },
        ],
        reasonWhen: 'no',
        reasonLabel: 'Please explain why',
        reasonPlaceholder: '',
        reasonRequired: true,
        conditions: [],
      },
      file: { type: 'file', label: 'File upload', required: false, accept: '', maxFiles: 1, options: [], conditions: [] },
      image: { type: 'image', label: 'Image upload', required: false, accept: 'image/*', maxFiles: 1, options: [], conditions: [] },
      heading: { type: 'heading', label: 'Section title', required: false, options: [], conditions: [] },
      paragraph: { type: 'paragraph', label: 'Instructions…', required: false, options: [], conditions: [] },
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
    const type = el('add-field-type').value;
    const field = defaultFieldFromType(type);
    state.schema.fields.push(field);
    selectField(field.id);
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
  }

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

  renderFieldList();
  renderFieldEditor();
})();
