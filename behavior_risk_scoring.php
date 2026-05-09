<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/candidate_scoring.php';
require_once __DIR__ . '/includes/behavior_risk_scoring.php';

checkLogin();

if (!featureEnabled($pdo, 'behavior_risk_scoring_enabled')) {
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
$selectedDogId = (int) ($_GET['dog_id'] ?? ($dogs[0]['id'] ?? 0));
if ($selectedDogId > 0) {
    $allowedDogIds = array_map(static fn($dog) => (int) $dog['id'], $dogs);
    if (!in_array($selectedDogId, $allowedDogIds, true)) {
        $selectedDogId = (int) ($dogs[0]['id'] ?? 0);
    }
}

$assessment = gpBehaviorRiskAssessment($pdo, $userId, $selectedDogId > 0 ? $selectedDogId : null);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Behavior Risk Scoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1120px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .score { font-size: 3rem; font-weight: 900; line-height: 1; }
        .band-low { color:#0f766e; }
        .band-moderate { color:#b45309; }
        .band-high { color:#b91c1c; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .small { color: #666; font-size: 13px; }
        .pill { display:inline-flex; align-items:center; padding:.3rem .6rem; border-radius:999px; font-weight:800; background:#eef2ff; color:#3730a3; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wrap">
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Behavior Risk Scoring</h1>
    <p class="small">A higher score means the current behavior picture is more fragile and should stay easier for now.</p>

    <div class="card">
        <form method="get">
            <label for="dog_id">Dog</label>
            <select name="dog_id" id="dog_id">
                <option value="0">All dogs</option>
                <?php foreach ($dogs as $dog): ?>
                    <option value="<?= (int) $dog['id'] ?>" <?= $selectedDogId === (int) $dog['id'] ? 'selected' : '' ?>><?= h($dog['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Score</button>
        </form>
    </div>

    <div class="grid">
        <div class="card">
            <div class="small">Current risk</div>
            <div class="score band-<?= h($assessment['band']) ?>"><?= (int) $assessment['score'] ?></div>
            <div class="pill"><?= h(ucfirst($assessment['band'])) ?></div>
            <p class="small">Open regressions: <?= (int) $assessment['open_regressions'] ?></p>
        </div>
        <div class="card">
            <h2 class="h5">What drove the score</h2>
            <?php if (!$assessment['reasons']): ?>
                <p class="small">No active incidents, regressions, or safety flags were found.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($assessment['reasons'] as $reason): ?>
                        <li><?= h($reason) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2 class="h5">Recommended next steps</h2>
            <ul>
                <?php foreach ($assessment['recommendations'] as $recommendation): ?>
                    <li><?= h($recommendation) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="card">
        <h2 class="h5">Recent behavior incidents</h2>
        <?php if (!$assessment['incidents']): ?>
            <p class="small">No active incidents found.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Dog</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Trigger</th>
                    <th>Context</th>
                </tr>
                <?php foreach ($assessment['incidents'] as $incident): ?>
                    <tr>
                        <td><?= h($incident['created_at']) ?></td>
                        <td><?= h($incident['dog_name']) ?></td>
                        <td><?= h($incident['incident_type']) ?></td>
                        <td><?= h((string) ($incident['severity'] ?? '')) ?></td>
                        <td><?= h($incident['trigger_description'] ?? '') ?></td>
                        <td><?= h($incident['context_environment'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="h5">Latest candidate assessment</h2>
        <?php if (!$assessment['candidate']): ?>
            <p class="small">No active candidate assessment is available.</p>
        <?php else: ?>
            <p><strong>Dog:</strong> <?= h($assessment['candidate']['dog_name']) ?></p>
            <p><strong>Focus level:</strong> <?= h((string) ($assessment['candidate']['focus_level_recommended'] ?? '—')) ?></p>
            <p><strong>Recommendation:</strong> <?= h((string) ($assessment['candidate']['recommendation'] ?? '')) ?></p>
            <?php if (!empty($assessment['candidate']['safety_flags'])): ?>
                <p><strong>Safety flags:</strong> <?= h((string) $assessment['candidate']['safety_flags']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
