<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.edit');

$caps = cms_admin_caps();
$canPublish = (bool) $caps['publish'];
$service = new CmsBlogAiService();
$cfg = CmsBlogAiService::config();
$queue = $service->queueState();
$configured = $service->isConfigured();

$topic = '';
$notes = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_queue') {
        if (!$canPublish) {
            cms_admin_back('/admin/cms-blog-generate', null, 'You need publishing permission to manage the cron topic queue.');
        }
        $lines = preg_split('/\R/', (string) ($_POST['topics'] ?? '')) ?: [];
        $service->saveTopics($lines);
        cms_admin_back('/admin/cms-blog-generate', 'Topic queue saved (' . count($service->queueState()['topics']) . ' pending).');
    }

    if ($action === 'generate') {
        if (!$canPublish) {
            cms_admin_back('/admin/cms-blog-generate', null, 'You need publishing permission to generate and publish articles.');
        }
        $topic = trim((string) ($_POST['topic'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        @set_time_limit(180);
        try {
            $result = $service->generateAndPublish($topic, $notes, $caps);
        } catch (Throwable $e) {
            error_log('CMS blog AI generate failed: ' . $e->getMessage());
            cms_admin_back('/admin/cms-blog-generate', null, app_safe_error_message($e, 'Article generation failed.'));
        }
        if ($result['errors'] !== []) {
            $_SESSION['flash_error'] = implode(' ', $result['errors']);
        } else {
            cms_admin_back(
                '/admin/cms-edit?id=' . (int) $result['id'],
                'Published “' . $result['title'] . '” at ' . $result['path'] . '.'
            );
        }
    } else {
        cms_admin_back('/admin/cms-blog-generate', null, 'Unknown action.');
    }
}

$csrf = Auth::csrfToken();
$pageTitle = 'AI blog';
$activeNav = 'cms-blog-ai';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>AI blog generator</h1>
  <div class="admin-header-actions">
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-content?type=article">Blog articles</a>
  </div>
</div>

<?php if (!$configured): ?>
  <div class="admin-card cms-callout">
    <h2 class="admin-card-title">OpenAI not configured</h2>
    <p>Add your ChatGPT API key in <code>config.local.php</code> under <code>openai.api_key</code> (see <code>config.example.php</code>). Optionally set <code>openai.model</code> and <code>blog_ai</code> options.</p>
  </div>
<?php endif; ?>

<form method="post" action="/admin/cms-blog-generate" class="admin-card">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="generate">
  <h2 class="admin-card-title">Generate and publish</h2>
  <p class="admin-field-hint">ChatGPT writes a full article and publishes it live at <code>/blog/{slug}</code> immediately. Review the post afterward and edit if needed.</p>
  <div class="admin-field">
    <label for="ai-topic">Topic or working title</label>
    <input type="text" id="ai-topic" name="topic" value="<?= e($topic) ?>" required maxlength="300" placeholder="e.g. HST filing deadlines for Ontario small businesses" <?= $configured && $canPublish ? '' : 'disabled' ?>>
  </div>
  <div class="admin-field">
    <label for="ai-notes">Notes for the model (optional)</label>
    <textarea id="ai-notes" name="notes" rows="4" placeholder="Audience, keywords, points to cover…" <?= $configured && $canPublish ? '' : 'disabled' ?>><?= e($notes) ?></textarea>
  </div>
  <?php if ($canPublish): ?>
    <div class="cms-form-actions">
      <button type="submit" class="admin-btn" data-busy="Generating…" <?= $configured ? '' : 'disabled' ?> data-confirm="Generate a new blog post and publish it live now?">Generate and publish</button>
    </div>
  <?php else: ?>
    <p class="admin-field-hint">Ask an administrator with publishing permission to generate posts.</p>
  <?php endif; ?>
</form>

<div class="admin-card">
  <h2 class="admin-card-title">Cron topic queue</h2>
  <p class="admin-field-hint">
    The campaign cron also runs the blog generator. When enabled, it publishes the next topic from this list
    (<?= (int) $cfg['max_per_run'] ?> per run, at least <?= (int) $cfg['min_hours_between'] ?> hour(s) apart).
    Cron is currently <strong><?= $cfg['enabled'] ? 'enabled' : 'disabled' ?></strong> in config
    <?= $queue['last_run_at'] ? '· last run ' . e(cms_admin_date($queue['last_run_at'])) : '· never run' ?>.
  </p>
  <?php if ($canPublish): ?>
    <form method="post" action="/admin/cms-blog-generate">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="save_queue">
      <div class="admin-field">
        <label for="ai-queue">Pending topics (one per line)</label>
        <textarea id="ai-queue" name="topics" rows="8" placeholder="Corporate tax installment tips&#10;Bookkeeping for sole proprietors"><?= e(implode("\n", $queue['topics'])) ?></textarea>
      </div>
      <button type="submit" class="admin-btn admin-btn-secondary">Save queue</button>
    </form>
  <?php else: ?>
    <?php if ($queue['topics'] === []): ?>
      <p class="admin-field-hint">Queue is empty.</p>
    <?php else: ?>
      <ul class="cms-link-list">
        <?php foreach ($queue['topics'] as $t): ?>
          <li><?= e($t) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($queue['done'] !== []): ?>
    <h3 class="admin-card-title cms-mt">Recently generated</h3>
    <ul class="cms-link-list">
      <?php foreach (array_slice($queue['done'], 0, 10) as $row): ?>
        <li>
          <?php if ($row['path'] !== ''): ?>
            <a href="<?= e($row['path']) ?>" target="_blank" rel="noopener"><?= e($row['title'] !== '' ? $row['title'] : $row['path']) ?></a>
          <?php else: ?>
            <?= e($row['title'] !== '' ? $row['title'] : $row['topic']) ?>
          <?php endif; ?>
          <?php if ($row['at'] !== ''): ?> <span class="admin-field-hint">· <?= e(cms_admin_date($row['at'])) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
