<?php

declare(strict_types=1);

/**
 * Editor save workflow: validation, URL changes, redirects, publishing rules, revisions and link indexing.
 * Every multi-table change runs in one transaction.
 */
final class CmsContentService
{
    public const ACTIONS = ['save', 'review', 'publish', 'schedule', 'unpublish', 'archive'];

    private CmsContentRepository $repo;

    public function __construct(?CmsContentRepository $repo = null)
    {
        $this->repo = $repo ?? new CmsContentRepository();
    }

    /**
     * @param array $input editor POST fields
     * @param array{edit: bool, publish: bool, seo: bool, code: bool} $caps
     * @return array{id: int, errors: list<string>, notices: list<string>}
     */
    public function save(array $input, array $caps): array
    {
        $errors = [];
        $notices = [];
        $id = (int) ($input['id'] ?? 0);
        $existing = $id > 0 ? $this->repo->find($id) : null;
        if ($id > 0 && $existing === null) {
            return ['id' => 0, 'errors' => ['That content no longer exists.'], 'notices' => []];
        }
        if (!$caps['edit']) {
            return ['id' => $id, 'errors' => ['You do not have permission to edit website content.'], 'notices' => []];
        }

        $action = (string) ($input['workflow_action'] ?? 'save');
        if (!in_array($action, self::ACTIONS, true)) {
            $action = 'save';
        }
        $publishingAction = in_array($action, ['publish', 'schedule', 'unpublish', 'archive'], true);
        if ($publishingAction && !$caps['publish']) {
            $errors[] = 'You need publishing permission to ' . $action . ' content.';
        }
        if ($existing && in_array($existing['status'], ['published', 'scheduled'], true) && !$caps['publish']) {
            $errors[] = 'This page is live or scheduled; changes need publishing permission. Ask a publisher, or save your changes as a revision note.';
        }

        $type = $existing['type'] ?? (string) ($input['type'] ?? 'page');
        if (!isset(CmsContentRepository::TYPES[$type])) {
            $errors[] = 'Unknown content type.';
            $type = 'page';
        }

        $title = trim((string) ($input['title'] ?? ''));
        $cityName = $type === 'city' ? trim((string) ($input['city_city'] ?? '')) : '';
        if ($title === '' && $type !== 'city') {
            $errors[] = 'Title is required.';
        }
        if (mb_strlen($title) > 500) {
            $errors[] = 'Title is too long.';
        }
        $internalName = trim((string) ($input['internal_name'] ?? '')) ?: ($title !== '' ? $title : $cityName);
        $h1 = trim((string) ($input['h1'] ?? ''));

        // Folder
        $folderId = (int) ($input['folder_id'] ?? 0) ?: null;
        $folder = null;
        if ($folderId !== null) {
            $folder = (new CmsFolderRepository())->find($folderId);
            if ($folder === null) {
                $errors[] = 'Selected folder does not exist.';
                $folderId = null;
            } elseif ($id > 0) {
                $folders = new CmsFolderRepository();
                $own = $folders->forLanding($id);
                if ($own !== null && $folders->wouldCreateCycle((int) $own['id'], $folderId)) {
                    $errors[] = 'A page cannot be placed under itself or one of its own sub-pages.';
                }
            }
        }

        // URL
        $slug = CmsPath::slugify((string) ($input['slug'] ?? '')) ?: CmsPath::slugify($title !== '' ? $title : $cityName);
        $changeUrl = $existing === null || !empty($input['change_url']);
        $path = (string) ($existing['path'] ?? '');
        if ($changeUrl) {
            if (($input['url_mode'] ?? 'auto') === 'custom') {
                $path = CmsPath::normalize((string) ($input['custom_path'] ?? ''));
                $segments = CmsPath::segments($path);
                $slug = $segments ? rawurldecode((string) end($segments)) : $slug;
            } else {
                $path = CmsPath::join((string) ($folder['url_prefix'] ?? ''), $slug);
            }
            if ($slug === '' && ($input['url_mode'] ?? 'auto') !== 'custom') {
                if ($title !== '' || $cityName !== '') {
                    $errors[] = 'Enter a slug using letters or numbers for the URL.';
                }
            } else {
                foreach (CmsPath::validate($path) as $e) {
                    $errors[] = $e;
                }
            }
            if ($this->repo->pathTaken($path, $id ?: null)) {
                $other = $this->repo->findByPath($path);
                $errors[] = 'The URL ' . $path . ' is already used by "' . ($other['title'] ?? 'another item') . '".';
            }
            if (CmsPath::legacyPageExists($path)) {
                $notices[] = 'Once published, this replaces the existing hand-written page at ' . $path . '.';
            }
        }

        // Body
        $bodyMode = (($input['body_mode'] ?? 'sanitized') === 'raw' && $caps['code']) ? 'raw' : 'sanitized';
        if (($existing['body_mode'] ?? '') === 'raw' && !$caps['code'] && isset($input['body_html'])) {
            $errors[] = 'This page uses unfiltered HTML; only users with code permission can edit its body.';
        }
        $bodyRaw = str_replace("\r\n", "\n", (string) ($input['body_html'] ?? ''));
        if (preg_match('/<\?(php|=)?/i', $bodyRaw)) {
            $errors[] = 'PHP code is not allowed in page content.';
        }
        $body = $bodyMode === 'raw' ? $bodyRaw : CmsHtml::sanitize($bodyRaw);
        if ($bodyMode === 'sanitized' && $bodyRaw !== '' && self::stripsScripts($bodyRaw)) {
            $notices[] = 'Scripts, event handlers and unsupported embeds were removed from the body.';
        }

        // Publishing dates
        $publishAt = function_exists('app_parse_local_datetime') ? app_parse_local_datetime((string) ($input['publish_at'] ?? '')) : null;
        $expireAt = function_exists('app_parse_local_datetime') ? app_parse_local_datetime((string) ($input['expire_at'] ?? '')) : null;
        $now = gmdate('Y-m-d H:i:s');
        if ($expireAt !== null && $publishAt !== null && $expireAt <= $publishAt) {
            $errors[] = 'Expiry must be after the publish date.';
        }
        $status = $existing['status'] ?? 'draft';
        $publishedAt = $existing['published_at'] ?? null;
        switch ($action) {
            case 'review':
                $status = 'review';
                break;
            case 'publish':
                if ($publishAt !== null && $publishAt > $now) {
                    $status = 'scheduled';
                    $notices[] = 'Publish date is in the future, so the page was scheduled.';
                } else {
                    $status = 'published';
                    $publishedAt = $publishedAt ?: ($publishAt ?? $now);
                }
                if ($expireAt !== null && $expireAt <= $now) {
                    $errors[] = 'Expiry date is already in the past.';
                }
                break;
            case 'schedule':
                if ($publishAt === null || $publishAt <= $now) {
                    $errors[] = 'Choose a future publish date to schedule.';
                }
                $status = 'scheduled';
                break;
            case 'unpublish':
                $status = 'unpublished';
                break;
            case 'archive':
                $status = 'archived';
                break;
        }
        if ($type === 'city' && in_array($status, ['published', 'scheduled'], true) && ($existing['status'] ?? '') !== $status) {
            $originDraft = str_contains((string) ($existing['internal_notes'] ?? ''), '[generated]')
                || str_contains((string) ($input['internal_notes'] ?? ''), '[generated]');
            if ($originDraft && empty($input['reviewed'])) {
                $errors[] = 'This city page was generated from a template. Tick "I have reviewed this page" before publishing.';
            }
        }

        // Author
        $authorId = (int) ($input['author_user_id'] ?? 0) ?: null;
        $authorName = trim((string) ($input['author_name'] ?? ''));
        if ($authorId !== null && $authorName === '') {
            $user = (new UserRepository())->find($authorId);
            $authorName = (string) ($user['name'] ?? '');
        }

        // Template
        $template = CmsRenderer::templateFor(['type' => $type, 'template' => (string) ($input['template'] ?? '')]);

        $language = (string) ($input['language'] ?? 'en-CA');
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $language)) {
            $errors[] = 'Language must be a code like en or en-CA.';
        }

        $faqs = self::collectFaqs($input);

        $featuredUrl = trim((string) ($input['featured_image_url'] ?? ''));
        if ($featuredUrl !== '' && !preg_match('#^(https://|/)[^\s"<>]+$#', $featuredUrl)) {
            $errors[] = 'Featured image URL must be https:// or a /path.';
        }

        // SEO
        $seoData = null;
        $seoErrors = [];
        if ($caps['seo'] || $existing === null) {
            [$seoData, $seoErrors] = self::collectSeo($input, $caps['seo']);
            $errors = array_merge($errors, $seoErrors);
        }

        $cityData = $type === 'city' ? self::collectCity($input, $errors) : null;

        if ($errors !== []) {
            return ['id' => $id, 'errors' => array_values(array_unique($errors)), 'notices' => $notices];
        }

        $row = [
            'type' => $type,
            'folder_id' => $folderId,
            'internal_name' => mb_substr($internalName, 0, 255),
            'title' => $title,
            'h1' => $h1,
            'slug' => $slug,
            'path' => $path,
            'body_html' => $body,
            'body_mode' => $bodyMode,
            'excerpt' => trim((string) ($input['excerpt'] ?? '')),
            'featured_media_id' => (int) ($input['featured_media_id'] ?? 0) ?: null,
            'featured_image_url' => $featuredUrl,
            'featured_alt' => mb_substr(trim((string) ($input['featured_alt'] ?? '')), 0, 500),
            'author_user_id' => $authorId,
            'author_name' => mb_substr($authorName, 0, 191),
            'template' => $template,
            'language' => $language,
            'status' => $status,
            'publish_at' => $publishAt,
            'expire_at' => $expireAt,
            'published_at' => $publishedAt,
            'internal_notes' => !empty($input['reviewed'])
                ? str_replace('[generated]', '[reviewed]', (string) ($input['internal_notes'] ?? ''))
                : (string) ($input['internal_notes'] ?? ''),
            'faqs_json' => json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_by' => Auth::userId(),
        ];
        if (isset($input['body_html']) === false && $existing) {
            unset($row['body_html'], $row['body_mode']);
        }

        $pdo = $this->repo->pdo();
        $pdo->beginTransaction();
        try {
            if ($existing === null) {
                $row['created_by'] = Auth::userId();
                $id = $this->repo->create($row);
            } else {
                $this->repo->update($id, $row);
            }

            if ($seoData !== null) {
                $this->repo->saveSeo($id, $seoData);
            }
            if ($cityData !== null) {
                $this->repo->saveCity($id, $cityData);
            }
            if ($type === 'article' || isset($input['term_ids']) || isset($input['tags'])) {
                $this->repo->setContentTerms($id, $this->collectTerms($input));
            }

            $redirects = new CmsRedirectRepository();
            if ($existing && $path !== $existing['path']) {
                $this->repo->recordUrlChange($id, (string) $existing['path'], $path);
                $wasPublic = !empty($existing['published_at']);
                if ($wasPublic && !empty($input['create_redirect'])) {
                    $this->retargetChains($redirects, (string) $existing['path'], $path);
                    $redirects->save(null, (string) $existing['path'], $path, 301, 'auto', 'URL changed for content #' . $id);
                    $notices[] = 'Added a 301 redirect from ' . $existing['path'] . ' to ' . $path . '.';
                } elseif ($wasPublic) {
                    $notices[] = 'The old URL ' . $existing['path'] . ' now returns 404. Add a redirect in the Redirect Manager if it had visitors or links.';
                }
            }
            $shadow = $redirects->findBySource($path);
            if ($shadow) {
                $redirects->delete((int) $shadow['id']);
                $notices[] = 'Removed the redirect from ' . $path . ' so this content can be reached.';
            }

            $indexed = $row['body_html'] ?? (string) ($existing['body_html'] ?? '');
            if ($type === 'city' && CmsCityDefaults::bodyIsEmpty($indexed) && $cityData !== null) {
                $indexed = CmsCityDefaults::fill('body_html', $cityData);
            }
            $this->indexLinks($id, $indexed);
            $this->repo->addRevision($id, $this->snapshot($id), trim((string) ($input['revision_comment'] ?? '')) ?: ucfirst($action === 'save' ? 'saved' : $action));
            $this->repo->pruneRevisions($id);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        CmsCache::flush();
        $logAction = match (true) {
            $existing === null => 'cms.created',
            $action === 'publish' && $status === 'published' => 'cms.published',
            $action === 'schedule' || $status === 'scheduled' && ($existing['status'] ?? '') !== 'scheduled' => 'cms.scheduled',
            $action === 'unpublish' => 'cms.unpublished',
            $action === 'archive' => 'cms.archived',
            default => 'cms.updated',
        };
        ActivityLog::record($logAction, 'cms_content', $id, ['path' => $path, 'type' => $type, 'status' => $status]);

        if ($type === 'city' && !CmsCityDefaults::bodyIsEmpty((string) ($row['body_html'] ?? $existing['body_html'] ?? ''))) {
            $quality = self::cityQualityWarnings($id);
            if ($quality !== []) {
                $notices[] = implode(' ', $quality) . ' See the Location tab.';
            }
        }
        $missingBlocks = CmsBlocks::missingTokens($body);
        if ($missingBlocks !== []) {
            $notices[] = 'Unknown or disabled block ' . implode(', ', array_map([CmsBlocks::class, 'tokenTag'], $missingBlocks)) . ' shows nothing until it exists and is active.';
        }
        return ['id' => $id, 'errors' => [], 'notices' => $notices];
    }

    /** Change workflow status only (list actions). */
    public function setStatus(int $id, string $action, array $caps): array
    {
        $content = $this->repo->find($id);
        if ($content === null) {
            return ['errors' => ['Content not found.']];
        }
        if (!$caps['publish']) {
            return ['errors' => ['You need publishing permission.']];
        }
        $now = gmdate('Y-m-d H:i:s');
        $update = match ($action) {
            'publish' => ['status' => 'published', 'published_at' => $content['published_at'] ?: $now],
            'unpublish' => ['status' => 'unpublished'],
            'archive' => ['status' => 'archived'],
            'restore' => ['status' => 'draft'],
            default => null,
        };
        if ($update === null) {
            return ['errors' => ['Unknown action.']];
        }
        if ($action === 'publish' && !empty($content['expire_at']) && $content['expire_at'] <= $now) {
            return ['errors' => ['The expiry date has passed; edit the page to change it before publishing.']];
        }
        if ($action === 'publish' && $content['type'] === 'city' && str_contains((string) $content['internal_notes'], '[generated]')) {
            return ['errors' => ['Generated city pages must be reviewed and published from the editor.']];
        }
        $this->repo->update($id, $update);
        $this->repo->addRevision($id, $this->snapshot($id), ucfirst($action));
        CmsCache::flush();
        $logAction = ['publish' => 'published', 'unpublish' => 'unpublished', 'archive' => 'archived', 'restore' => 'restored'][$action];
        ActivityLog::record('cms.' . $logAction, 'cms_content', $id, ['path' => $content['path']]);
        return ['errors' => []];
    }

    public const BULK_ACTIONS = [
        'publish' => 'Publish',
        'draft' => 'Move to draft',
        'unpublish' => 'Unpublish',
        'archive' => 'Archive',
        'delete' => 'Delete permanently',
    ];

    /**
     * Apply one status change or deletion to many items. Items already in the target state are skipped.
     *
     * @param list<int|string> $ids
     * @return array{done: int, skipped: int, errors: list<string>}
     */
    public function bulk(array $ids, string $action, array $caps): array
    {
        $out = ['done' => 0, 'skipped' => 0, 'errors' => []];
        if (!isset(self::BULK_ACTIONS[$action])) {
            $out['errors'][] = 'Choose what to do with the selected items.';
            return $out;
        }
        if (!$caps['publish']) {
            $out['errors'][] = 'You need publishing permission to change or delete content in bulk.';
            return $out;
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0))), 0, 1000);
        if ($ids === []) {
            $out['errors'][] = 'Select at least one item.';
            return $out;
        }
        $homeId = (int) CmsSiteConfig::value('home_content_id', 0);
        $skip = [
            'publish' => ['published'],
            'draft' => ['draft'],
            'unpublish' => ['draft', 'review', 'unpublished', 'archived'],
            'archive' => ['archived'],
        ];
        foreach ($ids as $id) {
            $content = $this->repo->find($id);
            if ($content === null || in_array($content['status'], $skip[$action] ?? [], true)) {
                $out['skipped']++;
                continue;
            }
            $label = '“' . CmsCityDefaults::label($content) . '”';
            if ($action === 'delete') {
                if ($id === $homeId) {
                    $out['errors'][] = $label . ' is the homepage and was not deleted.';
                    continue;
                }
                $this->delete($id);
                $out['done']++;
                continue;
            }
            $result = $this->setStatus($id, $action === 'draft' ? 'restore' : $action, $caps);
            if ($result['errors']) {
                $out['errors'][] = $label . ': ' . implode(' ', $result['errors']);
            } else {
                $out['done']++;
            }
        }
        return $out;
    }

    public function delete(int $id): void
    {
        $content = $this->repo->find($id);
        if ($content === null) {
            return;
        }
        $pdo = $this->repo->pdo();
        $pdo->beginTransaction();
        try {
            $this->repo->delete($id);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        if ((int) CmsSiteConfig::value('home_content_id', 0) === $id) {
            CmsSiteConfig::save(['home_content_id' => 0], true, 'Homepage content deleted');
        }
        CmsCache::flush();
        ActivityLog::record('cms.deleted', 'cms_content', $id, ['path' => $content['path'], 'title' => $content['title']]);
    }

    /** Full state used for revisions. */
    public function snapshot(int $id): array
    {
        $content = $this->repo->find($id) ?? [];
        unset($content['faqs']);
        return [
            'content' => $content,
            'seo' => array_diff_key($this->repo->seo($id), ['schema' => 1, 'content_id' => 1, 'updated_at' => 1]),
            'city' => ($content['type'] ?? '') === 'city' ? array_diff_key($this->repo->city($id), array_flip(['related_services', 'hours', 'nearby', 'cta', 'content_id'])) : null,
            'term_ids' => array_map(static fn ($t) => (int) $t['id'], $this->repo->contentTerms($id)),
        ];
    }

    /** Restore a revision's content, SEO and city data. The URL and status are kept as they are now. */
    public function restoreRevision(int $revisionId, array $caps): array
    {
        $rev = $this->repo->revision($revisionId);
        if ($rev === null) {
            return ['errors' => ['Revision not found.']];
        }
        $id = (int) $rev['content_id'];
        $current = $this->repo->find($id);
        if ($current === null) {
            return ['errors' => ['Content not found.']];
        }
        if (in_array($current['status'], ['published', 'scheduled'], true) && !$caps['publish']) {
            return ['errors' => ['Restoring a live page needs publishing permission.']];
        }
        $snap = $rev['snapshot'];
        if (!isset($snap['content'])) {
            return ['errors' => ['Autosaves are opened in the editor rather than restored directly.']];
        }
        $content = $snap['content'];
        if (($content['body_mode'] ?? '') === 'raw' && !$caps['code']) {
            return ['errors' => ['This revision contains unfiltered HTML; only users with code permission can restore it.']];
        }
        foreach (['id', 'path', 'slug', 'status', 'published_at', 'publish_at', 'expire_at', 'preview_token', 'created_at', 'created_by', 'type'] as $keep) {
            unset($content[$keep]);
        }
        $pdo = $this->repo->pdo();
        $pdo->beginTransaction();
        try {
            $content['updated_by'] = Auth::userId();
            $this->repo->update($id, $content);
            if (!empty($snap['seo']) && $caps['seo']) {
                $this->repo->saveSeo($id, $snap['seo']);
            }
            if (!empty($snap['city'])) {
                $this->repo->saveCity($id, $snap['city']);
            }
            if (isset($snap['term_ids'])) {
                $this->repo->setContentTerms($id, $snap['term_ids']);
            }
            $this->indexLinks($id, (string) ($content['body_html'] ?? ''));
            $this->repo->addRevision($id, $this->snapshot($id), 'Restored revision from ' . $rev['created_at']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        CmsCache::flush();
        ActivityLog::record('cms.revision_restored', 'cms_content', $id, ['revision' => $revisionId]);
        return ['errors' => [], 'id' => $id];
    }

    public function previewToken(int $id, bool $revoke = false): ?string
    {
        $token = $revoke ? null : bin2hex(random_bytes(20));
        $this->repo->update($id, ['preview_token' => $token]);
        ActivityLog::record($revoke ? 'cms.preview_revoked' : 'cms.preview_created', 'cms_content', $id);
        return $token;
    }

    /** Point existing redirects that end at $old directly to $new, so no chain is created. */
    private function retargetChains(CmsRedirectRepository $redirects, string $old, string $new): void
    {
        foreach ($redirects->all() as $r) {
            if ((string) $r['target'] === $old && (string) $r['source_path'] !== $new) {
                $redirects->save((int) $r['id'], (string) $r['source_path'], $new, (int) $r['status_code'], (string) $r['origin'], (string) $r['note']);
            }
        }
        $loop = $redirects->findBySource($new);
        if ($loop && (string) $loop['target'] === $old) {
            $redirects->delete((int) $loop['id']);
        }
    }

    public function indexLinks(int $id, string $html): void
    {
        $pdo = $this->repo->pdo();
        $pdo->prepare('DELETE FROM cms_content_links WHERE content_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('INSERT INTO cms_content_links (content_id, target, target_path, is_internal, kind, anchor) VALUES (?, ?, ?, ?, ?, ?)');
        $base = CmsSiteConfig::baseUrl();
        foreach (CmsHtml::links($html) as $link) {
            if (str_starts_with($link['href'], '{')) {
                continue;
            }
            $parsed = CmsPath::parseTarget($link['href'], $base);
            $internal = $parsed['ok'] && $parsed['internal'];
            $stmt->execute([$id, mb_substr($link['href'], 0, 1000), $internal ? mb_substr(strtok($parsed['value'], '?') ?: '/', 0, 500) : '', $internal ? 1 : 0, 'a', mb_substr($link['text'], 0, 500)]);
        }
        foreach (CmsHtml::images($html) as $img) {
            $parsed = CmsPath::parseTarget($img['src'], $base);
            $internal = $parsed['ok'] && $parsed['internal'];
            $stmt->execute([$id, mb_substr($img['src'], 0, 1000), $internal ? mb_substr(strtok($parsed['value'], '?') ?: '/', 0, 500) : '', $internal ? 1 : 0, 'img', mb_substr((string) $img['alt'], 0, 500)]);
        }
    }

    private static function stripsScripts(string $html): bool
    {
        return (bool) preg_match('/<script\b|\son[a-z]+\s*=|javascript:/i', $html);
    }

    /** @return list<array{question: string, answer: string}> */
    public static function collectFaqs(array $input): array
    {
        $out = [];
        $questions = (array) ($input['faq_q'] ?? []);
        $answers = (array) ($input['faq_a'] ?? []);
        foreach ($questions as $i => $q) {
            $q = trim((string) $q);
            $a = trim((string) ($answers[$i] ?? ''));
            if ($q !== '' && $a !== '') {
                $out[] = ['question' => mb_substr($q, 0, 500), 'answer' => mb_substr($a, 0, 4000)];
            }
        }
        return $out;
    }

    /** @return array{0: array, 1: list<string>} */
    public static function collectSeo(array $input, bool $canSeo): array
    {
        $errors = [];
        if (!$canSeo) {
            return [CmsContentRepository::defaultSeo() + ['schema_json' => ''], []];
        }
        $pickEnum = static fn (string $key, array $allowed, string $default) => in_array((string) ($input[$key] ?? ''), $allowed, true) ? (string) ($input[$key] ?? '') : $default;
        $intOrNull = static function ($v): ?int {
            $v = trim((string) $v);
            return $v === '' ? null : (int) $v;
        };
        $canonicalMode = $pickEnum('canonical_mode', ['auto', 'custom'], 'auto');
        $canonicalUrl = trim((string) ($input['canonical_url'] ?? ''));
        if ($canonicalMode === 'custom') {
            $check = CmsSeo::validateCanonical($canonicalUrl, CmsSiteConfig::baseUrl());
            if ($check !== null) {
                $errors[] = 'Canonical URL: ' . $check;
            }
        }
        $ogImageUrl = trim((string) ($input['og_image_url'] ?? ''));
        if ($ogImageUrl !== '' && !preg_match('#^(https://|/)[^\s"<>]+$#', $ogImageUrl)) {
            $errors[] = 'Social image URL must be https:// or a /path.';
        }
        $schema = ['disabled' => array_values(array_intersect((array) ($input['schema_disabled'] ?? []), array_keys(CmsStructuredData::TYPES)))];
        $custom = CmsStructuredData::parseCustom((string) ($input['schema_custom'] ?? ''));
        $errors = array_merge($errors, $custom['errors']);
        if ($custom['data']) {
            $schema['custom'] = $custom['data'];
        }
        $maxSnippet = $intOrNull($input['max_snippet'] ?? '');
        $maxVideo = $intOrNull($input['max_video_preview'] ?? '');
        if ($maxSnippet !== null && $maxSnippet < -1) {
            $errors[] = 'Max snippet must be -1 (no limit), 0 (no snippet) or a positive number.';
        }
        return [[
            'seo_title' => mb_substr(trim((string) ($input['seo_title'] ?? '')), 0, 500),
            'meta_description' => trim((string) ($input['meta_description'] ?? '')),
            'robots_index' => $pickEnum('robots_index', ['inherit', 'index', 'noindex'], 'inherit'),
            'robots_follow' => $pickEnum('robots_follow', ['inherit', 'follow', 'nofollow'], 'inherit'),
            'max_snippet' => $maxSnippet,
            'max_image_preview' => $pickEnum('max_image_preview', ['', 'none', 'standard', 'large'], ''),
            'max_video_preview' => $maxVideo,
            'robots_extra' => implode(',', CmsSeo::extraDirectives(implode(',', (array) ($input['robots_extra'] ?? [])))),
            'sitemap_include' => empty($input['sitemap_include']) ? 0 : 1,
            'canonical_mode' => $canonicalMode,
            'canonical_url' => $canonicalUrl,
            'og_title' => mb_substr(trim((string) ($input['og_title'] ?? '')), 0, 500),
            'og_description' => trim((string) ($input['og_description'] ?? '')),
            'og_image_id' => (int) ($input['og_image_id'] ?? 0) ?: null,
            'og_image_url' => $ogImageUrl,
            'og_image_alt' => mb_substr(trim((string) ($input['og_image_alt'] ?? '')), 0, 500),
            'og_type' => $pickEnum('og_type', ['', 'website', 'article', 'profile'], ''),
            'twitter_card' => $pickEnum('twitter_card', ['', 'summary', 'summary_large_image'], ''),
            'schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $errors];
    }

    private static function collectCity(array $input, array &$errors): array
    {
        $city = trim((string) ($input['city_city'] ?? ''));
        if ($city === '') {
            $errors[] = 'City name is required for city pages.';
        }
        $lines = static fn (string $key): array => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($input[$key] ?? '')) ?: [])));
        foreach (['city_map_url', 'city_map_embed_url'] as $key) {
            $v = trim((string) ($input[$key] ?? ''));
            if ($v !== '' && !preg_match('#^https://#', $v)) {
                $errors[] = 'Map links must start with https://.';
            }
        }
        $embed = trim((string) ($input['city_map_embed_url'] ?? ''));
        if ($embed !== '' && !preg_match('#^https://(www\.google\.com/maps/embed|maps\.google\.com/maps\?)#', $embed)) {
            $errors[] = 'Map embed must be a Google Maps embed URL (https://www.google.com/maps/embed?...).';
        }
        $hours = [];
        foreach ((array) ($input['city_hours_days'] ?? []) as $i => $days) {
            $days = trim((string) $days);
            $opens = trim((string) ($input['city_hours_opens'][$i] ?? ''));
            $closes = trim((string) ($input['city_hours_closes'][$i] ?? ''));
            if ($days !== '' && $opens !== '' && $closes !== '') {
                $hours[] = ['days' => $days, 'opens' => $opens, 'closes' => $closes];
            }
        }
        $nearby = [];
        foreach ($lines('city_nearby') as $line) {
            [$name, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($url !== '' && !preg_match('#^(/|https://)#', $url)) {
                $errors[] = 'Nearby area links must be /paths or https:// URLs.';
                continue;
            }
            $nearby[] = ['name' => $name, 'url' => $url];
        }
        $ctaUrl = trim((string) ($input['city_cta_url'] ?? ''));
        if ($ctaUrl !== '' && !preg_match('#^(/|https://|tel:|mailto:)#', $ctaUrl)) {
            $errors[] = 'Call-to-action link must be a /path, https://, tel: or mailto: link.';
        }
        return [
            'city' => mb_substr($city, 0, 191),
            'region' => mb_substr(trim((string) ($input['city_region'] ?? '')), 0, 191),
            'country' => mb_substr(trim((string) ($input['city_country'] ?? '')), 0, 100),
            'primary_service' => mb_substr(trim((string) ($input['city_primary_service'] ?? '')), 0, 191),
            'related_services_json' => json_encode($lines('city_related_services'), JSON_UNESCAPED_UNICODE),
            'phone' => mb_substr(trim((string) ($input['city_phone'] ?? '')), 0, 64),
            'email' => mb_substr(trim((string) ($input['city_email'] ?? '')), 0, 191),
            'address' => mb_substr(trim((string) ($input['city_address'] ?? '')), 0, 500),
            'hours_json' => json_encode($hours, JSON_UNESCAPED_UNICODE),
            'map_url' => trim((string) ($input['city_map_url'] ?? '')),
            'map_embed_url' => $embed,
            'nearby_json' => json_encode($nearby, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'cta_json' => json_encode(['label' => trim((string) ($input['city_cta_label'] ?? '')), 'url' => $ctaUrl, 'text' => trim((string) ($input['city_cta_text'] ?? ''))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'local_business' => empty($input['city_local_business']) ? 0 : 1,
        ];
    }

    /** @return list<int> */
    private function collectTerms(array $input): array
    {
        $ids = array_map('intval', (array) ($input['term_ids'] ?? []));
        foreach (preg_split('/,/', (string) ($input['new_categories'] ?? '')) ?: [] as $name) {
            if (trim($name) !== '') {
                $ids[] = $this->repo->ensureTerm('category', $name);
            }
        }
        foreach (preg_split('/,/', (string) ($input['tags'] ?? '')) ?: [] as $name) {
            if (trim($name) !== '') {
                $ids[] = $this->repo->ensureTerm('tag', $name);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Thin-content and near-duplicate checks for a city page.
     *
     * @return list<string>
     */
    public static function cityQualityWarnings(int $id): array
    {
        $repo = new CmsContentRepository();
        $content = $repo->find($id);
        if ($content === null) {
            return [];
        }
        $config = CmsSiteConfig::get();
        $city = $repo->city($id);
        $warnings = [];
        if (CmsCityDefaults::bodyIsEmpty((string) $content['body_html'])) {
            return CmsCityDefaults::get()['body_html'] === ''
                ? ['No body content, and the city page defaults have no body either.']
                : ['Uses the default city body, shared with other city pages. Add local content to make this page unique.'];
        }
        $words = CmsHtml::wordCount((string) $content['body_html']);
        if ($words < (int) $config['thin_content_words']) {
            $warnings[] = 'Thin content: ' . $words . ' words (aim for ' . (int) $config['thin_content_words'] . '+ with local details).';
        }
        $threshold = (int) $config['city_similarity_threshold'];
        $similar = [];
        foreach ($repo->search(['type' => 'city'], 200) as $other) {
            if ((int) $other['id'] === $id) {
                continue;
            }
            $otherCity = $repo->city((int) $other['id']);
            $otherBody = (string) CmsCityDefaults::apply($other, $otherCity)['body_html'];
            $score = CmsHtml::similarity((string) $content['body_html'], $otherBody, [(string) $city['city'], (string) $otherCity['city'], (string) $city['region'], (string) $otherCity['region']]);
            if ($score >= $threshold) {
                $similar[] = ['name' => (string) ($otherCity['city'] ?: CmsCityDefaults::label($other)), 'score' => $score];
            }
        }
        if ($similar !== []) {
            usort($similar, static fn ($a, $b) => $b['score'] <=> $a['score']);
            $names = array_column(array_slice($similar, 0, 3), 'name');
            $more = count($similar) - count($names);
            $warnings[] = 'Too similar to ' . count($similar) . ' other city page' . (count($similar) === 1 ? '' : 's')
                . ' (' . implode(', ', $names) . ($more > 0 ? ' and ' . $more . ' more' : '') . ', up to ' . $similar[0]['score'] . '% alike).';
        }
        return $warnings;
    }

    /**
     * Create city pages that only hold the city details; title, SEO and body come from the city page defaults.
     *
     * @param list<array{city: string, region?: string, country?: string, service?: string, path: string}> $cities
     * @return array{created: list<int>, errors: list<string>}
     */
    public function createDefaultCityPages(array $cities, ?int $folderId, bool $publish, array $caps): array
    {
        $created = [];
        $errors = [];
        foreach ($cities as $c) {
            $result = $this->save([
                'type' => 'city',
                'title' => '',
                'folder_id' => $folderId ?: '',
                'url_mode' => 'custom',
                'custom_path' => $c['path'],
                'body_html' => '',
                'language' => (string) CmsSiteConfig::value('language', 'en-CA'),
                'robots_index' => 'inherit',
                'robots_follow' => 'inherit',
                'sitemap_include' => '1',
                'canonical_mode' => 'auto',
                'city_city' => $c['city'],
                'city_region' => $c['region'] ?? '',
                'city_country' => $c['country'] ?? 'Canada',
                'city_primary_service' => $c['service'] ?? '',
                'workflow_action' => $publish ? 'publish' : 'save',
                'revision_comment' => 'Created from city name',
            ], $caps);
            if ($result['errors']) {
                $errors[] = $c['city'] . ': ' . implode(' ', $result['errors']);
            } else {
                $created[] = $result['id'];
            }
        }
        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Ensure the Service Area parent page exists at /service-area (Pages tab) with sub-pages for cities.
     *
     * @return array{content_id: int, folder_id: int|null}
     */
    public static function ensureServiceAreaPage(): array
    {
        $repo = new CmsContentRepository();
        $folders = new CmsFolderRepository();
        $existing = $repo->findByPath('/service-area');
        if ($existing === null) {
            $userId = class_exists('Auth') ? Auth::userId() : null;
            $id = $repo->create([
                'type' => 'page',
                'folder_id' => null,
                'internal_name' => 'Service Area',
                'title' => 'Service Areas',
                'h1' => 'Service Areas',
                'slug' => 'service-area',
                'path' => '/service-area',
                'body_html' => '<p>Find accounting, bookkeeping, payroll and tax services in your city. We serve communities across Ontario and Canada.</p>',
                'body_mode' => 'sanitized',
                'template' => 'service-areas',
                'status' => 'published',
                'published_at' => gmdate('Y-m-d H:i:s'),
                'faqs_json' => '[]',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $repo->saveSeo($id, [
                'seo_title' => 'Service Areas | Accountants Near You | Verma Accounting',
                'meta_description' => 'Find accounting, bookkeeping, payroll and tax services in your city. See every community Verma Accounting serves across Ontario and Canada.',
                'robots_index' => 'inherit',
                'robots_follow' => 'inherit',
                'sitemap_include' => 1,
                'canonical_mode' => 'auto',
                'schema_json' => '{"disabled":[]}',
            ]);
            $existing = $repo->find($id);
            CmsCache::flush();
            ActivityLog::record('cms.created', 'cms_content', $id, ['path' => '/service-area', 'title' => 'Service Areas']);
        } elseif (($existing['template'] ?? '') !== 'service-areas' && trim((string) ($existing['body_html'] ?? '')) === '') {
            $repo->update((int) $existing['id'], ['template' => 'service-areas']);
            $existing['template'] = 'service-areas';
        }

        $folders->syncLanding($existing, true);
        $folder = $folders->forLanding((int) $existing['id']);
        if ($folder !== null && ($folder['default_type'] ?? '') !== 'city') {
            $folders->update((int) $folder['id'], ['default_type' => 'city']);
        }

        $redirects = new CmsRedirectRepository();
        if ($redirects->findBySource('/service-areas') === null) {
            $redirects->save(null, '/service-areas', '/service-area', 301, 'auto', 'Renamed listing URL to /service-area');
        }

        return [
            'content_id' => (int) $existing['id'],
            'folder_id' => $folder !== null ? (int) $folder['id'] : null,
        ];
    }
}
