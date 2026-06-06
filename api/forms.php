<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['error' => 'Invalid JSON'], 400);
}

if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
    json_response(['error' => 'Invalid session. Refresh and try again.'], 403);
}

if (!Auth::check()) {
    json_response(['error' => 'Unauthorized'], 401);
}

$repo = new FormRepository();
$id = isset($input['id']) ? (int) $input['id'] : 0;
$title = trim($input['title'] ?? 'Untitled form');
$slug = slugify($input['slug'] ?? $title);
$description = trim($input['description'] ?? '');
$status = in_array($input['status'] ?? 'draft', ['draft', 'published'], true) ? $input['status'] : 'draft';
$schema = normalize_form_schema(is_array($input['schema'] ?? null) ? $input['schema'] : []);
$isSiteCta = !empty($input['is_site_cta']);
$ctaLabel = trim((string) ($input['cta_label'] ?? ''));

if ($id) {
    $existing = $repo->find($id);
    if (!$existing) {
        json_response(['error' => 'Form not found'], 404);
    }
    $slug = $repo->uniqueSlug($slug, $id);
    $ok = $repo->update($id, [
        'slug' => $slug,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'schema' => $schema,
        'cta_label' => $ctaLabel,
        'is_site_cta' => $isSiteCta && $status === 'published',
    ]);
    if (!$ok) {
        json_response(['error' => 'Update failed'], 500);
    }
    json_response([
        'ok' => true,
        'id' => $id,
        'slug' => $slug,
        'taxYear' => $schema['settings']['taxYear'] ?? null,
    ]);
}

$slug = $repo->uniqueSlug($slug);
$newId = $repo->create([
    'slug' => $slug,
    'title' => $title,
    'description' => $description,
    'status' => $status,
    'schema' => $schema,
]);
if ($isSiteCta && $status === 'published') {
    $repo->update($newId, [
        'cta_label' => $ctaLabel,
        'is_site_cta' => true,
        'status' => $status,
    ]);
} elseif ($ctaLabel !== '') {
    $repo->update($newId, ['cta_label' => $ctaLabel, 'status' => $status]);
}

json_response([
    'ok' => true,
    'id' => $newId,
    'slug' => $slug,
    'taxYear' => $schema['settings']['taxYear'] ?? null,
]);
