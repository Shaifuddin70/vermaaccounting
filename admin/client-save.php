<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('clients.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid session.');
}

$editId = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isNew = ($editId === null);

$name = trim((string) ($_POST['name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$company = trim((string) ($_POST['company'] ?? ''));
$sin = trim((string) ($_POST['sin'] ?? ''));
$dob = trim((string) ($_POST['date_of_birth'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

$errors = [];
if ($isNew && partner_user_id() !== null) {
    $errors[] = 'Partners can only work with clients linked to their submissions.';
}
if (!$isNew) {
    $existing = (new ClientRepository())->find($editId);
    if (!$existing) {
        $_SESSION['flash_error'] = 'Client not found.';
        header('Location: /admin/clients');
        exit;
    }
    assert_client_access($existing);
}
if ($name === '') {
    $errors[] = 'Name is required.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}
if ($dob !== '') {
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dob);
    if (!$parsed || $parsed->format('Y-m-d') !== $dob) {
        $errors[] = 'Enter a valid date of birth.';
    }
}

$clientRepo = new ClientRepository();

if (!$isNew) {
    $existing = $clientRepo->find((int) $editId);
    if (!$existing) {
        $_SESSION['flash_error'] = 'Client not found.';
        header('Location: /admin/clients');
        exit;
    }
}

if ($sin !== '' && $clientRepo->sinExists($sin, $editId)) {
    $errors[] = 'That SIN is already used by another client.';
}

if ($errors !== []) {
    $_SESSION['client_edit_errors'] = $errors;
    $_SESSION['client_edit_old'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'sin' => $sin,
        'date_of_birth' => $dob,
        'notes' => $notes,
    ];
    $back = $isNew ? '/admin/client-edit' : '/admin/client-edit?id=' . (int) $editId;
    header('Location: ' . $back);
    exit;
}

$data = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'company' => $company,
    'sin' => $sin,
    'date_of_birth' => $dob !== '' ? $dob : null,
    'notes' => $notes,
];

try {
    if ($isNew) {
        $id = $clientRepo->create($data, 'manual');
        ActivityLog::record('client.created', 'client', $id, ['name' => $name]);
        $_SESSION['flash_success'] = 'Client created.';
        header('Location: /admin/client?id=' . $id);
        exit;
    }

    $clientRepo->update((int) $editId, $data);
    ActivityLog::record('client.updated', 'client', (int) $editId, ['name' => $name]);
    $_SESSION['flash_success'] = 'Client updated.';
    header('Location: /admin/client?id=' . (int) $editId);
    exit;
} catch (PDOException $e) {
    $msg = strtolower($e->getMessage());
    if (str_contains($msg, 'uk_clients_sin') || str_contains($msg, 'duplicate')) {
        $message = 'That SIN is already used by another client.';
    } else {
        $message = 'Could not save this client.';
    }
    $_SESSION['client_edit_errors'] = [$message];
    $_SESSION['client_edit_old'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'sin' => $sin,
        'date_of_birth' => $dob,
        'notes' => $notes,
    ];
    $back = $isNew ? '/admin/client-edit' : '/admin/client-edit?id=' . (int) $editId;
    header('Location: ' . $back);
    exit;
}
