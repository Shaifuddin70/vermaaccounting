<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

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
Auth::requireCapability($isNew ? 'clients.create' : 'clients.edit');

$name = trim((string) ($_POST['name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$otherPhone = trim((string) ($_POST['other_phone'] ?? ''));
$company = trim((string) ($_POST['company'] ?? ''));
$addressStreet = trim((string) ($_POST['address_street'] ?? ''));
$addressCity = trim((string) ($_POST['address_city'] ?? ''));
$addressProvince = trim((string) ($_POST['address_province'] ?? ''));
$addressPostal = trim((string) ($_POST['address_postal'] ?? ''));
$addressCountry = trim((string) ($_POST['address_country'] ?? ''));
$sin = trim((string) ($_POST['sin'] ?? ''));
$dob = trim((string) ($_POST['date_of_birth'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
$lastActivity = trim((string) ($_POST['last_activity'] ?? ''));
$freeNotes = trim((string) ($_POST['notes'] ?? ''));
$emailUnsubscribed = !empty($_POST['email_unsubscribed']);
$isActive = (string) ($_POST['is_active'] ?? '1') !== '0';

$oldInput = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'other_phone' => $otherPhone,
    'company' => $company,
    'address_street' => $addressStreet,
    'address_city' => $addressCity,
    'address_province' => $addressProvince,
    'address_postal' => $addressPostal,
    'address_country' => $addressCountry,
    'sin' => $sin,
    'date_of_birth' => $dob,
    'status' => $status,
    'last_activity' => $lastActivity,
    'notes' => $freeNotes,
    'email_unsubscribed' => $emailUnsubscribed,
    'is_active' => $isActive,
];
$back = $isNew ? '/admin/client-edit' : '/admin/client-edit?id=' . (int) $editId;

$errors = [];
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
$postalCanada = $addressCountry === '' || in_array(strtolower($addressCountry), ['canada', 'ca', 'can'], true);
if ($addressPostal !== '' && $postalCanada && !preg_match('/^[A-Za-z]\d[A-Za-z]\s*-?\s*\d[A-Za-z]\d$/', $addressPostal)) {
    $errors[] = 'Enter a valid Canadian postal code (e.g. K1A 0B1).';
}
if ($dob !== '') {
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dob);
    if (!$parsed || $parsed->format('Y-m-d') !== $dob) {
        $errors[] = 'Enter a valid date of birth.';
    }
}

$clientRepo = new ClientRepository();

if ($sin !== '' && $clientRepo->sinExists($sin, $editId)) {
    $errors[] = 'That SIN is already used by another client.';
}

if ($errors !== []) {
    $_SESSION['client_edit_errors'] = $errors;
    $_SESSION['client_edit_old'] = $oldInput;
    header('Location: ' . $back);
    exit;
}

$data = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'company' => $company,
    'address_street' => $addressStreet,
    'address_city' => $addressCity,
    'address_province' => $addressProvince,
    'address_postal' => $addressPostal,
    'address_country' => $addressCountry,
    'is_active' => $isActive,
    'sin' => $sin,
    'date_of_birth' => $dob !== '' ? $dob : null,
    'notes' => client_compose_notes([
        'notes' => $freeNotes,
        'other_phone' => $otherPhone,
        'status' => $status,
        'last_activity' => $lastActivity,
    ]),
];

try {
    if ($isNew) {
        $id = $clientRepo->create($data, 'manual');
        if ($emailUnsubscribed) {
            $clientRepo->setEmailUnsubscribed($id, true);
        }
        $partnerId = partner_user_id();
        if ($partnerId !== null) {
            link_client_to_partner($id, $partnerId);
        }
        ActivityLog::record('client.created', 'client', $id, [
            'name' => $name,
            'partner_id' => $partnerId,
        ]);
        $_SESSION['flash_success'] = 'Client created.';
        header('Location: /admin/client?id=' . $id);
        exit;
    }

    $clientRepo->update((int) $editId, $data);
    if ($emailUnsubscribed !== $clientRepo->isEmailUnsubscribed((int) $editId)) {
        $clientRepo->setEmailUnsubscribed((int) $editId, $emailUnsubscribed);
    }
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
    $_SESSION['client_edit_old'] = $oldInput;
    header('Location: ' . $back);
    exit;
}
