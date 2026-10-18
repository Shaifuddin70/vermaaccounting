(function () {
  var slot = document.getElementById('admin-page-topbar-slot');
  var page = document.querySelector('.admin-page');
  if (slot && page) {
    var header = page.querySelector(':scope > .admin-header, :scope > .admin-page-intro > .admin-header');
    if (!header) {
      header = page.querySelector('.admin-header');
    }
    var fallback = slot.querySelector('[data-topbar-fallback]');

    if (header && header.querySelector('h1')) {
      if (fallback) {
        fallback.remove();
      }

      // Move title + shortcut buttons into the sticky top bar for every admin page.
      header.classList.add('admin-header--topbar');
      header.classList.remove('admin-header--page-actions');
      slot.appendChild(header);
    }
  }

  var clock = document.getElementById('admin-topbar-clock');
  if (!clock) return;

  var valueEl = clock.querySelector('.admin-topbar-clock-value');
  var tz = clock.getAttribute('data-timezone') || 'America/Toronto';
  if (!valueEl || typeof Intl === 'undefined' || !Intl.DateTimeFormat) return;

  var weekdayFmt = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    weekday: 'short'
  });
  var dateFmt = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  var timeFmt = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true
  });

  function tick() {
    var now = new Date();
    var weekday = weekdayFmt.format(now);
    var date = dateFmt.format(now);
    var time = timeFmt.format(now).replace(/\u202f/g, ' ');
    valueEl.textContent = weekday + ', ' + date + ' · ' + time;
    valueEl.setAttribute('datetime', now.toISOString());
  }

  tick();
  setInterval(tick, 1000);
})();
