<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';

requireAdmin();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$action = trim($_GET['action'] ?? '');
$userFilter = trim($_GET['user_id'] ?? '');
$params = [];
$where = [];

if ($action !== '') {
    $where[] = 'action = ?';
    $params[] = $action;
}

if ($userFilter !== '' && ctype_digit($userFilter)) {
    $where[] = 'user_id = ?';
    $params[] = (int)$userFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT id, user_id, action, target_type, target_id, details, ip_address, created_at
    FROM admin_audit_log
    $whereSql
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$actions = $pdo->query("
    SELECT DISTINCT action
    FROM admin_audit_log
    ORDER BY action
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Admin Audit Log</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1180px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 18px; margin: 14px 0; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        a, .btn, button { display: inline-block; border: 0; border-radius: 10px; padding: 10px 14px; background: #1f2937; color: white; text-decoration: none; font-weight: 700; }
        select, input { padding: 10px; border: 1px solid #ccc; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .small { color: #666; font-size: 13px; }
        .filters { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
        @media (max-width: 760px) {
            table { font-size: 13px; }
            .hide-small { display:none; }
        }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wrap">
    <div class="top">
        <h1>Admin Audit Log</h1>
        <a href="admin.php">Back to Admin</a>
    </div>

    <div class="card">
        <form class="filters" method="get" data-dirty-watch="off">
            <label>
                <div class="small">Action</div>
                <select name="action">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= h($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= h($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <div class="small">User ID</div>
                <input name="user_id" value="<?= h($userFilter) ?>" placeholder="Any">
            </label>

            <button type="submit">Filter</button>
            <a href="admin_audit_log.php">Reset</a>
        </form>
    </div>

    <div class="card">
        <p class="small">Showing latest 100 matching audit records.</p>
        <table>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Action</th>
                <th>Target</th>
                <th>Details</th>
                <th class="hide-small">IP</th>
            </tr>
            <?php if (!$rows): ?>
                <tr><td colspan="6">No audit records found.</td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= h($row['created_at']) ?></td>
                    <td><?= h($row['user_id']) ?></td>
                    <td><?= h($row['action']) ?></td>
                    <td><?= h(($row['target_type'] ?? '') . (($row['target_id'] ?? '') !== '' ? ' #' . $row['target_id'] : '')) ?></td>
                    <td><?= h($row['details']) ?></td>
                    <td class="hide-small"><?= h($row['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
