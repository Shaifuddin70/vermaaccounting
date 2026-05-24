<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$formId = (int) ($_GET['form_id'] ?? 0);
$repo = new FormRepository();
$form = $repo->find($formId);

if (!$form) {
    header('Location: /admin/forms.php');
    exit;
}

$schema = $repo->decodeSchema($form);
$submissions = $repo->submissionsForForm($formId);
$inputFields = array_filter($schema['fields'], fn ($f) => !in_array($f['type'], ['heading', 'paragraph'], true));

$pageTitle = 'Responses: ' . $form['title'];
$activeNav = 'forms';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Responses</h1>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <?php if ($submissions): ?>
      <a href="/admin/export-csv.php?form_id=<?= $formId ?>" class="admin-btn admin-btn-secondary">Export CSV</a>
    <?php endif; ?>
    <a href="/admin/form-builder.php?id=<?= $formId ?>" class="admin-btn admin-btn-secondary">← Back to form</a>
  </div>
</div>

<p style="color:#64748b;margin:-0.5rem 0 1rem;"><?= e($form['title']) ?> — <?= count($submissions) ?> submission(s)</p>

<div class="admin-card" style="overflow-x:auto;">
  <?php if (!$submissions): ?>
    <p style="color:#64748b;">No submissions yet.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Date</th>
          <?php foreach (array_slice($inputFields, 0, 5) as $field): ?>
            <th><?= e($field['label']) ?></th>
          <?php endforeach; ?>
          <th>Files</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $sub):
          $data = json_decode($sub['data_json'], true) ?: [];
          $files = $repo->filesForSubmission((int) $sub['id']);
        ?>
          <tr>
            <td><?= e($sub['created_at']) ?></td>
            <?php foreach (array_slice($inputFields, 0, 5) as $field):
              $key = $field['name'];
              $val = $data[$key] ?? '';
              if (is_array($val)) {
                  $val = implode(', ', $val);
              }
            ?>
              <td><?= e(mb_strimwidth((string) $val, 0, 60, '…')) ?></td>
            <?php endforeach; ?>
            <td>
              <?php if ($files): ?>
                <ul style="margin:0;padding-left:1rem;font-size:0.85rem;">
                  <?php foreach ($files as $file): ?>
                    <li>
                      <a href="/admin/download.php?file_id=<?= (int) $file['id'] ?>">
                        <?= e($file['original_name']) ?>
                      </a>
                      <span style="color:#64748b;">(<?= number_format((int) $file['size'] / 1024, 1) ?> KB)</span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <details>
                <summary>View all</summary>
                <dl style="font-size:0.85rem;margin:0.5rem 0;">
                  <?php foreach ($data as $k => $v): ?>
                    <dt><strong><?= e($k) ?></strong></dt>
                    <dd><?= e(is_array($v) ? implode(', ', $v) : (string) $v) ?></dd>
                  <?php endforeach; ?>
                </dl>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
