<?php

declare(strict_types=1);

/**
 * Capability-based access control for admin team members.
 *
 * Roles supply defaults; optional per-user permissions_json overrides the full set.
 * Partner data scoping (which submissions) remains separate in partner_helpers.php.
 */

/** @return array<string, array{label: string, description: string, group: string}> */
function permission_catalog(): array
{
    return [
        'dashboard.view' => [
            'label' => 'Dashboard',
            'description' => 'View the admin dashboard',
            'group' => 'Overview',
        ],
        'reports.view' => [
            'label' => 'Reports',
            'description' => 'View business reports',
            'group' => 'Overview',
        ],
        'submissions.view' => [
            'label' => 'View submissions',
            'description' => 'Open and review form submissions',
            'group' => 'Submissions',
        ],
        'submissions.complete' => [
            'label' => 'Mark complete',
            'description' => 'Change submission status',
            'group' => 'Submissions',
        ],
        'submissions.export' => [
            'label' => 'Export CSV',
            'description' => 'Download submission exports',
            'group' => 'Submissions',
        ],
        'submissions.manage' => [
            'label' => 'Edit / delete submissions',
            'description' => 'Edit submission data and delete records',
            'group' => 'Submissions',
        ],
        'clients.view' => [
            'label' => 'View clients',
            'description' => 'Browse client list and profiles',
            'group' => 'Clients & billing',
        ],
        'clients.manage' => [
            'label' => 'Manage clients',
            'description' => 'Create, edit, import, export, and delete clients',
            'group' => 'Clients & billing',
        ],
        'invoices.view' => [
            'label' => 'View invoices',
            'description' => 'Browse and open invoices',
            'group' => 'Clients & billing',
        ],
        'invoices.manage' => [
            'label' => 'Manage invoices',
            'description' => 'Create, edit, send, and configure invoices',
            'group' => 'Clients & billing',
        ],
        'forms.manage' => [
            'label' => 'Forms',
            'description' => 'Build and manage public forms',
            'group' => 'Content',
        ],
        'blogs.manage' => [
            'label' => 'Blogs',
            'description' => 'Create and publish blog posts',
            'group' => 'Content',
        ],
        'files.manage' => [
            'label' => 'Files',
            'description' => 'Browse and manage uploaded files',
            'group' => 'Content',
        ],
        'campaigns.manage' => [
            'label' => 'Campaigns',
            'description' => 'Create and send email campaigns',
            'group' => 'Communications',
        ],
        'email.tracker' => [
            'label' => 'Email tracker',
            'description' => 'View email open tracking',
            'group' => 'Communications',
        ],
        'email.holidays' => [
            'label' => 'Holiday emails',
            'description' => 'Manage holiday email schedules',
            'group' => 'Communications',
        ],
        'email.birthdays' => [
            'label' => 'Birthday emails',
            'description' => 'Manage birthday email messages',
            'group' => 'Communications',
        ],
        'email.settings' => [
            'label' => 'Email settings',
            'description' => 'Configure mail transport and templates',
            'group' => 'Communications',
        ],
        'team.manage' => [
            'label' => 'Team & permissions',
            'description' => 'Add team members and control their access',
            'group' => 'Settings',
        ],
        'activity.view' => [
            'label' => 'Activity log',
            'description' => 'View admin activity history',
            'group' => 'Settings',
        ],
    ];
}

/** @return list<string> */
function permission_all_keys(): array
{
    return array_keys(permission_catalog());
}

/**
 * @return array<string, list<array{key: string, label: string, description: string}>>
 */
function permission_catalog_grouped(): array
{
    $grouped = [];
    foreach (permission_catalog() as $key => $meta) {
        $group = $meta['group'];
        $grouped[$group] ??= [];
        $grouped[$group][] = [
            'key' => $key,
            'label' => $meta['label'],
            'description' => $meta['description'],
        ];
    }
    return $grouped;
}

/** @return array<string, list<string>> */
function permission_role_defaults(): array
{
    $all = permission_all_keys();
    return [
        'admin' => $all,
        'reviewer' => [
            'dashboard.view',
            'submissions.view',
            'submissions.complete',
            'submissions.export',
        ],
        'partner' => [
            'dashboard.view',
            'submissions.view',
        ],
    ];
}

/** @return list<string> */
function permission_defaults_for_role(string $role): array
{
    $defaults = permission_role_defaults();
    return $defaults[$role] ?? $defaults['reviewer'];
}

/**
 * @param mixed $raw
 * @return list<string>|null null means “use role defaults”
 */
function permission_parse_stored($raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_array($raw)) {
        $list = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $list = $decoded;
    }
    $allowed = array_flip(permission_all_keys());
    $out = [];
    foreach ($list as $item) {
        $key = trim((string) $item);
        if ($key !== '' && isset($allowed[$key])) {
            $out[$key] = true;
        }
    }
    return array_keys($out);
}

/**
 * Resolve effective capabilities for a user row / session context.
 *
 * @param array{role?: string, permissions_json?: mixed, is_config_admin?: bool}|null $user
 * @return list<string>
 */
function permission_resolve_for_user(?array $user): array
{
    if ($user === null) {
        return [];
    }
    if (!empty($user['is_config_admin'])) {
        return permission_all_keys();
    }
    $role = (string) ($user['role'] ?? 'reviewer');
    if ($role === 'admin') {
        return permission_all_keys();
    }
    $custom = permission_parse_stored($user['permissions_json'] ?? null);
    if ($custom !== null) {
        // Always keep dashboard so the account is usable after login.
        if (!in_array('dashboard.view', $custom, true)) {
            $custom[] = 'dashboard.view';
        }
        return array_values($custom);
    }
    return permission_defaults_for_role($role);
}

/** @param list<string> $capabilities */
function permission_user_can(array $capabilities, string $capability): bool
{
    if (in_array('*', $capabilities, true)) {
        return true;
    }
    if (in_array($capability, $capabilities, true)) {
        return true;
    }
    // Managing an area implies viewing it.
    $implies = [
        'clients.view' => 'clients.manage',
        'invoices.view' => 'invoices.manage',
    ];
    if (isset($implies[$capability]) && in_array($implies[$capability], $capabilities, true)) {
        return true;
    }
    return false;
}

/**
 * @param list<string>|null $keys
 */
function permission_encode_for_storage(?array $keys): ?string
{
    if ($keys === null) {
        return null;
    }
    $clean = permission_parse_stored($keys) ?? [];
    // Managing an area should include viewing it in the saved set too.
    if (in_array('clients.manage', $clean, true) && !in_array('clients.view', $clean, true)) {
        $clean[] = 'clients.view';
    }
    if (in_array('invoices.manage', $clean, true) && !in_array('invoices.view', $clean, true)) {
        $clean[] = 'invoices.view';
    }
    if ($clean === []) {
        return null;
    }
    return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
}
