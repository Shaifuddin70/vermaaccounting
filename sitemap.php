<?php

declare(strict_types=1);

/**
 * Dynamic sitemap including published blog posts.
 * Served at /sitemap.xml via .htaccess rewrite.
 */

require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$base = 'https://vermaaccounting.ca';
$today = gmdate('Y-m-d');

$static = [
    ['loc' => '/', 'changefreq' => 'weekly', 'priority' => '1.0', 'lastmod' => $today],
    ['loc' => '/services', 'changefreq' => 'monthly', 'priority' => '0.9', 'lastmod' => $today],
    ['loc' => '/accounting', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/bookkeeping', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/payroll', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/personal-tax', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/corporate-tax', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/business-registration', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/loan', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/resources', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/blog', 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/about', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
    ['loc' => '/contact', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
    ['loc' => '/submit-documents', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
    ['loc' => '/tax-intake', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => '/privacy', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
];

$blogs = [];
try {
    $blogs = (new BlogRepository())->published(200, 0);
} catch (Throwable $e) {
    $blogs = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($static as $row): ?>
  <url>
    <loc><?= htmlspecialchars($base . $row['loc'], ENT_XML1) ?></loc>
    <lastmod><?= htmlspecialchars($row['lastmod'], ENT_XML1) ?></lastmod>
    <changefreq><?= htmlspecialchars($row['changefreq'], ENT_XML1) ?></changefreq>
    <priority><?= htmlspecialchars($row['priority'], ENT_XML1) ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($blogs as $blog):
    $slug = trim((string) ($blog['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $lastmod = substr((string) ($blog['source_updated_at'] ?? $blog['updated_at'] ?? $blog['publish_date'] ?? $today), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmod)) {
        $lastmod = $today;
    }
?>
  <url>
    <loc><?= htmlspecialchars($base . '/blog/' . rawurlencode($slug), ENT_XML1) ?></loc>
    <lastmod><?= htmlspecialchars($lastmod, ENT_XML1) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endforeach; ?>
</urlset>
