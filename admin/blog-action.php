<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/blogs');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: /admin/blogs');
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$repo = new BlogRepository();

try {
    if ($action === 'sync') {
        $result = blog_sync_from_uplift();
        ActivityLog::record('blog.synced', 'blog', null, $result);
        $_SESSION['flash_success'] = sprintf(
            'Synced %d post%s from Uplift AI (%d new, %d updated).',
            $result['fetched'],
            $result['fetched'] === 1 ? '' : 's',
            $result['created'],
            $result['updated']
        );
        header('Location: /admin/blogs');
        exit;
    }

    $blog = $id > 0 ? $repo->find($id) : null;
    if ($blog === null) {
        $_SESSION['flash_error'] = 'Blog post not found.';
        header('Location: /admin/blogs');
        exit;
    }

    if ($action === 'publish') {
        $repo->setLocalStatus($id, 'published');
        ActivityLog::record('blog.published', 'blog', $id, ['slug' => $blog['slug']]);
        $_SESSION['flash_success'] = 'Post published on the website.';
        header('Location: /admin/blog?id=' . $id);
        exit;
    }

    if ($action === 'unpublish') {
        $repo->setLocalStatus($id, 'draft');
        ActivityLog::record('blog.unpublished', 'blog', $id, ['slug' => $blog['slug']]);
        $_SESSION['flash_success'] = 'Post unpublished (draft).';
        header('Location: /admin/blog?id=' . $id);
        exit;
    }

    if ($action === 'delete') {
        $repo->delete($id);
        ActivityLog::record('blog.deleted', 'blog', $id, ['slug' => $blog['slug'], 'uplift_id' => $blog['uplift_id']]);
        $_SESSION['flash_success'] = 'Post removed from this site (Uplift copy is unchanged).';
        header('Location: /admin/blogs');
        exit;
    }

    $_SESSION['flash_error'] = 'Unknown action.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: /admin/blogs');
exit;
