(function () {
  'use strict';

  var icons = {
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>',
    error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
    warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>'
  };

  function hideToast(toast) {
    toast.classList.remove('is-visible');
    toast.classList.add('is-hiding');
    setTimeout(function () {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 280);
  }

  function showToast(type, message, duration) {
    var root = document.getElementById('admin-toast-root');
    if (!root || !message) return;

    type = type || 'success';
    duration = duration || (type === 'error' ? 8000 : 5000);

    var toast = document.createElement('div');
    toast.className = 'admin-toast admin-toast--' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.innerHTML =
      '<span class="admin-toast-icon">' + (icons[type] || icons.success) + '</span>' +
      '<span class="admin-toast-message"></span>' +
      '<button type="button" class="admin-toast-close" aria-label="Dismiss">&times;</button>';
    toast.querySelector('.admin-toast-message').textContent = message;

    toast.querySelector('.admin-toast-close').addEventListener('click', function () {
      hideToast(toast);
    });

    root.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add('is-visible');
    });

    if (duration > 0) {
      setTimeout(function () {
        if (document.body.contains(toast)) hideToast(toast);
      }, duration);
    }
  }

  window.AdminToast = {
    show: showToast,
    success: function (message, duration) { showToast('success', message, duration); },
    error: function (message, duration) { showToast('error', message, duration); }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('admin-toast-root');
    if (!root || !root.dataset.toasts) return;

    try {
      JSON.parse(root.dataset.toasts).forEach(function (toast) {
        showToast(toast.type, toast.message);
      });
    } catch (e) {
      // ignore malformed toast payload
    }

    delete root.dataset.toasts;
  });

  function hideAlert(alert) {
    alert.classList.add('is-hiding');
    setTimeout(function () {
      if (alert.parentNode) alert.parentNode.removeChild(alert);
    }, 250);
  }

  // Dismissible inline alerts (validation errors, contextual warnings).
  document.querySelectorAll('.admin-alert').forEach(function (alert) {
    if (alert.querySelector('.admin-alert-close')) return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'admin-alert-close';
    btn.setAttribute('aria-label', 'Dismiss message');
    btn.innerHTML = '\u00d7';
    btn.addEventListener('click', function () { hideAlert(alert); });
    alert.appendChild(btn);
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
