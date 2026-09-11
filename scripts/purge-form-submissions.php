<?php

declare(strict_types=1);

/**
 * Purge spam submissions for a published form (default: contact).
 *
 * Usage:
 *   php scripts/purge-form-submissions.php
 *   php scripts/purge-form-submissions.php contact
 *   php scripts/purge-form-submissions.php contact --since=2026-09-01
 *   php scripts/purge-form-submissions.php contact --dry-run
 *
 * On hosting:
 *   VERMA_ENV=production php scripts/purge-form-submissions.php contact --since=2026-09-01
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../lib/bootstrap.php';

$slug = 'contact';
$since = null;
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, 8);
        continue;
    }
    if (!str_starts_with($arg, '--')) {
        $slug = $arg;
    }
}

$repo = new FormRepository();
$form = $repo->findBySlug($slug, false);
if (!$form) {
    fwrite(STDERR, "Form not found for slug: {$slug}\n");
    exit(1);
}

$formId = (int) $form['id'];
$total = $repo->countSubmissionsForForm($formId);

echo "Form: {$form['title']} (#{$formId}, slug={$slug})\n";
echo "Total submissions: {$total}\n";
if ($since !== null) {
    echo "Filter: created_at >= {$since}\n";
}
if ($dryRun) {
    echo "Dry run — nothing deleted.\n";
    exit(0);
}

if ($since !== null) {
    $result = $repo->deleteSubmissionsForFormSince($formId, $since);
} else {
    $result = $repo->deleteAllSubmissionsForForm($formId);
}

ActivityLog::record('submissions.purged', 'form', $formId, [
    'slug' => $slug,
    'since' => $since,
    'deleted' => $result['deleted'],
    'files_removed' => $result['files_removed'],
]);

echo "Deleted {$result['deleted']} submissions ({$result['files_removed']} files removed).\n";
