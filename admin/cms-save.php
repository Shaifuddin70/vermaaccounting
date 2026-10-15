<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.edit');
cms_admin_require_post();

$input = $_POST;
$id = (int) ($input['id'] ?? 0);
$caps = cms_admin_caps();
$repo = new CmsContentRepository();
$oldPath = $id > 0 ? (string) ($repo->find($id)['path'] ?? '') : '';

try {
    $result = (new CmsContentService())->save($input, $caps);
} catch (Throwable $e) {
    error_log('CMS save failed: ' . $e->getMessage());
    $result = ['id' => $id, 'errors' => [app_safe_error_message($e, 'The page could not be saved. Please try again.')], 'notices' => []];
}

$type = (string) ($input['type'] ?? 'page');
if ($result['errors'] !== []) {
    unset($input['csrf_token']);
    $_SESSION['cms_form_old'] = $input;
    $_SESSION['flash_error'] = implode(' ', $result['errors']);
    $back = $id > 0 ? '/admin/cms-edit?id=' . $id : '/admin/cms-edit?type=' . rawurlencode($type);
    header('Location: ' . $back, true, 303);
    exit;
}

$action = (string) ($input['workflow_action'] ?? 'save');
$messages = [
    'save' => 'Saved.',
    'review' => 'Submitted for review.',
    'publish' => 'Published.',
    'schedule' => 'Scheduled.',
    'unpublish' => 'Unpublished.',
    'archive' => 'Archived.',
];
$content = $repo->find((int) $result['id']);
$message = $messages[$action] ?? 'Saved.';
if ($content && $content['status'] === 'scheduled') {
    $message = 'Scheduled for ' . cms_admin_date($content['publish_at']) . '.';
}
if ($content && $content['type'] === 'page' && isset($input['has_subpages'])) {
    $folderError = (new CmsFolderRepository())->syncLanding($content, (string) $input['has_subpages'] === '1');
    if ($folderError !== null) {
        $_SESSION['flash_error'] = 'The page was saved, but its sub-pages setting was not changed: ' . $folderError;
    }
}
if ($content && $caps['publish'] && (isset($input['nav_placement']) || ($oldPath !== '' && $oldPath !== $content['path']))) {
    $placement = isset($input['nav_placement']) ? (string) $input['nav_placement'] : CmsNavigation::placement($oldPath);
    $navErrors = CmsNavigation::place((string) $content['path'], (string) $content['title'], $placement, $oldPath !== '' ? $oldPath : null);
    if ($navErrors !== []) {
        $_SESSION['flash_error'] = 'The page was saved, but the header menu was not updated: ' . implode(' ', $navErrors);
    }
}
if ($result['notices'] !== []) {
    $message .= ' ' . implode(' ', $result['notices']);
}
cms_admin_back('/admin/cms-edit?id=' . (int) $result['id'], $message);
