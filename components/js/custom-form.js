(function () {
  const form = document.getElementById('custom-form');
  if (!form) return;

  const statusEl = document.getElementById('custom-form-status');
  const fields = form.querySelectorAll('[data-field-id]');

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
  }

  form.addEventListener('change', applyConditions);
  form.addEventListener('input', applyConditions);
  applyConditions();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    applyConditions();
    statusEl.textContent = 'Submitting…';
    statusEl.className = 'custom-form-status';

    const data = new FormData(form);
    try {
      const res = await fetch('/api/submit.php', {
        method: 'POST',
        body: data,
      });
      const json = await res.json();
      if (!res.ok) {
        throw new Error(json.error || 'Submission failed');
      }
      statusEl.textContent = json.message || 'Thank you!';
      statusEl.className = 'custom-form-status is-success';
      form.reset();
      applyConditions();
    } catch (err) {
      statusEl.textContent = err.message;
      statusEl.className = 'custom-form-status is-error';
    }
  });
})();
