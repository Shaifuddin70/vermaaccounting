(function () {
  'use strict';

  var form = document.getElementById('invoice-list-filter');
  if (!form || form.getAttribute('data-realtime') !== '1') return;

  var search = document.getElementById('invoices-search');
  var status = document.getElementById('invoices-status');
  var timer = null;

  function submitNow() {
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }

  if (search) {
    search.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(submitNow, 320);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        window.clearTimeout(timer);
        submitNow();
      }
    });
  }

  if (status) {
    status.addEventListener('change', function () {
      window.clearTimeout(timer);
      submitNow();
    });
  }
})();
