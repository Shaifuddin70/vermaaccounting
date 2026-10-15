(function () {
  // Social FAB
  var fab = document.getElementById('socialFab');
  var toggle = document.getElementById('socialFabToggle');
  var actions = document.getElementById('socialFabActions');
  if (fab && toggle && actions) {
    var openFab = function () {
      fab.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      actions.setAttribute('aria-hidden', 'false');
    };
    var closeFab = function () {
      fab.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      actions.setAttribute('aria-hidden', 'true');
    };
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (fab.classList.contains('open')) {
        closeFab();
      } else {
        openFab();
      }
    });
    document.addEventListener('click', function (e) {
      if (!fab.contains(e.target)) closeFab();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeFab();
    });
    actions.querySelectorAll('.fab-action').forEach(function (a) {
      a.addEventListener('click', closeFab);
    });
  }

  // Scroll up button
  var scrollUpBtn = document.getElementById('scrollUpBtn');
  if (scrollUpBtn) {
    var toggleScrollUp = function () {
      scrollUpBtn.classList.toggle('visible', window.scrollY > 300);
    };
    window.addEventListener('scroll', toggleScrollUp, { passive: true });
    scrollUpBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    toggleScrollUp();
  }

  // Site inquiry form
  var form = document.getElementById('my-form');
  if (form) {
    var status = document.getElementById('my-form-status');
    var button = document.getElementById('my-form-button');
    var fail = 'Oops! There was a problem submitting your form';
    var showStatus = function (cls, text) {
      if (!status) return;
      status.className = cls;
      status.textContent = text;
    };

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      showStatus('', '');
      if (button) button.disabled = true;

      fetch(form.action, {
        method: form.method || 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data || {} };
          }).catch(function () {
            return { ok: response.ok, data: {} };
          });
        })
        .then(function (result) {
          if (result.ok && result.data.ok) {
            showStatus('success', result.data.message || 'Thanks for your submission!');
            form.reset();
            return;
          }
          var message = fail;
          if (result.data.error) {
            message = String(result.data.error);
          } else if (Array.isArray(result.data.errors) && result.data.errors.length) {
            message = result.data.errors
              .map(function (err) {
                return typeof err === 'string' ? err : (err && err.message) || '';
              })
              .filter(Boolean)
              .join(' ');
          }
          showStatus('error', message || fail);
        })
        .catch(function () {
          showStatus('error', fail);
        })
        .finally(function () {
          if (button) button.disabled = false;
        });
    });
  }

  // Calendly loads on first use instead of on every page view.
  if (!window.Calendly) {
    var calendlyQueue = [];
    var calendlyLoading = false;
    var shim = {
      initPopupWidget: function (options) {
        calendlyQueue.push(options);
        if (calendlyLoading) return;
        calendlyLoading = true;
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://assets.calendly.com/assets/external/widget.css';
        document.head.appendChild(css);
        var script = document.createElement('script');
        script.src = 'https://assets.calendly.com/assets/external/widget.js';
        script.async = true;
        var fallback = function () {
          calendlyQueue.forEach(function (o) {
            if (o && o.url) window.open(o.url, '_blank', 'noopener');
          });
          calendlyQueue = [];
        };
        script.onload = function () {
          var real = window.Calendly;
          if (!real || real === shim || typeof real.initPopupWidget !== 'function') {
            fallback();
            return;
          }
          calendlyQueue.forEach(function (o) { real.initPopupWidget(o); });
          calendlyQueue = [];
        };
        script.onerror = fallback;
        window.Calendly = undefined;
        document.head.appendChild(script);
      },
    };
    window.Calendly = shim;
  }
})();

function toggleFAQ(button) {
  var faqItem = button.parentElement;
  var answer = faqItem.querySelector('.faq-answer');
  var icon = button.querySelector('i');

  document.querySelectorAll('.faq-item').forEach(function (item) {
    if (item !== faqItem) {
      item.classList.remove('active');
      item.querySelector('.faq-answer').style.maxHeight = '0';
      item.querySelector('.faq-question i').style.transform = 'rotate(0deg)';
      item.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
    }
  });

  faqItem.classList.toggle('active');
  var open = faqItem.classList.contains('active');
  button.setAttribute('aria-expanded', open ? 'true' : 'false');
  answer.style.maxHeight = open ? answer.scrollHeight + 'px' : '0';
  icon.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
}

// Partner logo slider
(function () {
  var track = document.getElementById('partnerTrack');
  if (!track) return;
  var dots = document.querySelectorAll('.partner-dots .dot');
  var total = dots.length || 6;
  var current = 1;
  var manual = false;
  var manualTimer = null;

  function show(n) {
    if (!dots[n - 1]) return;
    dots.forEach(function (dot) {
      dot.classList.remove('active');
      dot.removeAttribute('aria-current');
    });
    dots[n - 1].classList.add('active');
    dots[n - 1].setAttribute('aria-current', 'true');
    // Each slide moves by one partner item (200px + 30px gap).
    track.style.transform = 'translateX(' + -(n - 1) * 230 + 'px)';
    track.style.animation = manual ? 'none' : 'slideRightToLeft 20s linear infinite';
  }

  dots.forEach(function (dot, i) {
    dot.addEventListener('click', function () {
      manual = true;
      current = i + 1;
      show(current);
      clearTimeout(manualTimer);
      manualTimer = setTimeout(function () { manual = false; }, 3000);
    });
  });

  setInterval(function () {
    if (manual) return;
    current = current >= total ? 1 : current + 1;
    show(current);
  }, 3000);
})();

// Hero background slider
(function () {
  var slides = document.querySelectorAll('.hero-slide');
  if (!slides.length) return;
  var index = 0;
  setInterval(function () {
    slides[index].classList.remove('active');
    index = (index + 1) % slides.length;
    slides[index].classList.add('active');
  }, 5000);
})();

document.addEventListener('DOMContentLoaded', function () {
  // Scroll animations
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

  document.querySelectorAll('.scroll-animate, .scroll-animate-left, .scroll-animate-right, .scroll-animate-scale').forEach(function (el) {
    observer.observe(el);
  });

  document.querySelectorAll('.services').forEach(function (card, index) {
    if (index > 0) card.classList.add('scroll-animate-delay-' + Math.min(index, 5));
  });
  document.querySelectorAll('.faq-item').forEach(function (item, index) {
    if (index > 0) item.classList.add('scroll-animate-delay-' + Math.min(index, 5));
  });

  // Animated counters for industry stats
  var statsObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animateCounter(entry.target, parseInt(entry.target.getAttribute('data-target'), 10));
        statsObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('.stat-number').forEach(function (stat) {
    statsObserver.observe(stat);
  });

  setTimeout(initTypingAnimation, 1000);
});

function animateCounter(element, target) {
  var current = 0;
  var increment = target / 100;
  var timer = setInterval(function () {
    current += increment;
    if (current >= target) {
      current = target;
      clearInterval(timer);
    }
    element.textContent = target >= 1000000 ? formatNumber(Math.floor(current)) : Math.floor(current);
  }, 20);
}

function formatNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
  return num.toString();
}

function initTypingAnimation() {
  var el = document.getElementById('typing-text');
  if (!el) return;
  var texts = ['Audits?', 'Tax Planning?', 'Bookkeeping?', 'Payroll?', 'Business Registration?', 'Financial Consulting?'];
  var textIndex = 0;
  var charIndex = 0;
  var deleting = false;

  function tick() {
    var text = texts[textIndex];
    var delay;
    if (deleting) {
      charIndex--;
      delay = 50;
    } else {
      charIndex++;
      delay = 100;
    }
    el.textContent = text.substring(0, charIndex);
    if (!deleting && charIndex === text.length) {
      delay = 2000;
      deleting = true;
    } else if (deleting && charIndex === 0) {
      deleting = false;
      textIndex = (textIndex + 1) % texts.length;
      delay = 500;
    }
    setTimeout(tick, delay);
  }
  tick();
}
