<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
checkLogin();
$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];

function getScalar(PDO $pdo, string $sql, array $params, $fallback = 0) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $fallback;
}

$totalLogs = (int) getScalar($pdo, 'SELECT COUNT(*) FROM daily_logs WHERE dog_id = ?', [$dogId], 0);
$avgFocus = (float) getScalar($pdo, 'SELECT ROUND(AVG(focus_level), 2) FROM daily_logs WHERE dog_id = ?', [$dogId], 0);
$logsThisWeek = (int) getScalar($pdo, "SELECT COUNT(*) FROM daily_logs WHERE dog_id = ? AND log_date >= " . dbDateSub('CURRENT_TIMESTAMP', 7, 'DAY'), [$dogId], 0);
$logsThisMonth = (int) getScalar($pdo, "SELECT COUNT(*) FROM daily_logs WHERE dog_id = ? AND log_date >= " . dbDateSub('CURRENT_TIMESTAMP', 30, 'DAY'), [$dogId], 0);
$mediaCount = (int) getScalar($pdo, "SELECT COUNT(*) FROM daily_logs WHERE dog_id = ? AND media_url IS NOT NULL AND media_url <> ''", [$dogId], 0);
$gpsCount = (int) getScalar($pdo, 'SELECT COUNT(*) FROM daily_logs WHERE dog_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL', [$dogId], 0);
$handlerCount = (int) getScalar($pdo, 'SELECT COUNT(DISTINCT user_id) FROM daily_logs WHERE dog_id = ?', [$dogId], 0);

$skillRows = [];
$fallback = $pdo->prepare('SELECT skills_practiced FROM daily_logs WHERE dog_id = ?');
$fallback->execute([$dogId]);
$counts = [];
foreach ($fallback->fetchAll() as $row) {
    foreach (json_decode($row['skills_practiced'] ?? '[]', true) ?: [] as $skill) {
        $skill = trim((string) $skill);
        if ($skill === '') {
            continue;
        }
        $counts[$skill] = ($counts[$skill] ?? 0) + 1;
    }
}
arsort($counts);
foreach (array_slice($counts, 0, 6, true) as $skill => $count) {
    $skillRows[] = ['skill' => $skill, 'skill_count' => $count];
}
$locationStmt = $pdo->prepare('SELECT location_type, COUNT(*) AS total FROM daily_logs WHERE dog_id = ? GROUP BY location_type ORDER BY total DESC, location_type ASC');
$locationStmt->execute([$dogId]);
$locationBreakdown = $locationStmt->fetchAll();
$trendStmt = $pdo->prepare("SELECT DATE(log_date) AS day_label, COUNT(*) AS total_logs, ROUND(AVG(focus_level), 2) AS avg_focus FROM daily_logs WHERE dog_id = ? AND log_date >= " . dbDateSub('CURRENT_DATE', 13, 'DAY') . " GROUP BY DATE(log_date) ORDER BY day_label ASC");
$trendStmt->execute([$dogId]);
$trendRows = $trendStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Training Stats</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet"><style>.stat-card{border:0;border-radius:1rem}.mini-bar{height:8px;border-radius:999px;background:#e9ecef;overflow:hidden}.mini-bar>span{display:block;height:100%;background:#0d6efd}</style></head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?><div class="container py-4" style="max-width: 920px;"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-0">📊 Training Stats</h2><small class="text-muted">Active dog: <?= e($dog['name']) ?></small></div><a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a></div><div class="row g-3 mb-3"><div class="col-6 col-md-3"><div class="card shadow-sm stat-card"><div class="card-body"><div class="text-muted small">Total Logs</div><div class="fs-3 fw-bold"><?= $totalLogs ?></div></div></div></div><div class="col-6 col-md-3"><div class="card shadow-sm stat-card"><div class="card-body"><div class="text-muted small">Avg Focus</div><div class="fs-3 fw-bold"><?= $totalLogs ? e(number_format($avgFocus,2)) : '—' ?></div></div></div></div><div class="col-6 col-md-3"><div class="card shadow-sm stat-card"><div class="card-body"><div class="text-muted small">Logs This Week</div><div class="fs-3 fw-bold"><?= $logsThisWeek ?></div></div></div></div><div class="col-6 col-md-3"><div class="card shadow-sm stat-card"><div class="card-body"><div class="text-muted small">Handlers Logging</div><div class="fs-3 fw-bold"><?= $handlerCount ?></div></div></div></div></div><div class="row g-3 mb-3"><div class="col-md-6"><div class="card shadow-sm stat-card h-100"><div class="card-body"><h5 class="card-title">Coverage</h5><div class="d-flex justify-content-between"><span>Logs with GPS</span><strong><?= $gpsCount ?>/<?= $totalLogs ?></strong></div><div class="mini-bar mb-3"><span style="width: <?= $totalLogs ? min(100, round(($gpsCount / $totalLogs) * 100)) : 0 ?>%"></span></div><div class="d-flex justify-content-between"><span>Logs with Media</span><strong><?= $mediaCount ?>/<?= $totalLogs ?></strong></div><div class="mini-bar"><span style="width: <?= $totalLogs ? min(100, round(($mediaCount / $totalLogs) * 100)) : 0 ?>%"></span></div></div></div></div><div class="col-md-6"><div class="card shadow-sm stat-card h-100"><div class="card-body"><h5 class="card-title">Activity Window</h5><p class="mb-2"><strong>This week:</strong> <?= $logsThisWeek ?> logs</p><p class="mb-0"><strong>This month:</strong> <?= $logsThisMonth ?> logs</p></div></div></div></div><div class="row g-3"><div class="col-lg-6"><div class="card shadow-sm stat-card h-100"><div class="card-body"><h5 class="card-title">Top Skills</h5><?php if (!$skillRows): ?><p class="text-muted mb-0">No skill data yet.</p><?php else: ?><?php $maxSkill = max(array_column($skillRows, 'skill_count')); foreach ($skillRows as $skillRow): ?><div class="mb-3"><div class="d-flex justify-content-between small mb-1"><span><?= e($skillRow['skill']) ?></span><strong><?= (int) $skillRow['skill_count'] ?></strong></div><div class="mini-bar"><span style="width: <?= $maxSkill ? round(($skillRow['skill_count'] / $maxSkill) * 100) : 0 ?>%"></span></div></div><?php endforeach; ?><?php endif; ?></div></div></div><div class="col-lg-6"><div class="card shadow-sm stat-card h-100"><div class="card-body"><h5 class="card-title">Environment Breakdown</h5><?php if (!$locationBreakdown): ?><p class="text-muted mb-0">No environment data yet.</p><?php else: ?><?php $maxLocation = max(array_column($locationBreakdown, 'total')); foreach ($locationBreakdown as $row): ?><div class="mb-3"><div class="d-flex justify-content-between small mb-1"><span><?= e($row['location_type'] ?: 'Other') ?></span><strong><?= (int) $row['total'] ?></strong></div><div class="mini-bar"><span style="width: <?= $maxLocation ? round(($row['total'] / $maxLocation) * 100) : 0 ?>%"></span></div></div><?php endforeach; ?><?php endif; ?></div></div></div></div><div class="card shadow-sm stat-card mt-3"><div class="card-body"><h5 class="card-title">Last 14 Days</h5><?php if (!$trendRows): ?><p class="text-muted mb-0">No recent logs yet.</p><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Logs</th><th>Avg Focus</th></tr></thead><tbody><?php foreach ($trendRows as $row): ?><tr><td><?= e(date('M d', strtotime($row['day_label']))) ?></td><td><?= (int) $row['total_logs'] ?></td><td><?= e(number_format((float) $row['avg_focus'], 2)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div><script src="app.js"></script></body></html>