<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/training_stats.php';
require_once __DIR__ . '/includes/candidate_scoring.php';
require_once __DIR__ . '/includes/community_challenges.php';

checkLogin();

if (!featureEnabled($pdo, 'community_challenges_enabled')) {
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

$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$trainingStats = getTrainingCoreStats($pdo, $userId);
$latestAssessment = gpLatestCandidateAssessment($pdo, $userId, $dogId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? 'save';
    $challenge = (string) ($_POST['challenge'] ?? gpCommunityChallengeDefault($trainingStats, $latestAssessment));
    $notes = cleanTextarea($_POST['notes'] ?? '', 1200);
    $markCheckIn = $action === 'check_in';
    gpCommunityChallengeSaveState($userId, $dogId, $challenge, $notes, $markCheckIn);
    header('Location: community_challenges.php?status=updated');
    exit;
}

$state = gpCommunityChallengeState($userId, $dogId);
$currentChallenge = gpCommunityChallengePlan((string) $state['challenge']);
$recommendedKey = gpCommunityChallengeDefault($trainingStats, $latestAssessment);
$recommended = gpCommunityChallengePlan($recommendedKey);
$challengeOptions = gpCommunityChallengeOptions();
$csrf = generateCsrfToken();
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.challenge-shell{max-width:1080px;margin:0 auto;padding:1rem 1rem 4rem}
.hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}
.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}
.card-soft{border:1px solid rgba(15,23,42,.08);border-radius:18px;box-shadow:0 8px 18px rgba(15,23,42,.08)}
.challenge-grid{display:grid;gap:1rem}
@media(min-width:960px){.challenge-grid{grid-template-columns:1.05fr .95fr}}
.challenge-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.75rem}
.challenge-option{border:1px solid rgba(15,23,42,.08);border-radius:16px;padding:.9rem;background:#fff;cursor:pointer}
.challenge-option input{margin-right:.45rem}
.challenge-meta{font-size:.84rem;color:#64748b}
.challenge-box{border:1px solid rgba(15,23,42,.08);border-radius:16px;padding:1rem;background:#fff}
.challenge-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;background:#eef2ff;color:#4338ca;padding:.35rem .7rem;font-weight:800;font-size:.82rem}
.challenge-list{margin-bottom:0;padding-left:1.15rem}
.muted{color:#64748b}
textarea{min-height:92px}
button[type=submit]{font-weight:800}
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
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="challenge-shell">
    <div class="hero mb-3">
        <div class="container px-0" style="max-width: 960px;">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="small opacity-75">Active dog: <?= e($dog['name']) ?></div>
                    <h1 class="mb-1">Community Challenges</h1>
                    <div class="small opacity-75">Pick a small challenge, keep it short, and log one clean win.</div>
                </div>
                <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            </div>
        </div>
    </div>

    <?php if ($status === 'updated'): ?>
        <div class="alert alert-success">Challenge saved.</div>
    <?php endif; ?>

    <div class="challenge-grid">
        <section class="card card-soft">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                    <div>
                        <h2 class="h5 mb-1">Recommended for today</h2>
                        <div class="challenge-meta">Based on the active dog's recent training activity.</div>
                    </div>
                    <span class="challenge-pill"><?= e($recommended['icon'] . ' ' . $recommended['label']) ?></span>
                </div>
                <p class="mb-3"><?= e($recommended['summary']) ?></p>
                <ul class="challenge-list">
                    <li><strong>Daily target:</strong> <?= e($recommended['daily_target']) ?></li>
                    <li><strong>Best for:</strong> <?= e($recommended['best_for']) ?></li>
                    <li><strong>Avoid:</strong> <?= e($recommended['avoid']) ?></li>
                    <li><strong>Finish line:</strong> <?= e($recommended['finish_line']) ?></li>
                </ul>
            </div>
        </section>

        <section class="card card-soft">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                    <div>
                        <h2 class="h5 mb-1">Current challenge</h2>
                        <div class="challenge-meta"><?= e((string) ($currentChallenge['daily_target'] ?? '')) ?></div>
                    </div>
                    <span class="badge text-bg-success"><?= (int) ($state['check_ins'] ?? 0) ?> check-ins</span>
                </div>
                <div class="challenge-box mb-3">
                    <div class="fw-bold"><?= e($currentChallenge['icon'] . ' ' . $currentChallenge['label']) ?></div>
                    <div class="muted mt-1"><?= e($currentChallenge['summary']) ?></div>
                    <div class="mt-3"><strong>Best for:</strong> <?= e($currentChallenge['best_for']) ?></div>
                    <div class="mt-2"><strong>Avoid:</strong> <?= e($currentChallenge['avoid']) ?></div>
                    <div class="mt-2"><strong>Finish line:</strong> <?= e($currentChallenge['finish_line']) ?></div>
                </div>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save">
                    <label class="form-label">Challenge</label>
                    <div class="challenge-options">
                        <?php foreach ($challengeOptions as $key => $option): ?>
                            <label class="challenge-option">
                                <input type="radio" name="challenge" value="<?= e($key) ?>" <?= $state['challenge'] === $key ? 'checked' : '' ?>>
                                <strong><?= e($option['icon'] . ' ' . $option['label']) ?></strong>
                                <div class="challenge-meta mt-1"><?= e($option['summary']) ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" placeholder="What went well, what got in the way, and what to try tomorrow."><?= e($state['notes']) ?></textarea>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Save challenge</button>
                        <button type="submit" name="action" value="check_in" class="btn btn-outline-success">Mark check-in</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <section class="card card-soft mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                <div>
                    <h2 class="h5 mb-1">Challenge options</h2>
                    <div class="challenge-meta">The options stay small so handlers can finish one without overcomplicating the day.</div>
                </div>
                <a href="training_program.php" class="btn btn-outline-secondary btn-sm">Training Program</a>
            </div>
            <div class="challenge-options">
                <?php foreach ($challengeOptions as $key => $option): ?>
                    <div class="challenge-box">
                        <div class="fw-bold"><?= e($option['icon'] . ' ' . $option['label']) ?></div>
                        <div class="muted mt-1"><?= e($option['summary']) ?></div>
                        <div class="challenge-meta mt-2"><strong>Daily target:</strong> <?= e($option['daily_target']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="card card-soft mt-3">
        <div class="card-body">
            <h2 class="h5 mb-2">Current training snapshot</h2>
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="challenge-box text-center"><div class="challenge-meta">Active goals</div><div class="display-6 fw-bold"><?= e($trainingStats['active_goals']) ?></div></div></div>
                <div class="col-6 col-md-3"><div class="challenge-box text-center"><div class="challenge-meta">Sessions this week</div><div class="display-6 fw-bold"><?= e($trainingStats['sessions_7d']) ?></div></div></div>
                <div class="col-6 col-md-3"><div class="challenge-box text-center"><div class="challenge-meta">7-day success</div><div class="display-6 fw-bold"><?= $trainingStats['avg_success_rate_7d'] === null ? '—' : e($trainingStats['avg_success_rate_7d']) . '%' ?></div></div></div>
                <div class="col-6 col-md-3"><div class="challenge-box text-center"><div class="challenge-meta">Open regressions</div><div class="display-6 fw-bold"><?= e($trainingStats['open_regressions']) ?></div></div></div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
