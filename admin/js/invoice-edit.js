(function () {
  'use strict';

  var form = document.getElementById('invoice-form');
  if (!form) return;

  var clients = Array.isArray(window.INVOICE_CLIENTS) ? window.INVOICE_CLIENTS : [];
  var services = Array.isArray(window.INVOICE_SERVICES) ? window.INVOICE_SERVICES : [];
  var clientIdInput = document.getElementById('client-id');
  var clientSearch = document.getElementById('client-search');
  var clientResults = document.getElementById('client-results');
  var clientSelected = document.getElementById('client-selected');
  var clientSelectedName = document.getElementById('client-selected-name');
  var clientSelectedMeta = document.getElementById('client-selected-meta');
  var clientClear = document.getElementById('client-clear');
  var discountInput = document.getElementById('discount-percent');
  var linesBody = document.getElementById('invoice-lines-body');
  var linesEmpty = document.getElementById('invoice-lines-empty');
  var lineTemplate = document.getElementById('invoice-line-template');
  var serviceSelect = document.getElementById('service-add-select');
  var serviceAddBtn = document.getElementById('service-add-btn');
  var customAddBtn = document.getElementById('custom-add-btn');
  var activeIndex = -1;

  function money(n) {
    var v = Math.round((Number(n) || 0) * 100) / 100;
    var abs = Math.abs(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (v < 0 ? '-$' : '$') + abs;
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function syncEmptyState() {
    if (!linesEmpty || !linesBody) return;
    linesEmpty.hidden = linesBody.querySelectorAll('[data-line-row]').length > 0;
  }

  function updateRowAmount(row) {
    if (!row) return;
    var price = parseFloat((row.querySelector('.invoice-line-price') || {}).value) || 0;
    var qty = parseFloat((row.querySelector('.invoice-line-qty') || {}).value) || 0;
    var amountEl = row.querySelector('.invoice-line-amount');
    if (amountEl) amountEl.textContent = money(price * qty);
  }

  function recalc() {
    var subtotal = 0;
    form.querySelectorAll('[data-line-row]').forEach(function (row) {
      var name = row.querySelector('.invoice-line-name');
      if (!name || !String(name.value || '').trim()) {
        updateRowAmount(row);
        return;
      }
      var price = parseFloat((row.querySelector('.invoice-line-price') || {}).value) || 0;
      var qty = parseFloat((row.querySelector('.invoice-line-qty') || {}).value) || 0;
      subtotal += price * qty;
      updateRowAmount(row);
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
    syncEmptyState();
  }

  function addLine(data) {
    if (!linesBody || !lineTemplate) return;
    var node = lineTemplate.content.cloneNode(true);
    var row = node.querySelector('[data-line-row]');
    if (!row) return;

    var serviceId = row.querySelector('input[name="line_service_id[]"]');
    var name = row.querySelector('.invoice-line-name');
    var desc = row.querySelector('.invoice-line-desc');
    var price = row.querySelector('.invoice-line-price');
    var qty = row.querySelector('.invoice-line-qty');

    if (serviceId) serviceId.value = data && data.service_id ? String(data.service_id) : '';
    if (name) name.value = (data && data.name) || '';
    if (desc) desc.value = (data && data.description) || '';
    if (price) price.value = (data && data.unit_price != null) ? String(data.unit_price) : '0';
    if (qty) qty.value = (data && data.quantity != null) ? String(data.quantity) : '1';

    linesBody.appendChild(node);
    if (name && !name.value) name.focus();
    recalc();
  }

  function addServiceFromSelect() {
    if (!serviceSelect) return;
    var id = String(serviceSelect.value || '');
    if (!id) return;
    var service = services.find(function (s) { return String(s.id) === id; });
    if (!service) return;
    addLine({
      service_id: service.id,
      name: service.name,
      description: service.description || '',
      unit_price: service.unit_price,
      quantity: '1'
    });
    serviceSelect.value = '';
    serviceSelect.focus();
  }

  function applyClient(client) {
    if (!client) return;
    if (clientIdInput) clientIdInput.value = String(client.id);
    if (clientSelectedName) clientSelectedName.textContent = client.name || '';
    if (clientSelectedMeta) {
      var bits = [];
      if (client.company) bits.push(client.company);
      if (client.email) bits.push(client.email);
      clientSelectedMeta.textContent = bits.join(' · ');
    }
    if (clientSelected) clientSelected.hidden = false;
    if (clientSearch) {
      clientSearch.value = '';
      clientSearch.setAttribute('aria-expanded', 'false');
    }
    hideResults();

    var company = document.getElementById('bill-company');
    var name = document.getElementById('bill-name');
    if (company && client.company) company.value = client.company;
    if (name && client.name) name.value = client.name;
  }

  function clearClient() {
    if (clientIdInput) clientIdInput.value = '';
    if (clientSelected) clientSelected.hidden = true;
    if (clientSelectedName) clientSelectedName.textContent = '';
    if (clientSelectedMeta) clientSelectedMeta.textContent = '';
    if (clientSearch) clientSearch.focus();
  }

  function hideResults() {
    if (!clientResults) return;
    clientResults.hidden = true;
    clientResults.innerHTML = '';
    activeIndex = -1;
    if (clientSearch) clientSearch.setAttribute('aria-expanded', 'false');
  }

  function filterClients(query) {
    var q = String(query || '').trim().toLowerCase();
    if (q === '') return clients.slice(0, 8);
    return clients.filter(function (c) {
      var hay = ((c.name || '') + ' ' + (c.company || '') + ' ' + (c.email || '')).toLowerCase();
      return hay.indexOf(q) !== -1;
    }).slice(0, 12);
  }

  function renderResults(query) {
    if (!clientResults) return;
    var matches = filterClients(query);
    if (!matches.length) {
      clientResults.innerHTML = '<div class="invoice-client-empty">No clients match “' + escapeHtml(query) + '”</div>';
      clientResults.hidden = false;
      if (clientSearch) clientSearch.setAttribute('aria-expanded', 'true');
      activeIndex = -1;
      return;
    }

    clientResults.innerHTML = matches.map(function (c, idx) {
      var meta = [];
      if (c.company) meta.push(escapeHtml(c.company));
      if (c.email) meta.push(escapeHtml(c.email));
      return '<button type="button" class="invoice-client-option" role="option" data-index="' + idx + '" data-id="' + c.id + '">'
        + '<strong>' + escapeHtml(c.name) + '</strong>'
        + (meta.length ? '<span>' + meta.join(' · ') + '</span>' : '')
        + '</button>';
    }).join('');
    clientResults.hidden = false;
    if (clientSearch) clientSearch.setAttribute('aria-expanded', 'true');
    activeIndex = -1;
    clientResults._matches = matches;
  }

  function highlightOption(next) {
    if (!clientResults) return;
    var options = clientResults.querySelectorAll('.invoice-client-option');
    if (!options.length) return;
    if (activeIndex >= 0 && options[activeIndex]) {
      options[activeIndex].classList.remove('is-active');
    }
    activeIndex = next;
    if (activeIndex < 0) activeIndex = options.length - 1;
    if (activeIndex >= options.length) activeIndex = 0;
    options[activeIndex].classList.add('is-active');
    options[activeIndex].scrollIntoView({ block: 'nearest' });
  }

  if (clientSearch) {
    clientSearch.addEventListener('input', function () { renderResults(clientSearch.value); });
    clientSearch.addEventListener('focus', function () { renderResults(clientSearch.value); });
    clientSearch.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (clientResults && clientResults.hidden) renderResults(clientSearch.value);
        highlightOption(activeIndex + 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightOption(activeIndex - 1);
      } else if (e.key === 'Enter') {
        if (activeIndex >= 0 && clientResults && clientResults._matches && clientResults._matches[activeIndex]) {
          e.preventDefault();
          applyClient(clientResults._matches[activeIndex]);
        }
      } else if (e.key === 'Escape') {
        hideResults();
      }
    });
  }

  if (clientResults) {
    clientResults.addEventListener('mousedown', function (e) {
      var btn = e.target.closest('.invoice-client-option');
      if (!btn) return;
      e.preventDefault();
      var id = String(btn.getAttribute('data-id') || '');
      var client = clients.find(function (c) { return String(c.id) === id; });
      if (client) applyClient(client);
    });
  }

  if (clientClear) clientClear.addEventListener('click', clearClient);

  document.addEventListener('click', function (e) {
    var picker = document.getElementById('invoice-client-picker');
    if (picker && !picker.contains(e.target)) hideResults();
  });

  if (serviceAddBtn) {
    serviceAddBtn.addEventListener('click', addServiceFromSelect);
  }
  if (serviceSelect) {
    serviceSelect.addEventListener('change', function () {
      if (serviceSelect.value) addServiceFromSelect();
    });
  }
  if (customAddBtn) {
    customAddBtn.addEventListener('click', function () {
      addLine({ name: '', description: '', unit_price: '0', quantity: '1' });
    });
  }

  form.addEventListener('input', function (e) {
    if (!e.target) return;
    if (
      e.target.classList.contains('invoice-calc-input') ||
      e.target.classList.contains('invoice-line-name') ||
      e.target === discountInput
    ) {
      recalc();
    }
  });

  form.addEventListener('click', function (e) {
    var btn = e.target.closest('.invoice-line-remove');
    if (!btn) return;
    var row = btn.closest('[data-line-row]');
    if (row) row.remove();
    recalc();
  });

  recalc();
})();
