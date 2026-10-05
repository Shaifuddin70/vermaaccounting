<?php
/** @var array $schema */
/** @var array $data */
/** @var array $filesByField */
/** @var int $submissionId */
/** @var int $formId */

$galleryContext = ['submissionId' => (int) ($submissionId ?? 0), 'formId' => (int) ($formId ?? 0)];
$renderAnswer = static function (array $field) use ($data, $filesByField, $galleryContext): void {
    $submissionId = $galleryContext['submissionId'];
    $formId = $galleryContext['formId'];
    $type = (string) $field['type'];
    $name = (string) $field['name'];
    $id = (string) $field['id'];

    if (in_array($type, ['file', 'image'], true)) {
        $fieldFiles = $filesByField[$id] ?? [];
        $galleryFieldId = $id;
        include __DIR__ . '/submission-files-gallery.php';
        return;
    }
    if ($type === 'partners') {
        echo '<p class="submission-value">' . e(format_partner_submission_value($data[$name] ?? [])) . '</p>';
        return;
    }
    if ($type === 'textarea') {
        echo '<p class="submission-value submission-value--block">' . nl2br(e(format_submission_value($data[$name] ?? ''))) . '</p>';
        return;
    }
    echo '<p class="submission-value">' . e(format_submission_value($data[$name] ?? '')) . '</p>';
};
?>
<?php foreach (submission_view_sections($schema, ['payment']) as $section):
  $answered = [];
  $unanswered = [];
  foreach ($section['fields'] as $field) {
      if (submission_field_is_answered($field, $data, $filesByField)) {
          $answered[] = $field;
      } else {
          $unanswered[] = $field;
      }
  }
?>
  <section class="admin-card sub-section">
    <header class="sub-section-head">
      <h2 class="sub-section-title"><?= e($section['title']) ?></h2>
      <?php if ($unanswered): ?>
        <span class="sub-section-count"><?= count($answered) ?> of <?= count($section['fields']) ?> answered</span>
      <?php endif; ?>
    </header>

    <?php if ($answered): ?>
      <dl class="submission-fields">
        <?php foreach ($answered as $field):
          $type = (string) $field['type'];
          $name = (string) $field['name'];
        ?>
          <?php if ($type === 'yes_no'):
            $val = strtolower(trim((string) ($data[$name] ?? '')));
            $followUpAnswers = [];
            foreach (yes_no_follow_ups($field) as $followUp) {
                $reasonKey = yes_no_follow_up_storage_key($name, $followUp);
                if (!isset($data[$reasonKey])) {
                    continue;
                }
                $raw = $data[$reasonKey];
                $display = ($followUp['type'] ?? '') === 'partners'
                    ? format_partner_submission_value($raw)
                    : trim(is_array($raw) ? implode(', ', array_map('strval', $raw)) : (string) $raw);
                if ($display !== '') {
                    $followUpAnswers[] = [(string) $followUp['label'], $display];
                }
            }
          ?>
            <div class="submission-field-row submission-field-row--yesno<?= $followUpAnswers ? ' submission-field-row--wide' : '' ?>">
              <dt><?= e($field['label']) ?></dt>
              <dd>
                <span class="sub-yesno sub-yesno--<?= $val === 'yes' ? 'yes' : 'no' ?>"><?= e(ucfirst($val)) ?></span>
                <?php foreach ($followUpAnswers as [$fuLabel, $fuValue]): ?>
                  <p class="submission-reason"><strong><?= e($fuLabel) ?>:</strong> <?= nl2br(e($fuValue)) ?></p>
                <?php endforeach; ?>
              </dd>
            </div>
          <?php else:
            $isWide = in_array($type, ['file', 'image', 'textarea'], true);
          ?>
            <div class="submission-field-row<?= $isWide ? ' submission-field-row--wide' : '' ?>">
              <dt><?= e($field['label']) ?></dt>
              <dd><?php $renderAnswer($field); ?></dd>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>

    <?php if ($unanswered): ?>
      <details class="sub-unanswered"<?= $answered ? '' : ' open' ?>>
        <summary><?= count($unanswered) ?> question<?= count($unanswered) === 1 ? '' : 's' ?> not answered</summary>
        <ul>
          <?php foreach ($unanswered as $field): ?>
            <li><?= e($field['label']) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
