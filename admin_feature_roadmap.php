<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['is_admin'])) {
    echo 'Admin access required.';
    exit;
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


<div class="wrap">
    <div class="top">
        <div>
            <h1>GuidePaw Feature Roadmap</h1>
            <div class="small">Tracks Must / Should / Could roadmap items and feature flag status.</div>
        </div>
        <div><a href="admin.php">← Admin</a></div>
    </div>

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
                <td>
                    <strong><?= h($row['label'] ?: $row['flag_key']) ?></strong><br>
                    <span class="small"><?= h($row['flag_key']) ?></span>
                </td>
                <td><span class="badge <?= h($row['priority_level']) ?>"><?= h(strtoupper($row['priority_level'])) ?></span></td>
                <td><?= h($row['lifecycle_status']) ?></td>
                <td>
                    <?php if ((int)$row['is_enabled'] === 1): ?>
                        <span class="badge enabled">Enabled</span>
                    <?php else: ?>
                        <span class="badge disabled">Disabled</span>
                    <?php endif; ?>
                </td>
                <td><?= h($row['owner_name'] ?: 'Unassigned') ?></td>
                <td><?= h($row['milestone']) ?></td>
                <td><?= h($row['success_metric']) ?></td>
                <td>
                    <strong>Acceptance:</strong> <?= h($row['acceptance_criteria']) ?><br>
                    <?php if (!empty($row['release_notes'])): ?>
                        <span class="small"><?= h($row['release_notes']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
