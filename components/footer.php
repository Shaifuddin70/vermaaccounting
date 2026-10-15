<?php
if (!function_exists('asset')) {
    require_once __DIR__ . '/../lib/helpers.php';
}
?>
<?php include __DIR__ . '/site-footer.php'; ?>
<?php include __DIR__ . '/site-footer-scripts.php'; ?>
<?= class_exists('CmsSiteConfig', false) ? CmsSiteConfig::bodyEndExtras() : '' ?>
<?php app_end_output_filter(); ?>
 </body>

 </html>