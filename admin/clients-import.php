<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$clientRepo = new ClientRepository();
$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $errors[] = 'Please choose a CSV file to upload.';
    } else {
        $upload = $_FILES['csv_file'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed. Try again.';
        } elseif ($upload['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File is too large (max 5 MB).';
        } else {
            $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $errors[] = 'Please upload a .csv file (export from Excel or Google Sheets).';
            } else {
                try {
                    $rows = parse_client_csv_file($upload['tmp_name']);
                    if (!$rows) {
                        $errors[] = 'No data rows found in the file.';
                    } else {
                        $result = $clientRepo->importRows($rows);
                        ActivityLog::record('clients.imported', 'clients', null, [
                            'imported' => $result['imported'],
                            'updated' => $result['updated'],
                            'skipped' => $result['skipped'],
                        ]);
                    }
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Import clients';
$activeNav = 'clients';
$csrf = Auth::csrfToken();

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Import clients</h1>
  <div class="admin-header-actions">
    <a href="/admin/clients" class="admin-btn admin-btn-secondary">← Back to clients</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="admin-alert admin-alert-error">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($result): ?>
  <div class="admin-alert admin-alert-success">
    Import finished:
    <?= (int) $result['imported'] ?> added,
    <?= (int) $result['updated'] ?> updated,
    <?= (int) $result['skipped'] ?> skipped.
  </div>
  <?php if ($result['errors']): ?>
    <div class="admin-alert admin-alert-error">
      <ul style="margin:0;padding-left:1.25rem;">
        <?php foreach ($result['errors'] as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2 style="margin:0 0 1rem;font-size:1rem;">Upload spreadsheet</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <div class="admin-field">
        <label for="csv_file">CSV file</label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
      </div>
      <button type="submit" class="admin-btn">Import clients</button>
    </form>
  </div>

  <div class="admin-card">
    <h2 style="margin:0 0 1rem;font-size:1rem;">Column guide</h2>
    <p style="margin:0 0 0.75rem;color:var(--admin-muted);font-size:0.9rem;">
      Export your sheet as <strong>CSV</strong> from Excel or Google Sheets. The first row must be column headers.
    </p>
    <table class="admin-table clients-import-guide">
      <thead>
        <tr>
          <th>Column</th>
          <th>Required</th>
          <th>Examples</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Name</td><td>Yes</td><td>Name, Full Name, Client Name</td></tr>
        <tr><td>CIN</td><td>No</td><td>CIN, Client ID, Client Number</td></tr>
        <tr><td>Email</td><td>No</td><td>Email, Email Address</td></tr>
        <tr><td>Phone</td><td>No</td><td>Phone, Mobile, Telephone</td></tr>
        <tr><td>Company</td><td>No</td><td>Company, Business</td></tr>
        <tr><td>Notes</td><td>No</td><td>Notes, Comments</td></tr>
      </tbody>
    </table>
    <p style="margin:1rem 0 0;font-size:0.85rem;color:var(--admin-muted);">
      Rows with the same CIN or email are updated instead of duplicated. Each CIN must be unique.
    </p>
    <a href="/admin/clients-import-template" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-top:1rem;">Download sample CSV</a>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
