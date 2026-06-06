(function () {
  var body = document.body;
  var sidebar = document.getElementById('admin-sidebar');
  var overlay = document.getElementById('admin-sidebar-overlay');
  var openBtn = document.getElementById('admin-menu-toggle');
  var closeBtn = document.getElementById('admin-sidebar-close');
  var nav = document.getElementById('admin-nav');

  if (!sidebar || !openBtn) return;

  function setOpen(open) {
    body.classList.toggle('admin-nav-open', open);
    openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    openBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    if (overlay) {
      overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (open) {
      document.documentElement.style.overflow = 'hidden';
    } else {
      document.documentElement.style.overflow = '';
    }
  }

  function openNav() { setOpen(true); }
  function closeNav() { setOpen(false); }
  function toggleNav() {
    setOpen(!body.classList.contains('admin-nav-open'));
  }

  openBtn.addEventListener('click', toggleNav);
  if (closeBtn) closeBtn.addEventListener('click', closeNav);
  if (overlay) overlay.addEventListener('click', closeNav);

  if (nav) {
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) closeNav();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && body.classList.contains('admin-nav-open')) {
      closeNav();
    }
  });

  window.addEventListener('resize', function () {
    if (window.matchMedia('(min-width: 901px)').matches) {
      closeNav();
    }
  });
})();
