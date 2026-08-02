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
  let pendingUploads = 0;

  const uploadSessionInput = document.getElementById('upload-session');
  let uploadSession = (() => {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  })();
  if (uploadSessionInput) uploadSessionInput.value = uploadSession;

  function applyNumberFormatMask(raw, format) {
    const digits = String(raw || '').replace(/\D+/g, '');
    const slots = (format.match(/#/g) || []).length;
    const limited = slots > 0 ? digits.slice(0, slots) : digits;
    let out = '';
    let di = 0;
    for (let i = 0; i < format.length; i++) {
      const ch = format[i];
      if (ch === '#') {
        if (di >= limited.length) break;
        out += limited[di++];
      } else if (di < limited.length) {
        out += ch;
      } else {
        break;
      }
    }
    return out;
  }

  function initNumberFormatFields() {
    form.querySelectorAll('input[data-number-format]').forEach((input) => {
      if (input.closest('[data-phone-field]')) return;
      const format = input.getAttribute('data-number-format') || '';
      if (!format) return;

      const reformat = () => {
        const next = applyNumberFormatMask(input.value, format);
        if (input.value !== next) {
          const end = next.length;
          input.value = next;
          try {
            input.setSelectionRange(end, end);
          } catch (err) {
            /* ignore unsupported selection */
          }
        }
      };

      input.addEventListener('input', reformat);
      input.addEventListener('blur', reformat);
      if (input.value) reformat();
    });
  }

  initNumberFormatFields();

  function phoneDialFromWrap(wrap) {
    const select = wrap.querySelector('[data-phone-cc]');
    if (select) {
      const opt = select.options[select.selectedIndex];
      return (opt && opt.getAttribute('data-dial')) || '+1';
    }
    const staticDial = wrap.querySelector('[data-phone-dial]');
    return (staticDial && staticDial.getAttribute('data-phone-dial')) || '+1';
  }

  function phoneFormatFromWrap(wrap) {
    const select = wrap.querySelector('[data-phone-cc]');
    if (select) {
      const opt = select.options[select.selectedIndex];
      return (opt && opt.getAttribute('data-format')) || '';
    }
    const combined = wrap.querySelector('[data-phone-combined]');
    return (combined && combined.getAttribute('data-phone-default-format')) || '';
  }

  function syncPhoneCombined(wrap) {
    const local = wrap.querySelector('[data-phone-local]');
    const combined = wrap.querySelector('[data-phone-combined]');
    if (!local || !combined) return;
    const national = String(local.value || '').trim();
    const dial = phoneDialFromWrap(wrap).trim();
    if (!national) {
      combined.value = '';
      return;
    }
    if (!dial || dial === '+') {
      combined.value = national;
      return;
    }
    combined.value = dial + ' ' + national;
  }

  function applyPhoneFormatToLocal(wrap) {
    const local = wrap.querySelector('[data-phone-local]');
    if (!local) return;
    const format = phoneFormatFromWrap(wrap);
    local.setAttribute('data-number-format', format);
    local.maxLength = format ? format.length : 32;
    if (!local.getAttribute('placeholder') || local.dataset.autoPlaceholder !== '0') {
      local.placeholder = format || 'Phone number';
    }
    const next = applyNumberFormatMask(local.value, format);
    if (local.value !== next) local.value = next;
    syncPhoneCombined(wrap);
  }

  function parsePrefillPhone(raw, wrap) {
    const str = String(raw || '').trim();
    if (!str) return { country: '', national: '' };
    const select = wrap.querySelector('[data-phone-cc]');
    const combined = wrap.querySelector('[data-phone-combined]');
    const defaultCountry =
      (combined && combined.getAttribute('data-phone-default-country')) || 'CA';

    if (!select) {
      const dial = phoneDialFromWrap(wrap);
      let national = str;
      if (dial && dial !== '+' && str.startsWith(dial)) {
        national = str.slice(dial.length).trim();
      }
      return { country: defaultCountry, national };
    }

    const options = Array.from(select.options).map((opt) => ({
      code: opt.value,
      dial: opt.getAttribute('data-dial') || '',
      format: opt.getAttribute('data-format') || '',
    }));
    options.sort((a, b) => b.dial.length - a.dial.length);

    for (const opt of options) {
      if (opt.dial && opt.dial !== '+' && (str.startsWith(opt.dial + ' ') || str.startsWith(opt.dial))) {
        return {
          country: opt.code,
          national: str.slice(opt.dial.length).trim(),
        };
      }
    }

    return { country: defaultCountry, national: str };
  }

  function initPhoneFields() {
    form.querySelectorAll('[data-phone-field]').forEach((wrap) => {
      const local = wrap.querySelector('[data-phone-local]');
      const select = wrap.querySelector('[data-phone-cc]');
      if (!local) return;

      const reformat = () => {
        const format = phoneFormatFromWrap(wrap);
        const next = applyNumberFormatMask(local.value, format);
        if (local.value !== next) {
          const end = next.length;
          local.value = next;
          try {
            local.setSelectionRange(end, end);
          } catch (err) {
            /* ignore */
          }
        }
        syncPhoneCombined(wrap);
      };

      local.addEventListener('input', reformat);
      local.addEventListener('blur', () => {
        reformat();
        const format = phoneFormatFromWrap(wrap);
        const slots = (format.match(/#/g) || []).length;
        const digits = String(local.value || '').replace(/\D+/g, '');
        if (local.required || digits.length) {
          if (slots > 0 && digits.length !== slots) {
            local.setCustomValidity('Enter a complete phone number.');
          } else {
            local.setCustomValidity('');
          }
        } else {
          local.setCustomValidity('');
        }
      });

      select?.addEventListener('change', () => {
        applyPhoneFormatToLocal(wrap);
        local.setCustomValidity('');
      });

      applyPhoneFormatToLocal(wrap);
    });
  }

  initPhoneFields();

  function setPhoneFieldValue(wrap, value) {
    const local = wrap.querySelector('[data-phone-local]');
    const select = wrap.querySelector('[data-phone-cc]');
    if (!local) return;
    const parsed = parsePrefillPhone(value, wrap);
    if (select && parsed.country) {
      select.value = parsed.country;
    }
    applyPhoneFormatToLocal(wrap);
    local.value = applyNumberFormatMask(parsed.national, phoneFormatFromWrap(wrap));
    syncPhoneCombined(wrap);
  }

  function initMinAgeDateFields() {
    form.querySelectorAll('input[type="date"][data-min-age]').forEach((input) => {
      const minAge = parseInt(input.getAttribute('data-min-age') || '0', 10);
      const max = input.getAttribute('max') || '';
      if (!minAge || !max) return;

      const validate = () => {
        if (!input.value) {
          input.setCustomValidity('');
          return true;
        }
        if (input.value > max) {
          input.setCustomValidity('Must be at least ' + minAge + ' years old.');
          return false;
        }
        input.setCustomValidity('');
        return true;
      };

      input.addEventListener('change', validate);
      input.addEventListener('input', validate);
      validate();
    });
  }

  initMinAgeDateFields();

  function fieldHasValue(wrap) {
    if (!wrap || wrap.style.display === 'none') return true;
    const fileField = wrap.querySelector('[data-file-field][data-required="1"]');
    if (fileField) {
      const tokenBox = fileField.querySelector('.custom-form-file-tokens');
      return !!(tokenBox && tokenBox.querySelector('input[data-staged-token]'));
    }
    const phoneLocal = wrap.querySelector('[data-phone-local]');
    if (phoneLocal) {
      const required = !!wrap.querySelector('label .required') || phoneLocal.required;
      if (!required) return true;
      const format = phoneFormatFromWrap(wrap);
      const slots = (format.match(/#/g) || []).length;
      const digits = String(phoneLocal.value || '').replace(/\D+/g, '');
      return slots > 0 ? digits.length === slots : digits.length > 0;
    }
    const radios = wrap.querySelectorAll('input[type="radio"]');
    if (radios.length) {
      return Array.from(radios).some((r) => r.checked);
    }
    const checks = wrap.querySelectorAll('input[type="checkbox"]');
    if (checks.length) {
      const required = wrap.querySelector('label .required');
      if (!required) return true;
      return Array.from(checks).some((c) => c.checked);
    }
    const control = wrap.querySelector(
      'input[data-dob-value], input:not([type="hidden"]):not([type="file"]):not([type="radio"]):not([type="checkbox"]), select, textarea'
    );
    if (!control) return true;
    if (!control.required && !wrap.querySelector('label .required')) return true;
    return String(control.value || '').trim() !== '';
  }

  function fieldLabel(wrap) {
    return wrap.querySelector('label')?.textContent?.replace(/\*/g, '').trim() || 'This field';
  }

  function failField(wrap, message, focusEl) {
    statusEl.textContent = message || 'Please complete: ' + fieldLabel(wrap);
    statusEl.className = 'custom-form-status is-error';
    const el = focusEl || wrap.querySelector('input:not([type="hidden"]), select, textarea');
    el?.focus();
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (el && typeof el.reportValidity === 'function') {
      try {
        el.reportValidity();
      } catch (err) {
        /* ignore */
      }
    }
    return false;
  }

  function validateEmailValue(value) {
    const v = String(value || '').trim();
    if (!v) return true;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  function validatePlainNumberValue(value) {
    const v = String(value || '').trim();
    if (!v) return true;
    return /^-?\d+(\.\d+)?$/.test(v);
  }

  function validateNumberFormatValue(value, format) {
    const v = String(value || '').trim();
    if (!v || !format) return true;
    const digits = v.replace(/\D+/g, '');
    const slots = (format.match(/#/g) || []).length;
    return slots > 0 ? digits.length === slots : true;
  }

  function validateFieldFormats(wrap) {
    const reasonWraps = wrap.querySelectorAll('[data-yes-no-reason-for]');
    for (const reasonWrap of reasonWraps) {
      if (reasonWrap.hidden) continue;
      const type = reasonWrap.getAttribute('data-reason-type') || 'textarea';
      const required = reasonWrap.getAttribute('data-reason-required') === '1';
      const label =
        reasonWrap.querySelector(':scope > label')?.textContent?.trim() || fieldLabel(wrap);

      if (type === 'checkbox') {
        const checks = reasonWrap.querySelectorAll('input[type="checkbox"]');
        const any = Array.from(checks).some((c) => c.checked);
        if (required && !any) {
          return failField(wrap, 'Please complete: ' + label, checks[0]);
        }
        continue;
      }

      if (type === 'radio') {
        const radios = reasonWrap.querySelectorAll('input[type="radio"]');
        const any = Array.from(radios).some((r) => r.checked);
        if (required && !any) {
          return failField(wrap, 'Please complete: ' + label, radios[0]);
        }
        continue;
      }

      if (type === 'file' || type === 'image') {
        const fileField = reasonWrap.querySelector('[data-file-field]');
        if (required && fileField) {
          const tokenBox = fileField.querySelector('.custom-form-file-tokens');
          const hasTokens = tokenBox && tokenBox.querySelector('input[data-staged-token]');
          if (!hasTokens) {
            return failField(
              wrap,
              'Please upload a file for: ' + label,
              fileField.querySelector('input[type="file"]')
            );
          }
        }
        continue;
      }

      if (type === 'tel') {
        const phoneWrap = reasonWrap.querySelector('[data-phone-field]');
        if (phoneWrap) {
          syncPhoneCombined(phoneWrap);
          const local = phoneWrap.querySelector('[data-phone-local]');
          const format = phoneFormatFromWrap(phoneWrap);
          const slots = (format.match(/#/g) || []).length;
          const digits = String(local?.value || '').replace(/\D+/g, '');
          if ((required || digits.length) && slots > 0 && digits.length !== slots) {
            local?.setCustomValidity('Enter a complete phone number.');
            return failField(wrap, 'Please enter a complete phone number.', local);
          }
          local?.setCustomValidity('');
        }
        continue;
      }

      const control = reasonWrap.querySelector(
        'textarea, select, input[type="text"], input[type="email"], input[type="number"], input[type="date"]'
      );
      if (!control) continue;
      const value = String(control.value || '').trim();
      if (required && !value) {
        control.setCustomValidity('This field is required.');
        return failField(wrap, 'Please complete: ' + label, control);
      }
      if (value) {
        if (type === 'email' && !validateEmailValue(value)) {
          control.setCustomValidity('Enter a valid email.');
          return failField(wrap, 'Please enter a valid email for: ' + label, control);
        }
        if (type === 'number') {
          const format = control.getAttribute('data-number-format') || '';
          if (format) {
            if (!validateNumberFormatValue(value, format)) {
              control.setCustomValidity('Enter a complete value.');
              return failField(wrap, 'Please complete: ' + label, control);
            }
          } else if (!validatePlainNumberValue(value)) {
            control.setCustomValidity('Enter a valid number.');
            return failField(wrap, 'Please enter a valid number for: ' + label, control);
          }
        }
      }
      control.setCustomValidity('');
    }

    const topPhone = Array.from(wrap.querySelectorAll('.custom-form-phone')).find(
      (el) => !el.closest('[data-yes-no-reason-for]')
    );
    if (topPhone) {
      const local = topPhone.querySelector('[data-phone-local]');
      const format = phoneFormatFromWrap(topPhone);
      const slots = (format.match(/#/g) || []).length;
      const digits = String(local?.value || '').replace(/\D+/g, '');
      if (local && (local.required || digits.length) && slots > 0 && digits.length !== slots) {
        local.setCustomValidity('Enter a complete phone number.');
        return failField(wrap, 'Please enter a complete phone number.', local);
      }
      if (local) local.setCustomValidity('');
    }

    const emailInput = Array.from(wrap.querySelectorAll('input[type="email"]')).find(
      (el) => !el.closest('[data-yes-no-reason-for]')
    );
    if (emailInput && emailInput.value && !validateEmailValue(emailInput.value)) {
      emailInput.setCustomValidity('Enter a valid email.');
      return failField(wrap, 'Please enter a valid email for: ' + fieldLabel(wrap), emailInput);
    }
    if (emailInput) emailInput.setCustomValidity('');

    const numberFormatted = wrap.querySelector('input[data-number-format]:not([data-phone-local])');
    if (numberFormatted && numberFormatted.value) {
      const format = numberFormatted.getAttribute('data-number-format') || '';
      if (!validateNumberFormatValue(numberFormatted.value, format)) {
        numberFormatted.setCustomValidity('Enter a complete value.');
        return failField(wrap, 'Please complete: ' + fieldLabel(wrap), numberFormatted);
      }
      numberFormatted.setCustomValidity('');
    }

    const plainNumber = wrap.querySelector('input[type="number"]:not([data-number-format])');
    if (plainNumber && plainNumber.value && !validatePlainNumberValue(plainNumber.value)) {
      plainNumber.setCustomValidity('Enter a valid number.');
      return failField(wrap, 'Please enter a valid number for: ' + fieldLabel(wrap), plainNumber);
    }
    if (plainNumber) plainNumber.setCustomValidity('');

    wrap.querySelectorAll('input[type="date"][data-min-age]').forEach((input) => {
      const max = input.getAttribute('max') || '';
      if (input.value && max && input.value > max) {
        input.setCustomValidity(
          'Must be at least ' + (input.getAttribute('data-min-age') || '') + ' years old.'
        );
      } else {
        input.setCustomValidity('');
      }
    });
    const invalidDate = wrap.querySelector('input[type="date"][data-min-age]:invalid');
    if (invalidDate) {
      return failField(wrap, 'Please check the date of birth / age requirements.', invalidDate);
    }

    return true;
  }

  function validatePage(pageEl) {
    if (!pageEl) return true;
    const wraps = pageEl.querySelectorAll('.custom-form-field');
    for (const wrap of wraps) {
      if (wrap.style.display === 'none') continue;
      const required =
        !!wrap.querySelector('label .required') ||
        !!wrap.querySelector('[required], [data-required="1"]');
      if (required && !fieldHasValue(wrap)) {
        return failField(wrap);
      }
      if (!validateFieldFormats(wrap)) return false;
    }
    return true;
  }

  function validateVisibleFormFields(opts) {
    const allPages = !!(opts && opts.allPages);
    applyConditions();
    const wraps = form.querySelectorAll('.custom-form-field');
    for (const wrap of wraps) {
      if (wrap.style.display === 'none') continue;
      const page = wrap.closest('.custom-form-page');
      if (!allPages && page && page.hidden) continue;
      const required =
        !!wrap.querySelector('label .required') ||
        !!wrap.querySelector('[required], [data-required="1"]');
      if (required && !fieldHasValue(wrap)) {
        if (allPages && page && page.hidden && typeof goToFormPage === 'function') {
          const pagesRoot = document.getElementById('form-pages');
          const pages = pagesRoot
            ? Array.from(pagesRoot.querySelectorAll('.custom-form-page'))
            : [];
          const idx = pages.indexOf(page);
          if (idx >= 0) goToFormPage(idx);
        }
        return failField(wrap);
      }
      if (!validateFieldFormats(wrap)) {
        if (allPages && page && page.hidden && typeof goToFormPage === 'function') {
          const pagesRoot = document.getElementById('form-pages');
          const pages = pagesRoot
            ? Array.from(pagesRoot.querySelectorAll('.custom-form-page'))
            : [];
          const idx = pages.indexOf(page);
          if (idx >= 0) goToFormPage(idx);
        }
        return false;
      }
    }

    for (const wrap of form.querySelectorAll('[data-file-field][data-required="1"]')) {
      const fieldWrap = wrap.closest('[data-field-id]');
      if (fieldWrap && fieldWrap.style.display === 'none') continue;
      const page = fieldWrap?.closest('.custom-form-page');
      if (!allPages && page && page.hidden) continue;
      const tokenBox = wrap.querySelector('.custom-form-file-tokens');
      const hasTokens = tokenBox && tokenBox.querySelector('input[data-staged-token]');
      if (!hasTokens) {
        if (allPages && page && page.hidden && typeof goToFormPage === 'function') {
          const pagesRoot = document.getElementById('form-pages');
          const pages = pagesRoot
            ? Array.from(pagesRoot.querySelectorAll('.custom-form-page'))
            : [];
          const idx = pages.indexOf(page);
          if (idx >= 0) goToFormPage(idx);
        }
        statusEl.textContent = 'Please upload all required files.';
        statusEl.className = 'custom-form-status is-error';
        wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
      }
    }

    let phoneBlocked = false;
    form.querySelectorAll('[data-phone-field]').forEach((phoneWrap) => {
      if (phoneBlocked) return;
      const fieldWrap = phoneWrap.closest('.custom-form-field');
      if (fieldWrap && fieldWrap.style.display === 'none') return;
      const page = fieldWrap?.closest('.custom-form-page');
      if (!allPages && page && page.hidden) return;
      syncPhoneCombined(phoneWrap);
      const local = phoneWrap.querySelector('[data-phone-local]');
      const format = phoneFormatFromWrap(phoneWrap);
      const slots = (format.match(/#/g) || []).length;
      const digits = String(local?.value || '').replace(/\D+/g, '');
      if (local && (local.required || digits.length) && slots > 0 && digits.length !== slots) {
        phoneBlocked = true;
        if (allPages && page && page.hidden && typeof goToFormPage === 'function') {
          const pagesRoot = document.getElementById('form-pages');
          const pages = pagesRoot
            ? Array.from(pagesRoot.querySelectorAll('.custom-form-page'))
            : [];
          const idx = pages.indexOf(page);
          if (idx >= 0) goToFormPage(idx);
        }
        local.setCustomValidity('Enter a complete phone number.');
        failField(fieldWrap || phoneWrap, 'Please enter a complete phone number.', local);
      } else if (local) {
        local.setCustomValidity('');
      }
    });
    if (phoneBlocked) return false;

    return true;
  }

  function scrollFormToTop() {
    const target =
      document.querySelector('.vf-card') ||
      document.getElementById('custom-form-app') ||
      form;
    if (!target) return;
    const offset = 12;
    const top = Math.max(0, target.getBoundingClientRect().top + window.scrollY - offset);
    window.scrollTo({ top, behavior: 'smooth' });
  }

  /** @type {((index: number, opts?: { scroll?: boolean }) => void) | null} */
  let goToFormPage = null;

  function initFormPages() {
    const pagesRoot = document.getElementById('form-pages');
    if (!pagesRoot || pagesRoot.getAttribute('data-multipage') !== '1') return;

    const pages = Array.from(pagesRoot.querySelectorAll('.custom-form-page'));
    if (pages.length < 2) return;

    const prevBtn = document.getElementById('form-page-prev');
    const nextBtn = document.getElementById('form-page-next');
    const submitBtn = document.getElementById('custom-form-submit');
    const stepLabel = document.getElementById('form-page-step-label');
    const titleLabel = document.getElementById('form-page-title-label');
    const progressFill = document.getElementById('form-page-progress-fill');
    let current = 0;

    function showPage(index, opts) {
      const shouldScroll = !opts || opts.scroll !== false;
      current = Math.max(0, Math.min(pages.length - 1, index));
      pages.forEach((page, i) => {
        page.hidden = i !== current;
      });
      if (prevBtn) prevBtn.hidden = current === 0;
      if (nextBtn) nextBtn.hidden = current === pages.length - 1;
      if (submitBtn) submitBtn.hidden = current !== pages.length - 1;
      if (stepLabel) stepLabel.textContent = 'Step ' + (current + 1) + ' of ' + pages.length;
      if (titleLabel) titleLabel.textContent = pages[current].getAttribute('data-page-title') || '';
      if (progressFill) {
        progressFill.style.width = ((current + 1) / pages.length) * 100 + '%';
      }
      if (statusEl) {
        statusEl.textContent = '';
        statusEl.className = 'custom-form-status';
      }
      applyConditions();
      if (shouldScroll) {
        requestAnimationFrame(() => scrollFormToTop());
      }
    }

    prevBtn?.addEventListener('click', () => {
      showPage(current - 1);
    });

    nextBtn?.addEventListener('click', () => {
      if (!validatePage(pages[current])) return;
      showPage(current + 1);
    });

    showPage(0, { scroll: false });
    goToFormPage = showPage;
  }

  initFormPages();

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function getFormSlug() {
    return form.querySelector('input[name="form_slug"]')?.value || '';
  }

  function updateSubmitState() {
    const btn = document.getElementById('custom-form-submit');
    if (!btn) return;
    btn.disabled = pendingUploads > 0;
    if (pendingUploads > 0) {
      btn.setAttribute('aria-busy', 'true');
    } else {
      btn.removeAttribute('aria-busy');
    }
  }

  function addHiddenToken(container, fieldName, token) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'staged_' + fieldName + '[]';
    input.value = token;
    input.dataset.stagedToken = token;
    container.appendChild(input);
  }

  function removeHiddenToken(container, token) {
    container.querySelectorAll('input[data-staged-token="' + token + '"]').forEach((node) => node.remove());
  }

  function countStagedTokens(container) {
    return container.querySelectorAll('input[data-staged-token]').length;
  }

  function initFileFields() {
    form.querySelectorAll('[data-file-field]').forEach((wrap) => {
      const input = wrap.querySelector('.custom-form-file-input');
      const queue = wrap.querySelector('.custom-form-file-queue');
      const tokenBox = wrap.querySelector('.custom-form-file-tokens');
      if (!input || !queue || !tokenBox) return;

      const fieldId = wrap.getAttribute('data-field-id');
      const fieldName = wrap.getAttribute('data-field-name');
      const maxFiles = parseInt(wrap.getAttribute('data-max-files') || '1', 10);

      input.addEventListener('change', async () => {
        const files = Array.from(input.files || []);
        input.value = '';
        if (!files.length) return;

        const current = countStagedTokens(tokenBox);
        const remaining = Math.max(0, maxFiles - current);
        if (remaining <= 0) {
          if (statusEl) {
            statusEl.textContent = 'Maximum number of files reached for this field.';
            statusEl.className = 'custom-form-status is-error';
          }
          return;
        }

        const batch = files.slice(0, remaining);
        for (const file of batch) {
          const item = document.createElement('div');
          item.className = 'custom-form-file-item is-uploading';
          item.innerHTML =
            '<div class="custom-form-file-item-main">' +
            '<span class="custom-form-file-item-name"></span>' +
            '<span class="custom-form-file-item-meta">Uploading…</span>' +
            '</div>' +
            '<button type="button" class="custom-form-file-remove" disabled>Remove</button>';
          item.querySelector('.custom-form-file-item-name').textContent = file.name;
          queue.appendChild(item);

          pendingUploads += 1;
          updateSubmitState();

          const fd = new FormData();
          fd.append('form_slug', getFormSlug());
          fd.append('field_id', fieldId);
          fd.append('upload_session', uploadSession);
          fd.append('file', file);

          try {
            const res = await fetch('/api/stage-upload', { method: 'POST', body: fd });
            const raw = await res.text();
            let json = null;
            try {
              json = JSON.parse(raw);
            } catch (parseErr) {
              throw new Error(
                res.ok
                  ? 'Upload failed (invalid server response).'
                  : 'Upload failed. The file may be too large or the server returned an error.'
              );
            }
            if (!res.ok) {
              throw new Error(json.error || 'Upload failed');
            }
            item.classList.remove('is-uploading');
            item.classList.add('is-done');
            item.querySelector('.custom-form-file-item-meta').textContent =
              formatFileSize(json.size || file.size) + ' · Uploaded';
            const removeBtn = item.querySelector('.custom-form-file-remove');
            removeBtn.disabled = false;
            removeBtn.addEventListener('click', async () => {
              removeBtn.disabled = true;
              const rm = new FormData();
              rm.append('form_slug', getFormSlug());
              rm.append('field_id', fieldId);
              rm.append('upload_session', uploadSession);
              rm.append('token', json.token);
              try {
                await fetch('/api/stage-upload-remove', { method: 'POST', body: rm });
              } catch (err) {
                /* keep token hidden if remove fails */
              }
              removeHiddenToken(tokenBox, json.token);
              item.remove();
            });
            addHiddenToken(tokenBox, fieldName, json.token);
          } catch (err) {
            item.classList.remove('is-uploading');
            item.classList.add('is-error');
            item.querySelector('.custom-form-file-item-meta').textContent = err.message || 'Upload failed';
            const removeBtn = item.querySelector('.custom-form-file-remove');
            removeBtn.disabled = false;
            removeBtn.textContent = 'Dismiss';
            removeBtn.addEventListener('click', () => item.remove());
          } finally {
            pendingUploads = Math.max(0, pendingUploads - 1);
            updateSubmitState();
          }
        }
      });
    });
  }

  initFileFields();

  function selectTaxYear(year) {
    if (taxYearInput) taxYearInput.value = String(year);
    if (taxYearLabel) taxYearLabel.textContent = String(year);
    if (taxYearBar) taxYearBar.hidden = false;
    if (taxYearStep) taxYearStep.hidden = true;
    form.hidden = false;
    const first = form.querySelector('input:not([type="hidden"]), select, textarea');
    if (first) first.focus();
  }

  function clearFormData() {
    form.reset();
    clearFileUploads();
    if (uploadSessionInput) {
      const bytes = new Uint8Array(16);
      crypto.getRandomValues(bytes);
      uploadSession = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
      uploadSessionInput.value = uploadSession;
    }
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      if (typeof el.setCustomValidity === 'function') el.setCustomValidity('');
      el.classList.remove('is-invalid');
    });
    form.querySelectorAll('[data-phone-field]').forEach((wrap) => {
      applyPhoneFormatToLocal(wrap);
    });
    goToFormPage?.(0, { scroll: false });
    applyConditions();
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
    clearFormData();
    if (taxYearInput) taxYearInput.value = '';
    if (taxYearSelect) taxYearSelect.value = '';
    form.hidden = true;
    if (successMessageEl) {
      successMessageEl.textContent = message || 'Thank you! Your response has been received.';
    }
    if (successPanel) {
      successPanel.hidden = false;
      requestAnimationFrame(() => scrollFormToTop());
    }
    if (statusEl) {
      statusEl.textContent = '';
      statusEl.className = 'custom-form-status';
    }
  }

  function clearFileUploads() {
    form.querySelectorAll('.custom-form-file-queue').forEach((queue) => {
      queue.innerHTML = '';
    });
    form.querySelectorAll('.custom-form-file-tokens').forEach((box) => {
      box.innerHTML = '';
    });
    pendingUploads = 0;
    updateSubmitState();
  }

  function resetTaxYearStep() {
    hideSuccessConfirmation();
    prefillDismissed = false;
    lastLookupKey = '';
    const previousYear = taxYearInput?.value || taxYearSelect?.value || '';
    if (taxYearBar) taxYearBar.hidden = true;
    if (taxYearStep) taxYearStep.hidden = false;
    form.hidden = true;
    clearFormData();
    if (taxYearInput) taxYearInput.value = '';
    if (taxYearSelect) taxYearSelect.value = previousYear;
    if (statusEl) {
      statusEl.textContent = '';
      statusEl.className = 'custom-form-status';
    }
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
    clearFormData();
    if (statusEl) {
      statusEl.textContent = '';
      statusEl.className = 'custom-form-status';
    }
    const first = form.querySelector('input:not([type="hidden"]), select, textarea');
    if (first) first.focus();
    requestAnimationFrame(() => scrollFormToTop());
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

    if (wrap.querySelector('[data-phone-field]')) {
      syncPhoneCombined(wrap);
      const combined = wrap.querySelector('[data-phone-combined]');
      return combined ? combined.value : '';
    }

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
        const phoneWrap = wrap.querySelector('[data-phone-field]');
        wrap.querySelectorAll('input, select, textarea').forEach((el) => {
          if (el.closest('.yes-no-reason-wrap')) return;
          el.disabled = !visible;
          if (!visible) {
            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
            else if (el.type !== 'file') {
              if (phoneWrap && el.matches('[data-phone-cc]')) return;
              el.value = '';
            }
          }
        });
        if (phoneWrap && visible) {
          applyPhoneFormatToLocal(phoneWrap);
        }
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
      if (!yesNoBlock) return;

      const selected = yesNoBlock.querySelector('input[type="radio"]:checked');
      const show = selected && selected.value === when;

      reasonWrap.hidden = !show;
      const fileField = reasonWrap.querySelector('[data-file-field]');
      if (fileField) {
        fileField.setAttribute('data-required', show && required ? '1' : '0');
      }

      reasonWrap.querySelectorAll('textarea, input, select').forEach((control) => {
        if (control.closest('.custom-form-file-tokens')) return;
        control.disabled = !show;
        if (!show) {
          if (control.type === 'checkbox' || control.type === 'radio') {
            control.checked = false;
          } else if (control.type !== 'file') {
            control.value = '';
          }
          control.removeAttribute('required');
          if (typeof control.setCustomValidity === 'function') {
            control.setCustomValidity('');
          }
        } else if (required) {
          const type = reasonWrap.getAttribute('data-reason-type') || '';
          if (
            control.matches('[data-phone-local]') ||
            (control.matches('textarea, select, input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input[type="date"]') &&
              !control.matches('[data-phone-cc]') &&
              !control.matches('[data-phone-combined]'))
          ) {
            control.setAttribute('required', 'required');
          } else if (type === 'radio' && control.type === 'radio') {
            /* HTML5 required on one radio in group is enough — set on first */
          } else {
            control.removeAttribute('required');
          }
        } else {
          control.removeAttribute('required');
        }
      });

      if (show && required) {
        const type = reasonWrap.getAttribute('data-reason-type') || '';
        if (type === 'radio') {
          const radios = reasonWrap.querySelectorAll('input[type="radio"]');
          radios.forEach((r, idx) => {
            if (idx === 0) r.setAttribute('required', 'required');
            else r.removeAttribute('required');
          });
        }
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

    if (wrap.querySelector('[data-phone-field]')) {
      setPhoneFieldValue(wrap, Array.isArray(value) ? value.join(', ') : String(value));
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
      const fieldId = wrap.getAttribute('data-field-id');
      form.querySelectorAll('[data-yes-no-reason-for="' + fieldId + '"]').forEach((reasonWrap) => {
        const control = reasonWrap.querySelector('textarea, input');
        if (!control || !control.name) return;
        if (control.name in data) {
          control.value = String(data[control.name]);
        }
      });
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

    if (pendingUploads > 0) {
      statusEl.textContent = 'Please wait for file uploads to finish.';
      statusEl.className = 'custom-form-status is-error';
      return;
    }

    if (!validateVisibleFormFields({ allPages: true })) {
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
