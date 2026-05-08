<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo 'Login required.';
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$status = $_GET['status'] ?? 'active';
if (!in_array($status, ['active', 'archived', 'all'], true)) {
    $status = 'active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_record') {
    $recordType = $_POST['record_type'] ?? '';
    $recordId = (int)($_POST['record_id'] ?? 0);

    if ($recordId > 0) {
        if ($recordType === 'goal') {
            $stmt = $pdo->prepare("UPDATE training_goals SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$recordId, $userId]);
            writeAuditLog($pdo, 'training_goal_restored', 'training_goals', $recordId, 'Training goal restored.');
        } elseif ($recordType === 'incident') {
            $stmt = $pdo->prepare("UPDATE behavior_incidents SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$recordId, $userId]);
            writeAuditLog($pdo, 'behavior_incident_restored', 'behavior_incidents', $recordId, 'Behavior incident restored.');
        } elseif ($recordType === 'session') {
            $stmt = $pdo->prepare("UPDATE training_sessions SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$recordId, $userId]);
            writeAuditLog($pdo, 'training_session_restored', 'training_sessions', $recordId, 'Training session restored.');
        } elseif ($recordType === 'assessment') {
            $stmt = $pdo->prepare("
                UPDATE dog_candidate_assessments a
                SET status = 'active',
                    updated_at = CURRENT_TIMESTAMP
                FROM dogs d
                WHERE a.id = ?
                  AND a.dog_id = d.id
                  AND d.owner_user_id = ?
            ");
            $stmt->execute([$recordId, $userId]);
            writeAuditLog($pdo, 'candidate_assessment_restored', 'dog_candidate_assessments', $recordId, 'Candidate assessment restored.');
        }
    }

    header('Location: training_history.php?status=archived&msg=updated');
    exit;
}

$statusSql = $status === 'all' ? '' : "AND COALESCE(status, 'active') = ?";

function bindStatus(PDOStatement $stmt, int $userId, string $status): void {
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    if ($status !== 'all') {
        $stmt->bindValue(2, $status);
    }
}

$goalsSql = "
    SELECT g.id, 'goal' AS record_type, g.created_at, d.name AS dog_name, g.goal_category AS type, g.current_problem AS summary, g.status
    FROM training_goals g
    JOIN dogs d ON d.id = g.dog_id
    WHERE g.user_id = ? $statusSql
    ORDER BY g.created_at DESC
    LIMIT 20
";
$stmt = $pdo->prepare($goalsSql);
bindStatus($stmt, $userId, $status);
$stmt->execute();
$goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$incidentsSql = "
    SELECT b.id, 'incident' AS record_type, b.created_at, d.name AS dog_name, b.incident_type AS type, b.context_environment AS summary, COALESCE(b.status, 'active') AS status
    FROM behavior_incidents b
    JOIN dogs d ON d.id = b.dog_id
    WHERE b.user_id = ? $statusSql
    ORDER BY b.created_at DESC
    LIMIT 20
";
$stmt = $pdo->prepare($incidentsSql);
bindStatus($stmt, $userId, $status);
$stmt->execute();
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sessionsSql = "
    SELECT s.id, 'session' AS record_type, s.created_at, d.name AS dog_name, s.progression_status AS type,
           CONCAT(s.reps_successful, '/', s.reps_attempted, ' successful') AS summary,
           COALESCE(s.status, 'active') AS status
    FROM training_sessions s
    JOIN dogs d ON d.id = s.dog_id
    WHERE s.user_id = ? $statusSql
    ORDER BY s.created_at DESC
    LIMIT 20
";
$stmt = $pdo->prepare($sessionsSql);
bindStatus($stmt, $userId, $status);
$stmt->execute();
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assessmentsSql = "
    SELECT a.id, 'assessment' AS record_type, a.created_at, d.name AS dog_name,
           CONCAT('Focus Level ', a.focus_level_recommended) AS type,
           a.recommendation AS summary,
           COALESCE(a.status, 'active') AS status
    FROM dog_candidate_assessments a
    JOIN dogs d ON d.id = a.dog_id
    WHERE d.owner_user_id = ? $statusSql
    ORDER BY a.created_at DESC
    LIMIT 20
";
$stmt = $pdo->prepare($assessmentsSql);
bindStatus($stmt, $userId, $status);
$stmt->execute();
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function renderRows(array $rows): void {
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . h($row['created_at']) . '</td>';
        echo '<td>' . h($row['dog_name']) . '</td>';
        echo '<td>' . h($row['type']) . '</td>';
        echo '<td>' . h($row['summary']) . '</td>';
        echo '<td>' . h($row['status']) . '</td>';
        echo '<td>';
        if (($row['status'] ?? '') === 'archived') {
            echo '<form method="post" onsubmit="return confirm(\'Restore this record?\');">';
            echo '<input type="hidden" name="action" value="restore_record">';
            echo '<input type="hidden" name="record_type" value="' . h($row['record_type']) . '">';
            echo '<input type="hidden" name="record_id" value="' . h($row['id']) . '">';
            echo '<button type="submit">Restore</button>';
            echo '</form>';
        }
        echo '</td>';
        echo '</tr>';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Training History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 18px; }
        a { color: #0b5ed7; text-decoration: none; }
        .filters a { display: inline-block; margin: 0 8px 8px 0; padding: 8px 10px; border: 1px solid #ddd; border-radius: 999px; background: #fff; }
        .filters a.active { background: #0d6efd; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .small { color: #666; font-size: 13px; }
        button { padding: 8px 10px; font-weight: 700; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wrap">
    <p><a href="training_program.php">← Training Program</a></p>
    <h1>Training History</h1>
    <p class="small">Review active and archived GuidePaw training records.</p>

    <div class="filters">
        <a class="<?= $status === 'active' ? 'active' : '' ?>" href="?status=active">Active</a>
        <a class="<?= $status === 'archived' ? 'active' : '' ?>" href="?status=archived">Archived</a>
        <a class="<?= $status === 'all' ? 'active' : '' ?>" href="?status=all">All</a>
        <a href="training_history_export.php?status=<?= h($status) ?>">⬇ Export CSV</a>
    </div>

    <?php foreach ([
        'Goals' => $goals,
        'Behavior Incidents' => $incidents,
        'Training Sessions' => $sessions,
        'Candidate Assessments' => $assessments
    ] as $title => $rows): ?>
        <div class="card">
            <h2><?= h($title) ?></h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Dog</th>
                    <th>Type</th>
                    <th>Summary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <?php if (!$rows): ?>
                    <tr><td colspan="6">No records found.</td></tr>
                <?php else: ?>
                    <?php renderRows($rows); ?>
                <?php endif; ?>
            </table>
        </div>
    <?php endforeach; ?>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
