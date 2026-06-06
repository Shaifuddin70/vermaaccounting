(function () {
  var slot = document.getElementById('admin-page-topbar-slot');
  var page = document.querySelector('.admin-page');
  if (!slot || !page) return;

  var header = page.querySelector(':scope > .admin-header');
  if (!header) return;

  header.classList.add('admin-header--topbar');
  slot.appendChild(header);
})();
