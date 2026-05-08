<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';

requireAdmin();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function healthBadge(bool $ok): string {
    return $ok
        ? '<span class="badge ok">OK</span>'
        : '<span class="badge bad">Needs attention</span>';
}

$checks = [];

try {
    $dbVersion = $pdo->query("SELECT version()")->fetchColumn();
    $dbOk = true;
} catch (Throwable $e) {
    $dbVersion = $e->getMessage();
    $dbOk = false;
}

$checks[] = ['Database connection', $dbOk, $dbVersion];
$checks[] = ['PHP version', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
$checks[] = ['ZipArchive support', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'Available' : 'Missing php-zip extension'];
$checks[] = ['Session active', session_status() === PHP_SESSION_ACTIVE, 'status=' . session_status()];

$schemaVersion = currentSchemaVersion($pdo);
$appliedMigrations = appliedMigrationVersions($pdo);
$availableMigrations = array_map(static fn(string $path): string => basename($path), availableMigrationFiles(dbDriverName()));
$pendingMigrations = array_values(array_diff($availableMigrations, $appliedMigrations));

$paths = [
    'uploads' => __DIR__ . '/uploads',
    'uploads/images' => __DIR__ . '/uploads/images',
    'uploads/videos' => __DIR__ . '/uploads/videos',
    'uploads/documents' => __DIR__ . '/uploads/documents',
    'assets/brand' => __DIR__ . '/assets/brand',
];

foreach ($paths as $label => $path) {
    $checks[] = [
        'Writable path: ' . $label,
        is_dir($path) && is_writable($path),
        $path
    ];
}

$tableCounts = [];
foreach ([
    'users',
    'dogs',
    'training_goals',
    'training_sessions',
    'behavior_incidents',
    'dog_candidate_assessments',
    'admin_audit_log',
    'feature_flags',
] as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
        $tableCounts[$table] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $tableCounts[$table] = 'error: ' . $e->getMessage();
    }
}

$auditRows = [];
try {
    $auditRows = $pdo->query("
        SELECT action, target_type, target_id, details, created_at
        FROM admin_audit_log
        ORDER BY created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $auditRows = [];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw System Health</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .card { background:#fff; border-radius:14px; padding:18px; margin:14px 0; box-shadow:0 2px 10px rgba(0,0,0,.08); }
        a.btn { display:inline-block; border-radius:10px; padding:10px 14px; background:#1f2937; color:#fff; text-decoration:none; font-weight:700; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        th, td { border:1px solid #ddd; padding:8px; text-align:left; vertical-align:top; }
        th { background:#eee; }
        .badge { display:inline-block; border-radius:999px; padding:4px 9px; font-weight:800; font-size:12px; }
        .ok { background:#d1e7dd; color:#0f5132; }
        .bad { background:#f8d7da; color:#842029; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .metric { background:#f8f9fa; border:1px solid #ddd; border-radius:12px; padding:12px; }
        .metric strong { display:block; font-size:1.5rem; }
        .small { color:#666; font-size:13px; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wrap">
    <div class="top">
        <h1>System Health</h1>
        <a class="btn" href="admin.php">Back to Admin</a>
    </div>

    <div class="card">
        <h2>Checks</h2>
        <table>
            <tr><th>Check</th><th>Status</th><th>Details</th></tr>
            <?php foreach ($checks as [$label, $ok, $detail]): ?>
                <tr>
                    <td><?= h($label) ?></td>
                    <td><?= healthBadge((bool)$ok) ?></td>
                    <td class="small"><?= h($detail) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2>Table Counts</h2>
        <div class="grid">
            <?php foreach ($tableCounts as $table => $count): ?>
                <div class="metric">
                    <div class="small"><?= h($table) ?></div>
                    <strong><?= h($count) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Schema Migrations</h2>
        <div class="grid">
            <div class="metric">
                <div class="small">Current schema version</div>
                <strong><?= h($schemaVersion) ?></strong>
            </div>
            <div class="metric">
                <div class="small">Applied migrations</div>
                <strong><?= h(count($appliedMigrations)) ?></strong>
            </div>
            <div class="metric">
                <div class="small">Pending migrations</div>
                <strong><?= h(count($pendingMigrations)) ?></strong>
            </div>
        </div>

        <div class="mt-3">
            <table>
                <tr><th>Applied migration files</th></tr>
                <?php foreach ($appliedMigrations as $version): ?>
                    <tr><td class="small"><?= h($version) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php if ($pendingMigrations): ?>
            <div class="mt-3">
                <table>
                    <tr><th>Pending migration files</th></tr>
                    <?php foreach ($pendingMigrations as $version): ?>
                        <tr><td class="small"><?= h($version) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent Audit Events</h2>
        <table>
            <tr><th>Date</th><th>Action</th><th>Target</th><th>Details</th></tr>
            <?php if (!$auditRows): ?>
                <tr><td colspan="4">No recent audit events found.</td></tr>
            <?php endif; ?>
            <?php foreach ($auditRows as $row): ?>
                <tr>
                    <td><?= h($row['created_at']) ?></td>
                    <td><?= h($row['action']) ?></td>
                    <td><?= h(($row['target_type'] ?? '') . (($row['target_id'] ?? '') !== '' ? ' #' . $row['target_id'] : '')) ?></td>
                    <td><?= h($row['details']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
