    </div>
  </main>
  <div
    id="admin-toast-root"
    class="admin-toast-root"
    aria-live="polite"
    aria-atomic="true"
    <?php if (!empty($adminToastMessages)): ?>
      data-toasts="<?= e(json_encode($adminToastMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)) ?>"
    <?php endif; ?>
  ></div>
  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.36/dist/fancybox/fancybox.umd.js" defer></script>
  <script src="/admin/js/admin-fancybox.js?v=1" defer></script>
  <script src="/admin/js/admin-topbar.js?v=2" defer></script>
  <script src="/admin/js/admin-nav.js?v=1" defer></script>
  <script src="/admin/js/admin-ui.js?v=2" defer></script>
</body>
</html>
