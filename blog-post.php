<?php

declare(strict_types=1);

/** Old blog post entry point. Articles are served by the CMS at /blog/{slug}. */

require_once __DIR__ . '/lib/bootstrap.php';

$slug = CmsPath::slugify((string) ($_GET['slug'] ?? ''));
header('Location: ' . ($slug !== '' ? '/blog/' . $slug : '/blog'), true, 301);
exit;
