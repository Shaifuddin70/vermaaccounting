(function () {
  function initFancybox() {
    if (typeof Fancybox === 'undefined') {
      return;
    }
    Fancybox.bind('[data-fancybox]', {
      animated: true,
      hideScrollbar: true,
      dragToClose: true,
      placeFocusBack: true,
      Carousel: {
        infinite: false,
      },
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFancybox);
  } else {
    initFancybox();
  }
})();
