<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/dog_access_dashboard.php';
require_once __DIR__ . '/includes/candidate_scoring.php';
require_once __DIR__ . '/includes/behavior_risk_scoring.php';
require_once __DIR__ . '/includes/candidate_comparison.php';
require_once __DIR__ . '/includes/community_challenges.php';
require_once __DIR__ . '/includes/coach_reviews.php';
require_once __DIR__ . '/includes/regression_engine.php';
require_once __DIR__ . '/includes/video_reviews.php';
require_once __DIR__ . '/includes/trucking_mode.php';
require_once __DIR__ . '/includes/wearable_integrations.php';
require_once __DIR__ . '/includes/trainer_marketplace.php';
require_once __DIR__ . '/includes/dog_access_expiry.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/training_suggestion_links.php';
require_once __DIR__ . '/includes/daily_wins.php';
require_once 'includes/app_config.php';

if (empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/seo.php';
    $landingTitle = 'GuidePaw | Dog Training Logs, Service Dog Profiles, Breed Research, and Guide Paw Tools';
    $landingDescription = 'GuidePaw helps handlers research breeds, track training, manage dog profiles, share public service dog details, and keep support tools organized. Guide Paw searches should land here too.';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $landingSchema = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => appShortName(),
            'alternateName' => 'Guide Paw',
            'url' => guidepawSeoAbsoluteUrl('/'),
            'description' => $landingDescription,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => guidepawSeoAbsoluteUrl('/breed_questionnaire.php') . '?breed_query={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => appShortName(),
            'alternateName' => 'Guide Paw',
            'url' => guidepawSeoAbsoluteUrl('/'),
            'logo' => guidepawSeoAbsoluteUrl('/assets/brand/guidepaw-logo.png'),
            'sameAs' => array_values(array_filter([
                gpEnv('GUIDEPAW_DISCORD_INVITE_URL', ''),
                gpEnv('GUIDEPAW_MERCH_STORE_URL', ''),
            ])),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'Is the breed questionnaire public?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Yes. It is meant for people researching a dog before they create an account.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Do I need an account to browse the examples?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'No. The public landing, breed tool, and support page are all readable without signing in.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'What is the dashboard for?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'The dashboard is for logged-in handlers to manage dogs, training, and support tools.',
                    ],
                ],
            ],
        ],
    ];
    ?>
    <?php guidepawSeoHead([
        'title' => $landingTitle,
        'description' => $landingDescription,
        'robots' => 'index,follow',
        'canonical' => guidepawSeoAbsoluteUrl('/'),
        'image' => '/assets/brand/guidepaw-logo.png',
        'json_ld' => $landingSchema,
    ]); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; color: #0f172a; }
        .landing-shell { max-width: 760px; margin: 0 auto; padding: 1rem 1rem 3rem; }
        .landing-hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color: #fff; border-radius: 20px; padding: 1.5rem; box-shadow: 0 8px 20px rgba(15,23,42,.18); }
        .landing-card { border: 1px solid rgba(15,23,42,.08); border-radius: 16px; box-shadow: 0 4px 12px rgba(15,23,42,.06); background: #fff; }
        .landing-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .landing-example { border: 1px solid rgba(15,23,42,.08); border-radius: 12px; padding: .85rem 1rem; background: #fff; }
        .landing-faq summary { cursor: pointer; font-weight: 700; }
        .landing-collapse { border: 1px solid rgba(15,23,42,.08); border-radius: 16px; background: #fff; box-shadow: 0 4px 12px rgba(15,23,42,.06); margin-bottom: .75rem; overflow: hidden; }
        .landing-collapse > summary { list-style: none; cursor: pointer; padding: 1rem 1.1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; font-weight: 700; font-size: 1rem; color: #0f172a; }
        .landing-collapse > summary::-webkit-details-marker { display: none; }
        .landing-collapse > summary::after { content: '⌄'; color: #94a3b8; font-size: 1.1rem; transition: transform .15s; flex-shrink: 0; }
        .landing-collapse[open] > summary::after { transform: rotate(180deg); }
        .landing-collapse > summary .lc-meta { font-size: .8rem; color: #64748b; font-weight: 500; }
        .landing-collapse-body { padding: 0 1rem 1rem; }
    </style>
    </head>
    <body>
    <?php guidepawBrandHeader(); ?>
    <main class="landing-shell">
        <section class="landing-hero mb-4">
            <img src="assets/brand/guidepaw-logo.png" alt="GuidePaw" style="height:52px;width:auto;margin-bottom:.75rem;display:block;">
            <h1 class="display-6 fw-bold mb-2">GuidePaw keeps handler work organized.</h1>
            <p class="mb-4" style="color:rgba(255,255,255,.82);font-size:1rem;">Research dog breeds, track training, manage profiles, and keep service dog paperwork in one place.</p>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <a class="btn btn-light btn-lg fw-bold" href="login.php">Sign In</a>
                <a class="btn btn-outline-light btn-lg fw-bold" href="register.php">Create an account</a>
                <a class="btn btn-outline-light" href="breed_questionnaire.php">Research a breed</a>
                <a class="btn btn-outline-light" href="app.php">Companion App</a>
            </div>
            <div style="background:rgba(255,255,255,.12);border-radius:12px;padding:.75rem 1rem;font-size:.85rem;color:rgba(255,255,255,.9);">
                <strong style="color:#fff;">Try a demo account</strong> — tap to sign in instantly.<br>
                <a href="login.php?demo=sarah" style="color:#fff;font-weight:700;">demo.sarah</a> (free) &nbsp;·&nbsp;
                <a href="login.php?demo=marcus" style="color:#fff;font-weight:700;">demo.marcus</a> (plus) &nbsp;·&nbsp;
                <a href="login.php?demo=jennifer" style="color:#fff;font-weight:700;">demo.jennifer</a> (pro)
                <br><span style="color:rgba(255,255,255,.6);font-size:.8rem;">Password pre-filled. Sandbox resets every 30 minutes.</span>
            </div>
        </section>

        <details class="landing-collapse">
            <summary>⚖️ Service Dog &amp; Handler Rights <span class="lc-meta">ADA, air travel, housing, state laws — no account needed</span></summary>
            <div class="landing-collapse-body">
                <div class="landing-grid">
                    <a class="landing-example text-decoration-none text-dark" href="state_access_laws.php"><div class="fw-bold mb-1">🗺️ State Access Laws</div><div class="text-muted small">Public access rights and key statutes for all 50 states.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="service_dog_rights.php"><div class="fw-bold mb-1">🪪 ADA Quick Ref</div><div class="text-muted small">What staff may ask, what they may not, and a calm response script.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="service_dog_esa_legal_info.php"><div class="fw-bold mb-1">📋 Service Dog &amp; ESA Legal Info</div><div class="text-muted small">Plain-language guide covering service dogs, ESAs, and housing.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="air_travel_rights.php"><div class="fw-bold mb-1">✈️ Air Travel Rights</div><div class="text-muted small">DOT forms, what airlines can require, and what to do at the gate.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="housing_access_faq.php"><div class="fw-bold mb-1">🏠 Housing &amp; Access FAQ</div><div class="text-muted small">When a landlord or HOA asks for paperwork or certification.</div></a>
                </div>
            </div>
        </details>

        <details class="landing-collapse">
            <summary>🔍 Browse Without an Account <span class="lc-meta">Breed tools, public profiles, companion app</span></summary>
            <div class="landing-collapse-body">
                <div class="landing-grid">
                    <a class="landing-example text-decoration-none text-dark" href="breed_questionnaire.php"><div class="fw-bold mb-1">Breed Questionnaire</div><div class="text-muted small">Size, focus, and family matching to narrow breeds worth researching.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="breed_comparison_hub.php"><div class="fw-bold mb-1">Breed Comparison Hub</div><div class="text-muted small">Cavalier, retriever, poodle, corgi, and compact companion comparisons.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="breed_family_guide.php"><div class="fw-bold mb-1">Breed Family Guide</div><div class="text-muted small">Family-level overview before comparing exact breeds.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="app.php"><div class="fw-bold mb-1">Companion App</div><div class="text-muted small">Native Android app — training logs, goal intake, and wearable data.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="dogs.php"><div class="fw-bold mb-1">Public Dog Profile</div><div class="text-muted small">Shareable profile and found-dog contact details via QR or direct link.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="faq.php"><div class="fw-bold mb-1">FAQ</div><div class="text-muted small">Common questions about breed research, support, and the app.</div></a>
                    <a class="landing-example text-decoration-none text-dark" href="support_funding.php"><div class="fw-bold mb-1">Support GuidePaw</div><div class="text-muted small">One-time, monthly, or à la carte services like QR tracking.</div></a>
                </div>
            </div>
        </details>

        <details class="landing-collapse">
            <summary>❓ How It Works &amp; Common Questions <span class="lc-meta">4 steps, 3 FAQs</span></summary>
            <div class="landing-collapse-body">
                <div class="text-uppercase text-muted fw-semibold small mb-2">How it works</div>
                <ol class="mb-4">
                    <li class="mb-1">Research a breed before you commit.</li>
                    <li class="mb-1">Create a handler account and add your dogs.</li>
                    <li class="mb-1">Track training in the app or on the web, and share a public dog profile.</li>
                    <li>Use built-in tools — goal intake, habit repair, and behavior tracking — as the dog's work develops.</li>
                </ol>
                <div class="text-uppercase text-muted fw-semibold small mb-2">Common questions</div>
                <details class="mb-2"><summary class="landing-faq">Is the breed questionnaire public?</summary><p class="text-muted small mt-2 mb-0">Yes. It is meant for people researching a dog before they create an account.</p></details>
                <details class="mb-2"><summary class="landing-faq">Do I need an account to browse?</summary><p class="text-muted small mt-2 mb-0">No. The public landing, breed tool, and support page are all readable without signing in.</p></details>
                <details><summary class="landing-faq">What is the dashboard for?</summary><p class="text-muted small mt-2 mb-0">The dashboard is for logged-in handlers to manage dogs, training, and support tools.</p></details>
            </div>
        </details>

        <section class="landing-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h2 class="h5 mb-0">New to GuidePaw?</h2>
                <a class="btn btn-primary fw-bold" href="register.php">Create a free account</a>
            </div>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

checkLogin();

gpExpireDogHandlerAccess($pdo);

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
if (isset($_GET['set_dog'])) {
    setActiveDogId($pdo, $userId, (int) $_GET['set_dog']);
    header('Location: index.php');
    exit;
}
$dogs = getAccessibleDogs($pdo, $userId);
$activeDog = getActiveDog($pdo, $userId);
$upcomingReminders = getUpcomingVetReminders($pdo, $userId, 4);
$upcomingRefills   = getUpcomingMedRefills($pdo, $userId, 14, 4);
$activeAlerts = $activeDog ? getDogAlertItems($pdo, $userId, (int) $activeDog['id']) : [];
$incomingDogTransfers = gpDashboardIncomingDogTransfers($pdo, $userId);
$latestCandidateAssessment = gpLatestCandidateAssessment($pdo, $userId, $activeDog ? (int) $activeDog['id'] : null);
$behaviorRiskState = gpBehaviorRiskAssessment($pdo, $userId, $activeDog ? (int) $activeDog['id'] : null);
$communityChallengeState = null;
$openRegressionEvents = $activeDog ? gpDashboardOpenRegressionEvents($pdo, $userId) : [];
$openRegressionCount = $activeDog ? gpRegressionEngineOpenCount($pdo, $userId, (int) $activeDog['id']) : 0;
$openCoachReviews = gpDashboardOpenCoachReviews($pdo, $userId);
$openVideoReviews = gpDashboardOpenVideoReviews($pdo, $userId);
$unreadNotifications = gpUnreadNotificationCount($pdo, $userId);
$dailyWinPrompt = $activeDog ? gpDailyWinPromptForDate() : null;
$dailyWinLogName = $dailyWinPrompt ? gpDailyWinLogName($dailyWinPrompt) : '';
$dailyWinExisting = ($activeDog && $dailyWinPrompt) ? gpDailyWinExistingLog($pdo, $userId, (int) $activeDog['id'], $dailyWinLogName) : null;
$candidateAttention = (!$latestCandidateAssessment || (int) ($latestCandidateAssessment['focus_level_recommended'] ?? 0) < 3) ? 1 : 0;
$attentionCount = count($activeAlerts) + count($upcomingReminders) + count($upcomingRefills) + count($incomingDogTransfers) + $openRegressionCount + count($openCoachReviews) + count($openVideoReviews) + $candidateAttention + $unreadNotifications;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_daily_win') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $prompt = $activeDog ? gpDailyWinPromptForDate() : null;
    if (!$activeDog || !$prompt) {
        header('Location: index.php?msg=daily_win_missing');
        exit;
    }
    if (!userCanEditDog($pdo, $userId, (int) $activeDog['id'])) {
        header('Location: index.php?msg=daily_win_forbidden');
        exit;
    }
    if (empty($_POST['daily_win_complete'])) {
        header('Location: index.php?msg=daily_win_checkbox');
        exit;
    }
    if (!$dailyWinExisting) {
        gpSaveDailyWin($pdo, $userId, (int) $activeDog['id'], $prompt, trim((string) ($_POST['daily_win_note'] ?? '')));
    }
    header('Location: index.php?msg=daily_win_saved');
    exit;
}
$dailyWinSavedToday = (bool) $dailyWinExisting;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .dashboard-dog-strip { padding: .5rem 0 .75rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
    .dashboard-dog-strip h1 { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; }
    .dashboard-dog-strip .dog-meta { font-size: .8rem; color: #64748b; }
    .command-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .command-title { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.75rem; flex-wrap:wrap; }
    .today-grid { display: flex; flex-wrap: wrap; gap: .6rem; }
    .today-action { display: inline-flex; align-items: center; gap: .5rem; min-height: 0; flex: 1 1 190px; padding: .85rem 1rem; border-radius: 14px; background: #fff; border: 1px solid rgba(15,23,42,.08); color: #1f2937; text-decoration: none; font-weight: 850; box-shadow: 0 4px 12px rgba(15,23,42,.06); }
    .today-action span { font-size: 1.15rem; line-height: 1; }
    .home-utility { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
    .home-utility .home-utility-text { color:#475569; font-size:.92rem; }
    .attention-empty { border: 1px dashed rgba(22,163,74,.36); background: #f0fdf4; border-radius: 16px; padding: 1rem; color: #166534; }
    .menu-hint { border: 1px dashed rgba(13,110,253,.38); background: #f8fbff; border-radius: 18px; padding: 1rem; }
    .notification-summary{border:1px solid #bfdbfe;background:#eff6ff;border-radius:18px;padding:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;}
    .fold-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; background: #fff; }
    .fold-card > summary { list-style: none; cursor: pointer; padding: 1rem 1rem .85rem; display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
    .fold-card > summary::-webkit-details-marker { display: none; }
    .fold-card > summary::after { content: '⌄'; color: #6b7280; font-size: 1.2rem; line-height: 1; transition: transform .15s ease; flex: 0 0 auto; margin-top: .1rem; }
    .fold-card[open] > summary::after { transform: rotate(180deg); }
    .fold-card .card-body { padding-top: 0; }
</style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell">
    <div class="dashboard-dog-strip">
        <h1>🐾 <?= e($activeDog['name'] ?? 'No active dog selected') ?></h1>
        <?php if ($activeDog): ?>
            <span class="dog-meta"><?= e($activeDog['breed'] ?: 'Breed Not Set') ?><?= !empty($activeDog['weight_lbs']) ? ' · ' . e((string) $activeDog['weight_lbs']) . ' lbs' : '' ?></span>
        <?php endif; ?>
    </div>
    <?php if (($_GET['msg'] ?? '') === 'setup_complete'): ?>
        <div class="alert alert-success mb-3">Setup complete. You can use the dashboard now.</div>
    <?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'daily_win_saved'): ?>
        <div class="alert alert-success mb-3">Daily win saved as a training log.</div>
    <?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'daily_win_checkbox'): ?>
        <div class="alert alert-warning mb-3">Check the box before saving today's quick win.</div>
    <?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'daily_win_forbidden'): ?>
        <div class="alert alert-warning mb-3">You only have view access for this dog, so the quick win cannot be saved.</div>
    <?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'daily_win_missing'): ?>
        <div class="alert alert-warning mb-3">Pick an active dog before saving today's quick win.</div>
    <?php endif; ?>

    <?php if ($unreadNotifications > 0): ?>
        <section class="notification-summary mb-3">
            <div><div class="fw-bold">🔔 <?= (int) $unreadNotifications ?> unread GuidePaw notification<?= $unreadNotifications === 1 ? '' : 's' ?></div><div class="small text-muted">Transfers, access changes, found-dog reports, and important account notices will appear here.</div></div>
            <a href="notifications.php" class="btn btn-primary btn-sm">Open Notifications</a>
        </section>
    <?php endif; ?>

    <?php gpDashboardRenderDogTransferAlerts($incomingDogTransfers); ?>

    <?php if ($dogs): ?>
        <details class="fold-card mb-3" open>
            <summary>
                <div>
                    <div class="small text-uppercase text-muted fw-semibold">Current dog</div>
                    <h2 class="h5 mb-1">Active Dog</h2>
                </div>
            </summary>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($dogs as $dog): ?>
                        <a href="index.php?set_dog=<?= (int) $dog['id'] ?>" class="btn <?= ($activeDog && (int) $activeDog['id'] === (int) $dog['id']) ? 'btn-primary' : 'btn-outline-secondary' ?> btn-sm">
                            <?= e($dog['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    <?php else: ?>
        <div class="alert alert-warning">No dog profiles yet. <a href="dogs.php" class="alert-link">Create your first dog</a>.</div>
    <?php endif; ?>

    <details class="fold-card mb-3" open id="today">
        <summary>
            <div>
                <div class="small text-uppercase text-muted fw-semibold">Quick path</div>
                <h2 class="h5 mb-1">Today</h2>
            </div>
        </summary>
        <div class="card-body">
            <?php if ($dailyWinPrompt): ?>
                <div class="mb-3 p-3 border rounded-4 bg-light">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Daily Quick Win</div>
                            <div class="fw-bold"><?= e($dailyWinPrompt['title']) ?></div>
                            <div class="small text-muted">Day <?= (int) $dailyWinPrompt['day'] ?> of <?= (int) $dailyWinPrompt['total'] ?>.</div>
                        </div>
                        <?php if ($dailyWinSavedToday): ?>
                            <span class="badge bg-success">Saved today</span>
                        <?php endif; ?>
                    </div>
                    <div class="small mb-3"><?= e($dailyWinPrompt['detail']) ?></div>
                    <?php if ($dailyWinExisting): ?>
                        <div class="alert alert-success py-2 mb-3">
                            This quick win is already saved as a training log.
                            <a href="view_logs.php" class="alert-link">Open history</a>.
                        </div>
                    <?php else: ?>
                        <form method="post" class="vstack gap-3">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            <input type="hidden" name="action" value="save_daily_win">
                            <input type="hidden" name="daily_win_title" value="<?= e($dailyWinPrompt['title']) ?>">
                            <input type="hidden" name="daily_win_detail" value="<?= e($dailyWinPrompt['detail']) ?>">
                            <label class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="daily_win_complete" value="1">
                                <span class="form-check-label fw-semibold">Done today</span>
                            </label>
                            <button class="btn btn-primary btn-sm align-self-start">Save quick win</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="today-grid">
                <?php if (featureEnabled($pdo, 'quick_session_enabled')): ?>
                    <a class="today-action" href="quick_log.php"><span>⚡</span>Quick Session</a>
                <?php endif; ?>
                <?php if (featureEnabled($pdo, 'detailed_log_enabled')): ?>
                    <a class="today-action" href="log_entry.php"><span>📝</span>Detailed Log</a>
                <?php endif; ?>
                <?php if (featureEnabled($pdo, 'goal_builder_enabled')): ?>
                    <a class="today-action" href="goal_builder.php"><span>🎯</span>Goal Builder</a>
                <?php elseif (featureEnabled($pdo, 'training_program_enabled')): ?>
                    <a class="today-action" href="training_program.php"><span>🎓</span>Training Program</a>
                <?php endif; ?>
                <?php if (featureEnabled($pdo, 'ada_wallet_enabled')): ?>
                    <a class="today-action" href="ada_access_card.php"><span>🪪</span>ADA Access</a>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <details class="fold-card mb-3" id="needs-attention">
        <summary>
            <div>
                <div class="small text-uppercase text-muted fw-semibold">Review queue</div>
                <h2 class="h5 mb-1">Needs Attention</h2>
                <div class="small text-muted"><?= (int) $attentionCount ?> item<?= $attentionCount === 1 ? '' : 's' ?> needing review.</div>
            </div>
        </summary>
        <div class="card-body">
            <?php if (!$activeAlerts && !$upcomingReminders && !$upcomingRefills && !$incomingDogTransfers && !$openCoachReviews && !$openVideoReviews && $unreadNotifications === 0): ?>
                <div class="attention-empty">✅ No active alerts, transfer requests, notifications, coach reviews, video reviews, or upcoming vet reminders right now.</div>
            <?php endif; ?>

            <?php if ($activeAlerts): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Priority</div>
                            <h3 class="h6 mb-1">Smart Alerts</h3>
                            <div class="small text-muted"><?= count($activeAlerts) ?> alert<?= count($activeAlerts) === 1 ? '' : 's' ?> ready to review.</div>
                        </div>
                        <a href="alerts.php" class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation();">View all</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="vstack gap-2">
                            <?php foreach (array_slice($activeAlerts, 0, 3) as $alert): ?>
                                <div class="alert-card <?= e($alert['level']) ?> rounded-3 border bg-white p-3">
                                    <div class="fw-semibold"><?= e($alert['title']) ?></div>
                                    <div class="small text-muted"><?= e($alert['detail']) ?></div>
                                    <?php $moduleLink = gpTrainingSuggestionLink(($alert['title'] ?? '') . ' ' . ($alert['detail'] ?? '')); if ($moduleLink): ?>
                                        <a class="btn btn-outline-primary btn-sm mt-2" href="<?= e($moduleLink['url']) ?>"><?= e($moduleLink['label']) ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($upcomingReminders): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Calendar</div>
                            <h3 class="h6 mb-1">Vet Reminders</h3>
                            <div class="small text-muted"><?= count($upcomingReminders) ?> appointment<?= count($upcomingReminders) === 1 ? '' : 's' ?> coming up.</div>
                        </div>
                        <a href="appointments.php" class="btn btn-outline-warning btn-sm" onclick="event.stopPropagation();">Appointments</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcomingReminders as $item): ?>
                                <div class="list-group-item px-0">
                                    <div class="fw-semibold text-break"><?= e($item['dog_name']) ?> — <?= e($item['title']) ?></div>
                                    <div class="small text-muted text-break"><?= e(date('M d, Y g:i A', strtotime($item['appointment_at']))) ?><?= !empty($item['clinic_name']) ? ' • ' . e($item['clinic_name']) : '' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($upcomingRefills): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Medications</div>
                            <h3 class="h6 mb-1">Refills Due Soon</h3>
                            <div class="small text-muted"><?= count($upcomingRefills) ?> refill<?= count($upcomingRefills) === 1 ? '' : 's' ?> within 14 days.</div>
                        </div>
                        <a href="medications.php" class="btn btn-outline-warning btn-sm" onclick="event.stopPropagation();">Medications</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcomingRefills as $item):
                                $daysLeft = (int) round((strtotime($item['refill_date']) - strtotime('today')) / 86400);
                                $badge = $daysLeft <= 0 ? '<span class="badge bg-danger ms-1">Overdue</span>'
                                       : ($daysLeft <= 3 ? '<span class="badge bg-danger ms-1">In ' . $daysLeft . 'd</span>'
                                       : '<span class="badge bg-warning text-dark ms-1">In ' . $daysLeft . 'd</span>');
                            ?>
                                <div class="list-group-item px-0">
                                    <div class="fw-semibold text-break"><?= e($item['dog_name']) ?> — <?= e($item['medication_name']) ?><?= $badge ?></div>
                                    <div class="small text-muted">Refill by <?= e(date('M d, Y', strtotime($item['refill_date']))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>

            <details class="fold-card mb-2">
                <summary>
                    <div>
                        <div class="small text-uppercase text-muted fw-semibold">Scoring</div>
                        <h3 class="h6 mb-1">Candidate Assessment</h3>
                        <div class="small text-muted"><?= $latestCandidateAssessment ? 'Latest assessment ready to review.' : 'No active candidate assessment yet.' ?></div>
                    </div>
                    <div class="badge bg-secondary">Summary</div>
                </summary>
                <div class="card-body pt-0">
                    <?php gpDashboardRenderCandidateAssessmentAlert($latestCandidateAssessment); ?>
                </div>
            </details>
            <?php if (featureEnabled($pdo, 'regression_engine_enabled')): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Regression</div>
                            <h3 class="h6 mb-1">Regression Engine</h3>
                            <div class="small text-muted"><?= (int) $openRegressionCount ?> open regression item<?= $openRegressionCount === 1 ? '' : 's' ?>.</div>
                        </div>
                        <div class="badge bg-secondary">Summary</div>
                    </summary>
                    <div class="card-body pt-0">
                        <?php gpDashboardRenderRegressionAlerts($openRegressionEvents, $openRegressionCount); ?>
                    </div>
                </details>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'behavior_risk_scoring_enabled') && $behaviorRiskState): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Risk</div>
                            <h3 class="h6 mb-1">Behavior Risk</h3>
                            <div class="small text-muted">Current score <?= (int) $behaviorRiskState['score'] ?>, <?= e(ucfirst((string) $behaviorRiskState['band'])) ?> risk.</div>
                        </div>
                        <a href="behavior_risk_scoring.php" class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation();">Open scoring</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="attention-empty">
                            Current score <?= (int) $behaviorRiskState['score'] ?>, <?= e(ucfirst((string) $behaviorRiskState['band'])) ?> risk.
                            <?php if (!empty($behaviorRiskState['candidate']['dog_name'])): ?>
                                Latest assessment: <?= e($behaviorRiskState['candidate']['dog_name']) ?>.
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($openCoachReviews): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Review</div>
                            <h3 class="h6 mb-1">Coach Review</h3>
                            <div class="small text-muted"><?= count($openCoachReviews) ?> coach review<?= count($openCoachReviews) === 1 ? '' : 's' ?> waiting.</div>
                        </div>
                        <a href="coach_review.php" class="btn btn-outline-primary btn-sm" onclick="event.stopPropagation();">Review queue</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="vstack gap-2">
                            <?php foreach (array_slice($openCoachReviews, 0, 3) as $review): ?>
                                <div class="alert-card <?= e(($review['priority'] ?? 'normal') === 'high' ? 'danger' : 'warning') ?> rounded-3 border bg-white p-3">
                                    <div class="fw-semibold"><?= e($review['dog_name']) ?> — <?= e($review['review_type'] ?? 'coach review') ?></div>
                                    <div class="small text-muted text-break"><?= e($review['reason'] ?? 'Open coaching follow-up') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($openVideoReviews): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Review</div>
                            <h3 class="h6 mb-1">Video Review</h3>
                            <div class="small text-muted"><?= count($openVideoReviews) ?> video review<?= count($openVideoReviews) === 1 ? '' : 's' ?> waiting.</div>
                        </div>
                        <a href="video_review.php" class="btn btn-outline-info btn-sm" onclick="event.stopPropagation();">Open videos</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="vstack gap-2">
                            <?php foreach (array_slice($openVideoReviews, 0, 3) as $review): ?>
                                <div class="alert-card info rounded-3 border bg-white p-3">
                                    <div class="fw-semibold"><?= e($review['dog_name']) ?> — <?= e($review['location_name'] ?? 'Video checkpoint') ?></div>
                                    <div class="small text-muted text-break"><?= e(date('M j, Y', strtotime((string) $review['log_date']))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </details>

    <?php if (($_GET['msg'] ?? '') === 'feature_disabled'): ?>
        <div class="alert alert-info">That GuidePaw feature is not enabled yet. It is on the roadmap and may be available in a future beta.</div>
    <?php elseif (($_GET['msg'] ?? '') === 'quick_session_disabled'): ?>
        <div class="alert alert-warning">Quick Session is temporarily disabled during beta.</div>
    <?php elseif (($_GET['msg'] ?? '') === 'detailed_log_disabled'): ?>
        <div class="alert alert-warning">Detailed Log is temporarily disabled during beta.</div>
    <?php endif; ?>

</main>

<script src="app.js"></script>
</body>
</html>
