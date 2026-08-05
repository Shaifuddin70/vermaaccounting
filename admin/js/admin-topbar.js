(function () {
  var slot = document.getElementById('admin-page-topbar-slot');
  var page = document.querySelector('.admin-page');
  if (!slot || !page) return;

  var header = page.querySelector(':scope > .admin-header, :scope > .admin-page-intro > .admin-header');
  if (!header) {
    header = page.querySelector('.admin-header');
  }
  var fallback = slot.querySelector('[data-topbar-fallback]');

  if (!header || !header.querySelector('h1')) {
    return;
  }

  if (fallback) {
    fallback.remove();
  }

  // Move title + shortcut buttons into the sticky top bar for every admin page.
  header.classList.add('admin-header--topbar');
  header.classList.remove('admin-header--page-actions');
  slot.appendChild(header);
})();
