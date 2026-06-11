(function () {
  'use strict';

  function hideAlert(alert) {
    alert.classList.add('is-hiding');
    setTimeout(function () {
      if (alert.parentNode) alert.parentNode.removeChild(alert);
    }, 250);
  }

  // Dismissible alerts + auto-fade for success messages
  document.querySelectorAll('.admin-alert').forEach(function (alert) {
    if (alert.querySelector('.admin-alert-close')) return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'admin-alert-close';
    btn.setAttribute('aria-label', 'Dismiss message');
    btn.innerHTML = '\u00d7';
    btn.addEventListener('click', function () { hideAlert(alert); });
    alert.appendChild(btn);

    // Auto-fade success flashes; leave errors and JS-managed boxes alone.
    var isManaged = alert.id === 'users-flash' || alert.id === 'umodal-errors';
    if (alert.classList.contains('admin-alert-success') && !isManaged) {
      setTimeout(function () {
        if (document.body.contains(alert)) hideAlert(alert);
      }, 6000);
    }
  });

  // Copy-to-clipboard buttons: data-copy="text" or data-copy-path="/relative/path"
  document.querySelectorAll('[data-copy], [data-copy-path]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy')
        || (window.location.origin + btn.getAttribute('data-copy-path'));
      var done = function () {
        btn.classList.add('is-copied');
        var prev = btn.getAttribute('title') || '';
        btn.setAttribute('title', 'Copied!');
        setTimeout(function () {
          btn.classList.remove('is-copied');
          btn.setAttribute('title', prev);
        }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done);
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* noop */ }
        document.body.removeChild(ta);
        done();
      }
    });
  });
})();
