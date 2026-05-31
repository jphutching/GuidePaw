<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
checkLogin();
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/audit_log.php';
requireAdmin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $enabled = $_POST['enabled'] ?? [];
    $stmt = $pdo->query("SELECT flag_key FROM feature_flags");
    $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE feature_flags SET is_enabled = :enabled, updated_at = CURRENT_TIMESTAMP WHERE flag_key = :key");
    foreach ($keys as $key) {
        $update->execute([
            ':enabled' => isset($enabled[$key]) ? 1 : 0,
            ':key' => $key,
        ]);
    }

    writeAuditLog($pdo, 'feature_flags_updated', 'feature_flags', null, 'Admin updated feature flag settings.');

    $message = 'Feature flags updated.';
}

$flags = getFeatureFlags($pdo);
$csrf = generateCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 760px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 18px; margin: 14px 0; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .row { display: flex; gap: 12px; align-items: flex-start; justify-content: space-between; }
        .label { font-weight: 700; font-size: 1.05rem; }
        .desc { color: #666; margin-top: 4px; }
        .msg { background: #e8f7ee; border: 1px solid #a8dfbd; padding: 10px; border-radius: 10px; }
        button, .btn { display: inline-block; border: 0; border-radius: 10px; padding: 12px 16px; background: #1f2937; color: white; text-decoration: none; font-weight: 700; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        input[type="checkbox"] { transform: scale(1.4); margin-top: 6px; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>


<div class="wrap">
    <div class="top">
        <h1>GuidePaw Admin</h1>
        <a class="btn" href="index.php">Handler Dashboard</a>
    </div>

    <div class="card">
        <div class="label">Admin Control Panel</div>
        <div class="desc">Core admin jobs stay up front. Less-used tools move under the folded section below.</div>
        <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn" href="admin_users.php">User Management</a>
            <a class="btn" href="admin_feedback.php">Feedback Reports</a>
            <a class="btn" href="admin_funding_settings.php">Funding Settings</a>
            <a class="btn" href="admin_business_costs.php">Business Costs</a>
            <a class="btn" href="admin_found_dog_reports.php">Found Dog Reports</a>
        </div>
    </div>

    <details class="card">
        <summary class="label" style="cursor:pointer; list-style:none;">More admin tools</summary>
        <div class="desc">Beta, roadmap, backup, audit, notifications, and health checks stay here unless you need them.</div>
        <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn" href="admin_beta_requests.php">Beta Requests</a>
            <a class="btn" href="admin_tactical_requests.php">Tactical Requests</a>
            <a class="btn" href="admin_feature_roadmap.php">Feature Roadmap</a>
            <a class="btn" href="admin_paywall_catalog.php">Paywall Catalog</a>
            <a class="btn" href="db_status.php">System Health</a>
            <a class="btn" href="admin_notification_test.php">Notifications</a>
            <a class="btn" href="export_backup.php?format=package">Backup Snapshot</a>
            <a class="btn" href="backup.php">Backup Tools</a>
            <a class="btn" href="admin_audit_log.php">Audit Log</a>
            <a class="btn" href="beta_qa_checklist.php">QA Checklist</a>
        </div>
    </details>

    <?php if ($message): ?>
        <div class="msg"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="label">Beta Status</div>
        <div class="desc">
            <?= count(array_filter($flags, fn($f) => (int)$f['is_enabled'] === 1)) ?> enabled /
            <?= count($flags) ?> total feature flags.
        </div>
    </div>

    <details class="card">
        <summary class="label" style="cursor:pointer; list-style:none;">Feature Flags</summary>
        <div class="desc"><?= count(array_filter($flags, fn($f) => (int)$f['is_enabled'] === 1)) ?> enabled / <?= count($flags) ?> total feature flags. Toggle saves immediately.</div>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <?php foreach ($flags as $flag): ?>
                <div class="card">
                    <div class="row">
                        <div>
                            <div class="label"><?= e($flag['label']) ?></div>
                            <div class="desc"><?= e($flag['description'] ?? '') ?></div>
                        </div>
                        <label>
                            <input type="checkbox" name="enabled[<?= e($flag['flag_key']) ?>]" value="1" <?= ((int)$flag['is_enabled'] === 1) ? 'checked' : '' ?> onchange="this.form.submit()">
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </form>
    </details>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
