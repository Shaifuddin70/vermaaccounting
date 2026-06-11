(function () {
  const form = document.getElementById('custom-form');
  if (!form) return;

  const statusEl = document.getElementById('custom-form-status');
  const successPanel = document.getElementById('custom-form-success');
  const successMessageEl = document.getElementById('custom-form-success-message');
  const successResetBtn = document.getElementById('custom-form-success-reset');
  const fields = form.querySelectorAll('[data-field-id]');
  const taxYearStep = document.getElementById('tax-year-step');
  const taxYearInput = document.getElementById('tax-year-input');
  const taxYearBar = document.getElementById('tax-year-selected-bar');
  const taxYearLabel = document.getElementById('tax-year-selected-label');
  const taxYearChangeBtn = document.getElementById('tax-year-change-btn');
  const taxYearSelect = document.getElementById('tax-year-select');
  const taxYearContinueBtn = document.getElementById('tax-year-continue-btn');
  const taxYearRequired = document.getElementById('custom-form-app')?.dataset.taxYear === '1';
  let prefillDismissed = false;
  let lastLookupKey = '';

  function selectTaxYear(year) {
    if (taxYearInput) taxYearInput.value = String(year);
    if (taxYearLabel) taxYearLabel.textContent = String(year);
    if (taxYearBar) taxYearBar.hidden = false;
    if (taxYearStep) taxYearStep.hidden = true;
    form.hidden = false;
    const first = form.querySelector('input:not([type="hidden"]), select, textarea');
    if (first) first.focus();
  }

  function hideSuccessConfirmation() {
    if (successPanel) successPanel.hidden = true;
  }

  function showSuccessConfirmation(message) {
    const prefillModal = document.getElementById('prefill-modal');
    if (prefillModal) prefillModal.hidden = true;
    document.body.style.overflow = '';
    if (taxYearStep) taxYearStep.hidden = true;
    if (taxYearBar) taxYearBar.hidden = true;
    form.hidden = true;
    if (successMessageEl) {
      successMessageEl.textContent = message || 'Thank you! Your response has been received.';
    }
    if (successPanel) {
      successPanel.hidden = false;
      successPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (statusEl) {
      statusEl.textContent = '';
      statusEl.className = 'custom-form-status';
    }
  }

  function resetTaxYearStep() {
    hideSuccessConfirmation();
    prefillDismissed = false;
    lastLookupKey = '';
    const previousYear = taxYearInput?.value || '';
    if (taxYearInput) taxYearInput.value = '';
    if (taxYearSelect) taxYearSelect.value = previousYear;
    if (taxYearBar) taxYearBar.hidden = true;
    if (taxYearStep) taxYearStep.hidden = false;
    form.hidden = true;
    form.reset();
    if (statusEl) {
      statusEl.textContent = '';
      statusEl.className = 'custom-form-status';
    }
    applyConditions();
    taxYearStep?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function resetFormForAnotherResponse() {
    hideSuccessConfirmation();
    prefillDismissed = false;
    lastLookupKey = '';
    if (taxYearRequired) {
      resetTaxYearStep();
      return;
    }
    form.hidden = false;
    form.reset();
    applyConditions();
    if (statusEl) {
      statusEl.textContent = '';
      statusEl.className = 'custom-form-status';
    }
    const first = form.querySelector('input:not([type="hidden"]), select, textarea');
    if (first) first.focus();
    form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  successResetBtn?.addEventListener('click', resetFormForAnotherResponse);

  function continueFromTaxYearSelect() {
    if (!taxYearSelect) return;
    const year = parseInt(taxYearSelect.value, 10);
    if (!year) {
      taxYearSelect.focus();
      taxYearSelect.classList.add('is-invalid');
      return;
    }
    taxYearSelect.classList.remove('is-invalid');
    selectTaxYear(year);
  }

  taxYearContinueBtn?.addEventListener('click', continueFromTaxYearSelect);
  taxYearSelect?.addEventListener('change', () => {
    taxYearSelect.classList.remove('is-invalid');
  });
  taxYearSelect?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      continueFromTaxYearSelect();
    }
  });

  taxYearChangeBtn?.addEventListener('click', resetTaxYearStep);

  function getFieldValue(fieldId) {
    const wrap = form.querySelector('[data-field-id="' + fieldId + '"]');
    if (!wrap) return '';

    const radio = wrap.querySelector('input[type="radio"]:checked');
    if (radio) return radio.value;

    const checks = wrap.querySelectorAll('input[type="checkbox"]:checked');
    if (checks.length) return Array.from(checks).map((c) => c.value);

    const select = wrap.querySelector('select');
    if (select) return select.value;

    const input = wrap.querySelector('input, textarea');
    return input ? input.value : '';
  }

  function ruleMatches(rule) {
    const val = getFieldValue(rule.field);
    const target = rule.value ?? '';

    switch (rule.operator) {
      case 'equals':
        return String(val) === String(target);
      case 'not_equals':
        return String(val) !== String(target);
      case 'contains':
        return String(val).toLowerCase().includes(String(target).toLowerCase());
      case 'empty':
        return val === '' || (Array.isArray(val) && val.length === 0);
      case 'not_empty':
        return val !== '' && !(Array.isArray(val) && val.length === 0);
      default:
        return false;
    }
  }

  function evaluateConditions(conditions) {
    if (!conditions || !conditions.length) return true;
    const block = conditions[0];
    if (!block.rules || !block.rules.length) return true;

    const results = block.rules.map(ruleMatches);
    const match = block.logic === 'any' ? results.some(Boolean) : results.every(Boolean);
    return block.action === 'hide' ? !match : match;
  }

  function applyConditions() {
    fields.forEach((wrap) => {
      const raw = wrap.getAttribute('data-conditions');
      if (!raw) {
        wrap.style.display = '';
        return;
      }
      try {
        const conditions = JSON.parse(raw);
        const visible = evaluateConditions(conditions);
        wrap.style.display = visible ? '' : 'none';
        wrap.querySelectorAll('input, select, textarea').forEach((el) => {
          if (el.closest('.yes-no-reason-wrap')) return;
          el.disabled = !visible;
          if (!visible) {
            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
            else if (el.type !== 'file') el.value = '';
          }
        });
      } catch (e) {
        wrap.style.display = '';
      }
    });
    applyYesNoReasons();
  }

  function applyYesNoReasons() {
    form.querySelectorAll('[data-yes-no-reason-for]').forEach((reasonWrap) => {
      const fieldId = reasonWrap.getAttribute('data-yes-no-reason-for');
      const when = reasonWrap.getAttribute('data-reason-when');
      const required = reasonWrap.getAttribute('data-reason-required') === '1';
      const yesNoBlock = form.querySelector('[data-yes-no-field="' + fieldId + '"]');
      const textarea = reasonWrap.querySelector('textarea');
      if (!yesNoBlock || !textarea) return;

      const selected = yesNoBlock.querySelector('input[type="radio"]:checked');
      const show = selected && selected.value === when;

      reasonWrap.hidden = !show;
      textarea.disabled = !show;
      if (!show) {
        textarea.value = '';
        textarea.removeAttribute('required');
      } else if (required) {
        textarea.setAttribute('required', 'required');
      } else {
        textarea.removeAttribute('required');
      }
    });
  }

  form.addEventListener('change', applyConditions);
  form.addEventListener('input', applyConditions);
  form.querySelectorAll('.yes-no-toggle-btn').forEach((label) => {
    label.addEventListener('click', () => {
      setTimeout(applyConditions, 0);
    });
  });
  applyConditions();

  /* ── Autofill from previous submission ─────────────────────────────────── */
  const schema = window.CUSTOM_FORM_SCHEMA || {};
  const dataMatchCfg = schema.settings?.dataMatch || {};
  const dataMatchOn =
    document.getElementById('custom-form-app')?.dataset.dataMatch === '1' &&
    dataMatchCfg.enabled &&
    (dataMatchCfg.fieldIds?.length || 0) >= 2;

  const prefillModal = document.getElementById('prefill-modal');
  const prefillConfirm = document.getElementById('prefill-confirm-btn');
  const prefillDecline = document.getElementById('prefill-decline-btn');
  const prefillMeta = document.getElementById('prefill-modal-meta');
  let pendingPrefillData = null;
  let lookupTimer = null;
  let lookupGeneration = 0;

  function getMatchFieldElements() {
    return form.querySelectorAll('[data-match-key="1"]');
  }

  function readMatchValues() {
    const values = {};
    getMatchFieldElements().forEach((wrap) => {
      const name = wrap.getAttribute('data-field-name');
      if (!name) return;
      values[name] = String(getFieldValue(wrap.getAttribute('data-field-id')) || '').trim();
    });
    return values;
  }

  function matchValuesComplete(values) {
    const keys = Object.keys(values);
    return keys.length >= 2 && keys.every((k) => values[k] !== '');
  }

  function setFieldValueFromPrefill(wrap, value) {
    const fieldId = wrap.getAttribute('data-field-id');
    if (value === undefined || value === null) return;

    const radios = wrap.querySelectorAll('input[type="radio"]');
    if (radios.length) {
      const str = String(value);
      radios.forEach((r) => {
        r.checked = r.value === str;
      });
      return;
    }

    const checks = wrap.querySelectorAll('input[type="checkbox"]');
    if (checks.length) {
      const arr = Array.isArray(value) ? value.map(String) : [String(value)];
      checks.forEach((c) => {
        c.checked = arr.includes(c.value);
      });
      return;
    }

    const select = wrap.querySelector('select');
    if (select) {
      select.value = String(value);
      return;
    }

    const fileInput = wrap.querySelector('input[type="file"]');
    if (fileInput) return;

    const input = wrap.querySelector('input, textarea');
    if (input) {
      input.value = Array.isArray(value) ? value.join(', ') : String(value);
    }

    const reasonWrap = form.querySelector('[data-yes-no-reason-for="' + fieldId + '"]');
    if (reasonWrap) {
      const reasonName = wrap.getAttribute('data-field-name') + '_reason';
      if (Object.prototype.hasOwnProperty.call(value, reasonName)) {
        /* value is object - skip */
      }
    }
  }

  function applyPrefillData(data) {
    fields.forEach((wrap) => {
      if (wrap.hasAttribute('data-match-key')) return;
      const name = wrap.getAttribute('data-field-name');
      if (!name || !(name in data)) return;
      setFieldValueFromPrefill(wrap, data[name]);
      const reasonKey = name + '_reason';
      if (reasonKey in data) {
        const reasonWrap = form.querySelector('[data-yes-no-reason-for="' + wrap.getAttribute('data-field-id') + '"]');
        const ta = reasonWrap?.querySelector('textarea');
        if (ta) ta.value = String(data[reasonKey]);
      }
    });
    applyConditions();
  }

  function showPrefillModal(data, submittedAt) {
    if (prefillDismissed) return;
    pendingPrefillData = data;
    if (prefillMeta && submittedAt) {
      prefillMeta.textContent = 'Last submitted: ' + submittedAt;
    } else if (prefillMeta) {
      prefillMeta.textContent = '';
    }
    if (prefillModal && !prefillModal.hidden) {
      return;
    }
    if (prefillModal) {
      prefillModal.hidden = false;
      document.body.style.overflow = 'hidden';
    }
  }

  function hidePrefillModal() {
    if (prefillModal) prefillModal.hidden = true;
    document.body.style.overflow = '';
    pendingPrefillData = null;
  }

  async function runLookup(generation) {
    if (!dataMatchOn || prefillDismissed || form.hidden) return;
    if (generation !== lookupGeneration) return;

    const values = readMatchValues();
    if (!matchValuesComplete(values)) return;

    const lookupKey = JSON.stringify(values) + (taxYearInput?.value || '');
    if (lookupKey === lastLookupKey) return;
    lastLookupKey = lookupKey;

    const slugInput = form.querySelector('input[name="form_slug"]');
    const payload = {
      form_slug: slugInput ? slugInput.value : '',
      match: values,
    };
    if (taxYearInput?.value) {
      payload.tax_year = parseInt(taxYearInput.value, 10);
    }

    try {
      const res = await fetch('/api/lookup-submission', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (generation !== lookupGeneration) return;
      const json = await res.json();
      if (generation !== lookupGeneration) return;
      if (json.found && json.data) {
        showPrefillModal(json.data, json.submitted_at || '');
      }
    } catch (err) {
      /* silent */
    }
  }

  function scheduleLookup() {
    if (!dataMatchOn) return;
    clearTimeout(lookupTimer);
    const generation = ++lookupGeneration;
    lookupTimer = setTimeout(() => runLookup(generation), 700);
  }

  if (dataMatchOn) {
    getMatchFieldElements().forEach((wrap) => {
      wrap.querySelectorAll('input, select, textarea').forEach((el) => {
        el.addEventListener('input', scheduleLookup);
        el.addEventListener('change', scheduleLookup);
      });
    });

    prefillConfirm?.addEventListener('click', () => {
      if (pendingPrefillData) {
        applyPrefillData(pendingPrefillData);
      }
      hidePrefillModal();
      lastLookupKey = '';
    });

    prefillDecline?.addEventListener('click', () => {
      prefillDismissed = true;
      hidePrefillModal();
    });

    prefillModal?.querySelectorAll('[data-prefill-close]').forEach((node) => {
      node.addEventListener('click', () => {
        prefillDismissed = true;
        hidePrefillModal();
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && prefillModal && !prefillModal.hidden) {
        prefillDismissed = true;
        hidePrefillModal();
      }
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    applyConditions();

    if (taxYearRequired && taxYearInput && !taxYearInput.value) {
      statusEl.textContent = 'Please select a tax year before submitting.';
      statusEl.className = 'custom-form-status is-error';
      if (taxYearStep) {
        form.hidden = true;
        taxYearStep.hidden = false;
        taxYearStep.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }

    statusEl.textContent = 'Submitting…';
    statusEl.className = 'custom-form-status';

    const data = new FormData(form);
    try {
      const res = await fetch('/api/submit', {
        method: 'POST',
        body: data,
      });
      const json = await res.json();
      if (!res.ok) {
        throw new Error(json.error || 'Submission failed');
      }
      showSuccessConfirmation(
        json.message || (window.CUSTOM_FORM_SCHEMA?.settings?.successMessage) || 'Thank you! Your response has been received.'
      );
    } catch (err) {
      statusEl.textContent = err.message;
      statusEl.className = 'custom-form-status is-error';
    }
  });
})();
