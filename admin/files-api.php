<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

function files_api_item(array $file): array
{
    $fileId = (int) $file['id'];
    $mime = (string) ($file['mime'] ?? '');
    return [
        'id' => $fileId,
        'original_name' => (string) ($file['original_name'] ?? ''),
        'mime' => $mime,
        'size' => (int) ($file['size'] ?? 0),
        'size_label' => format_file_size((int) ($file['size'] ?? 0)),
        'created_at' => (string) ($file['created_at'] ?? ''),
        'form_id' => (int) ($file['form_id'] ?? 0),
        'form_title' => (string) ($file['form_title'] ?? ''),
        'submission_id' => (int) ($file['submission_id'] ?? 0),
        'folder_id' => isset($file['folder_id']) && $file['folder_id'] !== null ? (int) $file['folder_id'] : null,
        'is_image' => is_image_mime($mime),
        'ext' => file_extension_label($file['original_name'] ?? '', $mime),
        'view_url' => '/admin/view-file?file_id=' . $fileId,
        'download_url' => '/admin/download?file_id=' . $fileId,
    ];
}

function files_api_folder_item(array $folder): array
{
    return [
        'id' => (int) $folder['id'],
        'name' => (string) ($folder['name'] ?? ''),
        'parent_id' => isset($folder['parent_id']) && $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null,
        'item_count' => (int) ($folder['item_count'] ?? 0),
    ];
}

function files_api_parse_folder_id(mixed $raw): ?int
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $id = (int) $raw;
    return $id > 0 ? $id : null;
}

$uploadRepo = new UploadRepository();
$folderRepo = new FileFolderRepository();
$formRepo = new FormRepository();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $formId = isset($_GET['form_id']) && $_GET['form_id'] !== '' ? (int) $_GET['form_id'] : null;
    if ($formId !== null && $formId < 1) {
        $formId = null;
    }
    $folderId = files_api_parse_folder_id($_GET['folder_id'] ?? null);
    if ($folderId !== null && !$folderRepo->find($folderId)) {
        json_response(['ok' => false, 'error' => 'Folder not found.'], 404);
    }
    $search = trim((string) ($_GET['q'] ?? ''));
    $sort = (string) ($_GET['sort'] ?? 'date');
    if (!in_array($sort, ['date', 'name', 'size'], true)) {
        $sort = 'date';
    }

    $files = $uploadRepo->listFilesForManager($formId, $search, $sort, $folderId);
    $forms = array_values(array_filter($formRepo->all(), fn (array $form): bool => !is_file_manager_form($form)));

    json_response([
        'ok' => true,
        'folder_id' => $folderId,
        'breadcrumb' => $folderRepo->breadcrumb($folderId),
        'folders' => array_map('files_api_folder_item', $folderRepo->listForApi($folderId)),
        'folder_options' => array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'path' => (string) $row['path'],
        ], $folderRepo->listAllForPicker()),
        'stats' => $uploadRepo->storageStats(),
        'forms' => array_map(static fn (array $form): array => [
            'id' => (int) $form['id'],
            'title' => (string) $form['title'],
        ], $forms),
        'files' => array_map('files_api_item', $files),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!Auth::verifyCsrf($csrf)) {
    json_response(['ok' => false, 'error' => 'Invalid request token.'], 403);
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'create_folder') {
    $parentId = files_api_parse_folder_id($_POST['parent_id'] ?? null);
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Folder name is required.'], 422);
    }

    try {
        $folderId = $folderRepo->create($parentId, $name);
    } catch (RuntimeException $e) {
        json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
    }

    $folder = $folderRepo->find($folderId);
    ActivityLog::record('folder.created', 'folder', $folderId, [
        'name' => $name,
        'parent_id' => $parentId,
    ]);

    json_response([
        'ok' => true,
        'folder' => files_api_folder_item([
            'id' => $folderId,
            'name' => (string) ($folder['name'] ?? $name),
            'parent_id' => $parentId,
            'item_count' => 0,
        ]),
    ]);
}

if ($action === 'rename_folder') {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($folderId < 1 || $name === '') {
        json_response(['ok' => false, 'error' => 'Folder and name are required.'], 422);
    }

    try {
        $folder = $folderRepo->rename($folderId, $name);
    } catch (RuntimeException $e) {
        json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
    }
    if (!$folder) {
        json_response(['ok' => false, 'error' => 'Folder not found.'], 404);
    }

    ActivityLog::record('folder.renamed', 'folder', $folderId, ['name' => $folder['name'] ?? '']);
    json_response([
        'ok' => true,
        'folder' => files_api_folder_item([
            'id' => $folderId,
            'name' => (string) ($folder['name'] ?? ''),
            'parent_id' => $folder['parent_id'] ?? null,
            'item_count' => $folderRepo->itemCount($folderId),
        ]),
    ]);
}

if ($action === 'delete_folder') {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    if ($folderId < 1) {
        json_response(['ok' => false, 'error' => 'Folder is required.'], 422);
    }

    $folder = $folderRepo->find($folderId);
    if (!$folder) {
        json_response(['ok' => false, 'error' => 'Folder not found.'], 404);
    }

    try {
        $folderRepo->delete($folderId);
    } catch (RuntimeException $e) {
        json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
    }

    ActivityLog::record('folder.deleted', 'folder', $folderId, [
        'name' => (string) ($folder['name'] ?? ''),
    ]);
    json_response(['ok' => true]);
}

if ($action === 'move_files') {
    $folderId = files_api_parse_folder_id($_POST['folder_id'] ?? null);
    $rawIds = $_POST['file_ids'] ?? [];
    if (is_string($rawIds)) {
        $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
    }
    if (!is_array($rawIds)) {
        $rawIds = [];
    }

    try {
        $moved = $uploadRepo->moveFilesToFolder($rawIds, $folderId);
    } catch (RuntimeException $e) {
        json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
    }

    ActivityLog::record('file.moved', 'folder', $folderId, ['count' => $moved]);
    json_response(['ok' => true, 'moved' => $moved]);
}

if ($action === 'move_folder') {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $parentId = files_api_parse_folder_id($_POST['parent_id'] ?? null);
    if ($folderId < 1) {
        json_response(['ok' => false, 'error' => 'Folder is required.'], 422);
    }

    try {
        $folder = $folderRepo->move($folderId, $parentId);
    } catch (RuntimeException $e) {
        json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
    }
    if (!$folder) {
        json_response(['ok' => false, 'error' => 'Folder not found.'], 404);
    }

    ActivityLog::record('folder.moved', 'folder', $folderId, ['parent_id' => $parentId]);
    json_response(['ok' => true]);
}

if ($action === 'rename') {
    $fileId = (int) ($_POST['file_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($fileId < 1 || $name === '') {
        json_response(['ok' => false, 'error' => 'File and name are required.'], 422);
    }

    $file = $uploadRepo->renameFile($fileId, $name);
    if (!$file) {
        json_response(['ok' => false, 'error' => 'Could not rename file.'], 404);
    }

    ActivityLog::record('file.renamed', 'file', $fileId, [
        'original_name' => $file['original_name'] ?? '',
    ]);

    json_response(['ok' => true, 'file' => files_api_item($file)]);
}

if ($action === 'delete') {
    $rawIds = $_POST['file_ids'] ?? [];
    if (is_string($rawIds)) {
        $rawIds = array_filter(array_map('trim', explode(',', $rawIds)));
    }
    if (!is_array($rawIds)) {
        $rawIds = [];
    }

    $deleted = $uploadRepo->deleteFiles($rawIds);
    foreach ($deleted as $file) {
        ActivityLog::record('file.deleted', 'file', (int) ($file['id'] ?? 0), [
            'original_name' => $file['original_name'] ?? '',
            'form_id' => (int) ($file['form_id'] ?? 0),
            'submission_id' => (int) ($file['submission_id'] ?? 0),
        ]);
    }

    json_response([
        'ok' => true,
        'deleted' => count($deleted),
        'stats' => $uploadRepo->storageStats(),
    ]);
}

if ($action === 'upload') {
    if (empty($_FILES['files'])) {
        json_response(['ok' => false, 'error' => 'No files uploaded.'], 422);
    }

    $folderId = files_api_parse_folder_id($_POST['folder_id'] ?? null);
    $uploads = $_FILES['files'];
    $uploaded = [];
    $errors = [];

    $count = is_array($uploads['name'] ?? null) ? count($uploads['name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        $fileUpload = [
            'name' => $uploads['name'][$i] ?? '',
            'tmp_name' => $uploads['tmp_name'][$i] ?? '',
            'size' => $uploads['size'][$i] ?? 0,
            'error' => $uploads['error'][$i] ?? UPLOAD_ERR_NO_FILE,
        ];

        try {
            $result = $uploadRepo->uploadManagerFile($fileUpload, $folderId);
            if ($result) {
                $full = $uploadRepo->findWithContext($result['id']);
                if ($full) {
                    $uploaded[] = files_api_item($full);
                    ActivityLog::record('file.uploaded', 'file', $result['id'], [
                        'original_name' => $result['original_name'],
                        'folder_id' => $folderId,
                    ]);
                }
            } else {
                $errors[] = ($fileUpload['name'] ?: 'File') . ' could not be uploaded.';
            }
        } catch (RuntimeException $e) {
            $errors[] = ($fileUpload['name'] ?: 'File') . ': ' . app_safe_error_message($e, 'Upload failed.');
        }
    }

    json_response([
        'ok' => $errors === [],
        'uploaded' => $uploaded,
        'errors' => $errors,
        'stats' => $uploadRepo->storageStats(),
    ], $uploaded === [] && $errors !== [] ? 422 : 200);
}

json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
