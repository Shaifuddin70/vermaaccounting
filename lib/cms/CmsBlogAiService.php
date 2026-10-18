<?php

declare(strict_types=1);

/**
 * ChatGPT-powered blog article generation that publishes into the CMS at /blog/{slug}.
 */
final class CmsBlogAiService
{
    private const QUEUE_KEY = 'cms_blog_ai_queue';

    private CmsContentRepository $repo;
    private OpenAiClient $ai;

    public function __construct(?CmsContentRepository $repo = null, ?OpenAiClient $ai = null)
    {
        $this->repo = $repo ?? new CmsContentRepository();
        $this->ai = $ai ?? new OpenAiClient();
    }

    /** @return array{enabled: bool, max_per_run: int, min_hours_between: int, author_name: string} */
    public static function config(): array
    {
        $c = app_config()['blog_ai'] ?? [];
        if (!is_array($c)) {
            $c = [];
        }
        return [
            'enabled' => !array_key_exists('enabled', $c) || !empty($c['enabled']),
            'max_per_run' => max(1, min(5, (int) ($c['max_per_run'] ?? 1))),
            'min_hours_between' => max(0, (int) ($c['min_hours_between'] ?? 24)),
            'author_name' => trim((string) ($c['author_name'] ?? 'Verma Accounting')) ?: 'Verma Accounting',
        ];
    }

    public function isConfigured(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * @return array{topics: list<string>, done: list<array{topic: string, path: string, title: string, at: string}>, last_run_at: ?string}
     */
    public function queueState(): array
    {
        $raw = (new SettingsRepository())->get(self::QUEUE_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $topics = [];
        foreach ((array) ($raw['topics'] ?? []) as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $topics[] = $t;
            }
        }
        $done = [];
        foreach ((array) ($raw['done'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $done[] = [
                'topic' => (string) ($row['topic'] ?? ''),
                'path' => (string) ($row['path'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'at' => (string) ($row['at'] ?? ''),
            ];
        }
        $last = $raw['last_run_at'] ?? null;
        return [
            'topics' => array_values($topics),
            'done' => array_slice($done, 0, 50),
            'last_run_at' => is_string($last) && $last !== '' ? $last : null,
        ];
    }

    /** @param list<string> $topics */
    public function saveTopics(array $topics): void
    {
        $clean = [];
        $seen = [];
        foreach ($topics as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $key = mb_strtolower($t);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $t;
        }
        $state = $this->queueState();
        $state['topics'] = $clean;
        $this->persistQueue($state);
    }

    /**
     * Normalize a ChatGPT JSON article into CmsContentService::save input fields (without workflow/folder).
     *
     * @param array<string, mixed> $json
     * @return array{article: array<string, mixed>, errors: list<string>}
     */
    public function normalizeArticle(array $json): array
    {
        $errors = [];
        $title = trim((string) ($json['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Generated article is missing a title.';
        }
        $slug = CmsPath::slugify((string) ($json['slug'] ?? '')) ?: CmsPath::slugify($title);
        if ($slug === '') {
            $errors[] = 'Could not build a URL slug from the generated title.';
        }
        $body = trim((string) ($json['body_html'] ?? ''));
        if ($body === '' || !preg_match('/<(p|h2|h3|ul|ol)\b/i', $body)) {
            $errors[] = 'Generated body HTML is empty or missing expected structure.';
        }
        if (preg_match('/<\?(php|=)?/i', $body)) {
            $errors[] = 'Generated body contains disallowed PHP tags.';
        }

        $excerpt = trim((string) ($json['excerpt'] ?? ''));
        $seoTitle = trim((string) ($json['seo_title'] ?? $title));
        $seoDescription = trim((string) ($json['seo_description'] ?? $excerpt));
        if (mb_strlen($seoDescription) > 320) {
            $seoDescription = mb_substr($seoDescription, 0, 317) . '…';
        }

        $tags = [];
        foreach ((array) ($json['tags'] ?? []) as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_slice(array_values(array_unique($tags)), 0, 12);

        $faqQ = [];
        $faqA = [];
        foreach ((array) ($json['faqs'] ?? []) as $faq) {
            if (!is_array($faq)) {
                continue;
            }
            $q = trim((string) ($faq['question'] ?? ''));
            $a = trim((string) ($faq['answer'] ?? ''));
            if ($q !== '' && $a !== '') {
                $faqQ[] = $q;
                $faqA[] = $a;
            }
        }

        return [
            'article' => [
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'body_html' => $body,
                'seo_title' => $seoTitle,
                'meta_description' => $seoDescription,
                'tags' => implode(', ', $tags),
                'faq_q' => $faqQ,
                'faq_a' => $faqA,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Build the save() payload for a normalized article (public for tests).
     *
     * @param array<string, mixed> $article from normalizeArticle()['article']
     * @return array<string, mixed>
     */
    public function buildSaveInput(array $article, int $folderId, string $authorName = ''): array
    {
        $cfg = self::config();
        $author = $authorName !== '' ? $authorName : $cfg['author_name'];
        [$slug, $path] = $this->uniqueSlugPath((string) $article['slug']);

        return [
            'type' => 'article',
            'folder_id' => $folderId,
            'url_mode' => 'auto',
            'slug' => $slug,
            'title' => (string) $article['title'],
            'h1' => '',
            'internal_name' => '',
            'body_html' => (string) $article['body_html'],
            'body_mode' => 'sanitized',
            'excerpt' => (string) ($article['excerpt'] ?? ''),
            'author_name' => $author,
            'template' => 'article',
            'tags' => (string) ($article['tags'] ?? ''),
            'new_categories' => 'Tax tips',
            'faq_q' => (array) ($article['faq_q'] ?? []),
            'faq_a' => (array) ($article['faq_a'] ?? []),
            'seo_title' => (string) ($article['seo_title'] ?? ''),
            'meta_description' => (string) ($article['meta_description'] ?? ''),
            'robots_index' => 'inherit',
            'robots_follow' => 'inherit',
            'sitemap_include' => '1',
            'canonical_mode' => 'auto',
            'og_type' => 'article',
            'workflow_action' => 'publish',
            'internal_notes' => '[ai-generated] Created by ChatGPT blog generator.',
            'revision_comment' => 'AI-generated and published',
            '_path' => $path,
        ];
    }

    /**
     * Generate from a topic and publish immediately.
     *
     * @param array{edit: bool, publish: bool, seo: bool, code: bool}|null $caps
     * @return array{id: int, path: string, title: string, errors: list<string>}
     */
    public function generateAndPublish(string $topic, string $notes = '', ?array $caps = null): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            return ['id' => 0, 'path' => '', 'title' => '', 'errors' => ['Enter a topic for the article.']];
        }
        if (!$this->isConfigured()) {
            return ['id' => 0, 'path' => '', 'title' => '', 'errors' => ['OpenAI API key is not configured.']];
        }

        $caps = $caps ?? ['edit' => true, 'publish' => true, 'seo' => true, 'code' => false];
        if (empty($caps['edit']) || empty($caps['publish'])) {
            return ['id' => 0, 'path' => '', 'title' => '', 'errors' => ['Publishing permission is required to generate and publish.']];
        }

        $folderId = $this->ensureBlogFolder();
        $messages = $this->promptMessages($topic, $notes);
        try {
            $json = $this->ai->chatJson($messages);
        } catch (Throwable $e) {
            return ['id' => 0, 'path' => '', 'title' => '', 'errors' => [$e->getMessage()]];
        }

        $normalized = $this->normalizeArticle($json);
        if ($normalized['errors'] !== []) {
            return ['id' => 0, 'path' => '', 'title' => '', 'errors' => $normalized['errors']];
        }

        $input = $this->buildSaveInput($normalized['article'], $folderId);
        $pathHint = (string) ($input['_path'] ?? '');
        unset($input['_path']);

        $result = (new CmsContentService($this->repo))->save($input, $caps);
        if ($result['errors'] !== []) {
            return ['id' => 0, 'path' => $pathHint, 'title' => (string) $normalized['article']['title'], 'errors' => $result['errors']];
        }

        $id = (int) $result['id'];
        $content = $this->repo->find($id);
        $path = (string) ($content['path'] ?? $pathHint);
        $title = (string) ($content['title'] ?? $normalized['article']['title']);

        ActivityLog::record('cms.blog_ai_generated', 'cms_content', $id, [
            'path' => $path,
            'title' => $title,
            'topic' => $topic,
            'model' => $this->ai->model(),
        ]);

        return ['id' => $id, 'path' => $path, 'title' => $title, 'errors' => []];
    }

    /**
     * Cron entry: pop topics from the queue and publish up to max_per_run.
     *
     * @return array{generated: int, skipped: ?string, items: list<array{topic: string, path: string, title: string}>, errors: list<string>}
     */
    public function runCron(): array
    {
        $cfg = self::config();
        if (!$cfg['enabled']) {
            return ['generated' => 0, 'skipped' => 'disabled', 'items' => [], 'errors' => []];
        }
        if (!$this->isConfigured()) {
            return ['generated' => 0, 'skipped' => 'no_api_key', 'items' => [], 'errors' => []];
        }

        $state = $this->queueState();
        if ($state['topics'] === []) {
            return ['generated' => 0, 'skipped' => 'empty_queue', 'items' => [], 'errors' => []];
        }

        if ($cfg['min_hours_between'] > 0 && $state['last_run_at'] !== null) {
            $last = strtotime($state['last_run_at'] . ' UTC');
            if ($last !== false && (time() - $last) < ($cfg['min_hours_between'] * 3600)) {
                return ['generated' => 0, 'skipped' => 'cooldown', 'items' => [], 'errors' => []];
            }
        }

        $caps = ['edit' => true, 'publish' => true, 'seo' => true, 'code' => false];
        $items = [];
        $errors = [];
        $max = $cfg['max_per_run'];

        for ($i = 0; $i < $max; $i++) {
            $state = $this->queueState();
            if ($state['topics'] === []) {
                break;
            }
            $topic = array_shift($state['topics']);
            $this->persistQueue($state);

            $result = $this->generateAndPublish($topic, '', $caps);
            if ($result['errors'] !== []) {
                // Put the topic back at the front so a transient API failure can retry.
                $state = $this->queueState();
                array_unshift($state['topics'], $topic);
                $state['topics'] = array_values(array_unique($state['topics']));
                $this->persistQueue($state);
                $errors[] = $topic . ': ' . implode(' ', $result['errors']);
                break;
            }

            $state = $this->queueState();
            array_unshift($state['done'], [
                'topic' => $topic,
                'path' => $result['path'],
                'title' => $result['title'],
                'at' => gmdate('Y-m-d H:i:s'),
            ]);
            $state['done'] = array_slice($state['done'], 0, 50);
            $state['last_run_at'] = gmdate('Y-m-d H:i:s');
            $this->persistQueue($state);

            $items[] = ['topic' => $topic, 'path' => $result['path'], 'title' => $result['title']];
        }

        return [
            'generated' => count($items),
            'skipped' => $items === [] && $errors === [] ? 'none' : null,
            'items' => $items,
            'errors' => $errors,
        ];
    }

    /** Ensure /blog folder and article-index landing page exist. @return int folder id */
    public function ensureBlogFolder(): int
    {
        $folders = new CmsFolderRepository();
        $folder = $folders->findByPrefix('/blog');
        if (!$folder) {
            $folderId = $folders->create([
                'parent_id' => null,
                'name' => 'Blog',
                'slug' => 'blog',
                'url_prefix' => '/blog',
                'default_type' => 'article',
            ]);
        } else {
            $folderId = (int) $folder['id'];
        }

        if (!$this->repo->findByPath('/blog')) {
            $indexId = $this->repo->create([
                'type' => 'page',
                'folder_id' => null,
                'internal_name' => 'Blog home',
                'title' => 'Accounting & Tax Blog',
                'h1' => 'Accounting & Tax Blog',
                'slug' => 'blog',
                'path' => '/blog',
                'body_html' => '<p>Practical tax, bookkeeping, and accounting guides for Ontario individuals and businesses.</p>',
                'body_mode' => 'sanitized',
                'template' => 'article-index',
                'status' => 'published',
                'published_at' => gmdate('Y-m-d H:i:s'),
                'faqs_json' => '[]',
                'created_by' => Auth::userId(),
                'updated_by' => Auth::userId(),
            ]);
            $this->repo->saveSeo($indexId, [
                'seo_title' => 'Accounting & Tax Blog | Verma Accounting Ontario',
                'meta_description' => 'Practical tax, bookkeeping, and accounting guides for Ontario individuals and businesses from Verma Accounting.',
                'robots_index' => 'inherit',
                'robots_follow' => 'inherit',
                'sitemap_include' => 1,
                'canonical_mode' => 'auto',
                'schema_json' => '{"disabled":[]}',
            ]);
            $folders->update($folderId, ['landing_content_id' => $indexId]);
        }

        return $folderId;
    }

    /** @return array{0: string, 1: string} slug and path */
    private function uniqueSlugPath(string $slug): array
    {
        $base = $slug !== '' ? $slug : 'article';
        $candidate = $base;
        $n = 2;
        while ($this->repo->pathTaken('/blog/' . $candidate)) {
            $candidate = $base . '-' . $n;
            $n++;
            if ($n > 100) {
                $candidate = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
                break;
            }
        }
        return [$candidate, '/blog/' . $candidate];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function promptMessages(string $topic, string $notes): array
    {
        $site = (string) CmsSiteConfig::value('site_name', 'Verma Accounting');
        $system = <<<SYS
You are a content writer for {$site}, a Canadian accounting firm serving Ontario individuals and businesses.
Write practical, accurate blog posts about tax, bookkeeping, payroll, HST/GST, CRA, and small-business accounting.
Rules:
- Use Canadian (Ontario) context and terminology. Do not invent credentials, licenses, case studies, or statistics.
- Do not give personalized legal or tax advice; keep guidance general and suggest consulting a professional when appropriate.
- Output a single JSON object only (no markdown fences) with keys:
  title (string), slug (kebab-case string), excerpt (1-2 sentences),
  body_html (HTML string using only <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a href>),
  seo_title (under 60 chars), seo_description (under 160 chars),
  tags (array of 3-8 short strings), faqs (array of 3-5 objects with question and answer strings).
- body_html should be 800–1400 words worth of content, with clear H2 sections. Do not include an H1 (the site template provides it).
- Do not wrap body_html in <html> or <body>. No scripts, styles, or images.
SYS;

        $user = "Write a new blog post about this topic:\n" . $topic;
        $notes = trim($notes);
        if ($notes !== '') {
            $user .= "\n\nAdditional notes from the editor:\n" . $notes;
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /** @param array{topics: list<string>, done: list<array>, last_run_at: ?string} $state */
    private function persistQueue(array $state): void
    {
        (new SettingsRepository())->set(self::QUEUE_KEY, [
            'topics' => array_values($state['topics'] ?? []),
            'done' => array_values(array_slice($state['done'] ?? [], 0, 50)),
            'last_run_at' => $state['last_run_at'] ?? null,
        ]);
    }
}
