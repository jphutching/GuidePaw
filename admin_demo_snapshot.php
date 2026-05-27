<?php
require_once 'includes/db_connect.php';
require_once 'includes/authz.php';
require_once 'includes/demo_mode.php';

requireAdmin();

$pdo = $GLOBALS['pdo'];

$s = $pdo->query("SELECT id, username FROM users WHERE username LIKE 'demo.%' ORDER BY username");
$demoUsers = $s->fetchAll(PDO::FETCH_ASSOC);

$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['capture'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
    foreach ($demoUsers as $u) {
        try {
            gpCaptureDemoSnapshot($pdo, (int) $u['id']);
            $results[$u['username']] = 'OK';
        } catch (Throwable $e) {
            $results[$u['username']] = 'ERROR: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Demo Snapshot Admin</title></head>
<body>
<?php require_once 'includes/brand_header.php'; guidepawBrandHeader('Demo Snapshots', $pdo); ?>
<div style="max-width:600px;margin:2rem auto;font-family:sans-serif;">
<h2>Demo Account Snapshot Manager</h2>
<?php if ($results): ?>
    <ul>
    <?php foreach ($results as $u => $msg): ?>
        <li><strong><?= e($u) ?></strong>: <?= e($msg) ?></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p>Demo accounts detected (username LIKE <code>demo.%</code>):</p>
<ul>
<?php foreach ($demoUsers as $u): ?>
    <li><?= e($u['username']) ?> (id=<?= (int)$u['id'] ?>)</li>
<?php endforeach; ?>
</ul>
<form method="post">
    <?= '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">' ?>
    <button name="capture" value="1" type="submit"
        style="padding:.5rem 1.5rem;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;">
        Capture Snapshots Now
    </button>
</form>
<p style="margin-top:1rem;font-size:.85rem;color:#666;">
    Capturing overwrites any existing snapshot. Do this once after seeding/editing demo accounts.
    Resets happen automatically 15 min after each demo login.
</p>
</div>
</body>
</html>
