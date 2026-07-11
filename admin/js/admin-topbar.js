(function () {
  var slot = document.getElementById('admin-page-topbar-slot');
  var page = document.querySelector('.admin-page');
  if (!slot || !page) return;

  var header = page.querySelector(':scope > .admin-header, :scope > .admin-page-intro > .admin-header');
  if (!header) {
    header = page.querySelector('.admin-header');
  }
  var fallback = slot.querySelector('[data-topbar-fallback]');

  if (!header) {
    return;
  }

  var title = header.querySelector('h1');
  if (!title) {
    return;
  }

  if (fallback) {
    fallback.remove();
  }

  // Keep the sticky top bar title-only so action buttons do not stack under it on mobile.
  var titleWrap = document.createElement('div');
  titleWrap.className = 'admin-header admin-header--topbar';
  titleWrap.appendChild(title);
  slot.appendChild(titleWrap);

  // Leave remaining actions (if any) in the page body.
  if (!header.children.length) {
    header.remove();
  } else {
    header.classList.add('admin-header--page-actions');
  }
})();
