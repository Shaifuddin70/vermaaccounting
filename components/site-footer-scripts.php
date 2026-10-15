<?php
// Shared footer scripts for hand-written pages. CMS pages load the same list from Site Configuration.
?>
 <!-- Bootstrap JavaScript CDN -->
 <script
   src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
   integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
   crossorigin="anonymous"></script>

 <!-- JavaScript -->
 <script src="<?= e(asset_versioned('components/js/site-header.js')) ?>"></script>
 <script src="<?= e(asset_versioned('components/js/engagement-modal.js')) ?>"></script>
 <script src="https://elfsightcdn.com/platform.js" async></script>
