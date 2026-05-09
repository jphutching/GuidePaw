<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/training_goals.php';
require_once __DIR__ . '/includes/goal_builder.php';

checkLogin();

if (!featureEnabled($pdo, 'goal_builder_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo 'Login required.';
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$defaultDogId = (int) ($dogs[0]['id'] ?? 0);
$status = $_GET['status'] ?? '';
$message = '';
$draft = null;
$values = [
    'dog_id' => $defaultDogId,
    'goal_category' => 'other',
    'current_problem' => '',
    'desired_behavior' => '',
    'context_environment' => '',
    'trigger_description' => '',
    'handler_time_budget_minutes' => 3,
    'reinforcer_preference' => '',
    'safety_risk' => 0,
    'success_criteria' => '',
    'maintenance_plan' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    if (!$dogs) {
        header('Location: goal_builder.php?status=need_dog');
        exit;
    }

    $values['dog_id'] = (int) ($_POST['dog_id'] ?? $defaultDogId);
    $values['goal_category'] = (string) ($_POST['goal_category'] ?? 'other');
    $values['current_problem'] = trim((string) ($_POST['current_problem'] ?? ''));
    $values['desired_behavior'] = trim((string) ($_POST['desired_behavior'] ?? ''));
    $values['context_environment'] = trim((string) ($_POST['context_environment'] ?? ''));
    $values['trigger_description'] = trim((string) ($_POST['trigger_description'] ?? ''));
    $values['handler_time_budget_minutes'] = (int) ($_POST['handler_time_budget_minutes'] ?? 3);
    $values['reinforcer_preference'] = trim((string) ($_POST['reinforcer_preference'] ?? ''));
    $values['safety_risk'] = !empty($_POST['safety_risk']) ? 1 : 0;
    $values['success_criteria'] = trim((string) ($_POST['success_criteria'] ?? ''));
    $values['maintenance_plan'] = trim((string) ($_POST['maintenance_plan'] ?? ''));

    $action = (string) ($_POST['action'] ?? 'build');
    $draft = gpGoalBuilderDraft($values);

    if ($action === 'save') {
        if (!in_array($values['dog_id'], array_map(static fn($dog) => (int) $dog['id'], $dogs), true)) {
            $values['dog_id'] = $defaultDogId;
        }

        $goalId = createTrainingGoal($pdo, $userId, [
            'dog_id' => $values['dog_id'],
            'goal_category' => $draft['goal_category'],
            'current_problem' => $values['current_problem'] ?: $draft['current_problem'],
            'desired_behavior' => $values['desired_behavior'] ?: $draft['desired_behavior'],
            'context_environment' => $values['context_environment'] ?: $draft['context_environment'],
            'trigger_description' => $values['trigger_description'] ?: $draft['trigger_description'],
            'handler_time_budget_minutes' => max(1, min(30, (int) $values['handler_time_budget_minutes'])),
            'reinforcer_preference' => $values['reinforcer_preference'] ?: $draft['reinforcer_preference'],
            'safety_risk' => $values['safety_risk'],
            'success_criteria' => $values['success_criteria'] ?: $draft['success_criteria'],
            'maintenance_plan' => $values['maintenance_plan'] ?: $draft['maintenance_plan'],
        ]);

        writeAuditLog($pdo, 'goal_builder_saved', 'training_goals', $goalId, 'Goal builder saved a generated training goal.');
        header('Location: training_goal_intake.php?msg=saved');
        exit;
    }

    $message = 'Draft generated. Review the preview, then save when ready.';
}

if ($draft === null) {
    $draft = gpGoalBuilderDraft($values);
}

$csrf = generateCsrfToken();
$options = gpGoalBuilderCategoryOptions();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Goal Builder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1120px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        select, textarea, input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { min-height: 80px; }
        button { margin-top: 16px; padding: 10px 14px; font-weight: 700; }
        .alert { padding: 10px; border-radius: 8px; background: #d1e7dd; margin-bottom: 12px; }
        .small { color: #666; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .preview { border: 1px solid #dbe2ea; border-radius: 12px; background: #fff; padding: 12px; }
        .pill { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; background: #eef2ff; color: #4338ca; padding: .32rem .7rem; font-weight: 800; }
        .muted { color: #64748b; }
        .stack { display: grid; gap: 1rem; }
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
    <p><a href="training_goal_intake.php">← Goal Intake</a></p>
    <h1>Goal Builder</h1>
    <p class="small">Turn a vague problem into a measurable training goal, then save it to the active dog.</p>

    <?php if ($message): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($status === 'saved'): ?>
        <div class="alert">Goal saved.</div>
    <?php elseif ($status === 'need_dog'): ?>
        <div class="alert">Add a dog profile before building a goal.</div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <?php if (!$dogs): ?>
                <p>No dogs found. Add a dog profile first, then come back to build a goal.</p>
            <?php else: ?>
            <form method="post" class="stack">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <div>
                    <label>Dog</label>
                    <select name="dog_id" required>
                        <?php foreach ($dogs as $dog): ?>
                            <option value="<?= (int) $dog['id'] ?>" <?= (int) $values['dog_id'] === (int) $dog['id'] ? 'selected' : '' ?>><?= h($dog['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Goal category</label>
                    <select name="goal_category">
                        <?php foreach ($options as $key => $option): ?>
                            <option value="<?= h($key) ?>" <?= $values['goal_category'] === $key ? 'selected' : '' ?>><?= h($option['icon'] . ' ' . $option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>What problem are you trying to solve?</label>
                    <textarea name="current_problem" placeholder="Example: My dog pulls hard at truck stops."><?= h($values['current_problem']) ?></textarea>
                </div>
                <div>
                    <label>What should the dog do instead?</label>
                    <textarea name="desired_behavior" placeholder="Example: Walk beside me with a loose leash and check in."><?= h($values['desired_behavior']) ?></textarea>
                </div>
                <div>
                    <label>Where does it happen?</label>
                    <textarea name="context_environment" placeholder="Truck stop, fuel island, cab, home, store, parking lot"><?= h($values['context_environment']) ?></textarea>
                </div>
                <div>
                    <label>What triggers it?</label>
                    <textarea name="trigger_description" placeholder="People, dogs, food, noise, fatigue, arriving after a long drive"><?= h($values['trigger_description']) ?></textarea>
                </div>
                <div>
                    <label>Daily training time budget in minutes</label>
                    <input type="number" name="handler_time_budget_minutes" value="<?= h((string) $values['handler_time_budget_minutes']) ?>" min="1" max="30">
                </div>
                <div>
                    <label>Best rewards</label>
                    <textarea name="reinforcer_preference" placeholder="Soft treats, kibble, sniffing, tug, praise, grass access"><?= h($values['reinforcer_preference']) ?></textarea>
                </div>
                <div>
                    <label>
                        <input type="checkbox" name="safety_risk" value="1" style="width:auto;" <?= !empty($values['safety_risk']) ? 'checked' : '' ?>>
                        This may be a safety issue
                    </label>
                </div>
                <div>
                    <label>Success criteria</label>
                    <textarea name="success_criteria" placeholder="Example: At a truck stop, dog walks 20 steps beside handler with loose leash, 2 check-ins, and no lunging."><?= h($values['success_criteria']) ?></textarea>
                </div>
                <div>
                    <label>Maintenance plan</label>
                    <textarea name="maintenance_plan" placeholder="Example: Practice 3 minutes after potty breaks, reward check-ins, repeat after travel disruptions."><?= h($values['maintenance_plan']) ?></textarea>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="action" value="build">Build Draft</button>
                    <button type="submit" name="action" value="save" class="btn btn-primary">Save Goal</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="preview">
                <div class="pill"><?= h($draft['category_icon'] . ' ' . $draft['category_label']) ?></div>
                <h2 class="h5 mt-3">Draft preview</h2>
                <p class="muted mb-3"><?= h($draft['summary']) ?></p>
                <p><strong>Current problem:</strong> <?= h($draft['current_problem']) ?></p>
                <p><strong>Desired behavior:</strong> <?= h($draft['desired_behavior']) ?></p>
                <p><strong>Context:</strong> <?= h($draft['context_environment']) ?></p>
                <p><strong>Trigger:</strong> <?= h($draft['trigger_description']) ?></p>
                <p><strong>Success criteria:</strong> <?= h($draft['success_criteria']) ?></p>
                <p><strong>Maintenance plan:</strong> <?= h($draft['maintenance_plan']) ?></p>
                <p><strong>Reward plan:</strong> <?= h($draft['reinforcer_preference']) ?></p>
                <p><strong>Time budget:</strong> <?= h((string) $draft['handler_time_budget_minutes']) ?> minutes</p>
                <p><strong>Safety risk:</strong> <?= !empty($draft['safety_risk']) ? 'Yes' : 'No' ?></p>
                <div class="small text-muted">The Save Goal button uses the drafted values, so you can adjust them before saving.</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
