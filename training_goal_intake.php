<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/training_goals.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!featureEnabled($pdo, 'goal_intake_enabled')) {
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

$view = strtolower(trim((string)($_GET['view'] ?? 'active')));
if (!in_array($view, ['active', 'archived', 'all'], true)) {
    $view = 'active';
}

$dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC);

$message = match ($_GET['msg'] ?? '') {
    'saved' => 'Training goal saved.',
    'updated' => 'Training goal updated.',
    default => '',
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $goalId = (int)($_POST['goal_id'] ?? 0);

    if ($action === 'archive') {
        if ($goalId > 0 && updateTrainingGoalStatus($pdo, $goalId, $userId, 'archived')) {
            writeAuditLog($pdo, 'training_goal_archived', 'training_goals', $goalId, 'Training goal archived.');
        }
        header('Location: training_goal_intake.php?view=' . urlencode($view) . '&msg=updated');
        exit;
    }

    if ($action === 'restore') {
        if ($goalId > 0 && updateTrainingGoalStatus($pdo, $goalId, $userId, 'active')) {
            writeAuditLog($pdo, 'training_goal_restored', 'training_goals', $goalId, 'Training goal restored.');
        }
        header('Location: training_goal_intake.php?view=' . urlencode($view) . '&msg=updated');
        exit;
    }

    createTrainingGoal($pdo, $userId, $_POST);

    header('Location: training_goal_intake.php?view=active&msg=saved');
    exit;
}

$recent = getRecentTrainingGoals($pdo, $userId, 8, $view);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Goal Intake</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 980px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        select, textarea, input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { min-height: 80px; }
        button { margin-top: 16px; padding: 10px 14px; font-weight: 700; }
        .alert { padding: 10px; border-radius: 8px; background: #d1e7dd; margin-bottom: 12px; }
        .small { color: #666; font-size: 13px; }
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
<?php require_once 'includes/mobile_nav.php'; ?>


<div class="wrap">
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Training Goal Intake</h1>
    <p class="small">Turn a real-life problem into a measurable GuidePaw training goal.</p>

    <?php if ($message): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <div style="margin-bottom:12px;">
        <a href="?view=active" style="display:inline-block;margin-right:8px;<?= $view === 'active' ? 'font-weight:800;text-decoration:underline;' : '' ?>">Active</a>
        <a href="?view=archived" style="display:inline-block;margin-right:8px;<?= $view === 'archived' ? 'font-weight:800;text-decoration:underline;' : '' ?>">Archived</a>
        <a href="?view=all" style="display:inline-block;<?= $view === 'all' ? 'font-weight:800;text-decoration:underline;' : '' ?>">All</a>
    </div>

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

                <label>Goal category</label>
                <select name="goal_category">
                    <option value="potty">Potty routine</option>
                    <option value="leash">Loose leash / pulling</option>
                    <option value="barking">Barking</option>
                    <option value="cab_calm">Cab calm / settling</option>
                    <option value="jumping">Jumping</option>
                    <option value="public_manners">Public manners</option>
                    <option value="psd_foundation">PSD/PTSD foundation</option>
                    <option value="other">Other</option>
                </select>

                <label>What problem are you trying to solve?</label>
                <textarea name="current_problem" required placeholder="Example: My dog pulls hard at truck stops."></textarea>

                <label>What should the dog do instead?</label>
                <textarea name="desired_behavior" required placeholder="Example: Walk beside me with a loose leash and check in."></textarea>

                <label>Where does it happen?</label>
                <textarea name="context_environment" placeholder="Truck stop, fuel island, cab, home, store, parking lot"></textarea>

                <label>What triggers it?</label>
                <textarea name="trigger_description" placeholder="People, dogs, food, noise, fatigue, arriving after a long drive"></textarea>

                <label>Daily training time budget in minutes</label>
                <input type="number" name="handler_time_budget_minutes" value="3" min="1" max="30">

                <label>Best rewards</label>
                <textarea name="reinforcer_preference" placeholder="Soft treats, kibble, sniffing, tug, praise, grass access"></textarea>

                <label>
                    <input type="checkbox" name="safety_risk" value="1" style="width:auto;">
                    This may be a safety issue
                </label>

                <label>Success criteria</label>
                <textarea name="success_criteria" required placeholder="Example: At a truck stop, dog walks 20 steps beside handler with loose leash, 2 check-ins, and no lunging."></textarea>

                <label>Maintenance plan</label>
                <textarea name="maintenance_plan" placeholder="Example: Practice 3 minutes after potty breaks, reward check-ins, repeat after travel disruptions."></textarea>

                <button type="submit">Save Goal</button>
            </form>
        <?php endif; ?>
    </div>

    <h2>Recent Goals</h2>
    <table>
        <tr>
            <th>Dog</th>
            <th>Category</th>
            <th>Problem</th>
            <th>Success criteria</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= h($row['dog_name']) ?></td>
                <td><?= h($row['goal_category']) ?></td>
                <td><?= h($row['current_problem']) ?></td>
                <td><?= h($row['success_criteria']) ?></td>
                <td><?= h($row['status']) ?></td>
                <td>
                    <?php if ($row['status'] === 'active'): ?>
                        <form method="post" onsubmit="return confirm('Archive this training goal?');">
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="goal_id" value="<?= h($row['id']) ?>">
                            <button type="submit">Archive</button>
                        </form>
                    <?php elseif ($row['status'] === 'archived'): ?>
                        <form method="post" onsubmit="return confirm('Restore this training goal?');">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="goal_id" value="<?= h($row['id']) ?>">
                            <button type="submit">Restore</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
