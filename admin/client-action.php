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

$action = (string) ($_POST['action'] ?? '');
$clientRepo = new ClientRepository();
$returnTo = client_action_return_url((string) ($_POST['return_to'] ?? ''), '/admin/clients');

if (in_array($action, ['delete_all', 'delete_bulk', 'delete'], true)) {
    Auth::requireCapability('clients.delete');
} else {
    Auth::requireCapability('clients.edit');
}

/** Only allow redirects back into the client pages. */
function client_action_return_url(string $url, string $fallback): string
{
    return preg_match('#^/admin/clients?(\?[^\s]*)?$#', $url) ? $url : $fallback;
}

if ($action === 'bulk_active' || $action === 'bulk_inactive') {
    $_POST['status'] = $action === 'bulk_active' ? 'active' : 'inactive';
    $action = 'bulk_status';
}

if ($action === 'bulk_status') {
    if (partner_user_id() !== null) {
        $_SESSION['flash_error'] = 'Partners cannot bulk-update clients.';
        header('Location: ' . $returnTo);
        exit;
    }
    $rawIds = $_POST['ids'] ?? [];
    $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];
    $active = ($_POST['status'] ?? '') === 'active';
    if (array_filter($ids) === []) {
        $_SESSION['flash_error'] = 'Select at least one client.';
        header('Location: ' . $returnTo);
        exit;
    }
    $changed = $clientRepo->setActive($ids, $active);
    ActivityLog::record('clients.bulk_status', 'clients', null, [
        'status' => $active ? 'active' : 'inactive',
        'requested' => count($ids),
        'changed' => $changed,
    ]);
    $label = $active ? 'active' : 'inactive';
    $_SESSION['flash_success'] = $changed === 0
        ? 'The selected clients were already ' . $label . '.'
        : $changed . ' client' . ($changed === 1 ? '' : 's') . ' marked ' . $label . '.';
    header('Location: ' . $returnTo);
    exit;
}

if ($action === 'delete_all') {
    if (partner_user_id() !== null) {
        $_SESSION['flash_error'] = 'Partners cannot delete all clients.';
        header('Location: /admin/clients');
        exit;
    }
    $confirm = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
    if ($confirm !== 'DELETE ALL') {
        $_SESSION['flash_error'] = 'Type DELETE ALL to confirm deleting every client.';
        header('Location: /admin/clients');
        exit;
    }

    $deleted = $clientRepo->deleteAll();
    ActivityLog::record('clients.delete_all', 'clients', null, [
        'deleted' => $deleted,
    ]);

    if ($deleted < 1) {
        $_SESSION['flash_success'] = 'There were no clients to delete.';
    } elseif ($deleted === 1) {
        $_SESSION['flash_success'] = '1 client was deleted.';
    } else {
        $_SESSION['flash_success'] = 'All ' . $deleted . ' clients were deleted.';
    }
    header('Location: /admin/clients');
    exit;
}

if ($action === 'delete_bulk') {
    if (partner_user_id() !== null) {
        $_SESSION['flash_error'] = 'Partners cannot bulk-delete clients.';
        header('Location: /admin/clients');
        exit;
    }
    $rawIds = $_POST['ids'] ?? [];
    if (!is_array($rawIds)) {
        $rawIds = [];
    }
    $ids = array_values(array_unique(array_filter(
        array_map(static fn ($id): int => (int) $id, $rawIds),
        static fn (int $id): bool => $id > 0
    )));

    if ($ids === []) {
        $_SESSION['flash_error'] = 'Select at least one client to delete.';
        header('Location: ' . $returnTo);
        exit;
    }

    $deleted = $clientRepo->deleteMany($ids);
    ActivityLog::record('clients.bulk_deleted', 'clients', null, [
        'requested' => count($ids),
        'deleted' => $deleted,
        'ids' => $ids,
    ]);

    if ($deleted < 1) {
        $_SESSION['flash_error'] = 'Could not delete the selected clients.';
    } elseif ($deleted === 1) {
        $_SESSION['flash_success'] = '1 client was deleted.';
    } else {
        $_SESSION['flash_success'] = $deleted . ' clients were deleted.';
    }
    header('Location: ' . $returnTo);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$client = $clientRepo->find($id);

if (!$client || $id < 1) {
    $_SESSION['flash_error'] = 'Client not found.';
    header('Location: /admin/clients');
    exit;
}
assert_client_access($client);

if ($action === 'delete') {
    $name = (string) ($client['name'] ?? 'Client');
    if ($clientRepo->delete($id)) {
        ActivityLog::record('client.deleted', 'client', $id, ['name' => $name]);
        $_SESSION['flash_success'] = '“' . $name . '” was deleted.';
    } else {
        $_SESSION['flash_error'] = 'Could not delete this client.';
    }
    header('Location: /admin/clients');
    exit;
}

if ($action === 'set_active') {
    $active = ($_POST['status'] ?? '') === 'active';
    $clientRepo->setActive([$id], $active);
    ActivityLog::record('client.status_changed', 'client', $id, [
        'name' => (string) ($client['name'] ?? ''),
        'status' => $active ? 'active' : 'inactive',
    ]);
    $_SESSION['flash_success'] = '“' . ($client['name'] ?? 'Client') . '” marked ' . ($active ? 'active' : 'inactive') . '.';
    header('Location: ' . client_action_return_url((string) ($_POST['return_to'] ?? ''), '/admin/client?id=' . $id));
    exit;
}

$_SESSION['flash_error'] = 'Unknown action.';
header('Location: /admin/client?id=' . $id);
exit;
