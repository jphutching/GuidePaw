<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/behavior_incidents.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!featureEnabled($pdo, 'habit_repair_enabled')) {
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

$protocols = [
    'potty_accidents' => [
        'title' => 'Potty Accident Reset',
        'time' => 'Start in under 3 minutes',
        'steps' => [
            'Rule out a medical issue if accidents are sudden, frequent, painful, or unusual.',
            'Restart potty breaks after waking, eating, drinking, play, arrival, and before driving.',
            'Reward heavily outside immediately after potty.',
            'Restrict freedom after a failed potty attempt.',
            'Clean accidents with enzyme cleaner.',
            'Track time, location, and trigger.',
            'After 72 clean hours, slowly expand freedom.'
        ]
    ],
    'pulling' => [
        'title' => 'Pulling Reset',
        'time' => '2 to 5 minutes',
        'steps' => [
            'Use a shorter route with fewer distractions.',
            'Stop rehearsing pulling.',
            'Reward check-ins before the leash gets tight.',
            'Turn or pause before the dog reaches the end of the leash.',
            'Use sniffing as a reward when the leash is loose.',
            'Progress when the leash is loose for about 80 percent of the route.'
        ]
    ],
    'jumping' => [
        'title' => 'Jumping Reset',
        'time' => '2 minutes',
        'steps' => [
            'Prevent rehearsals with leash, gate, crate, or distance.',
            'Do not give attention while paws are up.',
            'Reward four paws on the floor.',
            'Practice calm greetings with low excitement.',
            'Use sit or settle as the replacement behavior.'
        ]
    ],
    'barking' => [
        'title' => 'Barking Reset',
        'time' => '2 to 5 minutes',
        'steps' => [
            'Identify the trigger.',
            'Increase distance from the trigger.',
            'Reward quiet check-ins.',
            'Redirect before barking escalates.',
            'Use cab window or visual management when needed.',
            'Avoid yelling because it can increase arousal.'
        ]
    ],
    'cab_settling' => [
        'title' => 'Cab Settling Reset',
        'time' => '3 minutes',
        'steps' => [
            'Start after potty and light movement.',
            'Send dog to mat, bed, crate, or safe cab rest spot.',
            'Reward calm breathing and lying down.',
            'Add safe chew or enrichment.',
            'Keep the first session short.',
            'Gradually increase duration after easy wins.'
        ]
    ]
];

$selected = $_GET['protocol'] ?? 'potty_accidents';
if (!isset($protocols[$selected])) {
    $selected = 'potty_accidents';
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'archive') {
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE behavior_incidents SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->execute([$incidentId, $userId]);
        writeAuditLog($pdo, 'behavior_incident_archived', 'behavior_incidents', $incidentId, 'Behavior incident archived.');

        header('Location: habit_repair.php?msg=updated');
        exit;
    }

    createBehaviorIncident($pdo, $userId, $_POST);

    header('Location: habit_repair.php?msg=saved');
    exit;
}

$dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC);

$recent = getRecentBehaviorIncidents($pdo, $userId, 8);

$current = $protocols[$selected];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Habit Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 980px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .nav a { display: inline-block; margin: 0 8px 8px 0; padding: 8px 10px; background: #fff; border: 1px solid #ddd; border-radius: 999px; }
        .nav a.active { background: #0d6efd; color: #fff; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        select, textarea, input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { min-height: 70px; }
        button { margin-top: 16px; padding: 10px 14px; font-weight: 700; }
        .alert { padding: 10px; border-radius: 8px; background: #d1e7dd; margin-bottom: 12px; }
        .small { color: #666; font-size: 13px; }
        li { margin-bottom: 8px; }
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
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Habit Repair</h1>
    <p class="small">Regression is not failure. Make it easier, get one clean win, then rebuild.</p>

    <?php if ($message): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <div class="nav">
        <?php foreach ($protocols as $key => $protocol): ?>
            <a class="<?= $key === $selected ? 'active' : '' ?>" href="?protocol=<?= h($key) ?>"><?= h($protocol['title']) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2><?= h($current['title']) ?></h2>
        <p class="small"><?= h($current['time']) ?></p>
        <ol>
            <?php foreach ($current['steps'] as $step): ?>
                <li><?= h($step) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>

    <div class="card">
        <h2>Log this issue</h2>
        <?php if (!$dogs): ?>
            <p>No dogs found. Add a dog profile first.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="incident_type" value="<?= h($selected) ?>">

                <label>Dog</label>
                <select name="dog_id" required>
                    <?php foreach ($dogs as $dog): ?>
                        <option value="<?= h($dog['id']) ?>"><?= h($dog['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Where did it happen?</label>
                <textarea name="context_environment" placeholder="Truck cab, fuel island, truck stop grass, home, parking lot"></textarea>

                <label>What triggered it?</label>
                <textarea name="trigger_description" placeholder="Dog, person, noise, delay, fatigue, arrival, excitement"></textarea>

                <label>Severity</label>
                <select name="severity">
                    <option value="1">1 - minor</option>
                    <option value="2" selected>2 - mild</option>
                    <option value="3">3 - moderate</option>
                    <option value="4">4 - serious</option>
                    <option value="5">5 - safety concern</option>
                </select>

                <label>Notes</label>
                <textarea name="notes" placeholder="What happened? What helped? What should we try next time?"></textarea>

                <button type="submit">Save Incident</button>
            </form>
        <?php endif; ?>
    </div>

    <h2>Recent Behavior Incidents</h2>
    <table>
        <tr>
            <th>Date</th>
            <th>Dog</th>
            <th>Type</th>
            <th>Severity</th>
            <th>Context</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= h($row['created_at']) ?></td>
                <td><?= h($row['dog_name']) ?></td>
                <td><?= h($row['incident_type']) ?></td>
                <td><?= h($row['severity']) ?></td>
                <td><?= h($row['context_environment']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Archive this behavior incident?');">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="incident_id" value="<?= h($row['id']) ?>">
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
