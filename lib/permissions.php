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
            'label' => 'Export submissions',
            'description' => 'Download submission CSV exports',
            'group' => 'Submissions',
        ],
        'submissions.edit' => [
            'label' => 'Edit submissions',
            'description' => 'Edit submission field data',
            'group' => 'Submissions',
        ],
        'submissions.delete' => [
            'label' => 'Delete submissions',
            'description' => 'Permanently delete submissions',
            'group' => 'Submissions',
        ],

        'clients.view' => [
            'label' => 'View clients',
            'description' => 'Browse the client list and profiles',
            'group' => 'Clients',
        ],
        'clients.create' => [
            'label' => 'Create clients',
            'description' => 'Add new clients manually',
            'group' => 'Clients',
        ],
        'clients.edit' => [
            'label' => 'Edit clients',
            'description' => 'Update client details',
            'group' => 'Clients',
        ],
        'clients.delete' => [
            'label' => 'Delete clients',
            'description' => 'Delete one or more clients',
            'group' => 'Clients',
        ],
        'clients.import' => [
            'label' => 'Import clients',
            'description' => 'Import clients from CSV',
            'group' => 'Clients',
        ],
        'clients.export' => [
            'label' => 'Export clients',
            'description' => 'Download the client directory as CSV',
            'group' => 'Clients',
        ],
        'clients.sync' => [
            'label' => 'Sync clients',
            'description' => 'Sync clients from form submissions',
            'group' => 'Clients',
        ],

        'invoices.view' => [
            'label' => 'View invoices',
            'description' => 'Browse and open invoices / PDFs',
            'group' => 'Invoices',
        ],
        'invoices.create' => [
            'label' => 'Create invoices',
            'description' => 'Create new invoices',
            'group' => 'Invoices',
        ],
        'invoices.edit' => [
            'label' => 'Edit invoices',
            'description' => 'Edit invoices and change status',
            'group' => 'Invoices',
        ],
        'invoices.delete' => [
            'label' => 'Delete invoices',
            'description' => 'Permanently delete invoices',
            'group' => 'Invoices',
        ],
        'invoices.send' => [
            'label' => 'Send invoices',
            'description' => 'Email invoice PDFs to clients',
            'group' => 'Invoices',
        ],
        'invoices.settings' => [
            'label' => 'Invoice settings',
            'description' => 'Company details and invoice services',
            'group' => 'Invoices',
        ],

        'forms.view' => [
            'label' => 'View forms',
            'description' => 'Browse public forms and their submissions',
            'group' => 'Content',
        ],
        'forms.manage' => [
            'label' => 'Manage forms',
            'description' => 'Build, publish, and delete forms',
            'group' => 'Content',
        ],
        'blogs.view' => [
            'label' => 'View blogs',
            'description' => 'Browse blog posts in admin',
            'group' => 'Content',
        ],
        'blogs.manage' => [
            'label' => 'Manage blogs',
            'description' => 'Create, edit, and publish blog posts',
            'group' => 'Content',
        ],
        'files.view' => [
            'label' => 'View files',
            'description' => 'Browse uploaded files',
            'group' => 'Content',
        ],
        'files.manage' => [
            'label' => 'Manage files',
            'description' => 'Upload, download zip, and delete files',
            'group' => 'Content',
        ],

        'campaigns.view' => [
            'label' => 'View campaigns',
            'description' => 'Browse email campaigns',
            'group' => 'Communications',
        ],
        'campaigns.manage' => [
            'label' => 'Manage campaigns',
            'description' => 'Create, schedule, and send campaigns',
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

/**
 * Legacy capability keys still accepted from stored JSON / older grants.
 *
 * @return array<string, list<string>>
 */
function permission_legacy_aliases(): array
{
    return [
        'clients.manage' => [
            'clients.view',
            'clients.create',
            'clients.edit',
            'clients.delete',
            'clients.import',
            'clients.export',
            'clients.sync',
        ],
        'invoices.manage' => [
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'invoices.send',
            'invoices.settings',
        ],
        'submissions.manage' => [
            'submissions.edit',
            'submissions.delete',
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
 * Expand legacy aliases and drop unknown keys.
 *
 * @param list<string> $keys
 * @return list<string>
 */
function permission_expand_keys(array $keys): array
{
    $allowed = array_flip(permission_all_keys());
    $aliases = permission_legacy_aliases();
    $out = [];

    foreach ($keys as $item) {
        $key = trim((string) $item);
        if ($key === '') {
            continue;
        }
        if (isset($aliases[$key])) {
            foreach ($aliases[$key] as $expanded) {
                if (isset($allowed[$expanded])) {
                    $out[$expanded] = true;
                }
            }
            continue;
        }
        if (isset($allowed[$key])) {
            $out[$key] = true;
        }
    }

    return array_keys($out);
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

    $expanded = permission_expand_keys(array_map('strval', $list));
    return $expanded === [] ? null : $expanded;
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

/**
 * Capabilities that grant another capability (e.g. create implies view).
 *
 * @return array<string, list<string>>
 */
function permission_implies_map(): array
{
    return [
        'clients.view' => [
            'clients.create',
            'clients.edit',
            'clients.delete',
            'clients.import',
            'clients.export',
            'clients.sync',
        ],
        'invoices.view' => [
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'invoices.send',
            'invoices.settings',
        ],
        'submissions.view' => [
            'submissions.complete',
            'submissions.export',
            'submissions.edit',
            'submissions.delete',
        ],
        'forms.view' => ['forms.manage'],
        'blogs.view' => ['blogs.manage'],
        'files.view' => ['files.manage'],
        'campaigns.view' => ['campaigns.manage'],
    ];
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

    // Legacy aliases still grant their expanded set.
    $aliases = permission_legacy_aliases();
    if (isset($aliases[$capability])) {
        // Asking for a legacy key: treat as having it if any/all expanded? Prefer all for manage checks.
        foreach ($aliases[$capability] as $needed) {
            if (!permission_user_can($capabilities, $needed)) {
                return false;
            }
        }
        return true;
    }

    // Managing / mutating an area implies viewing it.
    $implies = permission_implies_map();
    if (isset($implies[$capability])) {
        foreach ($implies[$capability] as $grantor) {
            if (in_array($grantor, $capabilities, true)) {
                return true;
            }
        }
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
    $clean = permission_expand_keys($keys);

    // Ensure view accompanies any write permission in the same module.
    foreach (permission_implies_map() as $viewKey => $writers) {
        $hasWriter = false;
        foreach ($writers as $writer) {
            if (in_array($writer, $clean, true)) {
                $hasWriter = true;
                break;
            }
        }
        if ($hasWriter && !in_array($viewKey, $clean, true)) {
            $clean[] = $viewKey;
        }
    }

    if ($clean === []) {
        return null;
    }
    return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
}
