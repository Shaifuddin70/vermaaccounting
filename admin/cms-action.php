<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.edit', 'cms.publish');

$action = (string) ($_POST['action'] ?? '');

if ($action === 'autosave') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Auth::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        json_response(['ok' => false, 'error' => 'Session expired. Reload the page.'], 403);
    }
    $repo = new CmsContentRepository();
    $id = (int) ($_POST['id'] ?? 0);
    $content = $id > 0 ? $repo->find($id) : null;
    if ($content === null || !Auth::can('cms.edit')) {
        json_response(['ok' => false, 'error' => 'Save the page once before autosave can run.'], 400);
    }
    $form = $_POST;
    unset($form['csrf_token'], $form['action'], $form['workflow_action']);
    $repo->addRevision($id, ['form' => $form], 'Autosave', true);
    json_response(['ok' => true, 'saved_at' => cms_admin_date(now_iso())]);
}

cms_admin_require_post();
$id = (int) ($_POST['id'] ?? 0);
$return = (string) ($_POST['return'] ?? '');
if ($return === '' || !str_starts_with($return, '/admin/')) {
    $return = $id > 0 ? '/admin/cms-edit?id=' . $id : '/admin/cms-content';
}
$service = new CmsContentService();
$repo = new CmsContentRepository();
$caps = cms_admin_caps();

switch ($action) {
    case 'bulk':
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');
        $result = $service->bulk((array) ($_POST['ids'] ?? []), $bulkAction, $caps);
        $verb = ['publish' => 'published', 'draft' => 'moved to draft', 'unpublish' => 'unpublished', 'archive' => 'archived', 'delete' => 'deleted'][$bulkAction] ?? '';
        $message = null;
        if ($verb !== '' && ($result['done'] > 0 || $result['skipped'] > 0)) {
            $message = $result['done'] . ' ' . ($result['done'] === 1 ? 'item' : 'items') . ' ' . $verb . '.'
                . ($result['skipped'] > 0 ? ' ' . $result['skipped'] . ' already ' . ($bulkAction === 'unpublish' ? 'offline' : 'in that state') . ', skipped.' : '');
        }
        cms_admin_back($return, $message, $result['errors'] ? implode(' ', array_slice($result['errors'], 0, 5)) . (count($result['errors']) > 5 ? ' And ' . (count($result['errors']) - 5) . ' more.' : '') : null);

    case 'publish':
    case 'unpublish':
    case 'archive':
    case 'restore':
        $result = $service->setStatus($id, $action, $caps);
        if ($result['errors']) {
            cms_admin_back($return, null, implode(' ', $result['errors']));
        }
        cms_admin_back($return, ['publish' => 'Published.', 'unpublish' => 'Unpublished.', 'archive' => 'Archived.', 'restore' => 'Restored as a draft.'][$action]);

    case 'preview':
        if (!$caps['edit'] || $repo->find($id) === null) {
            cms_admin_back($return, null, 'Could not create a preview link.');
        }
        $service->previewToken($id);
        cms_admin_back('/admin/cms-edit?id=' . $id, 'Preview link created. Share it only with people who should see the draft.');

    case 'preview_revoke':
        if ($caps['edit'] && $repo->find($id)) {
            $service->previewToken($id, true);
        }
        cms_admin_back('/admin/cms-edit?id=' . $id, 'Preview link revoked.');

    case 'delete':
        if (!$caps['publish']) {
            cms_admin_back($return, null, 'You need publishing permission to delete content.');
        }
        $content = $repo->find($id);
        if ($content === null) {
            cms_admin_back('/admin/cms-content', null, 'Content not found.');
        }
        $label = CmsCityDefaults::label($content);
        $service->delete($id);
        cms_admin_back('/admin/cms-content?type=' . rawurlencode((string) $content['type']), 'Deleted “' . $label . '”.');

    case 'restore_revision':
        $result = $service->restoreRevision((int) ($_POST['revision_id'] ?? 0), $caps);
        if ($result['errors']) {
            cms_admin_back($return, null, implode(' ', $result['errors']));
        }
        cms_admin_back('/admin/cms-edit?id=' . (int) $result['id'], 'Revision restored. The URL and status were not changed.');

    case 'set_home':
        if (!$caps['code']) {
            cms_admin_back($return, null, 'Changing the homepage needs site code permission.');
        }
        $content = $repo->find($id);
        if ($content === null || !CmsContentRepository::isLive($content) || $content['type'] !== 'page') {
            cms_admin_back($return, null, 'Only a published page can be the homepage.');
        }
        $errors = CmsSiteConfig::save(['home_content_id' => $id], true, 'Homepage set to ' . $content['path']);
        if ($errors) {
            cms_admin_back($return, null, implode(' ', $errors));
        }
        ActivityLog::record('cms.homepage_set', 'cms_content', $id, ['path' => $content['path']]);
        cms_admin_back($return, 'This page is now the homepage. Its own URL redirects to / automatically.');
}

cms_admin_back($return, null, 'Unknown action.');
