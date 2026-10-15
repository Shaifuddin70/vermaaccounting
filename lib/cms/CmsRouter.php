<?php

declare(strict_types=1);

/**
 * Resolves a public request to a redirect, CMS content or a real 404.
 * Hand-written PHP pages are served by the web server before this runs.
 */
final class CmsRouter
{
    /**
     * Decide the response for a request path. Pure enough to unit-test: no output, no exit.
     *
     * @return array{status: int, headers: array<string, string>, body: string, cache?: bool}
     */
    public static function resolve(string $requestUri, array $query = [], string $method = 'GET'): array
    {
        $rawPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
        if ($rawPath === '') {
            $rawPath = '/';
        }
        $queryString = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');

        if ($rawPath === '/cms/preview') {
            return self::preview((string) ($query['token'] ?? ''), (int) ($query['page'] ?? 1));
        }

        $path = CmsPath::normalize($rawPath);
        $repo = new CmsRedirectRepository();

        $redirect = $repo->findBySource($path);
        if ($redirect) {
            return self::redirectResponse($redirect, $repo);
        }

        if ($rawPath !== $path && self::exists($path)) {
            return self::to($path . ($queryString !== '' ? '?' . $queryString : ''), 301);
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return CmsRenderer::notFound();
        }

        $contents = new CmsContentRepository();
        $content = $path === '/' ? self::homeContent() : $contents->findByPath($path);
        if ($content && $path !== '/' && (int) $content['id'] === (int) CmsSiteConfig::value('home_content_id', 0) && CmsContentRepository::isLive($content)) {
            return self::to('/' . ($queryString !== '' ? '?' . $queryString : ''), 301);
        }
        if ($content && CmsContentRepository::isLive($content)) {
            return self::renderContent($content, $path, max(1, (int) ($query['page'] ?? 1)));
        }

        if (!$content) {
            $moved = $contents->findByOldPath($path);
            if ($moved && CmsContentRepository::isLive($moved) && $moved['path'] !== $path) {
                return self::to((string) $moved['path'], 301);
            }
        }

        return CmsRenderer::notFound();
    }

    /** Render live content with the page cache. */
    public static function renderContent(array $content, string $path, int $page = 1): array
    {
        $key = $path . '|' . $page;
        $cached = CmsCache::get($key);
        if ($cached !== null) {
            $cached['headers']['X-CMS-Cache'] = 'hit';
            return $cached + ['cache' => true];
        }
        $response = CmsRenderer::render($content, ['page' => $page, 'path' => $path]);
        if ($response['status'] === 200) {
            $ttl = CmsCache::DEFAULT_TTL;
            if (!empty($content['expire_at'])) {
                $ttl = min($ttl, max(0, (int) strtotime($content['expire_at'] . ' UTC') - time()));
            }
            CmsCache::put($key, $response['status'], $response['headers'], $response['body'], $ttl);
        }
        return $response;
    }

    public static function homeContent(): ?array
    {
        $id = (int) CmsSiteConfig::value('home_content_id', 0);
        return $id > 0 ? (new CmsContentRepository())->find($id) : null;
    }

    private static function preview(string $token, int $page): array
    {
        $content = (new CmsContentRepository())->findByPreviewToken($token);
        if ($content === null) {
            return CmsRenderer::notFound();
        }
        $response = CmsRenderer::render($content, ['preview' => true, 'page' => max(1, $page)]);
        $response['headers']['X-Robots-Tag'] = 'noindex, nofollow';
        $response['headers']['Cache-Control'] = 'private, no-store';
        $response['headers']['Referrer-Policy'] = 'no-referrer';
        return $response;
    }

    private static function redirectResponse(array $redirect, CmsRedirectRepository $repo): array
    {
        $repo->recordHit((int) $redirect['id']);
        $code = (int) $redirect['status_code'];
        if ($code === 410) {
            return CmsRenderer::notFound(410);
        }
        $target = (string) $redirect['target'];
        $parsed = CmsPath::parseTarget($target, CmsSiteConfig::baseUrl());
        if (!$parsed['ok']) {
            return CmsRenderer::notFound();
        }
        return self::to($parsed['value'], in_array($code, [301, 302, 307, 308], true) ? $code : 301);
    }

    private static function to(string $target, int $code): array
    {
        $location = $target[0] === '/' ? CmsSiteConfig::baseUrl() . $target : $target;
        return [
            'status' => $code,
            'headers' => ['Location' => $location, 'Cache-Control' => $code === 301 || $code === 308 ? 'public, max-age=3600' : 'no-cache'],
            'body' => '',
        ];
    }

    /** Whether a normalized path answers with 200 or a redirect (CMS, redirect table or hand-written page). */
    public static function exists(string $path): bool
    {
        if (CmsPath::legacyPageExists($path)) {
            return true;
        }
        if ((new CmsRedirectRepository())->findBySource($path)) {
            return true;
        }
        $content = (new CmsContentRepository())->findByPath($path);
        return $content !== null && CmsContentRepository::isLive($content);
    }

    public static function emit(array $response): void
    {
        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . str_replace(["\r", "\n"], '', $value));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $response['body'];
        }
    }
}
