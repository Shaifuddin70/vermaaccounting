<?php

declare(strict_types=1);

require_once __DIR__ . '/../seo.php';
require_once __DIR__ . '/CmsSchema.php';
require_once __DIR__ . '/CmsPath.php';
require_once __DIR__ . '/CmsContentRepository.php';
require_once __DIR__ . '/CmsFolderRepository.php';
require_once __DIR__ . '/CmsRedirectRepository.php';
require_once __DIR__ . '/CmsMediaRepository.php';
require_once __DIR__ . '/CmsSiteConfig.php';
require_once __DIR__ . '/CmsCompany.php';
require_once __DIR__ . '/CmsHtml.php';
require_once __DIR__ . '/CmsSeo.php';
require_once __DIR__ . '/CmsStructuredData.php';
require_once __DIR__ . '/CmsCache.php';
require_once __DIR__ . '/CmsRenderer.php';
require_once __DIR__ . '/CmsRouter.php';
require_once __DIR__ . '/CmsContentService.php';
require_once __DIR__ . '/CmsSitemap.php';
require_once __DIR__ . '/CmsAudit.php';
require_once __DIR__ . '/CmsMigration.php';
require_once __DIR__ . '/CmsIntegrations.php';
require_once __DIR__ . '/CmsDiff.php';
require_once __DIR__ . '/CmsStylesheets.php';
require_once __DIR__ . '/CmsBlocks.php';
require_once __DIR__ . '/CmsCityDefaults.php';
require_once __DIR__ . '/CmsNavigation.php';

/**
 * Called from components/header.php: serve a CMS page or redirect instead of the hand-written page
 * when one has been published at the same URL (or as the homepage).
 */
function cms_takeover_legacy_page(): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) || PHP_SAPI === 'cli') {
        return;
    }
    try {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $rawPath = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $path = CmsPath::normalize($uri);
        if ($rawPath !== $path && rawurldecode($rawPath) !== $path && CmsPath::legacyPageExists($path)) {
            $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Location: ' . CmsSiteConfig::baseUrl() . $path . ($query !== '' ? '?' . $query : ''), true, 301);
            exit;
        }
        if (!(new CmsRedirectRepository())->findBySource($path)) {
            $content = $path === '/' ? CmsRouter::homeContent() : (new CmsContentRepository())->findByPath($path);
            if ($content === null || !CmsContentRepository::isLive($content)) {
                return;
            }
        }
        $response = CmsRouter::resolve((string) ($_SERVER['REQUEST_URI'] ?? '/'), $_GET, (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    } catch (Throwable $e) {
        error_log('CMS takeover failed: ' . $e->getMessage());
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    CmsRouter::emit($response);
    exit;
}

/**
 * Publish scheduled content and unpublish expired content. Run from cron.
 *
 * @return array{published: int, expired: int}
 */
function cms_process_schedule(?string $nowUtc = null): array
{
    $now = $nowUtc ?? gmdate('Y-m-d H:i:s');
    $repo = new CmsContentRepository();
    $published = 0;
    $expired = 0;
    foreach ($repo->duePublications($now) as $content) {
        $repo->update((int) $content['id'], ['status' => 'published', 'published_at' => $content['published_at'] ?: $now]);
        ActivityLog::record('cms.published', 'cms_content', (int) $content['id'], ['path' => $content['path'], 'by' => 'schedule']);
        $published++;
    }
    foreach ($repo->dueExpiries($now) as $content) {
        $repo->update((int) $content['id'], ['status' => 'unpublished']);
        ActivityLog::record('cms.unpublished', 'cms_content', (int) $content['id'], ['path' => $content['path'], 'by' => 'expiry']);
        $expired++;
    }
    if ($published || $expired) {
        CmsCache::flush();
    }
    return ['published' => $published, 'expired' => $expired];
}

function cms_status_badge(string $status): string
{
    $map = ['published' => 'sent', 'scheduled' => 'scheduled', 'review' => 'scheduled', 'draft' => 'draft', 'unpublished' => 'draft', 'archived' => 'draft'];
    $label = CmsContentRepository::STATUSES[$status] ?? ucfirst($status);
    return '<span class="campaign-status-badge campaign-status-badge--' . ($map[$status] ?? 'draft') . ' cms-status cms-status--' . e($status) . '">' . e($label) . '</span>';
}

function cms_public_url(string $path): string
{
    return CmsSiteConfig::baseUrl() . ($path === '/' ? '/' : $path);
}
