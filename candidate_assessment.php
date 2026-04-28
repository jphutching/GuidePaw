<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/candidate_scoring.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!featureEnabled($pdo, 'candidate_scoring_enabled')) {
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

$dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dogId = (int)($_POST['dog_id'] ?? 0);

    $scoreKeys = candidateScoreKeys();

    $scores = [];
    foreach ($scoreKeys as $key) {
        $scores[$key] = clampCandidateScore($_POST[$key] ?? 3);
    }

    $avg = averageCandidateScore($scores);
    $safetyFlags = trim($_POST['safety_flags'] ?? '');
    $candidateRecommendation = recommendCandidateFocusLevel($avg, $safetyFlags);
    $focusLevel = $candidateRecommendation['focus_level'];
    $recommendation = $candidateRecommendation['recommendation'];

    $insert = $pdo->prepare("
        INSERT INTO dog_candidate_assessments
        (
            dog_id,
            focus_level_recommended,
            health_notes,
            confidence_score,
            startle_recovery_score,
            handler_engagement_score,
            food_motivation_score,
            toy_motivation_score,
            settle_score,
            human_neutrality_score,
            dog_neutrality_score,
            environment_score,
            handling_score,
            safety_flags,
            recommendation
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insert->execute([
        $dogId,
        $focusLevel,
        trim($_POST['health_notes'] ?? ''),
        $scores['confidence_score'],
        $scores['startle_recovery_score'],
        $scores['handler_engagement_score'],
        $scores['food_motivation_score'],
        $scores['toy_motivation_score'],
        $scores['settle_score'],
        $scores['human_neutrality_score'],
        $scores['dog_neutrality_score'],
        $scores['environment_score'],
        $scores['handling_score'],
        $safetyFlags,
        $recommendation
    ]);

    $message = 'Candidate assessment saved.';
    $result = [
        'average' => number_format($avg, 1),
        'focus_level' => $focusLevel,
        'recommendation' => $recommendation
    ];
}

$recentStmt = $pdo->prepare("
    SELECT a.*, d.name AS dog_name
    FROM dog_candidate_assessments a
    JOIN dogs d ON d.id = a.dog_id
    WHERE d.owner_user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$recentStmt->execute([$userId]);
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [
    'confidence_score' => 'Confidence',
    'startle_recovery_score' => 'Startle recovery',
    'handler_engagement_score' => 'Handler engagement',
    'food_motivation_score' => 'Food motivation',
    'toy_motivation_score' => 'Toy motivation',
    'settle_score' => 'Ability to settle',
    'human_neutrality_score' => 'Human neutrality',
    'dog_neutrality_score' => 'Dog neutrality',
    'environment_score' => 'Truck/environment confidence',
    'handling_score' => 'Handling tolerance'
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Candidate Assessment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 980px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        select, textarea, input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { min-height: 80px; }
        button { margin-top: 16px; padding: 10px 14px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .alert { padding: 10px; border-radius: 8px; background: #d1e7dd; margin-bottom: 12px; }
        .result { background: #e7f1ff; padding: 12px; border-radius: 8px; }
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


<div class="wrap">
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Candidate Assessment</h1>
    <p class="small">Score 1 = major concern, 3 = acceptable foundation, 5 = excellent.</p>

    <?php if ($message): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="result">
            <strong>Average score:</strong> <?= h($result['average']) ?><br>
            <strong>Recommended focus level:</strong> <?= h($result['focus_level']) ?><br>
            <strong>Recommendation:</strong> <?= h($result['recommendation']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if (!$dogs): ?>
            <p>No dogs found. Add a dog profile first.</p>
        <?php else: ?>
            <form method="post">
                <label>Dog</label>
                <select name="dog_id" required>
                    <?php foreach ($dogs as $dog): ?>
                        <option value="<?= h($dog['id']) ?>"><?= h($dog['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="grid">
                    <?php foreach ($labels as $key => $label): ?>
                        <label>
                            <?= h($label) ?>
                            <select name="<?= h($key) ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label>Health notes</label>
                <textarea name="health_notes" placeholder="Health, structure, fatigue, pain, medication, vet concerns"></textarea>

                <label>Safety flags</label>
                <textarea name="safety_flags" placeholder="Bite history, severe fear, shutdown, uncontrolled lunging, severe dog/human aggression"></textarea>

                <button type="submit">Save Assessment</button>
            </form>
        <?php endif; ?>
    </div>

    <h2>Recent Assessments</h2>
    <table>
        <tr>
            <th>Date</th>
            <th>Dog</th>
            <th>Focus</th>
            <th>Recommendation</th>
        </tr>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= h($row['created_at']) ?></td>
                <td><?= h($row['dog_name']) ?></td>
                <td><?= h($row['focus_level_recommended']) ?></td>
                <td><?= h($row['recommendation']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
