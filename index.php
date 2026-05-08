<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/dog_access_dashboard.php';
require_once __DIR__ . '/includes/candidate_scoring.php';
require_once __DIR__ . '/includes/candidate_comparison.php';
require_once __DIR__ . '/includes/coach_reviews.php';
require_once __DIR__ . '/includes/video_reviews.php';
require_once __DIR__ . '/includes/trucking_mode.php';
require_once __DIR__ . '/includes/dog_access_expiry.php';
require_once __DIR__ . '/includes/notifications.php';
require_once 'includes/app_config.php';
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
$openCoachReviews = gpDashboardOpenCoachReviews($pdo, $userId);
$openVideoReviews = gpDashboardOpenVideoReviews($pdo, $userId);
$unreadNotifications = gpUnreadNotificationCount($pdo, $userId);
$candidateAttention = (!$latestCandidateAssessment || (int) ($latestCandidateAssessment['focus_level_recommended'] ?? 0) < 3) ? 1 : 0;
$attentionCount = count($activeAlerts) + count($upcomingReminders) + count($incomingDogTransfers) + count($openCoachReviews) + count($openVideoReviews) + $candidateAttention + $unreadNotifications;
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
    .today-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
    @media (min-width: 760px) { .today-grid { grid-template-columns: repeat(4, 1fr); } }
    .today-action { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; min-height: 92px; border-radius: 18px; background: #fff; border: 1px solid rgba(15,23,42,.08); color: #1f2937; text-decoration: none; font-weight: 850; box-shadow: 0 6px 18px rgba(15,23,42,.08); }
    .today-action span { font-size: 1.65rem; line-height: 1; margin-bottom: .35rem; }
    .attention-empty { border: 1px dashed rgba(22,163,74,.36); background: #f0fdf4; border-radius: 16px; padding: 1rem; color: #166534; }
    .menu-hint { border: 1px dashed rgba(13,110,253,.38); background: #f8fbff; border-radius: 18px; padding: 1rem; }
    .notification-summary{border:1px solid #bfdbfe;background:#eff6ff;border-radius:18px;padding:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;}
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
            <a href="settings.php" class="btn btn-outline-light btn-sm">Settings</a>
        </div>
    </div>
</header>

<main class="page-shell mt-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span data-network-status class="badge bg-secondary">Checking...</span>
        <span class="badge bg-dark" data-queue-count style="display:none;">0</span>
        <span data-notification-state class="badge bg-secondary">Notifications off</span>
        <small class="text-muted">Queued offline logs/media & vet reminders</small>
        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-sync-queued>Sync queued logs</button>
        <button type="button" class="btn btn-outline-success btn-sm" data-enable-notifications>Enable reminders</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-test-notification>Test alert</button>
    </div>
    <div class="alert alert-info py-2 small">Install the app to your home screen and allow notifications for the best appointment reminder experience. Alerts work through the browser/PWA layer in this build.</div>

    <?php if ($unreadNotifications > 0): ?>
        <section class="notification-summary mb-3">
            <div><div class="fw-bold">🔔 <?= (int) $unreadNotifications ?> unread GuidePaw notification<?= $unreadNotifications === 1 ? '' : 's' ?></div><div class="small text-muted">Transfers, access changes, found-dog reports, and important account notices will appear here.</div></div>
            <a href="notifications.php" class="btn btn-primary btn-sm">Open Notifications</a>
        </section>
    <?php endif; ?>

    <?php gpDashboardRenderDogTransferAlerts($incomingDogTransfers); ?>

    <?php if ($dogs): ?>
        <section class="card command-card mb-3">
            <div class="card-body">
                <div class="command-title">
                    <div>
                        <h2 class="h5 mb-1">Active Dog</h2>
                        <div class="small text-muted">Switch dogs or manage dog profiles.</div>
                    </div>
                    <a href="dogs.php" class="btn btn-outline-primary btn-sm">Manage Dogs</a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($dogs as $dog): ?>
                        <a href="index.php?set_dog=<?= (int) $dog['id'] ?>" class="btn <?= ($activeDog && (int) $activeDog['id'] === (int) $dog['id']) ? 'btn-primary' : 'btn-outline-secondary' ?> btn-sm">
                            <?= e($dog['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php else: ?>
        <div class="alert alert-warning">No dog profiles yet. <a href="dogs.php" class="alert-link">Create your first dog</a>.</div>
    <?php endif; ?>

    <section class="card command-card mb-3" id="today">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Today</h2>
                    <div class="small text-muted">Fast actions for what handlers do most often.</div>
                </div>
            </div>
            <div class="today-grid">
                <?php if (featureEnabled($pdo, 'quick_session_enabled')): ?>
                    <a class="today-action" href="quick_log.php"><span>⚡</span>Quick Session</a>
                <?php endif; ?>
                <?php if (featureEnabled($pdo, 'detailed_log_enabled')): ?>
                    <a class="today-action" href="log_entry.php"><span>📝</span>Detailed Log</a>
                <?php endif; ?>
                <?php if (featureEnabled($pdo, 'trucking_mode_enabled')): ?>
                    <a class="today-action" href="trucking_mode.php"><span>🚚</span>Trucking Mode</a>
                <?php endif; ?>
                <a class="today-action" href="view_logs.php"><span>📋</span>History</a>
                <?php if (featureEnabled($pdo, 'ada_wallet_enabled')): ?>
                    <a class="today-action" href="ada_access_card.php"><span>🪪</span>ADA Access</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card command-card mb-3">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Needs Attention</h2>
            <div class="small text-muted"><?= (int) $attentionCount ?> item<?= $attentionCount === 1 ? '' : 's' ?> needing review.</div>
        </div>
    </div>

            <?php if (!$activeAlerts && !$upcomingReminders && !$incomingDogTransfers && !$openCoachReviews && !$openVideoReviews && $unreadNotifications === 0): ?>
                <div class="attention-empty">✅ No active alerts, transfer requests, notifications, coach reviews, video reviews, or upcoming vet reminders right now.</div>
            <?php endif; ?>

            <?php if ($activeAlerts): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <h3 class="h6 mb-0">Smart Alerts</h3>
                        <a href="alerts.php" class="btn btn-outline-danger btn-sm">View all</a>
                    </div>
                    <div class="vstack gap-2">
                        <?php foreach (array_slice($activeAlerts, 0, 3) as $alert): ?>
                            <div class="alert-card <?= e($alert['level']) ?> rounded-3 border bg-white p-3">
                                <div class="fw-semibold"><?= e($alert['title']) ?></div>
                                <div class="small text-muted"><?= e($alert['detail']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($upcomingReminders): ?>
                <div>
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <h3 class="h6 mb-0">Vet Reminders</h3>
                        <a href="appointments.php" class="btn btn-outline-warning btn-sm">Appointments</a>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingReminders as $item): ?>
                            <div class="list-group-item px-0">
                                <div class="fw-semibold text-break"><?= e($item['dog_name']) ?> — <?= e($item['title']) ?></div>
                                <div class="small text-muted text-break"><?= e(date('M d, Y g:i A', strtotime($item['appointment_at']))) ?><?= !empty($item['clinic_name']) ? ' • ' . e($item['clinic_name']) : '' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php gpDashboardRenderCandidateAssessmentAlert($latestCandidateAssessment); ?>
            <?php if (featureEnabled($pdo, 'candidate_comparison_enabled') && count($dogs) > 1): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <h3 class="h6 mb-0">Candidate Comparison</h3>
                        <a href="candidate_comparison.php" class="btn btn-outline-secondary btn-sm">Compare dogs</a>
                    </div>
                    <div class="attention-empty">Compare the active dog against other accessible dogs to see their latest candidate scores side by side.</div>
                </div>
            <?php endif; ?>

            <?php if ($openCoachReviews): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <h3 class="h6 mb-0">Coach Review</h3>
                        <a href="coach_review.php" class="btn btn-outline-primary btn-sm">Review queue</a>
                    </div>
                    <div class="vstack gap-2">
                        <?php foreach (array_slice($openCoachReviews, 0, 3) as $review): ?>
                            <div class="alert-card <?= e(($review['priority'] ?? 'normal') === 'high' ? 'danger' : 'warning') ?> rounded-3 border bg-white p-3">
                                <div class="fw-semibold"><?= e($review['dog_name']) ?> — <?= e($review['review_type'] ?? 'coach review') ?></div>
                                <div class="small text-muted text-break"><?= e($review['reason'] ?? 'Open coaching follow-up') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($openVideoReviews): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <h3 class="h6 mb-0">Video Review</h3>
                        <a href="video_review.php" class="btn btn-outline-info btn-sm">Open videos</a>
                    </div>
                    <div class="vstack gap-2">
                        <?php foreach (array_slice($openVideoReviews, 0, 3) as $review): ?>
                            <div class="alert-card info rounded-3 border bg-white p-3">
                                <div class="fw-semibold"><?= e($review['dog_name']) ?> — <?= e($review['location_name'] ?? 'Video checkpoint') ?></div>
                                <div class="small text-muted text-break"><?= e(date('M j, Y', strtotime((string) $review['log_date']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($_GET['msg'] ?? '') === 'feature_disabled'): ?>
        <div class="alert alert-info">That GuidePaw feature is not enabled yet. It is on the roadmap and may be available in a future beta.</div>
    <?php elseif (($_GET['msg'] ?? '') === 'quick_session_disabled'): ?>
        <div class="alert alert-warning">Quick Session is temporarily disabled during beta.</div>
    <?php elseif (($_GET['msg'] ?? '') === 'detailed_log_disabled'): ?>
        <div class="alert alert-warning">Detailed Log is temporarily disabled during beta.</div>
    <?php endif; ?>

    <section class="menu-hint mb-4">
        <div class="fw-bold mb-1">Need another tool?</div>
        <div class="small text-muted">Tap <strong>Menu</strong> in the bottom navigation. Tools are now grouped under Dog, Logs, Training, Care, Access, Support, and Admin.</div>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>
