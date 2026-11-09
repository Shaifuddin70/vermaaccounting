<?php
declare(strict_types=1);

/**
 * Remove unauthorized/broken Cloudinary (jse5fsui) image URLs from CMS articles.
 * Those assets return HTTP 401 and show as 404 images in crawls.
 *
 * Usage:
 *   php scripts/scrub-broken-blog-images.php           # dry-run
 *   php scripts/scrub-broken-blog-images.php --apply   # write changes
 *   VERMA_ENV=production php scripts/scrub-broken-blog-images.php --apply
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$apply = in_array('--apply', $argv ?? [], true);
$needle = 'res.cloudinary.com/jse5fsui/';

$pdo = Database::instance()->pdo();
$stmt = $pdo->query("
    SELECT id, path, type, body_html, featured_image_url, featured_alt, updated_at
    FROM cms_contents
    WHERE body_html LIKE '%{$needle}%'
       OR featured_image_url LIKE '%{$needle}%'
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo ($apply ? "APPLY" : "DRY-RUN") . " — rows with broken Cloudinary host: " . count($rows) . PHP_EOL;

$updated = 0;
foreach ($rows as $row) {
    $id = (int) $row['id'];
    $body = (string) ($row['body_html'] ?? '');
    $feat = trim((string) ($row['featured_image_url'] ?? ''));
    $newBody = $body;
    $newFeat = $feat;
    $changes = [];

    // Drop <img> tags whose src points at the broken Cloudinary cloud.
    $newBody = preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function (array $m) use ($needle): string {
            $tag = $m[0];
            if (stripos($tag, $needle) !== false) {
                return '';
            }
            return $tag;
        },
        $newBody
    ) ?? $newBody;

    // Also strip bare URLs / markdown leftovers for that host.
    $newBody = preg_replace(
        '#https?://res\.cloudinary\.com/jse5fsui/image/upload/[^\s"\'<>]+#i',
        '',
        $newBody
    ) ?? $newBody;

    $newBody = preg_replace("/\n{3,}/", "\n\n", $newBody) ?? $newBody;

    if ($newBody !== $body) {
        $changes[] = 'body_html';
    }

    if ($feat !== '' && stripos($feat, $needle) !== false) {
        $newFeat = '';
        $changes[] = 'featured_image_url';
    }

    if ($changes === []) {
        continue;
    }

    echo "#{$id} {$row['path']} — " . implode(', ', $changes) . PHP_EOL;
    if (!$apply) {
        continue;
    }

    $upd = $pdo->prepare('UPDATE cms_contents SET body_html = ?, featured_image_url = ?, updated_at = ? WHERE id = ?');
    $upd->execute([$newBody, $newFeat, now_iso(), $id]);
    $updated++;
}

if ($apply) {
    if (class_exists('CmsCache')) {
        $flushed = CmsCache::flush();
        echo "Flushed CMS cache ({$flushed} entries)." . PHP_EOL;
    }
    echo "Updated {$updated} content row(s)." . PHP_EOL;
} else {
    echo "Re-run with --apply to write changes." . PHP_EOL;
}
