<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
$activeDog = getActiveDog($pdo, $userId);
$is2faEnabled = !empty($user['is_2fa_enabled']);
$remembered = !empty($_SESSION['remember_me']);
$expiresAt = !empty($_SESSION['login_expires_at']) ? (int) $_SESSION['login_expires_at'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Settings · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .settings-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .settings-link { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-top:1px solid rgba(15,23,42,.08); color:#1f2937; text-decoration:none; }
    .settings-link:first-of-type { border-top:0; }
    .settings-icon { font-size:1.25rem; margin-right:.45rem; }
    .settings-muted { color:#6b7280; font-size:.9rem; }
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">⚙️ Settings</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <section class="card settings-card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">Account</h2>
            <p class="settings-muted mb-3">Handler sign-in, password, and account security.</p>
            <div class="mb-3"><div class="fw-semibold">Signed in as</div><div class="text-muted"><?= e($user['username'] ?? 'handler') ?></div></div>
            <a class="settings-link" href="handler_profile.php"><span><span class="settings-icon">👤</span>Handler profile</span><span>›</span></a>
            <a class="settings-link" href="reset_password.php"><span><span class="settings-icon">🔑</span>Change password</span><span>›</span></a>
            <a class="settings-link" href="setup_2fa.php"><span><span class="settings-icon">🛡️</span><?= $is2faEnabled ? 'Manage 2-factor auth' : 'Enable 2-factor auth' ?></span><span class="badge <?= $is2faEnabled ? 'bg-success' : 'bg-secondary' ?>"><?= $is2faEnabled ? 'On' : 'Off' ?></span></a>
            <div class="settings-link"><span><span class="settings-icon">⏱️</span>Remember me</span><span class="settings-muted text-end"><?= $remembered ? 'On for this session' : 'Off' ?><?php if ($expiresAt): ?><br>Expires <?= e(date('M j, g:i A', $expiresAt)) ?><?php endif; ?></span></div>
        </div>
    </section>

    <section class="card settings-card mb-3"><div class="card-body"><h2 class="h5 mb-1">Notifications</h2><p class="settings-muted mb-3">Browser/PWA reminders and queued offline sync.</p><div class="d-grid gap-2"><button type="button" class="btn btn-outline-success" data-enable-notifications>Enable reminders</button><button type="button" class="btn btn-outline-secondary" data-test-notification>Test notification</button><button type="button" class="btn btn-outline-primary" data-sync-queued>Sync queued logs</button></div><div class="mt-3 d-flex flex-wrap gap-2"><span data-network-status class="badge bg-secondary">Checking...</span><span data-notification-state class="badge bg-secondary">Notifications off</span><span class="badge bg-dark" data-queue-count style="display:none;">0</span></div></div></section>

    <section class="card settings-card mb-3"><div class="card-body"><h2 class="h5 mb-1">Session</h2><p class="settings-muted mb-3">Sign out of GuidePaw on this device.</p><a href="logout.php" class="btn btn-danger w-100">Logout</a></div></section>
</main>
<script src="app.js"></script>
</body>
</html>
