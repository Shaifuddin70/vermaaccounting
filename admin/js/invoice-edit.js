(function () {
  'use strict';

  var form = document.getElementById('invoice-form');
  if (!form) return;

  var clients = window.INVOICE_CLIENTS || {};
  var clientSelect = document.getElementById('client-id');
  var discountInput = document.getElementById('discount-percent');
  var customWrap = document.getElementById('custom-items');
  var addBtn = document.getElementById('add-custom-item');
  var template = document.getElementById('custom-item-template');

  function money(n) {
    var v = Math.round((Number(n) || 0) * 100) / 100;
    var abs = Math.abs(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (v < 0 ? '-$' : '$') + abs;
  }

  function recalc() {
    var subtotal = 0;

    form.querySelectorAll('[data-service-row]').forEach(function (row) {
      var toggle = row.querySelector('.invoice-service-toggle');
      if (!toggle || !toggle.checked) return;
      var price = row.querySelector('input[name^="service_price"]');
      var qty = row.querySelector('input[name^="service_qty"]');
      var p = parseFloat(price && price.value) || 0;
      var q = parseFloat(qty && qty.value) || 0;
      subtotal += p * q;
    });

    form.querySelectorAll('[data-custom-row]').forEach(function (row) {
      var name = row.querySelector('input[name="custom_name[]"]');
      if (!name || !String(name.value || '').trim()) return;
      var price = row.querySelector('input[name="custom_price[]"]');
      var qty = row.querySelector('input[name="custom_qty[]"]');
      var p = parseFloat(price && price.value) || 0;
      var q = parseFloat(qty && qty.value) || 0;
      subtotal += p * q;
    });

    subtotal = Math.round(subtotal * 100) / 100;
    var discountPct = Math.max(0, Math.min(100, parseFloat(discountInput && discountInput.value) || 0));
    var discountAmt = Math.round(subtotal * (discountPct / 100) * 100) / 100;
    var total = Math.round(Math.max(0, subtotal - discountAmt) * 100) / 100;

    var elSub = document.getElementById('preview-subtotal');
    var elDisc = document.getElementById('preview-discount');
    var elTotal = document.getElementById('preview-total');
    var discRow = document.getElementById('preview-discount-row');
    var discLabel = document.getElementById('preview-discount-label');

    if (elSub) elSub.textContent = money(subtotal);
    if (elTotal) elTotal.textContent = money(total);
    if (discRow) {
      if (discountPct > 0) {
        discRow.hidden = false;
        if (discLabel) discLabel.textContent = discountPct + '% Discount';
        if (elDisc) elDisc.textContent = '(' + money(discountAmt) + ')';
      } else {
        discRow.hidden = true;
      }
    }
  }

  function syncServiceRow(row) {
    var toggle = row.querySelector('.invoice-service-toggle');
    var fields = row.querySelector('.invoice-service-fields');
    var on = !!(toggle && toggle.checked);
    row.classList.toggle('is-selected', on);
    if (fields) fields.hidden = !on;
    fields && fields.querySelectorAll('input, textarea, select').forEach(function (el) {
      if (el.type === 'hidden') {
        el.disabled = !on;
        return;
      }
      el.disabled = !on;
    });
    recalc();
  }

  form.querySelectorAll('[data-service-row]').forEach(function (row) {
    var toggle = row.querySelector('.invoice-service-toggle');
    if (toggle) {
      toggle.addEventListener('change', function () {
        syncServiceRow(row);
      });
    }
  });

  form.addEventListener('input', function (e) {
    if (e.target && (e.target.classList.contains('invoice-calc-input') || e.target === discountInput)) {
      recalc();
    }
  });

  if (clientSelect) {
    clientSelect.addEventListener('change', function () {
      var id = String(clientSelect.value || '');
      var data = clients[id];
      if (!data) return;
      var company = document.getElementById('bill-company');
      var name = document.getElementById('bill-name');
      if (company && data.company) company.value = data.company;
      if (name && data.name) name.value = data.name;
    });
  }

  if (addBtn && template && customWrap) {
    addBtn.addEventListener('click', function () {
      var node = template.content.cloneNode(true);
      customWrap.appendChild(node);
      recalc();
    });
  }

  form.addEventListener('click', function (e) {
    var btn = e.target.closest('.remove-custom-item');
    if (!btn) return;
    var row = btn.closest('[data-custom-row]');
    if (row) row.remove();
    recalc();
  });

  recalc();
})();
