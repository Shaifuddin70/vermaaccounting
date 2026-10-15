<?php

declare(strict_types=1);

/**
 * XML sitemaps from published, indexable, self-canonical URLs only.
 * Hand-written pages are listed until a CMS page or redirect replaces them.
 */
final class CmsSitemap
{
    public const PARTS = ['pages' => 'Pages', 'articles' => 'Articles', 'cities' => 'City pages', 'legacy' => 'Hand-written pages'];
    public const MAX_URLS = 45000;

    /**
     * @return array<string, list<array{loc: string, lastmod: string, path: string, title: string}>>
     */
    public static function entries(): array
    {
        $config = CmsSiteConfig::get();
        $base = CmsSiteConfig::baseUrl();
        $repo = new CmsContentRepository();
        $folders = [];
        foreach ((new CmsFolderRepository())->all() as $f) {
            $folders[$f['id']] = $f;
        }
        $homeId = (int) $config['home_content_id'];
        $out = ['pages' => [], 'articles' => [], 'cities' => [], 'legacy' => []];
        $taken = [];

        foreach ($repo->published(null, self::MAX_URLS) as $content) {
            $path = (int) $content['id'] === $homeId ? '/' : (string) $content['path'];
            $taken[$content['path']] = true;
            $taken[$path] = true;
            $seo = $repo->seo((int) $content['id']);
            $folder = $content['folder_id'] ? ($folders[$content['folder_id']] ?? null) : null;
            $effective = CmsSeo::effective($content, $seo, $folder, ['path' => $path]);
            if (!$effective['sitemap']) {
                continue;
            }
            $part = match ($content['type']) {
                'article' => 'articles',
                'city' => 'cities',
                default => 'pages',
            };
            $out[$part][] = [
                'loc' => $effective['canonical'],
                'lastmod' => self::w3c((string) $content['updated_at']),
                'path' => $path,
                'title' => CmsCityDefaults::label($content),
            ];
        }

        if ((int) $config['sitemap_legacy'] === 1) {
            $redirects = new CmsRedirectRepository();
            foreach (CmsPath::legacyPages() as $path) {
                if (isset($taken[$path]) || $redirects->findBySource($path) || CmsSeo::pathExcludedFromSitemap($path, (string) $config['sitemap_exclude'])) {
                    continue;
                }
                $file = PROJECT_ROOT . ($path === '/' ? '/index.php' : $path . '.php');
                $out['legacy'][] = [
                    'loc' => $base . ($path === '/' ? '/' : $path),
                    'lastmod' => is_file($file) ? gmdate('Y-m-d\TH:i:s\Z', (int) filemtime($file)) : '',
                    'path' => $path,
                    'title' => $path,
                ];
            }
        }
        return $out;
    }

    private static function w3c(string $utc): string
    {
        $ts = strtotime($utc . ' UTC');
        return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : '';
    }

    public static function indexXml(?array $entries = null): string
    {
        $entries ??= self::entries();
        $base = CmsSiteConfig::baseUrl();
        $w = new XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('sitemapindex');
        $w->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($entries as $part => $urls) {
            if ($urls === []) {
                continue;
            }
            $latest = max(array_map(static fn ($u) => $u['lastmod'], $urls));
            $w->startElement('sitemap');
            $w->writeElement('loc', $base . '/sitemap-' . $part . '.xml');
            if ($latest !== '') {
                $w->writeElement('lastmod', $latest);
            }
            $w->endElement();
        }
        $w->endElement();
        $w->endDocument();
        return $w->outputMemory();
    }

    public static function partXml(string $part, ?array $entries = null): ?string
    {
        $entries ??= self::entries();
        if (!isset($entries[$part])) {
            return null;
        }
        $w = new XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('urlset');
        $w->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($entries[$part] as $url) {
            $w->startElement('url');
            $w->writeElement('loc', $url['loc']);
            if ($url['lastmod'] !== '') {
                $w->writeElement('lastmod', $url['lastmod']);
            }
            $w->endElement();
        }
        $w->endElement();
        $w->endDocument();
        return $w->outputMemory();
    }

    /** @return list<string> problems found when parsing the generated XML */
    public static function validate(string $xml): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml);
        $errors = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        if (!$ok) {
            return $errors ?: ['XML could not be parsed.'];
        }
        $root = $doc->documentElement?->localName;
        if (!in_array($root, ['urlset', 'sitemapindex'], true)) {
            $errors[] = 'Unexpected root element ' . $root . '.';
        }
        foreach ($doc->getElementsByTagName('loc') as $loc) {
            if (!preg_match('#^https?://#', $loc->textContent)) {
                $errors[] = 'Relative URL in sitemap: ' . $loc->textContent;
            }
        }
        return $errors;
    }

    public static function robotsTxt(): string
    {
        $txt = (string) CmsSiteConfig::value('robots_txt', CmsSiteConfig::defaultRobotsTxt());
        return str_replace('{{base_url}}', CmsSiteConfig::baseUrl(), $txt);
    }

    /**
     * Warnings for a robots.txt draft: site-wide blocks, blocked CMS pages and a missing sitemap line.
     *
     * @return list<string>
     */
    public static function robotsWarnings(string $txt): array
    {
        $warnings = [];
        $rules = CmsSeo::robotsRules($txt);
        foreach ($rules as [$type, $pattern]) {
            if ($type === 'disallow' && $pattern === '/') {
                $warnings[] = '"Disallow: /" blocks the entire site for all crawlers.';
            }
        }
        foreach (['/', '/blog', '/contact', '/services'] as $probe) {
            if (CmsSeo::blockedByRobotsTxt($probe, $txt)) {
                $warnings[] = 'Important URL ' . $probe . ' is blocked.';
            }
        }
        $blocked = 0;
        foreach ((new CmsContentRepository())->published(null, 2000) as $c) {
            if (CmsSeo::blockedByRobotsTxt((string) $c['path'], $txt)) {
                $blocked++;
            }
        }
        if ($blocked > 0) {
            $warnings[] = $blocked . ' published CMS page(s) would be blocked from crawling. robots.txt does not remove pages from search; use noindex for that.';
        }
        if (!preg_match('/^\s*sitemap:/im', $txt)) {
            $warnings[] = 'No Sitemap: line. Add "Sitemap: {{base_url}}/sitemap.xml".';
        }
        if (preg_match('/^\s*(noindex|nofollow)\s*:/im', $txt)) {
            $warnings[] = 'Noindex/Nofollow lines in robots.txt are not supported by Google.';
        }
        return array_values(array_unique($warnings));
    }
}
