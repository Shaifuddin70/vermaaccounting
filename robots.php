<?php

declare(strict_types=1);

/** /robots.txt, edited in Website → Sitemap & robots. */

require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=900');
echo CmsSitemap::robotsTxt();
