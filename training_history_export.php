<?php
require_once __DIR__ . '/includes/db_connect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php?msg=login_required');
    exit;
}

$status = $_GET['status'] ?? 'active';
if (!in_array($status, ['active', 'archived', 'all'], true)) {
    $status = 'active';
}

$statusSql = $status === 'all' ? '' : "AND COALESCE(status, 'active') = ?";

function runTrainingHistoryExportQuery(PDO $pdo, string $sql, int $userId, string $status): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    if ($status !== 'all') {
        $stmt->bindValue(2, $status);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$rows = [];

$queries = [
    'goal' => "
        SELECT 'goal' AS record_type, g.created_at, d.name AS dog_name, g.goal_category AS type,
               g.current_problem AS summary, g.status
        FROM training_goals g
        JOIN dogs d ON d.id = g.dog_id
        WHERE g.user_id = ? $statusSql
        ORDER BY g.created_at DESC
    ",
    'behavior_incident' => "
        SELECT 'behavior_incident' AS record_type, b.created_at, d.name AS dog_name, b.incident_type AS type,
               b.context_environment AS summary, COALESCE(b.status, 'active') AS status
        FROM behavior_incidents b
        JOIN dogs d ON d.id = b.dog_id
        WHERE b.user_id = ? $statusSql
        ORDER BY b.created_at DESC
    ",
    'training_session' => "
        SELECT 'training_session' AS record_type, s.created_at, d.name AS dog_name, s.progression_status AS type,
               CONCAT(s.reps_successful, '/', s.reps_attempted, ' successful') AS summary,
               COALESCE(s.status, 'active') AS status
        FROM training_sessions s
        JOIN dogs d ON d.id = s.dog_id
        WHERE s.user_id = ? $statusSql
        ORDER BY s.created_at DESC
    ",
    'candidate_assessment' => "
        SELECT 'candidate_assessment' AS record_type, a.created_at, d.name AS dog_name,
               CONCAT('Focus Level ', a.focus_level_recommended) AS type,
               a.recommendation AS summary,
               COALESCE(a.status, 'active') AS status
        FROM dog_candidate_assessments a
        JOIN dogs d ON d.id = a.dog_id
        WHERE d.owner_user_id = ? $statusSql
        ORDER BY a.created_at DESC
    ",
];

foreach ($queries as $query) {
    $rows = array_merge($rows, runTrainingHistoryExportQuery($pdo, $query, $userId, $status));
}

usort($rows, function ($a, $b) {
    return strcmp((string)$b['created_at'], (string)$a['created_at']);
});

$filename = 'guidepaw-training-history-' . $status . '-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['record_type', 'created_at', 'dog_name', 'type', 'summary', 'status']);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['record_type'] ?? '',
        $row['created_at'] ?? '',
        $row['dog_name'] ?? '',
        $row['type'] ?? '',
        $row['summary'] ?? '',
        $row['status'] ?? '',
    ]);
}

fclose($out);
exit;
