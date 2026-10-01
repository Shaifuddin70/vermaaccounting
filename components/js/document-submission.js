(function () {
  var app = document.getElementById('custom-form-app');
  if (!app || app.getAttribute('data-document-submission') !== '1') return;

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
