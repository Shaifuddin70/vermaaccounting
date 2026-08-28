(function () {
  var app = document.getElementById('custom-form-app');
  if (!app || app.getAttribute('data-document-submission') !== '1') return;

  var serviceStep = document.getElementById('doc-service-step');
  var form = document.getElementById('custom-form');
  var serviceInput = document.getElementById('tax-service-type-input');
  var serviceBar = document.getElementById('doc-service-selected-bar');
  var serviceLabel = document.getElementById('doc-service-selected-label');
  var changeBtn = document.getElementById('doc-service-change-btn');

  var serviceLabels = {
    personal: 'Personal tax',
    business: 'Business tax',
  };

  function showServiceStep() {
    if (serviceStep) serviceStep.hidden = false;
    if (form) form.hidden = true;
    if (serviceBar) serviceBar.hidden = true;
    if (serviceInput) serviceInput.value = '';
  }

  function showForm(serviceType) {
    if (!serviceType || !serviceLabels[serviceType]) return;
    if (serviceInput) serviceInput.value = serviceType;
    if (serviceLabel) serviceLabel.textContent = serviceLabels[serviceType];
    if (serviceStep) serviceStep.hidden = true;
    if (serviceBar) serviceBar.hidden = false;
    if (form) {
      form.hidden = false;
      var firstInput = form.querySelector('input:not([type="hidden"]), textarea, select, button');
      if (firstInput && typeof firstInput.focus === 'function') {
        firstInput.focus();
      }
    }
  }

  app.querySelectorAll('[data-service-type]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      showForm(btn.getAttribute('data-service-type'));
    });
  });

  if (changeBtn) {
    changeBtn.addEventListener('click', showServiceStep);
  }

  var customerField = document.querySelector('[data-field-name="customer_id"] input, #cf-doc_customer_id');
  if (customerField) {
    var hint = document.createElement('p');
    hint.className = 'doc-customer-id-status';
    hint.setAttribute('role', 'status');
    hint.setAttribute('aria-live', 'polite');
    customerField.parentElement.appendChild(hint);

    var lookupTimer = null;
    var lastLookup = '';

    function setHint(message, state) {
      hint.textContent = message || '';
      hint.className = 'doc-customer-id-status' + (state ? ' doc-customer-id-status--' + state : '');
    }

    function lookupCustomerId() {
      var value = String(customerField.value || '').trim();
      if (value === '' || value === lastLookup) return;
      lastLookup = value;

      setHint('Checking your details…', 'pending');

      fetch('/api/lookup-client', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
        },
        body: 'customer_id=' + encodeURIComponent(value),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data && data.found) {
            setHint(data.message || 'Customer found.', 'success');
          } else {
            setHint((data && data.message) || 'Customer ID not found.', 'error');
          }
        })
        .catch(function () {
          setHint('', '');
        });
    }

    customerField.addEventListener('blur', lookupCustomerId);
    customerField.addEventListener('input', function () {
      lastLookup = '';
      setHint('', '');
      window.clearTimeout(lookupTimer);
      lookupTimer = window.setTimeout(lookupCustomerId, 500);
    });
  }
})();
