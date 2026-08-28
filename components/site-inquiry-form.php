<?php
declare(strict_types=1);

/**
 * Public site inquiry form (Contact Us + service page CTAs).
 * Posts into the admin Form Builder form with slug "contact".
 *
 * @var bool $includeService Include the service select (Contact Us page only).
 * @var bool $withLabels     Show field labels (Contact Us page).
 */
$includeService = !empty($includeService);
$withLabels = !empty($withLabels);

$schemaFields = null;
try {
    if (!class_exists('FormRepository', false)) {
        require_once __DIR__ . '/../lib/bootstrap.php';
    }
    if (function_exists('ensure_contact_form')) {
        ensure_contact_form();
    }
    $repo = new FormRepository();
    $contactForm = $repo->findBySlug(contact_form_slug(), true);
    if ($contactForm) {
        $schemaFields = $repo->decodeSchema($contactForm)['fields'] ?? null;
    }
} catch (Throwable) {
    $schemaFields = null;
}

/**
 * @param array<string, mixed> $field
 */
$renderInquiryField = static function (array $field, bool $withLabels): void {
    $type = (string) ($field['type'] ?? 'text');
    if (in_array($type, ['heading', 'paragraph', 'page_break', 'file', 'image', 'partners'], true)) {
        return;
    }

    $name = (string) ($field['name'] ?? '');
    if ($name === '') {
        return;
    }

    $id = 'inquiry-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
    $label = trim((string) ($field['label'] ?? $name));
    $placeholder = trim((string) ($field['placeholder'] ?? $label));
    $required = !empty($field['required']);
    $reqAttr = $required ? ' required' : '';

    if ($withLabels && $label !== '') {
        echo '<label for="' . e($id) . '">' . e($label) . ($required ? '' : ' <span class="inquiry-optional">(optional)</span>') . '</label>';
    }

    if ($type === 'textarea') {
        echo '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="4" placeholder="' . e($placeholder) . '"' . $reqAttr . '></textarea>';
        return;
    }

    if ($type === 'select') {
        echo '<select id="' . e($id) . '" name="' . e($name) . '"' . $reqAttr . '>';
        $emptyLabel = $placeholder !== '' ? $placeholder : ('Select ' . $label);
        echo '<option value="">' . e($emptyLabel) . '</option>';
        foreach ($field['options'] ?? [] as $option) {
            if (!is_array($option)) {
                continue;
            }
            $value = (string) ($option['value'] ?? '');
            $optLabel = (string) ($option['label'] ?? $value);
            if ($value === '') {
                continue;
            }
            echo '<option value="' . e($value) . '">' . e($optLabel) . '</option>';
        }
        echo '</select>';
        return;
    }

    $inputType = match ($type) {
        'email' => 'email',
        'tel' => 'tel',
        'number' => 'text',
        'date' => 'date',
        default => 'text',
    };
    $autocomplete = match ($name) {
        'name' => 'name',
        'email' => 'email',
        'phone' => 'tel',
        default => '',
    };
    $autoAttr = $autocomplete !== '' ? ' autocomplete="' . e($autocomplete) . '"' : '';

    echo '<input type="' . e($inputType) . '" id="' . e($id) . '" name="' . e($name) . '" placeholder="' . e($placeholder) . '"' . $reqAttr . $autoAttr . '>';
};
?>
<form id="my-form" class="<?= $withLabels ? 'my-form--labeled' : '' ?>" action="/api/submit" method="POST">
  <input type="hidden" name="form_slug" value="contact">

  <?php if (is_array($schemaFields) && $schemaFields !== []): ?>
    <?php foreach ($schemaFields as $field): ?>
      <?php
        if (!is_array($field)) {
            continue;
        }
        $fieldName = (string) ($field['name'] ?? '');
        if (!$includeService && $fieldName === 'service') {
            continue;
        }
        // Contact page requires service in the UI even if optional in schema.
        if ($includeService && $fieldName === 'service') {
            $field['required'] = true;
        }
        $renderInquiryField($field, $withLabels);
      ?>
    <?php endforeach; ?>
  <?php else: ?>
    <?php if ($withLabels): ?><label for="inquiry-name">Name</label><?php endif; ?>
    <input type="text" id="inquiry-name" name="name" placeholder="Name" required autocomplete="name">

    <?php if ($withLabels): ?><label for="inquiry-phone">Phone</label><?php endif; ?>
    <input type="text" id="inquiry-phone" name="phone" placeholder="Phone" required autocomplete="tel">

    <?php if ($withLabels): ?><label for="inquiry-email">Email</label><?php endif; ?>
    <input type="email" id="inquiry-email" name="email" placeholder="Email" required autocomplete="email">

    <?php if ($includeService): ?>
      <?php if ($withLabels): ?><label for="service-select">Service</label><?php endif; ?>
      <select name="service" id="service-select" required>
        <option value="">Select a Service</option>
        <option value="Bookkeeping">Bookkeeping</option>
        <option value="Financial Accounting">Financial Accounting</option>
        <option value="Payroll">Payroll</option>
        <option value="Personal Tax Preparation">Personal Tax Preparation</option>
        <option value="Corporate Tax Services">Corporate Tax Services</option>
        <option value="Business Registration">Business Registration</option>
        <option value="Other">Other</option>
      </select>
    <?php endif; ?>

    <?php if ($withLabels): ?><label for="inquiry-message">Message</label><?php endif; ?>
    <textarea id="inquiry-message" name="message" rows="4" placeholder="Message" required></textarea>
  <?php endif; ?>

  <button type="submit" id="my-form-button">Send message</button>
  <p id="my-form-status" role="status" aria-live="polite"></p>
</form>
