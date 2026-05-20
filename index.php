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
    $landingTitle = 'GuidePaw | Dog Training Logs, Service Dog Profiles, and Handler Tools';
    $landingDescription = 'GuidePaw helps handlers research breeds, track training, manage dog profiles, share public service dog details, and keep support tools organized.';
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
        .landing-shell { max-width: 1100px; margin: 0 auto; padding: 1rem 1rem 3rem; }
        .landing-hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color: #fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
        .landing-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); background: #fff; }
        .landing-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .landing-pill { display:inline-flex; align-items:center; gap:.45rem; border-radius:999px; padding:.45rem .75rem; background: rgba(255,255,255,.12); font-weight:700; }
        .landing-example { border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: 1rem; background: #fff; height: 100%; }
        .landing-faq summary { cursor: pointer; font-weight: 700; }
    </style>
    </head>
    <body>
    <?php guidepawBrandHeader(); ?>
    <main class="landing-shell">
        <section class="landing-hero mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="flex-grow-1" style="min-width: 260px;">
                    <div class="landing-pill mb-3">Dog training logs • service dog profiles • breed research</div>
                    <h1 class="display-6 fw-bold mb-3">GuidePaw keeps handler work organized.</h1>
                    <p class="lead mb-4"><?= e($landingDescription) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-light btn-lg fw-bold" href="breed_questionnaire.php">Research a breed</a>
                        <a class="btn btn-outline-light btn-lg fw-bold" href="register.php">Create an account</a>
                        <a class="btn btn-outline-light btn-lg fw-bold" href="support_funding.php">Support GuidePaw</a>
                    </div>
                </div>
                <div class="landing-card p-3 text-dark" style="min-width: 280px; max-width: 360px; width: 100%;">
                    <div class="small text-uppercase text-muted fw-semibold">What people use it for</div>
                    <ul class="mt-3 mb-0 ps-3">
                        <li>Track training sessions, reminders, and quick wins.</li>
                        <li>Share a public dog profile and found-dog contact details.</li>
                        <li>Compare breeds before committing to a dog.</li>
                        <li>Keep ADA, travel, and support notes in one place.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="mb-4">
            <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
                <div>
                    <div class="text-uppercase text-muted fw-semibold small">Examples</div>
                    <h2 class="h3 mb-0">Public examples people can browse</h2>
                </div>
                <div class="text-muted">These are read-only entry points, not editable marketing filler.</div>
            </div>
            <div class="landing-grid">
                <div class="landing-example">
                    <div class="fw-bold mb-1">Breed Questionnaire</div>
                    <div class="text-muted small">Size, focus, family, and nickname matching to help narrow breeds worth researching.</div>
                </div>
                <div class="landing-example">
                    <div class="fw-bold mb-1">Public Dog Profile</div>
                    <div class="text-muted small">A shareable profile for contact details, found-dog reports, and public notes.</div>
                </div>
                <div class="landing-example">
                    <div class="fw-bold mb-1">Support Options</div>
                    <div class="text-muted small">One-time support, monthly support, and a la carte services like QR tracking.</div>
                </div>
                <div class="landing-example">
                    <div class="fw-bold mb-1">Service Dog Notes</div>
                    <div class="text-muted small">Practical ADA and travel notes for handlers who need a quick reference.</div>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="landing-card p-4 h-100">
                    <div class="text-uppercase text-muted fw-semibold small mb-2">How it works</div>
                    <h2 class="h3">A straightforward path from research to daily use</h2>
                    <ol class="mt-3 mb-0">
                        <li>Research a breed before you commit.</li>
                        <li>Create a handler account and add your dogs.</li>
                        <li>Track training, reminders, and public profile details.</li>
                        <li>Keep support options and add-ons separate from the core workflow.</li>
                    </ol>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="landing-card p-4 h-100">
                    <div class="text-uppercase text-muted fw-semibold small mb-2">Common questions</div>
                    <div class="landing-faq accordion accordion-flush" id="gpFaq">
                        <details class="mb-3">
                            <summary>Is the breed questionnaire public?</summary>
                            <p class="text-muted mt-2 mb-0">Yes. It is meant for people researching a dog before they create an account.</p>
                        </details>
                        <details class="mb-3">
                            <summary>Do I need an account to browse the examples?</summary>
                            <p class="text-muted mt-2 mb-0">No. The public landing, breed tool, and support page are all readable without signing in.</p>
                        </details>
                        <details>
                            <summary>What is the main dashboard for?</summary>
                            <p class="text-muted mt-2 mb-0">The dashboard is for logged-in handlers to manage dogs, training, and support tools.</p>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-uppercase text-muted fw-semibold small mb-1">Next step</div>
                    <h2 class="h4 mb-0">Start with the breed questionnaire or sign in to your dashboard.</h2>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Open breed tool</a>
                    <a class="btn btn-outline-primary fw-bold" href="login.php">Sign in</a>
                </div>
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
$activeAlerts = $activeDog ? getDogAlertItems($pdo, $userId, (int) $activeDog['id']) : [];
$incomingDogTransfers = gpDashboardIncomingDogTransfers($pdo, $userId);
$latestCandidateAssessment = gpLatestCandidateAssessment($pdo, $userId, $activeDog ? (int) $activeDog['id'] : null);
$behaviorRiskState = gpBehaviorRiskAssessment($pdo, $userId, $activeDog ? (int) $activeDog['id'] : null);
$communityChallengeState = $activeDog ? gpCommunityChallengeState($userId, (int) $activeDog['id']) : null;
$openRegressionEvents = $activeDog ? gpDashboardOpenRegressionEvents($pdo, $userId) : [];
$openRegressionCount = $activeDog ? gpRegressionEngineOpenCount($pdo, $userId, (int) $activeDog['id']) : 0;
$openCoachReviews = gpDashboardOpenCoachReviews($pdo, $userId);
$openVideoReviews = gpDashboardOpenVideoReviews($pdo, $userId);
$wearableEvents = gpWearableRecentEvents($pdo, $userId, $activeDog ? (int) $activeDog['id'] : null, 1);
$latestWearableSync = $wearableEvents[0] ?? null;
$trainerMarketplaceEntries = gpTrainerMarketplaceEntries($pdo, $userId);
$unreadNotifications = gpUnreadNotificationCount($pdo, $userId);
$dailyWinPrompt = $activeDog ? gpDailyWinPromptForDate() : null;
$dailyWinLogName = $dailyWinPrompt ? gpDailyWinLogName($dailyWinPrompt) : '';
$dailyWinExisting = ($activeDog && $dailyWinPrompt) ? gpDailyWinExistingLog($pdo, $userId, (int) $activeDog['id'], $dailyWinLogName) : null;
$candidateAttention = (!$latestCandidateAssessment || (int) ($latestCandidateAssessment['focus_level_recommended'] ?? 0) < 3) ? 1 : 0;
$attentionCount = count($activeAlerts) + count($upcomingReminders) + count($incomingDogTransfers) + $openRegressionCount + count($openCoachReviews) + count($openVideoReviews) + $candidateAttention + $unreadNotifications;

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
    .dashboard-hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color: #fff; border-radius: 0 0 28px 28px; padding: 1.25rem 1rem 1.5rem; box-shadow: 0 10px 24px rgba(15,23,42,.18); }
    .dashboard-hero .btn-outline-light { border-color: rgba(255,255,255,.45); }
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

<header class="dashboard-hero">
    <div class="container px-0" style="max-width: 960px;">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="min-w-0">
                <div class="small opacity-75"><?= e(appName()) ?> • Signed in as <?= e($user['username'] ?? 'handler') ?></div>
                <h1 class="h2 mb-1 text-break">🐾 <?= e($activeDog['name'] ?? 'No active dog selected') ?></h1>
                <div class="small opacity-75 text-break">
                    <?php if ($activeDog): ?>
                        <?= e($activeDog['breed'] ?: 'Breed Not Set') ?>
                        <?= !empty($activeDog['weight_lbs']) ? ' • ' . e((string) $activeDog['weight_lbs']) . ' lbs' : '' ?>
                        • <?= e(ucfirst($activeDog['access_role'])) ?> access
                    <?php else: ?>
                        Add a dog profile to start logging.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="page-shell mt-3">
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

    <div class="home-utility mb-3">
        <span data-network-status class="badge bg-secondary">Checking...</span>
    </div>

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
            <?php if (!$activeAlerts && !$upcomingReminders && !$incomingDogTransfers && !$openCoachReviews && !$openVideoReviews && $unreadNotifications === 0): ?>
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
            <?php if (featureEnabled($pdo, 'wearable_integrations_enabled')): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Care</div>
                            <h3 class="h6 mb-1">Wearable Sync</h3>
                            <div class="small text-muted">
                                <?php if ($latestWearableSync): ?>
                                    Last sync <?= e((string) ($latestWearableSync['recorded_for_date'] ?? $latestWearableSync['created_at'])) ?>.
                                <?php else: ?>
                                    No wearable snapshots recorded yet.
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="wearable_integrations.php" class="btn btn-outline-dark btn-sm" onclick="event.stopPropagation();">Open sync hub</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="attention-empty">
                            <?php if ($latestWearableSync): ?>
                                Last sync <?= e((string) ($latestWearableSync['recorded_for_date'] ?? $latestWearableSync['created_at'])) ?> from <?= e((string) ($latestWearableSync['source'] ?? 'manual')) ?>.
                            <?php else: ?>
                                No wearable snapshots recorded yet.
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'trainer_marketplace_enabled')): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Training</div>
                            <h3 class="h6 mb-1">Trainer Marketplace</h3>
                            <div class="small text-muted"><?= (int) count($trainerMarketplaceEntries) ?> trainer profile<?= count($trainerMarketplaceEntries) === 1 ? '' : 's' ?> ready.</div>
                        </div>
                        <a href="trainer_marketplace.php" class="btn btn-outline-primary btn-sm" onclick="event.stopPropagation();">Open directory</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="attention-empty">
                            <?= (int) count($trainerMarketplaceEntries) ?> trainer profile<?= count($trainerMarketplaceEntries) === 1 ? '' : 's' ?> ready in the directory.
                        </div>
                    </div>
                </details>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'candidate_comparison_enabled') && count($dogs) > 1): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Compare</div>
                            <h3 class="h6 mb-1">Candidate Comparison</h3>
                            <div class="small text-muted">Compare the active dog against other accessible dogs.</div>
                        </div>
                        <a href="candidate_comparison.php" class="btn btn-outline-secondary btn-sm" onclick="event.stopPropagation();">Compare dogs</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="attention-empty">Compare the active dog against other accessible dogs to see their latest candidate scores side by side.</div>
                    </div>
                </details>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'community_challenges_enabled') && $activeDog && $communityChallengeState): ?>
                <details class="fold-card mb-2">
                    <summary>
                        <div>
                            <div class="small text-uppercase text-muted fw-semibold">Community</div>
                            <h3 class="h6 mb-1">Community Challenge</h3>
                            <div class="small text-muted"><?= e(gpCommunityChallengeDashboardLabel($communityChallengeState)) ?></div>
                        </div>
                        <a href="community_challenges.php" class="btn btn-outline-success btn-sm" onclick="event.stopPropagation();">Open challenge</a>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="attention-empty"><?= e(gpCommunityChallengeDashboardLabel($communityChallengeState)) ?> · <?= e((string) (($communityChallengeState['check_ins'] ?? 0))) ?> check-ins logged.</div>
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

    <section class="menu-hint mb-4">
        <div class="fw-bold mb-1">Need another tool?</div>
        <div class="small text-muted">Tap <strong>Menu</strong> in the bottom navigation. Tools are now grouped under Dog, Logs, Training, Care, and More.</div>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>
