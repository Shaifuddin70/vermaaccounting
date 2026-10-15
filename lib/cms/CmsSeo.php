<?php

declare(strict_types=1);

/**
 * Effective SEO for a content record: page override, then folder default, then site default.
 */
final class CmsSeo
{
    public const TITLE_MAX = 60;
    public const DESC_MIN = 70;
    public const DESC_MAX = 160;
    public const EXTRA_DIRECTIVES = ['noarchive', 'notranslate', 'noimageindex', 'nositelinkssearchbox'];

    /**
     * @param array $content cms_contents row
     * @param array $seo cms_seo row (or defaults)
     * @param array|null $folder cms_folders row
     * @param array{page?: int, preview?: bool, path?: string} $context
     */
    public static function effective(array $content, array $seo, ?array $folder = null, array $context = []): array
    {
        $content = CmsCityDefaults::apply($content);
        $seo = CmsCityDefaults::applySeo($content, $seo);
        $config = CmsSiteConfig::get();
        $base = CmsSiteConfig::baseUrl();
        $folderDefaults = $folder['seo_defaults'] ?? [];
        $path = $context['path'] ?? (string) $content['path'];
        $page = max(1, (int) ($context['page'] ?? 1));
        $warnings = [];

        // Title
        $suffix = (string) $config['title_suffix'];
        $customTitle = trim((string) ($seo['seo_title'] ?? ''));
        $baseTitle = trim((string) ($content['title'] ?? ''));
        if ($customTitle !== '') {
            $title = $customTitle;
        } else {
            $title = $baseTitle;
            if ($suffix !== '' && !str_contains(mb_strtolower($title), mb_strtolower(trim($suffix, " |-–")))) {
                $title .= $suffix;
            }
        }
        if ($page > 1) {
            $title = preg_replace('/(\s[|\-–]\s)/u', ' – Page ' . $page . '$1', $title, 1, $count) ?? $title;
            if (!$count) {
                $title .= ' – Page ' . $page;
            }
        }

        // Description
        $description = trim((string) ($seo['meta_description'] ?? ''));
        $descriptionSource = 'custom';
        if ($description === '') {
            $description = trim((string) ($content['excerpt'] ?? ''));
            $descriptionSource = 'excerpt';
        }
        if ($description === '') {
            $description = CmsHtml::plainText((string) ($content['body_html'] ?? ''), 155);
            $descriptionSource = 'body';
        }
        if ($description === '') {
            $description = (string) $config['default_description'];
            $descriptionSource = 'site default';
        }
        if ($page > 1) {
            $description = 'Page ' . $page . ': ' . $description;
        }

        // Robots
        $pick = static function (string $key, string $inherit) use ($seo, $folderDefaults, $config): array {
            $own = (string) ($seo[$key] ?? $inherit);
            if ($own !== '' && $own !== $inherit) {
                return [$own, 'page'];
            }
            $fromFolder = (string) ($folderDefaults[$key] ?? '');
            if ($fromFolder !== '' && $fromFolder !== $inherit) {
                return [$fromFolder, 'folder'];
            }
            return [(string) $config[$key], 'site'];
        };
        [$index, $indexSource] = $pick('robots_index', 'inherit');
        [$follow, $followSource] = $pick('robots_follow', 'inherit');
        $isLive = CmsContentRepository::isLive($content);
        if (!empty($context['preview']) || !$isLive) {
            $index = 'noindex';
            $follow = 'nofollow';
            $indexSource = $followSource = 'not published';
        }

        $maxSnippet = $seo['max_snippet'] ?? null;
        if ($maxSnippet === null || $maxSnippet === '') {
            $maxSnippet = $folderDefaults['max_snippet'] ?? $config['max_snippet'];
        }
        $maxImage = (string) (($seo['max_image_preview'] ?? '') ?: ($folderDefaults['max_image_preview'] ?? '') ?: $config['max_image_preview']);
        $maxVideo = $seo['max_video_preview'] ?? null;
        if ($maxVideo === null || $maxVideo === '') {
            $maxVideo = $folderDefaults['max_video_preview'] ?? $config['max_video_preview'];
        }
        $directives = [$index, $follow];
        if ((int) $maxSnippet === 0) {
            $directives[] = 'nosnippet';
        } else {
            $directives[] = 'max-snippet:' . (int) $maxSnippet;
        }
        $directives[] = 'max-image-preview:' . $maxImage;
        $directives[] = 'max-video-preview:' . (int) $maxVideo;
        foreach (self::extraDirectives((string) ($seo['robots_extra'] ?? '')) as $extra) {
            $directives[] = $extra;
        }
        $robots = implode(', ', $directives);

        // Canonical
        $selfUrl = $base . ($path === '/' ? '/' : $path) . ($page > 1 ? '?page=' . $page : '');
        $canonical = $selfUrl;
        $canonicalSource = 'self';
        if (($seo['canonical_mode'] ?? 'auto') === 'custom') {
            $custom = trim((string) ($seo['canonical_url'] ?? ''));
            $check = self::validateCanonical($custom, $base);
            if ($check === null) {
                $canonical = $custom;
                $canonicalSource = 'custom';
            } else {
                $warnings[] = ['level' => 'error', 'text' => 'Custom canonical ignored: ' . $check];
            }
        }
        $isSelfCanonical = rtrim($canonical, '/') === rtrim($selfUrl, '/');

        // Social
        $ogImage = '';
        $ogImageAlt = trim((string) ($seo['og_image_alt'] ?? ''));
        $ogImageSize = null;
        $mediaRepo = null;
        if (!empty($seo['og_image_id'])) {
            $mediaRepo ??= new CmsMediaRepository();
            $m = $mediaRepo->find((int) $seo['og_image_id']);
            if ($m) {
                $ogImage = $m['url'];
                $ogImageAlt = $ogImageAlt ?: (string) $m['alt'];
                $ogImageSize = [$m['width'], $m['height']];
            }
        }
        if ($ogImage === '' && trim((string) ($seo['og_image_url'] ?? '')) !== '') {
            $ogImage = trim((string) $seo['og_image_url']);
        }
        $featured = CmsRenderer::featuredImage($content);
        if ($ogImage === '' && $featured !== null) {
            $ogImage = $featured['url'];
            $ogImageAlt = $ogImageAlt ?: $featured['alt'];
            $ogImageSize = $featured['width'] ? [$featured['width'], $featured['height']] : null;
        }
        if ($ogImage === '') {
            $ogImage = (string) $config['default_og_image'];
            $ogImageAlt = $ogImageAlt ?: (string) $config['default_og_image_alt'];
        }
        $ogType = (string) (($seo['og_type'] ?? '') ?: (($content['type'] ?? '') === 'article' ? 'article' : 'website'));
        $social = [
            'title' => trim((string) ($seo['og_title'] ?? '')) ?: ($customTitle !== '' ? $customTitle : $baseTitle),
            'description' => trim((string) ($seo['og_description'] ?? '')) ?: $description,
            'image' => $ogImage !== '' ? self::absolute($ogImage, $base) : '',
            'image_alt' => $ogImageAlt,
            'image_size' => $ogImageSize,
            'type' => $ogType,
            'twitter_card' => (string) (($seo['twitter_card'] ?? '') ?: $config['twitter_card']),
        ];

        // Sitemap eligibility
        $typeEnabled = match ((string) ($content['type'] ?? 'page')) {
            'article' => (bool) $config['sitemap_articles'],
            'city' => (bool) $config['sitemap_cities'],
            default => (bool) $config['sitemap_pages'],
        };
        $sitemap = $isLive && $index === 'index' && (int) ($seo['sitemap_include'] ?? 1) === 1 && $isSelfCanonical && $typeEnabled
            && !self::pathExcludedFromSitemap($path, (string) $config['sitemap_exclude']);

        // Warnings
        if ($customTitle === '') {
            $warnings[] = ['level' => 'info', 'text' => 'No custom SEO title; using the page title' . ($suffix !== '' ? ' plus the site suffix' : '') . '.'];
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            $warnings[] = ['level' => 'warning', 'text' => 'Title is ' . mb_strlen($title) . ' characters; search results usually show about ' . self::TITLE_MAX . '.'];
        }
        if ($baseTitle === '') {
            $warnings[] = ['level' => 'error', 'text' => 'Page title is missing.'];
        }
        if ($descriptionSource !== 'custom') {
            $warnings[] = ['level' => 'warning', 'text' => 'No meta description; falling back to the ' . $descriptionSource . '.'];
        } elseif (mb_strlen($description) < self::DESC_MIN || mb_strlen($description) > self::DESC_MAX) {
            $warnings[] = ['level' => 'warning', 'text' => 'Meta description is ' . mb_strlen($description) . ' characters; aim for ' . self::DESC_MIN . '–' . self::DESC_MAX . '.'];
        }
        $ownIndex = (string) ($seo['robots_index'] ?? 'inherit');
        if ($isLive && $index === 'noindex') {
            $warnings[] = ['level' => 'error', 'text' => 'This published page is set to noindex (' . $indexSource . ' setting), so search engines are asked not to index it.'];
        }
        if ($ownIndex === 'noindex' && $canonicalSource === 'custom') {
            $warnings[] = ['level' => 'warning', 'text' => 'noindex combined with a custom canonical sends mixed signals; usually use one or the other.'];
        }
        if ((int) ($seo['sitemap_include'] ?? 1) === 1 && $index === 'noindex' && $isLive) {
            $warnings[] = ['level' => 'info', 'text' => 'Sitemap inclusion is on, but noindex pages are left out of the sitemap.'];
        }
        if ($canonicalSource === 'custom' && !$isSelfCanonical) {
            $warnings[] = ['level' => 'info', 'text' => 'Canonical points to another URL, so this page is left out of the sitemap.'];
            $host = parse_url($canonical, PHP_URL_HOST);
            if ($host && strcasecmp((string) $host, (string) parse_url($base, PHP_URL_HOST)) !== 0) {
                $warnings[] = ['level' => 'warning', 'text' => 'Canonical points to a different domain (' . $host . ').'];
            }
        }
        if (self::blockedByRobotsTxt($path, (string) $config['robots_txt'])) {
            $warnings[] = ['level' => 'error', 'text' => 'robots.txt blocks this URL, so crawlers cannot fetch it' . ($index === 'noindex' ? ' or see its noindex' : '') . '.'];
        }
        if ($social['image'] === '') {
            $warnings[] = ['level' => 'info', 'text' => 'No social image.'];
        } elseif ($ogImageSize !== null && ($ogImageSize[0] < 600 || $ogImageSize[1] < 315)) {
            $warnings[] = ['level' => 'warning', 'text' => 'Social image is smaller than 600×315 and may be shown as a small thumbnail.'];
        }

        return [
            'title' => $title,
            'description' => $description,
            'description_source' => $descriptionSource,
            'robots' => $robots,
            'index' => $index,
            'follow' => $follow,
            'index_source' => $indexSource,
            'follow_source' => $followSource,
            'canonical' => $canonical,
            'canonical_source' => $canonicalSource,
            'self_canonical' => $isSelfCanonical,
            'url' => $selfUrl,
            'social' => $social,
            'sitemap' => $sitemap,
            'language' => (string) (($content['language'] ?? '') ?: $config['language']),
            'warnings' => $warnings,
        ];
    }

    /** @return list<string> */
    public static function extraDirectives(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', strtolower($raw)) ?: [] as $d) {
            if (in_array($d, self::EXTRA_DIRECTIVES, true)) {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }

    /** Null when valid, otherwise the reason. */
    public static function validateCanonical(string $url, string $base): ?string
    {
        if ($url === '') {
            return 'it is empty.';
        }
        if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'it must be an absolute http(s) URL.';
        }
        if (preg_match('/[\s<>"]/', $url)) {
            return 'it contains invalid characters.';
        }
        if (str_starts_with(strtolower($base), 'https://') && str_starts_with(strtolower($url), 'http://')
            && strcasecmp((string) parse_url($url, PHP_URL_HOST), (string) parse_url($base, PHP_URL_HOST)) === 0) {
            return 'it uses http:// while the site uses https://.';
        }
        return null;
    }

    public static function absolute(string $url, string $base): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return $base . '/' . ltrim($url, '/');
    }

    public static function pathExcludedFromSitemap(string $path, string $rules): bool
    {
        foreach (CmsSiteConfig::lines($rules) as $rule) {
            if ($rule === $path || (str_ends_with($rule, '*') && str_starts_with($path, rtrim($rule, '*')))) {
                return true;
            }
        }
        return false;
    }

    /** Simple robots.txt evaluation for the "*" group (longest match wins, Allow beats Disallow on ties). */
    public static function blockedByRobotsTxt(string $path, string $robotsTxt): bool
    {
        $rules = self::robotsRules($robotsTxt);
        $best = null;
        $bestLen = -1;
        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '') {
                continue;
            }
            $regex = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')) . '#';
            if (preg_match($regex, $path)) {
                $len = strlen($pattern);
                if ($len > $bestLen || ($len === $bestLen && $type === 'allow')) {
                    $best = $type;
                    $bestLen = $len;
                }
            }
        }
        return $best === 'disallow';
    }

    /** @return list<array{0: string, 1: string}> rules for User-agent: * */
    public static function robotsRules(string $robotsTxt): array
    {
        $rules = [];
        $inStar = false;
        $sawRule = false;
        foreach (preg_split('/\R/', $robotsTxt) ?: [] as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if ($sawRule) {
                    $inStar = false;
                    $sawRule = false;
                }
                $inStar = $inStar || $value === '*';
                continue;
            }
            if (in_array($field, ['allow', 'disallow'], true)) {
                $sawRule = true;
                if ($inStar) {
                    $rules[] = [$field, $value];
                }
            }
        }
        return $rules;
    }

    /**
     * Other content using the same effective title or description.
     *
     * @return array{title: list<array>, description: list<array>}
     */
    public static function duplicates(int $contentId, string $title, string $description): array
    {
        $suffix = (string) CmsSiteConfig::value('title_suffix', '');
        $out = ['title' => [], 'description' => []];
        foreach ((new CmsContentRepository())->allLight() as $row) {
            if ((int) $row['id'] === $contentId || in_array($row['status'], ['archived'], true)) {
                continue;
            }
            if (($row['type'] ?? '') === 'city') {
                $row = CmsCityDefaults::apply($row);
                $row = CmsCityDefaults::applySeo($row, $row);
            }
            $otherTitle = trim((string) ($row['seo_title'] ?? '')) ?: trim((string) $row['title']) . $suffix;
            if ($title !== '' && mb_strtolower($otherTitle) === mb_strtolower($title)) {
                $out['title'][] = $row;
            }
            $otherDesc = trim((string) ($row['meta_description'] ?? ''));
            if ($description !== '' && $otherDesc !== '' && mb_strtolower($otherDesc) === mb_strtolower($description)) {
                $out['description'][] = $row;
            }
        }
        return $out;
    }
}
