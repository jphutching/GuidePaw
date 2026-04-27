<?php
require_once 'includes/db_connect.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once 'includes/app_config.php';
checkLogin();

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
.gp-brand-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(12px, 4vw, 28px);
    margin-bottom: clamp(16px, 4vw, 28px);
    flex-wrap: wrap;
}
.gp-brand-logo {
    width: clamp(120px, 32vw, 190px);
    height: auto;
    border-radius: 14px;
    display: block;
}
.gp-brand-copy {
    text-align: left;
    color: #fff;
    min-width: 180px;
}
.gp-brand-name {
    font-size: clamp(1.45rem, 5vw, 2.4rem);
    font-weight: 800;
    line-height: 1;
}
.gp-brand-tagline {
    margin-top: 8px;
    font-size: clamp(.72rem, 2.5vw, .95rem);
    letter-spacing: .12em;
    text-transform: uppercase;
    opacity: .78;
}
@media (max-width: 520px) {
    .gp-brand-copy {
        text-align: center;
    }
}
</style>

</head>
<body class="pb-5">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
    <div class="topbar p-4 shadow-sm">
    <div class="gp-brand-hero">
        <img class="gp-brand-logo" src="/assets/brand/guidepaw-logo.png" alt="GuidePaw">
        <div class="gp-brand-copy">
            <div class="gp-brand-name">GuidePaw</div>
            <div class="gp-brand-tagline">Training. Trust. For the Journey.</div>
        </div>
    </div>
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="small opacity-75"><?= e(appName()) ?> • Signed in as <?= e($user['username'] ?? 'handler') ?></div>
                <h2 class="mb-1">🐾 <?= e($activeDog['name'] ?? 'No active dog selected') ?></h2>
                <div class="small opacity-75">
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

    <div class="page-shell mt-3">
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

        <?php if ($dogs): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Dog Switcher</h5>
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
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No dog profiles yet. <a href="dogs.php" class="alert-link">Create your first dog</a>.</div>
        <?php endif; ?>


        <?php if ($activeDog): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Smart Alerts</h5>
                        <a href="alerts.php" class="btn btn-outline-danger btn-sm">View all</a>
                    </div>
                    <?php if (!$activeAlerts): ?>
                        <div class="small text-muted mb-0">No active alerts for this dog right now.</div>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach (array_slice($activeAlerts, 0, 3) as $alert): ?>
                                <div class="alert-card <?= e($alert['level']) ?> rounded-3 border bg-white p-3">
                                    <div class="fw-semibold"><?= e($alert['title']) ?></div>
                                    <div class="small text-muted"><?= e($alert['detail']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($upcomingReminders): ?>
            <div class="card shadow-sm mb-3 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Upcoming Vet Reminders</h5>
                        <a href="appointments.php" class="btn btn-outline-warning btn-sm">Appointments</a>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingReminders as $item): ?>
                            <div class="list-group-item px-0">
                                <div class="fw-semibold"><?= e($item['dog_name']) ?> — <?= e($item['title']) ?></div>
                                <div class="small text-muted"><?= e(date('M d, Y g:i A', strtotime($item['appointment_at']))) ?><?= !empty($item['clinic_name']) ? ' • ' . e($item['clinic_name']) : '' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (($_GET['msg'] ?? '') === 'feature_disabled'): ?>
                <div class="alert alert-info">That GuidePaw feature is not enabled yet. It is on the roadmap and may be available in a future beta.</div>
            <?php elseif (($_GET['msg'] ?? '') === 'quick_session_disabled'): ?>
            <div class="alert alert-warning">Quick Session is temporarily disabled during beta.</div>
        <?php elseif (($_GET['msg'] ?? '') === 'detailed_log_disabled'): ?>
            <div class="alert alert-warning">Detailed Log is temporarily disabled during beta.</div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-6 col-md-4">
                <?php if (featureEnabled($pdo, 'quick_session_enabled')): ?>
                    <a href="quick_log.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">⚡<br>Quick Session</a>
                <?php else: ?>
                    <div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">⚡<br>Quick Session<br><small>Coming soon</small></div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-4">
                <?php if (featureEnabled($pdo, 'detailed_log_enabled')): ?>
                    <a href="log_entry.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">📝<br>Detailed Log</a>
                <?php else: ?>
                    <div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">📝<br>Detailed Log<br><small>Coming soon</small></div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-4"><a href="view_logs.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">📋<br>History</a></div>
            <div class="col-6 col-md-4"><a href="stats.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">📊<br>Stats</a></div>
            <div class="col-6 col-md-4"><a href="dogs.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🐕<br>Dogs</a></div>
            <div class="col-6 col-md-4"><a href="dog_profile.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🪪<br>Dog Profile</a></div>
            <div class="col-6 col-md-4"><a href="collaboration.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🤝<br>Handlers</a></div>
            <?php if (featureEnabled($pdo, 'health_docs_enabled')): ?>
                <div class="col-6 col-md-4"><a href="dog_health.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🩺<br>Health Docs</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">🩺<br>Health Docs<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'vet_appointments_enabled')): ?>
                <div class="col-6 col-md-4"><a href="appointments.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">📅<br>Vet Appts</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">📅<br>Vet Appts<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'alerts_enabled')): ?>
                <div class="col-6 col-md-4"><a href="alerts.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🧠<br>Alerts</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">🧠<br>Alerts<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'training_program_enabled')): ?>
                <div class="col-6 col-md-4"><a href="training_program.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🎓<br>Training</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">🎓<br>Training<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'medications_enabled')): ?>
                <div class="col-6 col-md-4"><a href="medications.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">💊<br>Meds</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">💊<br>Meds<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'certification_enabled')): ?>
                <div class="col-6 col-md-4"><a href="certification.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">✅<br>Certification</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">✅<br>Certification<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <?php if (featureEnabled($pdo, 'backup_tools_enabled')): ?>
                <div class="col-6 col-md-4"><a href="backup.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">💾<br>Backup</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">💾<br>Backup<br><small>Coming soon</small></div></div>
            <?php endif; ?>
            <div class="col-6 col-md-4"><a href="api_tokens.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🔐<br>API Tokens</a></div>
            <div class="col-6 col-md-4"><a href="db_status.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🗄️<br>DB Status</a></div>
            
            <?php if (featureEnabled($pdo, 'ada_wallet_enabled')): ?>
                <div class="col-6 col-md-4"><a href="ada_wallet_card.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🪪<br>ADA Wallet Card</a></div>
            <?php else: ?>
                <div class="col-6 col-md-4"><div class="btn btn-tile w-100 shadow-sm text-dark text-center disabled opacity-75">🪪<br>ADA Wallet Card<br><small>Coming soon</small></div></div>
            <?php endif; ?>
        </div>
    </div>

    <script src="app.js"></script>

<div class="container my-4">
    <h2 class="h5 mb-3">Training Core</h2>
    <div class="row g-3">
        <?php if (featureEnabled($pdo, 'candidate_scoring_enabled')): ?>
            <div class="col-6 col-md-3">
                <a href="candidate_assessment.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🐾<br>Candidate<br>Assessment</a>
            </div>
        <?php endif; ?>

        <?php if (featureEnabled($pdo, 'goal_intake_enabled')): ?>
            <div class="col-6 col-md-3">
                <a href="training_goal_intake.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🎯<br>Goal<br>Intake</a>
            </div>
        <?php endif; ?>

        <?php if (featureEnabled($pdo, 'habit_repair_enabled')): ?>
            <div class="col-6 col-md-3">
                <a href="habit_repair.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🛠️<br>Habit<br>Repair</a>
            </div>
        <?php endif; ?>

        <?php if (featureEnabled($pdo, 'training_progression_enabled')): ?>
            <div class="col-6 col-md-3">
                <a href="training_session_log.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">✅<br>Session<br>Log</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['is_admin'])): ?>
            <div class="col-6 col-md-3">
                <a href="admin_feature_roadmap.php" class="btn btn-tile w-100 shadow-sm text-decoration-none text-dark text-center">🗺️<br>Feature<br>Roadmap</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
