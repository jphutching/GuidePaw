<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user  = requireApiUser($pdo);
$dogId = (int) ($user['active_dog_id'] ?? 0);

if ($dogId <= 0) {
    apiJson(['success' => true, 'dog_id' => null, 'message' => 'No active dog.', 'stats' => null]);
}

// Summary counts
$totalLogs    = (int) getScalar($pdo, 'SELECT COUNT(*) FROM daily_logs WHERE dog_id = ?', [$dogId], 0);
$avgFocus     = $totalLogs ? (float) getScalar($pdo, 'SELECT ROUND(AVG(focus_level)::numeric, 2) FROM daily_logs WHERE dog_id = ?', [$dogId], 0) : 0.0;
$logsThisWeek  = (int) getScalar($pdo, "SELECT COUNT(*) FROM daily_logs WHERE dog_id = ? AND log_date >= " . dbDateSub('CURRENT_DATE', 7, 'DAY'), [$dogId], 0);
$logsThisMonth = (int) getScalar($pdo, "SELECT COUNT(*) FROM daily_logs WHERE dog_id = ? AND log_date >= " . dbDateSub('CURRENT_DATE', 30, 'DAY'), [$dogId], 0);

// Top skills
$skillStmt = $pdo->prepare('SELECT skills_practiced FROM daily_logs WHERE dog_id = ?');
$skillStmt->execute([$dogId]);
$counts = [];
foreach ($skillStmt->fetchAll(PDO::FETCH_COLUMN) as $raw) {
    foreach (json_decode((string) $raw, true) ?: [] as $skill) {
        $skill = trim((string) $skill);
        if ($skill !== '') {
            $counts[$skill] = ($counts[$skill] ?? 0) + 1;
        }
    }
}
arsort($counts);
$topSkills = [];
foreach (array_slice($counts, 0, 8, true) as $skill => $count) {
    $topSkills[] = ['skill' => $skill, 'count' => $count];
}

// Environment breakdown
$envStmt = $pdo->prepare('SELECT location_type, COUNT(*) AS total FROM daily_logs WHERE dog_id = ? GROUP BY location_type ORDER BY total DESC, location_type ASC');
$envStmt->execute([$dogId]);
$locationBreakdown = array_values(array_map(static fn(array $r): array => [
    'type'  => (string) ($r['location_type'] ?: 'Other'),
    'count' => (int) $r['total'],
], $envStmt->fetchAll(PDO::FETCH_ASSOC) ?: []));

// Last 14 days trend
$trendStmt = $pdo->prepare("SELECT DATE(log_date) AS day_label, COUNT(*) AS total_logs, ROUND(AVG(focus_level)::numeric, 2) AS avg_focus FROM daily_logs WHERE dog_id = ? AND log_date >= " . dbDateSub('CURRENT_DATE', 13, 'DAY') . " GROUP BY DATE(log_date) ORDER BY day_label ASC");
$trendStmt->execute([$dogId]);
$trend14d = array_values(array_map(static fn(array $r): array => [
    'date'      => (string) $r['day_label'],
    'logs'      => (int) $r['total_logs'],
    'avg_focus' => (float) $r['avg_focus'],
], $trendStmt->fetchAll(PDO::FETCH_ASSOC) ?: []));

apiJson([
    'success' => true,
    'dog_id'  => $dogId,
    'stats'   => [
        'total_logs'         => $totalLogs,
        'avg_focus'          => $avgFocus,
        'logs_this_week'     => $logsThisWeek,
        'logs_this_month'    => $logsThisMonth,
        'top_skills'         => $topSkills,
        'location_breakdown' => $locationBreakdown,
        'trend_14d'          => $trend14d,
    ],
]);
