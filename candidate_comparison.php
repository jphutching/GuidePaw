<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/candidate_comparison.php';

checkLogin();

if (!featureEnabled($pdo, 'candidate_comparison_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
if (!$user) {
    logoutSessionState();
    header('Location: login.php?msg=session_expired');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$dogsStmt = $pdo->prepare("SELECT id, name, breed FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$activeDogId = getActiveDogId($pdo, $userId);
$requestedDogIds = $_GET['dog_ids'] ?? [];
if (!is_array($requestedDogIds)) {
    $requestedDogIds = [$requestedDogIds];
}
$selectedDogIds = array_values(array_filter(array_map('intval', $requestedDogIds), static fn ($id) => $id > 0));
$selectedDogIds = gpCandidateComparisonResolveDogIds($dogs, $activeDogId !== null ? (int) $activeDogId : null, $selectedDogIds, 4);
$rows = gpCandidateComparisonRows($pdo, $userId, $selectedDogIds);
$labels = candidateScoreLabels();
$hasComparisons = count($rows) > 1;
$averageAcrossDogs = null;
if ($rows) {
    $scores = array_values(array_filter(array_map(static fn (array $row) => $row['average_score'], $rows), static fn ($value) => $value !== null));
    if ($scores) {
        $averageAcrossDogs = round(array_sum($scores) / count($scores), 1);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Candidate Comparison</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1140px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .chip { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .7rem; border-radius: 999px; background: #eef6ff; color: #0d6efd; font-weight: 700; font-size: .85rem; }
        .dog-picker { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
        .dog-picker label { display: flex; align-items: center; gap: .5rem; padding: .7rem .8rem; border: 1px solid #dfe3e8; border-radius: 10px; background: #fff; }
        .compare-table { width: 100%; border-collapse: collapse; background: #fff; }
        .compare-table th, .compare-table td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        .compare-table th { background: #eef2f7; }
        .metric-name { font-weight: 700; background: #fafafa; width: 190px; }
        .muted { color: #666; font-size: 13px; }
        .stack { display: grid; gap: 12px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .summary-card { border: 1px solid #dfe3e8; border-radius: 10px; padding: 12px; background: #fff; }
        .summary-title { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin-bottom: .25rem; }
        .summary-value { font-size: 1.5rem; font-weight: 800; }
        .recommendation { font-size: .92rem; }
        @media (max-width: 900px) {
            .compare-table, .compare-table thead, .compare-table tbody, .compare-table tr, .compare-table th, .compare-table td { display: block; width: 100%; }
            .compare-table thead { display: none; }
            .compare-table tr { margin-bottom: 12px; border: 1px solid #dfe3e8; border-radius: 10px; overflow: hidden; }
            .compare-table td { border: 0; border-bottom: 1px solid #eef2f7; }
            .compare-table td.metric-name { width: 100%; background: #eef2f7; }
        }
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
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Candidate Comparison</h1>
    <p class="muted">Compare up to four of your dogs using their latest active candidate assessments.</p>

    <div class="card">
        <form method="get" class="stack">
            <div>
                <div class="chip">Select dogs to compare</div>
                <div class="muted" style="margin-top:.5rem;">The active dog is preselected when possible.</div>
            </div>
            <div class="dog-picker">
                <?php foreach ($dogs as $dog): ?>
                    <label>
                        <input type="checkbox" name="dog_ids[]" value="<?= (int) $dog['id'] ?>" <?= in_array((int) $dog['id'], $selectedDogIds, true) ? 'checked' : '' ?>>
                        <span>
                            <strong><?= h($dog['name']) ?></strong><br>
                            <span class="muted"><?= h($dog['breed'] ?: 'Breed not set') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div>
                <button type="submit">Compare Selected Dogs</button>
            </div>
        </form>
    </div>

    <?php if (!$dogs): ?>
        <div class="card">
            <p class="mb-0">No owned dogs found. Add a dog profile first.</p>
        </div>
    <?php elseif (!gpCandidateComparisonTableReady($pdo)): ?>
        <div class="card">
            <p class="mb-0">Candidate comparison storage is not available yet.</p>
        </div>
    <?php elseif (!$rows): ?>
        <div class="card">
            <p class="mb-0">Select at least one accessible dog to compare.</p>
        </div>
    <?php else: ?>
        <div class="summary-grid">
            <div class="summary-card"><div class="summary-title">Dogs selected</div><div class="summary-value"><?= count($rows) ?></div></div>
            <div class="summary-card"><div class="summary-title">Dogs with assessments</div><div class="summary-value"><?= count(array_filter($rows, static fn (array $row) => !empty($row['has_assessment']))) ?></div></div>
            <div class="summary-card"><div class="summary-title">Average score</div><div class="summary-value"><?= $averageAcrossDogs === null ? '—' : h(number_format($averageAcrossDogs, 1)) ?></div></div>
            <div class="summary-card"><div class="summary-title">Comparison status</div><div class="summary-value"><?= $hasComparisons ? 'Side by side' : 'Single dog' ?></div></div>
        </div>

        <div class="card" style="overflow-x:auto;">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <?php foreach ($rows as $row): ?>
                            <th><?= h($row['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="metric-name">Latest assessment</td>
                        <?php foreach ($rows as $row): ?>
                            <td>
                                <strong><?= h($row['focus_label']) ?></strong><br>
                                <span class="muted"><?= $row['assessed_at'] ? h(date('M j, Y', strtotime((string) $row['assessed_at']))) : 'No assessment yet' ?></span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="metric-name">Average score</td>
                        <?php foreach ($rows as $row): ?>
                            <td><?= $row['average_score'] === null ? '—' : h(number_format((float) $row['average_score'], 1)) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="metric-name">Recommendation</td>
                        <?php foreach ($rows as $row): ?>
                            <td class="recommendation"><?= h((string) ($row['recommended_path'] ?? 'No recommendation yet')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ($labels as $key => $label): ?>
                        <tr>
                            <td class="metric-name"><?= h($label) ?></td>
                            <?php foreach ($rows as $row): ?>
                                <td>
                                    <?php $assessment = $row['assessment'] ?? null; ?>
                                    <?= $assessment && isset($assessment[$key]) ? h((string) $assessment[$key]) : '—' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td class="metric-name">Safety flags</td>
                        <?php foreach ($rows as $row): ?>
                            <td><?= !empty($row['assessment']['safety_flags']) ? h((string) $row['assessment']['safety_flags']) : 'None recorded' ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
