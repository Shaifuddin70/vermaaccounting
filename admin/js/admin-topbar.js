(function () {
  var slot = document.getElementById('admin-page-topbar-slot');
  var page = document.querySelector('.admin-page');
  if (!slot || !page) return;

  var header = page.querySelector('.admin-header');
  var fallback = slot.querySelector('[data-topbar-fallback]');

  if (!header) {
    return;
  }

  if (fallback) {
    fallback.remove();
  }

  header.classList.add('admin-header--topbar');
  slot.appendChild(header);
})();
