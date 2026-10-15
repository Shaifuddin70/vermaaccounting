<?php

declare(strict_types=1);

/**
 * /sitemap.xml (index) and /sitemap-{part}.xml, generated from published, indexable, self-canonical URLs.
 */

require_once __DIR__ . '/lib/bootstrap.php';

$part = strtolower(trim((string) ($_GET['part'] ?? ''), '-'));
if ($part === '' && preg_match('#/sitemap-([a-z]+)\.xml$#', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), $m)) {
    $part = $m[1];
}

if ($part !== '' && !isset(CmsSitemap::PARTS[$part])) {
    CmsRouter::emit(CmsRenderer::notFound());
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=900');
echo $part === '' ? CmsSitemap::indexXml() : CmsSitemap::partXml($part);
