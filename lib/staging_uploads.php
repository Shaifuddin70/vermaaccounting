<?php

declare(strict_types=1);

function staging_upload_session_pattern(): string
{
    return '/^[a-f0-9]{32}$/';
}

function normalize_upload_session_id(string $session): string
{
    $session = strtolower(preg_replace('/[^a-f0-9]/', '', $session) ?? '');
    return preg_match(staging_upload_session_pattern(), $session) ? $session : '';
}

function staging_upload_dir(string $session): string
{
    return UPLOADS_DIR . '/staging/' . $session;
}

function staging_manifest_path(string $session): string
{
    return staging_upload_dir($session) . '/manifest.json';
}

/** @return array{tokens: array<string, array<string, mixed>>} */
function staging_read_manifest(string $session): array
{
    $path = staging_manifest_path($session);
    if (!is_file($path)) {
        return ['tokens' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !is_array($data['tokens'] ?? null)) {
        return ['tokens' => []];
    }
    return $data;
}

/** @param array{tokens: array<string, array<string, mixed>>} $manifest */
function staging_write_manifest(string $session, array $manifest): void
{
    $dir = staging_upload_dir($session);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        staging_manifest_path($session),
        json_encode($manifest, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/** @return array<string, mixed>|null */
function staging_find_field(array $schema, string $fieldId): ?array
{
    foreach ($schema['fields'] as $field) {
        if (($field['id'] ?? '') === $fieldId) {
            return $field;
        }
    }
    return null;
}

function staging_max_files_for_field(array $field): int
{
    $max = (int) ($field['maxFiles'] ?? 1);
    return max(1, min(10, $max));
}

/**
 * Map common extensions to canonical MIME types when finfo is vague.
 *
 * @return array<string, string>
 */
function upload_extension_mime_map(): array
{
    return [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
    ];
}

/**
 * Resolve a reliable MIME type for an uploaded file.
 *
 * @param array{name?: string, tmp_name?: string, type?: string} $upload
 */
function resolve_upload_mime(array $upload): string
{
    $tmp = (string) ($upload['tmp_name'] ?? '');
    $clientType = strtolower(trim((string) ($upload['type'] ?? '')));
    $ext = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $extMap = upload_extension_mime_map();

    $detected = '';
    if ($tmp !== '' && is_file($tmp)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = strtolower((string) ($finfo->file($tmp) ?: ''));
    }

    // Prefer extension when finfo is generic or mismatches known document types.
    $generic = ['', 'application/octet-stream', 'text/plain', 'inode/x-empty'];
    if ($ext !== '' && isset($extMap[$ext])) {
        $canonical = $extMap[$ext];
        if ($detected === '' || in_array($detected, $generic, true)) {
            return $canonical;
        }
        // PDF/ZIP sometimes reported under alternate types
        if ($ext === 'pdf' && (str_contains($detected, 'pdf') || in_array($detected, $generic, true))) {
            return 'application/pdf';
        }
        if ($ext === 'zip' && (str_contains($detected, 'zip') || in_array($detected, $generic, true))) {
            return 'application/zip';
        }
        // If extension is a known document and detection isn't an image, trust extension for docs
        if (in_array($ext, ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'csv'], true)
            && !str_starts_with($detected, 'image/')) {
            return $canonical;
        }
    }

    if ($detected !== '') {
        if ($detected === 'application/x-zip-compressed' || $detected === 'application/x-zip') {
            return 'application/zip';
        }
        if (in_array($detected, ['application/x-pdf', 'application/acrobat', 'application/nappdf'], true)) {
            return 'application/pdf';
        }
        return $detected;
    }

    if ($clientType !== '') {
        return $clientType;
    }

    return $extMap[$ext] ?? 'application/octet-stream';
}

function upload_php_error_message(int $error, string $label): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ' exceeds the maximum upload size.',
        UPLOAD_ERR_PARTIAL => $label . ' was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded for ' . $label . '.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder is missing. Please contact support.',
        UPLOAD_ERR_CANT_WRITE => 'Could not save ' . $label . ' to disk.',
        UPLOAD_ERR_EXTENSION => $label . ' was blocked by a server extension.',
        default => 'Upload failed for ' . $label . '.',
    };
}

/**
 * Whether an image field should stay image-only (based on accept attribute).
 */
function field_accepts_only_images(array $field): bool
{
    if ((string) ($field['type'] ?? '') !== 'image') {
        return false;
    }
    $accept = strtolower(trim((string) ($field['accept'] ?? 'image/*')));
    if ($accept === '' || $accept === 'image/*') {
        return true;
    }
    // Explicit document types in accept → allow mixed uploads on this field
    return !preg_match('/(\.pdf|\.zip|\.doc|\.docx|\.xls|\.xlsx|\.csv|\.txt|application\/pdf|application\/zip)\b/', $accept);
}

/**
 * @param array{error:int,name:string,tmp_name:string,size:int,type:string} $upload
 * @return array{ok: bool, error?: string, mime?: string}
 */
function validate_form_upload_file(array $upload, array $field, int $maxBytes, array $allowedMimes): array
{
    $type = (string) ($field['type'] ?? 'file');
    $label = (string) ($field['label'] ?? 'File');
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_php_error_message($error, $label)];
    }
    if ((int) ($upload['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => $label . ' exceeds max file size (' . format_file_size($maxBytes) . ').'];
    }

    $mime = resolve_upload_mime($upload);

    if (field_accepts_only_images($field) && !str_starts_with($mime, 'image/')) {
        return ['ok' => false, 'error' => $label . ' must be an image (JPG, PNG, GIF, or WebP).'];
    }

    if ($allowedMimes) {
        $normalizedAllowed = array_map('strtolower', $allowedMimes);
        // Treat zip variants as equivalent
        if ($mime === 'application/zip') {
            $normalizedAllowed = array_merge($normalizedAllowed, [
                'application/zip',
                'application/x-zip-compressed',
                'application/x-zip',
            ]);
        }
        if ($mime === 'application/pdf') {
            $normalizedAllowed = array_merge($normalizedAllowed, [
                'application/pdf',
                'application/x-pdf',
            ]);
        }
        if (!in_array(strtolower($mime), $normalizedAllowed, true)) {
            return ['ok' => false, 'error' => $label . ' file type is not allowed. Use images, PDF, ZIP, or Office documents.'];
        }
    }

    return ['ok' => true, 'mime' => $mime];
}

/**
 * @param array{error:int,name:string,tmp_name:string,size:int,type:string} $upload
 * @return array{token: string, original_name: string, size: int, mime: string}
 */
function staging_store_upload(
    array $form,
    array $field,
    string $session,
    array $upload,
    string $mime
): array {
    $token = bin2hex(random_bytes(16));
    $ext = pathinfo($upload['name'], PATHINFO_EXTENSION);
    $stored = $token . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');

    $dir = staging_upload_dir($session);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($upload['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    $manifest = staging_read_manifest($session);
    $manifest['tokens'][$token] = [
        'field_id' => (string) $field['id'],
        'field_name' => (string) $field['name'],
        'form_id' => (int) $form['id'],
        'stored_name' => $stored,
        'original_name' => $upload['name'],
        'mime' => $mime,
        'size' => (int) $upload['size'],
        'created_at' => now_iso(),
    ];
    staging_write_manifest($session, $manifest);

    return [
        'token' => $token,
        'original_name' => (string) $upload['name'],
        'size' => (int) $upload['size'],
        'mime' => $mime,
    ];
}

function staging_count_tokens_for_field(string $session, string $fieldId): int
{
    $manifest = staging_read_manifest($session);
    $count = 0;
    foreach ($manifest['tokens'] as $meta) {
        if (($meta['field_id'] ?? '') === $fieldId) {
            $count++;
        }
    }
    return $count;
}

function staging_remove_token(string $session, string $token): bool
{
    $manifest = staging_read_manifest($session);
    if (!isset($manifest['tokens'][$token])) {
        return false;
    }

    $meta = $manifest['tokens'][$token];
    $path = staging_upload_dir($session) . '/' . ($meta['stored_name'] ?? '');
    if (is_file($path)) {
        unlink($path);
    }
    unset($manifest['tokens'][$token]);
    staging_write_manifest($session, $manifest);
    return true;
}

/**
 * @param list<string> $tokens
 * @return array{0: list<string>, 1: list<array<string, mixed>>} stored names + filesMeta
 */
function staging_claim_tokens(
    string $session,
    array $field,
    array $form,
    array $schema,
    array $tokens,
    array $postData
): array {
    $manifest = staging_read_manifest($session);
    $fieldId = (string) $field['id'];
    $fieldName = (string) $field['name'];
    $storedNames = [];
    $filesMeta = [];

    foreach ($tokens as $token) {
        $token = preg_replace('/[^a-f0-9]/', '', (string) $token) ?? '';
        if ($token === '' || !isset($manifest['tokens'][$token])) {
            continue;
        }
        $meta = $manifest['tokens'][$token];
        if (($meta['field_id'] ?? '') !== $fieldId || (int) ($meta['form_id'] ?? 0) !== (int) $form['id']) {
            continue;
        }

        $src = staging_upload_dir($session) . '/' . ($meta['stored_name'] ?? '');
        if (!is_file($src)) {
            unset($manifest['tokens'][$token]);
            continue;
        }

        $finalDir = UPLOADS_DIR . '/' . $form['id'];
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $basename = basename((string) $meta['stored_name']);
        $dest = $finalDir . '/' . $basename;
        if (!rename($src, $dest)) {
            throw new RuntimeException('Could not finalize upload.');
        }

        unset($manifest['tokens'][$token]);
        $storedPath = $form['id'] . '/' . $basename;
        $storedNames[] = $storedPath;
        $filesMeta[] = [
            'field_id' => $fieldId,
            'stored_name' => $storedPath,
            'original_name' => client_upload_original_name((string) $meta['original_name'], $schema, $postData),
            'mime' => (string) ($meta['mime'] ?? 'application/octet-stream'),
            'size' => (int) ($meta['size'] ?? 0),
        ];
    }

    staging_write_manifest($session, $manifest);
    return [$storedNames, $filesMeta];
}

/**
 * @param array{error:int,name:string,tmp_name:string,size:int,type:string} $upload
 * @return array{stored: string, meta: array<string, mixed>}
 */
function finalize_direct_upload(
    array $form,
    array $field,
    array $schema,
    array $upload,
    string $mime,
    array $postData
): array {
    $ext = pathinfo($upload['name'], PATHINFO_EXTENSION);
    $stored = bin2hex(random_bytes(16)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
    $destDir = UPLOADS_DIR . '/' . $form['id'];
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $dest = $destDir . '/' . $stored;
    if (!move_uploaded_file($upload['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save ' . ($field['label'] ?? 'file'));
    }

    $storedPath = $form['id'] . '/' . $stored;
    return [
        'stored' => $storedPath,
        'meta' => [
            'field_id' => (string) $field['id'],
            'stored_name' => $storedPath,
            'original_name' => client_upload_original_name($upload['name'], $schema, $postData),
            'mime' => $mime,
            'size' => (int) $upload['size'],
        ],
    ];
}

/** @return list<array{error:int,name:string,tmp_name:string,size:int,type:string}> */
function normalize_files_array(array $files): array
{
    if (!is_array($files['name'] ?? null)) {
        return [$files];
    }

    $out = [];
    foreach ($files['name'] as $i => $name) {
        $out[] = [
            'name' => $name,
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }
    return $out;
}

/**
 * @return array{0: mixed, 1: list<array<string, mixed>>}
 */
function process_field_file_uploads(
    array $field,
    array $form,
    array $schema,
    string $uploadSession,
    array $postData,
    int $maxBytes,
    array $allowedMimes,
    array &$errors
): array {
    $name = (string) $field['name'];
    $fieldId = (string) $field['id'];
    $maxFiles = staging_max_files_for_field($field);
    $filesMeta = [];
    $storedNames = [];

    $stagedKey = 'staged_' . $name;
    $tokens = $postData[$stagedKey] ?? [];
    if (!is_array($tokens)) {
        $tokens = $tokens !== '' ? [(string) $tokens] : [];
    }
    $tokens = array_values(array_filter(array_map('strval', $tokens)));

    if ($uploadSession !== '' && $tokens !== []) {
        if (count($tokens) > $maxFiles) {
            $errors[] = (string) $field['label'] . ' allows at most ' . $maxFiles . ' file(s).';
            return ['', []];
        }
        try {
            [$storedNames, $filesMeta] = staging_claim_tokens($uploadSession, $field, $form, $schema, $tokens, $postData);
        } catch (Throwable $e) {
            $errors[] = 'Could not finalize uploads for ' . $field['label'] . '.';
            return ['', []];
        }
    }

    if (isset($_FILES[$name])) {
        $uploads = normalize_files_array($_FILES[$name]);
        foreach ($uploads as $upload) {
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (count($storedNames) >= $maxFiles) {
                $errors[] = (string) $field['label'] . ' allows at most ' . $maxFiles . ' file(s).';
                break;
            }
            $check = validate_form_upload_file($upload, $field, $maxBytes, $allowedMimes);
            if (!$check['ok']) {
                $errors[] = (string) ($check['error'] ?? 'Upload failed.');
                continue;
            }
            try {
                $final = finalize_direct_upload($form, $field, $schema, $upload, (string) $check['mime'], $postData);
                $storedNames[] = $final['stored'];
                $filesMeta[] = $final['meta'];
            } catch (Throwable $e) {
                $errors[] = 'Could not save ' . $field['label'] . '.';
            }
        }
    }

    if (!empty($field['required']) && $storedNames === []) {
        $errors[] = (string) $field['label'] . ' is required.';
    }

    $dataValue = count($storedNames) <= 1
        ? ($storedNames[0] ?? '')
        : $storedNames;

    return [$dataValue, $filesMeta];
}
