/**
 * Public site navigation: desktop dropdown + mobile drawer.
 * Mobile breakpoint must match CSS (max-width: 991px).
 */
(function () {
  "use strict";

  var MOBILE_MQ = "(max-width: 991px)";

  function isMobile() {
    return window.matchMedia(MOBILE_MQ).matches;
  }

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var navbar = document.querySelector(".verma-nav");
    var hamburger = document.getElementById("hamburgerMenu");
    var checkbox = document.getElementById("hamburgerCheckbox");
    var menu = document.querySelector(".verma-menu");
    var overlay = document.getElementById("navOverlay");
    var submenus = menu ? menu.querySelectorAll(".verma-submenu") : [];

    if (!navbar || !menu) return;

    window.addEventListener(
      "scroll",
      function () {
        navbar.classList.toggle("scrolled", window.scrollY > 40);
      },
      { passive: true }
    );

    highlightActiveLink();

    function closeAllSubmenus(except) {
      submenus.forEach(function (submenu) {
        if (except && submenu === except) return;
        submenu.classList.remove("active");
        var toggle = submenu.querySelector(".verma-toggle");
        if (toggle) toggle.setAttribute("aria-expanded", "false");
      });
    }

    // Desktop: CSS :hover handles submenu.
    // Mobile: accordion via .active; only one open at a time.
    submenus.forEach(function (submenu) {
      var toggle = submenu.querySelector(".verma-toggle");
      if (!toggle) return;

      toggle.addEventListener("click", function (e) {
        if (!isMobile()) return;

        e.preventDefault();
        e.stopPropagation();

        var willOpen = !submenu.classList.contains("active");
        closeAllSubmenus(willOpen ? submenu : null);
        submenu.classList.toggle("active", willOpen);
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
      });
    });

    if (checkbox && hamburger) {
      function setMenuOpen(open) {
        checkbox.checked = open;
        menu.classList.toggle("active", open);
        hamburger.classList.toggle("is-open", open);
        hamburger.setAttribute("aria-expanded", open ? "true" : "false");
        document.body.classList.toggle("nav-open", open);
        if (overlay) {
          overlay.classList.toggle("is-visible", open);
          overlay.setAttribute("aria-hidden", open ? "false" : "true");
        }
        if (!open) {
          closeAllSubmenus();
        }
      }

      function closeMenu() {
        setMenuOpen(false);
      }

      function openMenu() {
        setMenuOpen(true);
      }

      checkbox.addEventListener("change", function () {
        setMenuOpen(checkbox.checked);
      });

      if (overlay) {
        overlay.addEventListener("click", closeMenu);
      }

      var closeBtn = document.getElementById("mobileMenuClose");
      if (closeBtn) {
        closeBtn.addEventListener("click", function (e) {
          e.preventDefault();
          closeMenu();
        });
      }

      menu.querySelectorAll(".verma-link:not(.verma-toggle)").forEach(function (link) {
        link.addEventListener("click", function () {
          if (isMobile()) closeMenu();
        });
      });

      menu.querySelectorAll(".verma-submenu-link").forEach(function (link) {
        link.addEventListener("click", function () {
          if (isMobile()) closeMenu();
        });
      });

      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && menu.classList.contains("active")) {
          closeMenu();
        }
      });

      window.addEventListener("resize", function () {
        if (!isMobile() && menu.classList.contains("active")) {
          closeMenu();
        }
      });

      window.__vermaNav = { open: openMenu, close: closeMenu };
    }
  });

  function highlightActiveLink() {
    var path = window.location.pathname.replace(/\/$/, "") || "/";
    var aboutGroup = { "/about": true, "/contact": true, "/privacy": true };

    document.querySelectorAll(".verma-link").forEach(function (link) {
      link.classList.remove("active");
      try {
        var url = new URL(link.href, window.location.origin);
        if (url.origin !== window.location.origin) return;
        var linkPath = url.pathname.replace(/\/$/, "") || "/";
        if (path === linkPath) {
          link.classList.add("active");
        } else if (
          link.classList.contains("verma-toggle") &&
          linkPath === "/about" &&
          aboutGroup[path]
        ) {
          link.classList.add("active");
        }
      } catch (err) {
        /* ignore bad hrefs */
      }
    });
  }
})();
