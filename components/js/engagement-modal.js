(function () {
  var MODAL_ID = 'verma-engagement-modal';
  var STORAGE_KEY = 'verma_engagement_modal_dismissed';
  var DELAY_MS = 5000;

  var skipPaths = {
    '/contact': true,
    '/contact-us': true,
    '/submit-documents': true,
  };

  function shouldSkip() {
    var path = (window.location.pathname || '/').replace(/\/$/, '') || '/';
    if (skipPaths[path]) return true;
    if (path.indexOf('/admin') === 0) return true;
    if (path.indexOf('/form/') === 0) return true;
    try {
      if (sessionStorage.getItem(STORAGE_KEY) === '1') return true;
    } catch (err) {
      /* ignore storage errors */
    }
    return false;
  }

  function getModal() {
    return document.getElementById(MODAL_ID);
  }

  function markDismissed() {
    try {
      sessionStorage.setItem(STORAGE_KEY, '1');
    } catch (err) {
      /* ignore */
    }
  }

  function openModal(modal) {
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () {
      modal.classList.add('is-visible');
    });
    document.body.classList.add('verma-engagement-modal-open');
  }

  function closeModal(modal) {
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('verma-engagement-modal-open');
    markDismissed();
    window.setTimeout(function () {
      modal.hidden = true;
    }, 280);
  }

  function init() {
    if (shouldSkip()) return;

    var modal = getModal();
    if (!modal) return;

    modal.querySelectorAll('[data-engagement-dismiss]').forEach(function (el) {
      el.addEventListener('click', function () {
        closeModal(modal);
      });
    });

    var bookBtn = document.getElementById('verma-engagement-book');
    if (bookBtn) {
      bookBtn.addEventListener('click', function () {
        var url = bookBtn.getAttribute('data-calendly-url') || '';
        closeModal(modal);
        if (url && typeof window.Calendly !== 'undefined' && window.Calendly.initPopupWidget) {
          window.Calendly.initPopupWidget({ url: url });
        } else if (url) {
          window.open(url, '_blank', 'noopener');
        }
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
        closeModal(modal);
      }
    });

    window.setTimeout(function () {
      if (shouldSkip()) return;
      openModal(modal);
    }, DELAY_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
