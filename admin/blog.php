<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$target = '/admin/cms-content?type=article';
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $blog = (new BlogRepository())->find($id);
    $content = $blog ? (new CmsContentRepository())->findByPath('/blog/' . CmsPath::slugify((string) $blog['slug'])) : null;
    if ($content) {
        $target = '/admin/cms-edit?id=' . (int) $content['id'];
    }
}
header('Location: ' . $target, true, 301);
exit;
