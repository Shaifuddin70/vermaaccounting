<?php

declare(strict_types=1);

/**
 * Technical SEO checks across CMS content, redirects and sitemaps.
 * The results are checks against stated rules, not a prediction of rankings.
 */
final class CmsAudit
{
    public const CHECKS = [
        'title_missing' => 'Missing title',
        'title_duplicate' => 'Duplicate title',
        'title_length' => 'Title length',
        'description_missing' => 'Missing meta description',
        'description_duplicate' => 'Duplicate meta description',
        'description_length' => 'Description length',
        'h1_missing' => 'Missing H1',
        'h1_multiple' => 'Multiple H1',
        'heading_skip' => 'Heading hierarchy',
        'canonical_invalid' => 'Invalid canonical',
        'canonical_conflict' => 'Canonical conflict',
        'noindex_published' => 'Accidental noindex',
        'robots_blocked' => 'Blocked by robots.txt',
        'link_broken' => 'Broken internal link',
        'image_broken' => 'Broken image',
        'image_alt' => 'Missing image alt text',
        'image_oversized' => 'Oversized image',
        'orphan' => 'Orphan page',
        'redirect_chain' => 'Redirect chain',
        'redirect_loop' => 'Redirect loop',
        'redirect_target' => 'Redirect to missing page',
        'schema_invalid' => 'Invalid structured data',
        'sitemap_missing' => 'Published URL not in sitemap',
        'sitemap_error' => 'Sitemap URL not reachable',
        'city_duplicate' => 'Near-duplicate city page',
        'thin_content' => 'Thin content',
        'block_missing' => 'Missing block',
        'city_default' => 'City page on default content',
        'breadcrumbs_missing' => 'Missing breadcrumbs',
        'url_invalid' => 'Invalid URL',
    ];

    /**
     * @param array{content_id?: int, include_drafts?: bool} $options
     * @return list<array{severity: string, check: string, message: string, content_id: ?int, path: string, title: string}>
     */
    public static function run(array $options = []): array
    {
        $repo = new CmsContentRepository();
        $config = CmsSiteConfig::get();
        $only = (int) ($options['content_id'] ?? 0);
        $includeDrafts = !empty($options['include_drafts']) || $only > 0;
        $issues = [];
        $add = static function (string $severity, string $check, string $message, ?array $content = null, string $path = '') use (&$issues): void {
            $issues[] = [
                'severity' => $severity,
                'check' => $check,
                'message' => $message,
                'content_id' => $content ? (int) $content['id'] : null,
                'path' => $content ? (string) $content['path'] : $path,
                'title' => $content ? (string) $content['title'] : '',
            ];
        };

        $all = $repo->search(['include_archived' => false], 5000);
        $folders = [];
        foreach ((new CmsFolderRepository())->all() as $f) {
            $folders[$f['id']] = $f;
        }
        $titles = [];
        $descriptions = [];
        $effectiveById = [];
        $targets = [];
        foreach ($all as $content) {
            if (!$includeDrafts && !CmsContentRepository::isLive($content)) {
                continue;
            }
            $content = CmsCityDefaults::apply($content);
            $seo = CmsCityDefaults::applySeo($content, $repo->seo((int) $content['id']));
            $folder = $content['folder_id'] ? ($folders[$content['folder_id']] ?? null) : null;
            $eff = CmsSeo::effective($content, $seo, $folder, []);
            $effectiveById[$content['id']] = [$content, $seo, $eff];
            $titles[mb_strtolower($eff['title'])][] = $content;
            if ($eff['description_source'] === 'custom') {
                $descriptions[mb_strtolower($eff['description'])][] = $content;
            }
        }

        $linkStmt = Database::instance()->pdo()->query('SELECT content_id, target, target_path, is_internal, kind, anchor FROM cms_content_links');
        $linksByContent = [];
        $inbound = [];
        foreach ($linkStmt->fetchAll() as $link) {
            $linksByContent[(int) $link['content_id']][] = $link;
            if ((int) $link['is_internal'] === 1 && $link['kind'] === 'a') {
                $inbound[$link['target_path']][(int) $link['content_id']] = true;
            }
        }
        $legacyLinked = self::legacyLinkedPaths();
        $existsCache = [];
        $exists = static function (string $path) use (&$existsCache): bool {
            if (!array_key_exists($path, $existsCache)) {
                $file = PROJECT_ROOT . $path;
                $existsCache[$path] = $path === '/' || CmsRouter::exists($path) || (is_file($file) && !str_ends_with($path, '.php'));
            }
            return $existsCache[$path];
        };
        $sitemapPaths = [];
        $sitemapEntries = CmsSitemap::entries();
        foreach ($sitemapEntries as $urls) {
            foreach ($urls as $u) {
                $sitemapPaths[$u['path']] = true;
            }
        }

        foreach ($effectiveById as [$content, $seo, $eff]) {
            if ($only > 0 && (int) $content['id'] !== $only) {
                continue;
            }
            $live = CmsContentRepository::isLive($content);
            $body = (string) $content['body_html'];
            $template = CmsRenderer::templateFor($content);

            if (trim((string) $content['title']) === '') {
                $add('error', 'title_missing', 'Page has no title.', $content);
            }
            if (count($titles[mb_strtolower($eff['title'])] ?? []) > 1) {
                $add('warning', 'title_duplicate', 'Title "' . $eff['title'] . '" is used by ' . (count($titles[mb_strtolower($eff['title'])]) - 1) . ' other page(s).', $content);
            }
            if (mb_strlen($eff['title']) > CmsSeo::TITLE_MAX) {
                $add('info', 'title_length', 'Title is ' . mb_strlen($eff['title']) . ' characters (over ' . CmsSeo::TITLE_MAX . ').', $content);
            }
            if ($eff['description_source'] !== 'custom') {
                $add('warning', 'description_missing', 'No meta description; using the ' . $eff['description_source'] . '.', $content);
            } else {
                if (count($descriptions[mb_strtolower($eff['description'])] ?? []) > 1) {
                    $add('warning', 'description_duplicate', 'Meta description is shared with other pages.', $content);
                }
                $len = mb_strlen($eff['description']);
                if ($len < CmsSeo::DESC_MIN || $len > CmsSeo::DESC_MAX) {
                    $add('info', 'description_length', 'Meta description is ' . $len . ' characters (aim for ' . CmsSeo::DESC_MIN . '–' . CmsSeo::DESC_MAX . ').', $content);
                }
            }

            $headings = CmsHtml::headings($body);
            $bodyH1 = count(array_filter($headings, static fn ($h) => $h['level'] === 1));
            if ($template === 'full-width' && $bodyH1 === 0) {
                $add('error', 'h1_missing', 'The body has no H1. The page shows only the body, so add one Heading 1.', $content);
            } elseif ($template !== 'full-width' && $bodyH1 > 0 && $content['type'] !== 'article') {
                $add('warning', 'h1_multiple', 'Body contains an H1 in addition to the page heading; use H2 in the body.', $content);
            } elseif ($bodyH1 > 1) {
                $add('warning', 'h1_multiple', 'Body contains ' . $bodyH1 . ' H1 headings.', $content);
            }
            $prev = $template === 'full-width' ? 0 : 1;
            foreach ($headings as $h) {
                if ($h['level'] > $prev + 1) {
                    $add('info', 'heading_skip', 'Heading "' . mb_substr($h['text'], 0, 60) . '" jumps from H' . $prev . ' to H' . $h['level'] . '.', $content);
                    break;
                }
                $prev = $h['level'];
            }

            if (($seo['canonical_mode'] ?? 'auto') === 'custom') {
                $check = CmsSeo::validateCanonical((string) $seo['canonical_url'], CmsSiteConfig::baseUrl());
                if ($check !== null) {
                    $add('error', 'canonical_invalid', 'Custom canonical ' . $check, $content);
                } else {
                    $parsed = CmsPath::parseTarget((string) $seo['canonical_url'], CmsSiteConfig::baseUrl());
                    if ($parsed['internal']) {
                        $target = strtok($parsed['value'], '?') ?: '/';
                        if (!$exists($target)) {
                            $add('error', 'canonical_conflict', 'Canonical points to ' . $target . ', which does not resolve.', $content);
                        } elseif ((new CmsRedirectRepository())->findBySource($target)) {
                            $add('warning', 'canonical_conflict', 'Canonical points to ' . $target . ', which redirects.', $content);
                        } else {
                            $other = $repo->findByPath($target);
                            if ($other) {
                                $otherSeo = $repo->seo((int) $other['id']);
                                if (($otherSeo['canonical_mode'] ?? 'auto') === 'custom') {
                                    $add('warning', 'canonical_conflict', 'Canonical points to ' . $target . ', which itself canonicalises elsewhere (chain).', $content);
                                }
                            }
                        }
                    }
                }
            }
            if ($live && $eff['index'] === 'noindex') {
                $add('error', 'noindex_published', 'Published page is noindex (' . $eff['index_source'] . ' setting).', $content);
            }
            if (CmsSeo::blockedByRobotsTxt((string) $content['path'], (string) $config['robots_txt'])) {
                $add('error', 'robots_blocked', 'robots.txt blocks this URL.', $content);
            }

            foreach ($linksByContent[(int) $content['id']] ?? [] as $link) {
                if ((int) $link['is_internal'] !== 1) {
                    continue;
                }
                $target = (string) $link['target_path'];
                if ($link['kind'] === 'img') {
                    if (!is_file(PROJECT_ROOT . rawurldecode($target))) {
                        $add('error', 'image_broken', 'Image ' . $target . ' does not exist.', $content);
                    }
                } elseif (!$exists($target)) {
                    $add('error', 'link_broken', 'Link to ' . $target . ' ("' . mb_substr((string) $link['anchor'], 0, 40) . '") is broken.', $content);
                } elseif ((new CmsRedirectRepository())->findBySource($target)) {
                    $add('info', 'link_broken', 'Link to ' . $target . ' goes through a redirect; link to the final URL instead.', $content);
                }
            }
            foreach (CmsHtml::images($body) as $img) {
                if ($img['alt'] === null || ($img['alt'] === '' && !preg_match('/role=/', $body))) {
                    $add('warning', 'image_alt', 'Image ' . mb_substr($img['src'], 0, 80) . ' has no alt text.', $content);
                }
            }
            $featured = CmsRenderer::featuredImage($content);
            if ($featured !== null && $featured['alt'] === '') {
                $add('warning', 'image_alt', 'Featured image has no alt text.', $content);
            }

            $path = (string) $content['path'];
            if ($live && $content['type'] !== 'article' && !isset($inbound[$path]) && !isset($legacyLinked[$path]) && (int) $config['home_content_id'] !== (int) $content['id']) {
                $add('info', 'orphan', 'No other CMS page or site menu links here.', $content);
            }
            if ($live && $eff['index'] === 'index' && !isset($sitemapPaths[$path])) {
                $add('info', 'sitemap_missing', 'Indexable published page is not in the sitemap (' . ((int) ($seo['sitemap_include'] ?? 1) === 0 ? 'sitemap inclusion is off' : 'canonical points elsewhere or type excluded') . ').', $content);
            }
            if (CmsPath::validate($path) !== [] && (int) $config['home_content_id'] !== (int) $content['id']) {
                $add('error', 'url_invalid', 'URL fails validation: ' . implode(' ', CmsPath::validate($path)), $content);
            }
            foreach ((array) ($seo['schema']['custom'] ?? []) as $block) {
                $errs = CmsStructuredData::validateObject(is_array($block) ? $block : []);
                if ($errs) {
                    $add('error', 'schema_invalid', 'Custom structured data: ' . implode('; ', $errs), $content);
                }
            }
            $words = CmsHtml::wordCount($body);
            $usesCityDefault = in_array('body_html', (array) ($content['city_defaults'] ?? []), true);
            if ($usesCityDefault) {
                $add('info', 'city_default', 'Uses the default city content, shared with other city pages.', $content);
            } elseif ($template !== 'article-index' && $words < (int) $config['thin_content_words'] && in_array($content['type'], ['article', 'city'], true)) {
                $add('warning', 'thin_content', 'Only ' . $words . ' words of body content.', $content);
            }
            foreach (CmsBlocks::missingTokens((string) $content['body_html']) as $token) {
                $add('error', 'block_missing', CmsBlocks::tokenTag($token) . ' does not exist or is disabled, so nothing shows in its place.', $content);
            }
            if ($content['type'] === 'city') {
                foreach (CmsContentService::cityQualityWarnings((int) $content['id']) as $w) {
                    if (str_starts_with($w, 'Too similar')) {
                        $add('warning', 'city_duplicate', $w, $content);
                    }
                }
            }
        }

        if ($only === 0) {
            $redirects = new CmsRedirectRepository();
            foreach ($redirects->all() as $r) {
                if ((int) $r['status_code'] === 410) {
                    continue;
                }
                $trace = $redirects->trace((string) $r['source_path']);
                if ($trace['loop']) {
                    $add('error', 'redirect_loop', 'Redirect loop: ' . implode(' → ', $trace['hops']), null, (string) $r['source_path']);
                } elseif (count($trace['hops']) > 2) {
                    $add('warning', 'redirect_chain', 'Redirect chain of ' . (count($trace['hops']) - 1) . ' hops: ' . implode(' → ', $trace['hops']), null, (string) $r['source_path']);
                }
                $final = (string) $trace['final'];
                if (!$trace['loop'] && $final !== '' && $final[0] === '/' && !$exists(strtok($final, '?') ?: '/')) {
                    $add('error', 'redirect_target', 'Redirect ends at ' . $final . ', which returns 404.', null, (string) $r['source_path']);
                }
            }
            foreach ($sitemapEntries as $urls) {
                foreach ($urls as $u) {
                    if (!$exists($u['path'])) {
                        $add('error', 'sitemap_error', 'Sitemap lists ' . $u['path'] . ', which does not resolve.', null, $u['path']);
                    }
                }
            }
            foreach ((new CmsMediaRepository())->search('', 500) as $m) {
                if ($m['oversized']) {
                    $add('info', 'image_oversized', 'Media file ' . $m['url'] . ' is ' . round($m['bytes'] / 1024) . ' KB; compress it for faster pages.', null, $m['url']);
                }
                if (trim((string) $m['alt']) === '') {
                    $add('info', 'image_alt', 'Media library image ' . $m['url'] . ' has no default alt text.', null, $m['url']);
                }
            }
        }

        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, static fn ($a, $b) => [$order[$a['severity']], $a['path']] <=> [$order[$b['severity']], $b['path']]);
        return $issues;
    }

    /** Paths linked from the shared header/footer of the hand-written site (treated as navigation, not orphans). */
    private static function legacyLinkedPaths(): array
    {
        $out = [];
        foreach (['site-nav.php', 'site-footer.php'] as $file) {
            $src = (string) @file_get_contents(PROJECT_ROOT . '/components/' . $file);
            if (preg_match_all('/(?:href="|\'url\' => \')(\/[^"\'#?]*)/', $src, $m)) {
                foreach ($m[1] as $href) {
                    $out[CmsPath::normalize($href)] = true;
                }
            }
        }
        foreach (CmsNavigation::paths() as $path) {
            $out[$path] = true;
        }
        $nav = (string) CmsSiteConfig::value('header_html', '') . (string) CmsSiteConfig::value('footer_html', '');
        if (preg_match_all('/href="(\/[^"#?]*)"/', $nav, $m)) {
            foreach ($m[1] as $href) {
                $out[CmsPath::normalize($href)] = true;
            }
        }
        return $out;
    }

    /**
     * Internal link counts between live CMS pages, fewest inbound links first.
     *
     * @return list<array{id: int, path: string, title: string, type: string, inbound: int, outbound: int, menu: bool}>
     */
    public static function linkGraph(): array
    {
        $repo = new CmsContentRepository();
        $homeId = (int) CmsSiteConfig::value('home_content_id', 0);
        $pages = [];
        foreach ($repo->published(null, 5000) as $c) {
            $pages[(int) $c['id']] = $c;
        }
        $inbound = [];
        $outbound = [];
        $rows = Database::instance()->pdo()->query("SELECT DISTINCT content_id, target_path FROM cms_content_links WHERE is_internal = 1 AND kind = 'a'")->fetchAll();
        foreach ($rows as $r) {
            $from = (int) $r['content_id'];
            if (!isset($pages[$from])) {
                continue;
            }
            $outbound[$from] = ($outbound[$from] ?? 0) + 1;
            if ((string) $r['target_path'] !== (string) $pages[$from]['path']) {
                $inbound[(string) $r['target_path']][$from] = true;
            }
        }
        $menu = self::legacyLinkedPaths();
        $out = [];
        foreach ($pages as $id => $c) {
            $path = (string) $c['path'];
            $links = $inbound[$path] ?? [];
            if ($id === $homeId) {
                $links += $inbound['/'] ?? [];
            }
            $out[] = [
                'id' => $id,
                'path' => $id === $homeId ? '/' : $path,
                'title' => CmsCityDefaults::label($c),
                'type' => (string) $c['type'],
                'inbound' => count($links),
                'outbound' => $outbound[$id] ?? 0,
                'menu' => $id === $homeId || isset($menu[$path]) || $c['type'] === 'article',
            ];
        }
        usort($out, static fn ($a, $b) => [$a['menu'], $a['inbound'], $a['path']] <=> [$b['menu'], $b['inbound'], $b['path']]);
        return $out;
    }

    /** @return array{error: int, warning: int, info: int} */
    public static function summary(array $issues): array
    {
        $out = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($issues as $i) {
            $out[$i['severity']]++;
        }
        return $out;
    }
}
