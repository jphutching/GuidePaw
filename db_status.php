<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
checkLogin();
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strtolower(appEnv('APP_ALLOW_DB_MIGRATIONS', 'false')) === 'true') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $applied = applyPendingMigrations($pdo);
    $message = $applied ? ('Applied: ' . implode(', ', $applied)) : 'No pending migrations.';
}
$available = array_map('basename', availableMigrationFiles(dbDriverName()));
$applied = appliedMigrationVersions($pdo);
$pending = array_values(array_diff($available, $applied));
$csrf = generateCsrfToken();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database Status</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet"></head><body>
<?php guidepawBrandHeader(); ?>

<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?><div class="container py-4" style="max-width:900px"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Database Status</h3><a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a></div><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><div class="row g-3 mb-4"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Driver</div><div class="fs-5"><?= e(dbDriverName()) ?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Current schema version</div><div class="fs-5"><?= e(currentSchemaVersion($pdo)) ?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Pending migrations</div><div class="fs-5"><?= count($pending) ?></div></div></div></div></div><div class="card shadow-sm mb-4"><div class="card-body"><h5 class="mb-3">Migration control</h5><p class="text-muted">Set <code>APP_ALLOW_DB_MIGRATIONS=true</code> to apply pending migrations from the UI.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button class="btn btn-primary" <?= strtolower(appEnv('APP_ALLOW_DB_MIGRATIONS', 'false')) === 'true' ? '' : 'disabled' ?>>Apply pending migrations</button></form></div></div><div class="card shadow-sm"><div class="card-body"><h5>Pending files</h5><?php if (!$pending): ?><div class="text-muted">No pending migration files.</div><?php else: ?><ul class="mb-0"><?php foreach ($pending as $file): ?><li><code><?= e($file) ?></code></li><?php endforeach; ?></ul><?php endif; ?></div></div></div><?php guidepawFormUx(); ?>
</body></html>
