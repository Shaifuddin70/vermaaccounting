/**
 * Public site header: scroll state, desktop mega menus, mobile drawer.
 * Mobile breakpoint must match css/header.css (max-width: 991px).
 */
(function () {
  "use strict";

  var header = document.getElementById("siteHeader");
  if (!header) return;

  var mobileMq = window.matchMedia("(max-width: 991px)");
  var hoverMq = window.matchMedia("(hover: hover) and (pointer: fine)");

  function onScroll() {
    header.classList.toggle("is-scrolled", window.scrollY > 12);
  }
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  markActiveLinks();

  /* ---------- Desktop mega menus ---------- */

  var items = Array.prototype.slice.call(header.querySelectorAll(".sh-item"));
  var closeTimer = null;

  function setOpen(item, open) {
    item.classList.toggle("is-open", open);
    var trigger = item.querySelector(".sh-trigger");
    if (trigger) trigger.setAttribute("aria-expanded", open ? "true" : "false");
  }

  function closeAll(except) {
    items.forEach(function (item) {
      if (item !== except) setOpen(item, false);
    });
  }

  items.forEach(function (item) {
    var trigger = item.querySelector(".sh-trigger");
    if (!trigger) return;

    trigger.addEventListener("click", function () {
      var isOpen = item.classList.contains("is-open");
      closeAll(item);
      setOpen(item, hoverMq.matches ? true : !isOpen);
    });

    trigger.addEventListener("keydown", function (e) {
      if (e.key === "ArrowDown") {
        e.preventDefault();
        closeAll(item);
        setOpen(item, true);
        var first = item.querySelector(".sh-mega a");
        if (first) first.focus();
      }
    });

    item.addEventListener("mouseenter", function () {
      if (!hoverMq.matches) return;
      window.clearTimeout(closeTimer);
      closeAll(item);
      setOpen(item, true);
    });

    item.addEventListener("mouseleave", function () {
      if (!hoverMq.matches) return;
      closeTimer = window.setTimeout(function () {
        setOpen(item, false);
      }, 160);
    });

    item.addEventListener("focusout", function (e) {
      if (!item.contains(e.relatedTarget)) setOpen(item, false);
    });
  });

  document.addEventListener("click", function (e) {
    if (!header.contains(e.target)) closeAll();
  });

  /* ---------- Mobile drawer ---------- */

  var drawer = document.getElementById("shDrawer");
  var overlay = document.getElementById("shOverlay");
  var burger = header.querySelector(".sh-burger");

  function isDrawerOpen() {
    return !!drawer && drawer.classList.contains("is-open");
  }

  function setDrawer(open) {
    if (!drawer || !burger) return;
    var wasOpen = isDrawerOpen();
    drawer.classList.toggle("is-open", open);
    if (overlay) overlay.classList.toggle("is-open", open);
    drawer.inert = !open;
    drawer.setAttribute("aria-hidden", open ? "false" : "true");
    burger.setAttribute("aria-expanded", open ? "true" : "false");
    burger.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    document.body.classList.toggle("sh-menu-open", open);

    if (open) {
      var closeBtn = drawer.querySelector(".sh-close");
      if (closeBtn) closeBtn.focus();
    } else if (wasOpen) {
      burger.focus();
    }
  }

  if (burger) {
    burger.addEventListener("click", function () {
      setDrawer(!isDrawerOpen());
    });
  }

  document.querySelectorAll("[data-sh-close]").forEach(function (el) {
    el.addEventListener("click", function () {
      setDrawer(false);
    });
  });

  if (drawer) {
    var toggles = Array.prototype.slice.call(drawer.querySelectorAll(".sh-dtoggle"));

    toggles.forEach(function (toggle) {
      toggle.addEventListener("click", function () {
        var willOpen = toggle.getAttribute("aria-expanded") !== "true";
        toggles.forEach(function (other) {
          var sub = document.getElementById(other.getAttribute("aria-controls"));
          var open = other === toggle && willOpen;
          other.setAttribute("aria-expanded", open ? "true" : "false");
          if (sub) sub.hidden = !open;
        });
      });
    });

    drawer.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        setDrawer(false);
      });
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    if (isDrawerOpen()) {
      setDrawer(false);
      return;
    }
    var open = items.filter(function (item) {
      return item.classList.contains("is-open");
    })[0];
    if (open) {
      setOpen(open, false);
      var trigger = open.querySelector(".sh-trigger");
      if (trigger) trigger.focus();
    }
  });

  function onBreakpointChange() {
    if (!mobileMq.matches && isDrawerOpen()) setDrawer(false);
    if (mobileMq.matches) closeAll();
  }
  if (mobileMq.addEventListener) {
    mobileMq.addEventListener("change", onBreakpointChange);
  } else if (mobileMq.addListener) {
    mobileMq.addListener(onBreakpointChange);
  }

  /* ---------- Active page ---------- */

  function normalizePath(pathname) {
    return pathname.replace(/\.php$/, "").replace(/\/$/, "") || "/";
  }

  function markActiveLinks() {
    var current = normalizePath(window.location.pathname);
    var selector = ".sh-link[href], .sh-mega-link, .sh-dlink[href], .sh-dsub-link";

    document.querySelectorAll(selector).forEach(function (link) {
      var url;
      try {
        url = new URL(link.getAttribute("href"), window.location.origin);
      } catch (err) {
        return;
      }
      if (url.origin !== window.location.origin) return;
      if (normalizePath(url.pathname) !== current) return;

      link.classList.add("is-active");
      link.setAttribute("aria-current", "page");

      var item = link.closest(".sh-item");
      if (item) {
        var trigger = item.querySelector(".sh-trigger");
        if (trigger) trigger.classList.add("is-active");
      }
      var group = link.closest(".sh-dgroup");
      if (group) {
        var toggle = group.querySelector(".sh-dtoggle");
        if (toggle) toggle.classList.add("is-active");
      }
    });
  }
})();
