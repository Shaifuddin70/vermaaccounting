// Navigation JavaScript for dropdown functionality and smooth interactions

document.addEventListener("DOMContentLoaded", function () {
  // Initialize navigation functionality
  initNavigation();
  initDropdown();
  initActiveLink();
  initMobileMenu();
});

// Initialize navigation
function initNavigation() {
  const navbar = document.querySelector(".verma-nav");
  if (!navbar) return;

  // Add scroll effect to navbar
  window.addEventListener("scroll", function () {
    if (window.scrollY > 50) {
      navbar.classList.add("scrolled");
    } else {
      navbar.classList.remove("scrolled");
    }
  });
}

// Initialize dropdown functionality
function initDropdown() {
  const dropdowns = document.querySelectorAll(".verma-submenu");

  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".verma-toggle");
    const menu = dropdown.querySelector(".verma-submenu-items");

    if (!toggle || !menu) return;

    // Handle click events
    toggle.addEventListener("click", function (e) {
      // Only prevent default if clicking on the arrow or if it's a mobile device
      if (
        e.target.classList.contains("verma-arrow") ||
        window.innerWidth <= 768
      ) {
        e.preventDefault();
        e.stopPropagation();

        // Close other dropdowns
        closeOtherDropdowns(dropdown);

        // Toggle current dropdown
        toggleDropdown(dropdown);
      }
      // If clicking on the link text, allow default behavior (navigation)
    });

    // Handle hover events for desktop
    if (window.innerWidth > 768) {
      dropdown.addEventListener("mouseenter", function () {
        openDropdown(dropdown);
      });

      dropdown.addEventListener("mouseleave", function () {
        closeDropdown(dropdown);
      });
    }
  });

  // Close dropdowns when clicking outside
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".verma-submenu")) {
      closeAllDropdowns();
    }
  });

  // Close dropdowns on escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeAllDropdowns();
    }
  });
}

// Toggle dropdown
function toggleDropdown(dropdown) {
  const menu = dropdown.querySelector(".verma-submenu-items");
  const isOpen = menu.classList.contains("open");

  if (isOpen) {
    closeDropdown(dropdown);
  } else {
    openDropdown(dropdown);
  }
}

// Open dropdown
function openDropdown(dropdown) {
  const menu = dropdown.querySelector(".verma-submenu-items");
  const arrow = dropdown.querySelector(".verma-arrow");

  menu.classList.add("open");
  menu.style.opacity = "1";
  menu.style.visibility = "visible";
  menu.style.transform = "translateY(0)";

  if (arrow) {
    arrow.style.transform = "rotate(180deg)";
  }
}

// Close dropdown
function closeDropdown(dropdown) {
  const menu = dropdown.querySelector(".verma-submenu-items");
  const arrow = dropdown.querySelector(".verma-arrow");

  menu.classList.remove("open");
  menu.style.opacity = "0";
  menu.style.visibility = "hidden";
  menu.style.transform = "translateY(-10px)";

  if (arrow) {
    arrow.style.transform = "rotate(0deg)";
  }
}

// Close other dropdowns
function closeOtherDropdowns(currentDropdown) {
  const allDropdowns = document.querySelectorAll(".verma-submenu");
  allDropdowns.forEach((dropdown) => {
    if (dropdown !== currentDropdown) {
      closeDropdown(dropdown);
    }
  });
}

// Close all dropdowns
function closeAllDropdowns() {
  const allDropdowns = document.querySelectorAll(".verma-submenu");
  allDropdowns.forEach((dropdown) => {
    closeDropdown(dropdown);
  });
}

// Initialize active link highlighting
function initActiveLink() {
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll(".verma-link");

  navLinks.forEach((link) => {
    link.classList.remove("active");

    // Check if current page matches link
    const linkPath = new URL(link.href).pathname;
    if (
      currentPath === linkPath ||
      (currentPath === "/" && linkPath.includes("index.html")) ||
      (currentPath.includes("index.html") && linkPath === "/")
    ) {
      link.classList.add("active");
    }
  });
}

// Initialize mobile menu functionality
function initMobileMenu() {
  const hamburgerCheckbox = document.getElementById("hamburgerCheckbox");
  const hamburgerMenu = document.getElementById("hamburgerMenu");
  const mobileClose = document.getElementById("mobileMenuClose");
  const navLinks = document.querySelector(".verma-menu");

  if (!hamburgerCheckbox || !navLinks) return;

  // Function to open mobile menu
  function openMobileMenu() {
    hamburgerCheckbox.checked = true;
    navLinks.classList.add("active");
    document.body.style.overflow = "hidden";
  }

  // Function to close mobile menu
  function closeMobileMenu() {
    hamburgerCheckbox.checked = false;
    navLinks.classList.remove("active");
    document.body.style.overflow = "";
  }

  // Toggle mobile menu when hamburger is clicked
  if (hamburgerMenu) {
    hamburgerMenu.addEventListener("click", function (e) {
      // The checkbox will be toggled automatically, so we just need to handle the menu state
      setTimeout(() => {
        if (hamburgerCheckbox.checked) {
          openMobileMenu();
        } else {
          closeMobileMenu();
        }
      }, 0);
    });
  }

  // Also listen to checkbox change event
  hamburgerCheckbox.addEventListener("change", function () {
    if (this.checked) {
      openMobileMenu();
    } else {
      closeMobileMenu();
    }
  });

  // Close mobile menu with close button
  if (mobileClose) {
    mobileClose.addEventListener("click", function () {
      closeMobileMenu();
    });
  }

  // Handle mobile dropdown functionality (only on mobile - on desktop, allow link to navigate to /services)
  const dropdownToggle = navLinks.querySelector(".verma-toggle");
  const navDropdown = navLinks.querySelector(".verma-submenu");

  if (dropdownToggle && navDropdown) {
    dropdownToggle.addEventListener("click", function (e) {
      // Only prevent navigation when on mobile - let desktop users click through to /services
      if (window.innerWidth <= 768) {
        e.preventDefault();
        e.stopPropagation();

        // Toggle dropdown
        navDropdown.classList.toggle("active");

        // Rotate arrow
        const arrow = dropdownToggle.querySelector(".verma-arrow");
        if (arrow) {
          arrow.style.transform = navDropdown.classList.contains("active")
            ? "rotate(180deg)"
            : "rotate(0deg)";
        }
      }
    });
  }

  // Close menu when clicking on a regular link (not dropdown toggle)
  const navLinkItems = navLinks.querySelectorAll(
    ".verma-link:not(.verma-toggle)"
  );
  navLinkItems.forEach((link) => {
    link.addEventListener("click", function () {
      closeMobileMenu();
    });
  });

  // Close menu when clicking on dropdown items
  const dropdownItems = navLinks.querySelectorAll(".verma-submenu-link");
  dropdownItems.forEach((item) => {
    item.addEventListener("click", function () {
      closeMobileMenu();
    });
  });

  // Close menu when clicking outside
  document.addEventListener("click", function (event) {
    if (
      !navLinks.contains(event.target) &&
      !hamburgerMenu.contains(event.target)
    ) {
      closeMobileMenu();
    }
  });

  // Handle window resize
  window.addEventListener("resize", function () {
    if (window.innerWidth > 768) {
      closeMobileMenu();
    }
  });
}

// Smooth scrolling for anchor links
function initSmoothScrolling() {
  const anchorLinks = document.querySelectorAll('a[href^="#"]');

  anchorLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      const targetId = this.getAttribute("href").substring(1);
      const targetElement = document.getElementById(targetId);

      if (targetElement) {
        e.preventDefault();
        targetElement.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    });
  });
}

// Add loading animation
function addLoadingAnimation() {
  const navLinks = document.querySelectorAll(".verma-link");

  navLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      // Add loading state
      this.style.opacity = "0.7";
      this.style.pointerEvents = "none";

      // Remove loading state after navigation
      setTimeout(() => {
        this.style.opacity = "1";
        this.style.pointerEvents = "auto";
      }, 1000);
    });
  });
}

// Initialize all functionality
function initAll() {
  initSmoothScrolling();
  addLoadingAnimation();
}

// Call initialization
initAll();
