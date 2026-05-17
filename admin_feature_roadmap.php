<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/form_ux.php';

requireAdmin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$adminCheck = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
$adminCheck->execute([$userId]);
$debugAdminValue = (int)$adminCheck->fetchColumn();
if ($debugAdminValue !== 1) {
    $_SESSION['is_admin'] = 0;
    header('Location: index.php?msg=admin_required');
    exit;
}
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/audit_log.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
requireAdmin();
}

$stmt = $pdo->query("
    SELECT
        fr.id,
        fr.flag_key,
        ff.label,
        ff.is_enabled,
        fr.priority_level,
        fr.lifecycle_status,
        fr.owner_name,
        fr.milestone,
        fr.success_metric,
        fr.acceptance_criteria,
        fr.release_notes
    FROM feature_roadmap fr
    LEFT JOIN feature_flags ff ON ff.flag_key = fr.flag_key
    ORDER BY
        CASE fr.priority_level
            WHEN 'must' THEN 1
            WHEN 'should' THEN 2
            WHEN 'could' THEN 3
            ELSE 4
        END,
        fr.milestone,
        COALESCE(ff.sort_order, 999),
        fr.flag_key
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = (($_GET['msg'] ?? '') === 'roadmap_updated') ? 'Roadmap item updated.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $lifecycleStatus = trim($_POST['lifecycle_status'] ?? 'backlog');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $milestone = trim($_POST['milestone'] ?? '');
    $releaseNotes = trim($_POST['release_notes'] ?? '');
    $flagEnabled = isset($_POST['is_enabled']) ? 1 : 0;

    $allowedStatuses = [
        'backlog',
        'spec_ready',
        'feature_flag_created',
        'database_api_ready',
        'ui_hidden_behind_flag',
        'internal_testing',
        'beta_enabled',
        'metrics_reviewed',
        'fully_released',
        'maintenance_owner_assigned'
    ];

    if (!in_array($lifecycleStatus, $allowedStatuses, true)) {
        $lifecycleStatus = 'backlog';
    }

    $stmt = $pdo->prepare("
        UPDATE feature_roadmap
        SET lifecycle_status = ?,
            owner_name = ?,
            milestone = ?,
            release_notes = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$lifecycleStatus, $ownerName, $milestone, $releaseNotes, $id]);

    $flagStmt = $pdo->prepare("
        UPDATE feature_flags
        SET is_enabled = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE flag_key = (
            SELECT flag_key FROM feature_roadmap WHERE id = ?
        )
    ");
    $flagStmt->execute([$flagEnabled, $id]);

    writeAuditLog($pdo, 'roadmap_item_updated', 'feature_roadmap', $id, 'Admin updated roadmap item and feature flag state.');

    header('Location: admin_feature_roadmap.php?msg=roadmap_updated');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Feature Roadmap</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1200px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 16px; }
        a { color: #0b5ed7; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; text-align: left; }
        th { background: #eee; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; background: #e9ecef; }
        .enabled { background: #d1e7dd; }
        .disabled { background: #f8d7da; }
        .must { background: #ffe5d0; }
        .should { background: #fff3cd; }
        .could { background: #e2e3ff; }
        .small { color: #666; font-size: 13px; }
        input, select, textarea { width: 100%; box-sizing: border-box; padding: 6px; }
        textarea { min-height: 54px; }
        button { margin-top: 6px; padding: 6px 10px; font-weight: 700; }
    </style>

<style>
.gp-brand-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(12px, 4vw, 28px);
    margin: clamp(12px, 3vw, 22px) auto;
    flex-wrap: wrap;
}
.gp-brand-logo {
    width: clamp(120px, 34vw, 175px);
    height: auto;
    border-radius: 16px;
    display: block;
}
.gp-brand-copy {
    text-align: center;
    color: #fff;
}
.gp-brand-tagline {
    font-family: 'Trebuchet MS', 'Arial Rounded MT Bold', system-ui, sans-serif;
    font-size: clamp(.86rem, 3.1vw, 1.08rem);
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #ffffff;
    text-shadow: 0 2px 8px rgba(0,0,0,.28);
}
</style>

</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>


<div class="wrap">
    <div class="top">
        <div>
            <h1>GuidePaw Feature Roadmap</h1>
            <div class="small">Tracks Must / Should / Could roadmap items and feature flag status.</div>
        </div>
        <div><a href="admin.php">← Admin</a></div>
    </div>

    <?php if ($message): ?>
        <div style="padding:14px 16px;border-radius:10px;background:#d1e7dd;border:1px solid #badbcc;color:#0f5132;font-weight:800;margin-bottom:14px;">✅ <?= h($message) ?></div>
    <?php endif; ?>

    <table>
        <thead>
        <tr>
            <th>Feature</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Flag enabled?</th>
            <th>Owner</th>
            <th>Milestone</th>
            <th>Success metric</th>
            <th>Notes</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <form method="post">
                    <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                    <td>
                        <strong><?= h($row['label'] ?: $row['flag_key']) ?></strong><br>
                        <span class="small"><?= h($row['flag_key']) ?></span>
                    </td>
                    <td><span class="badge <?= h($row['priority_level']) ?>"><?= h(strtoupper($row['priority_level'])) ?></span></td>
                    <td>
                        <select name="lifecycle_status" onchange="this.form.submit()">
                            <?php foreach (['backlog','spec_ready','feature_flag_created','database_api_ready','ui_hidden_behind_flag','internal_testing','beta_enabled','metrics_reviewed','fully_released','maintenance_owner_assigned'] as $status): ?>
                                <option value="<?= h($status) ?>" <?= $row['lifecycle_status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <label class="small">
                            <input type="checkbox" name="is_enabled" value="1" <?= (int)$row['is_enabled'] === 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                            Enabled
                        </label>
                    </td>
                    <td><input name="owner_name" value="<?= h($row['owner_name']) ?>" placeholder="Owner" onchange="this.form.submit()"></td>
                    <td><input name="milestone" value="<?= h($row['milestone']) ?>" placeholder="Milestone" onchange="this.form.submit()"></td>
                    <td><?= h($row['success_metric']) ?></td>
                    <td>
                        <strong>Acceptance:</strong> <?= h($row['acceptance_criteria']) ?><br>
                        <textarea name="release_notes" placeholder="Notes" onchange="this.form.submit()"><?= h($row['release_notes']) ?></textarea>
                        <button type="submit">Save</button>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
