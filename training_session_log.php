<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/training_progression.php';
require_once __DIR__ . '/includes/training_data.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!featureEnabled($pdo, 'training_progression_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo 'Login required.';
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'archive') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE training_sessions SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        writeAuditLog($pdo, 'training_session_archived', 'training_sessions', $sessionId, 'Training session archived.');

        header('Location: training_session_log.php?msg=updated');
        exit;
    }

    $dogId = (int)($_POST['dog_id'] ?? 0);
    $goalId = ($_POST['goal_id'] ?? '') !== '' ? (int)$_POST['goal_id'] : null;
    $moduleId = ($_POST['module_id'] ?? '') !== '' ? (int)$_POST['module_id'] : null;
    $stepId = ($_POST['step_id'] ?? '') !== '' ? (int)$_POST['step_id'] : null;
    $attempted = max(0, (int)($_POST['reps_attempted'] ?? 0));
    $successful = max(0, min($attempted, (int)($_POST['reps_successful'] ?? 0)));
    $stress = max(1, min(5, (int)($_POST['stress_level'] ?? 2)));
    $confidence = max(1, min(5, (int)($_POST['handler_confidence'] ?? 3)));
    $safetyFlag = !empty($_POST['safety_flag_active']);

    $status = progressionStatus($attempted, $successful, $stress, $confidence, $safetyFlag);
    $rate = calculateSuccessRate($attempted, $successful);

    $stmt = $pdo->prepare("
        INSERT INTO training_sessions
        (user_id, dog_id, goal_id, module_id, step_id, context_environment, reps_attempted, reps_successful, stress_level, handler_confidence, notes, progression_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $dogId,
        $goalId,
        $moduleId,
        $stepId,
        trim($_POST['context_environment'] ?? ''),
        $attempted,
        $successful,
        $stress,
        $confidence,
        trim($_POST['notes'] ?? ''),
        $status
    ]);

    if ($status === 'regression_detected' || $status === 'paused_for_review') {
        createRegressionEvent(
            $pdo,
            $userId,
            $dogId,
            $goalId,
            $moduleId,
            $status,
            recommendRecoveryStep($stress, $safetyFlag)
        );
    }

    $message = 'Training session saved.';
    $result = [
        'rate' => number_format($rate * 100, 0) . '%',
        'status' => $status,
        'recommendation' => recommendRecoveryStep($stress, $safetyFlag)
    ];
}

$dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC);

$goalsStmt = $pdo->prepare("
    SELECT g.id, g.goal_category, g.success_criteria, d.name AS dog_name
    FROM training_goals g
    JOIN dogs d ON d.id = g.dog_id
    WHERE g.user_id = ? AND g.status = 'active'
    ORDER BY g.created_at DESC
");
$goalsStmt->execute([$userId]);
$goals = $goalsStmt->fetchAll(PDO::FETCH_ASSOC);

$modules = $pdo->query("SELECT id, title FROM training_modules WHERE is_active = 1 ORDER BY level_number, sort_order")->fetchAll(PDO::FETCH_ASSOC);
$commandCueGroups = getTrainingCommandCueSuggestions();

$recentStmt = $pdo->prepare("
    SELECT s.*, d.name AS dog_name, m.title AS module_title
    FROM training_sessions s
    JOIN dogs d ON d.id = s.dog_id
    LEFT JOIN training_modules m ON m.id = s.module_id
    WHERE s.user_id = ? AND COALESCE(s.status, 'active') = 'active'
    ORDER BY s.created_at DESC
    LIMIT 8
");
$recentStmt->execute([$userId]);
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Training Session Log</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 980px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        select, textarea, input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { min-height: 70px; }
        button { margin-top: 16px; padding: 10px 14px; font-weight: 700; }
        .alert { padding: 10px; border-radius: 8px; background: #d1e7dd; margin-bottom: 12px; }
        .result { background: #e7f1ff; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .small { color: #666; font-size: 13px; }
        .cue-guide { background:#f8fafc; border:1px solid #dbeafe; border-radius:10px; padding:12px; margin:12px 0; }
        .cue-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap:8px; margin-top:8px; }
        .cue-box { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:8px; }
        .cue-pill { display:inline-block; border:1px solid #cbd5e1; border-radius:999px; padding:2px 7px; margin:2px 0; font-weight:700; background:#fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eee; }
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
    <p><a href="training_program.php">← Training Program</a></p>
    <h1>Training Session Log</h1>
    <p class="small">Log one tiny session. GuidePaw decides whether to progress, repeat, reset, or pause.</p>

    <?php if ($message): ?><div class="alert"><?= h($message) ?></div><?php endif; ?>

    <?php if ($result): ?>
        <div class="result">
            <strong>Success rate:</strong> <?= h($result['rate']) ?><br>
            <strong>Status:</strong> <?= h($result['status']) ?><br>
            <strong>Reset note:</strong> <?= h($result['recommendation']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if (!$dogs): ?>
            <p>No dogs found. Add a dog profile first.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <label>Dog</label>
                <select name="dog_id" required>
                    <?php foreach ($dogs as $dog): ?>
                        <option value="<?= h($dog['id']) ?>"><?= h($dog['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Goal</label>
                <select name="goal_id">
                    <option value="">No specific goal</option>
                    <?php foreach ($goals as $goal): ?>
                        <option value="<?= h($goal['id']) ?>"><?= h($goal['dog_name'] . ' - ' . ($goal['success_criteria'] ?: $goal['goal_category'])) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Module</label>
                <select name="module_id">
                    <option value="">No module selected</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= h($module['id']) ?>"><?= h($module['title']) ?></option>
                    <?php endforeach; ?>
                </select>


                <?php if (!empty($commandCueGroups)): ?>
                    <div class="cue-guide">
                        <strong>Suggested command words</strong>
                        <div class="small">Use these as a quick reference while logging. Pick one cue per behavior and stay consistent.</div>
                        <div class="cue-grid">
                            <?php foreach ($commandCueGroups as $groupName => $cueItems): ?>
                                <div class="cue-box">
                                    <strong><?= h($groupName) ?></strong>
                                    <?php foreach (array_slice($cueItems, 0, 4) as $cueItem): ?>
                                        <div class="small">
                                            <?= h($cueItem['skill']) ?>:
                                            <span class="cue-pill"><?= h($cueItem['cue']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <label>Context / environment</label>
                <textarea name="context_environment" placeholder="Cab, truck stop, fuel island, home, parking lot"></textarea>

                <label>Reps attempted</label>
                <input type="number" name="reps_attempted" value="5" min="0" max="50">

                <label>Reps successful</label>
                <input type="number" name="reps_successful" value="4" min="0" max="50">

                <label>Dog stress level</label>
                <select name="stress_level">
                    <option value="1">1 - relaxed</option>
                    <option value="2" selected>2 - mild</option>
                    <option value="3">3 - workable</option>
                    <option value="4">4 - stressed</option>
                    <option value="5">5 - unsafe / overwhelmed</option>
                </select>

                <label>Handler confidence</label>
                <select name="handler_confidence">
                    <option value="1">1 - not confident</option>
                    <option value="2">2</option>
                    <option value="3" selected>3 - okay</option>
                    <option value="4">4</option>
                    <option value="5">5 - very confident</option>
                </select>

                <label>
                    <input type="checkbox" name="safety_flag_active" value="1" style="width:auto;">
                    Safety flag active
                </label>

                <label>Notes</label>
                <textarea name="notes" placeholder="What worked? What distracted the dog? What should be easier next time?"></textarea>

                <button type="submit">Save Session</button>
            </form>
        <?php endif; ?>
    </div>

    <h2>Recent Sessions</h2>
    <table>
        <tr>
            <th>Date</th>
            <th>Dog</th>
            <th>Module</th>
            <th>Success</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= h($row['created_at']) ?></td>
                <td><?= h($row['dog_name']) ?></td>
                <td><?= h($row['module_title']) ?></td>
                <td><?= h($row['reps_successful'] . '/' . $row['reps_attempted']) ?></td>
                <td><?= h($row['progression_status']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Archive this training session?');">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="session_id" value="<?= h($row['id']) ?>">
                        <button type="submit">Archive</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
