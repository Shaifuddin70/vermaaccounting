<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$slug = trim((string) ($_POST['form_slug'] ?? ''));
$fieldId = trim((string) ($_POST['field_id'] ?? ''));
$session = normalize_upload_session_id(trim((string) ($_POST['upload_session'] ?? '')));
$token = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['token'] ?? '')) ?? '';

if ($slug === '' || $fieldId === '' || $session === '' || $token === '') {
    json_response(['error' => 'Missing parameters.'], 400);
}

$repo = new FormRepository();
$form = $repo->findBySlug($slug, true);
if (!$form) {
    json_response(['error' => 'Form not found.'], 404);
}

$manifest = staging_read_manifest($session);
$meta = $manifest['tokens'][$token] ?? null;
if (
    !is_array($meta)
    || ($meta['field_id'] ?? '') !== $fieldId
    || (int) ($meta['form_id'] ?? 0) !== (int) $form['id']
) {
    json_response(['error' => 'Upload not found.'], 404);
}

staging_remove_token($session, $token);
json_response(['ok' => true]);
