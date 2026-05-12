<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
$facebookUrl = trim((string) ($user['facebook_url'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Contact Us · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .contact-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .contact-link { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-top:1px solid rgba(15,23,42,.08); color:#1f2937; text-decoration:none; }
    .contact-link:first-of-type { border-top:0; }
    .contact-icon { font-size:1.25rem; margin-right:.45rem; }
    .contact-muted { color:#6b7280; font-size:.9rem; }
    .contact-url { word-break: break-word; text-align: right; }
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">📇 Contact Us</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <section class="card contact-card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">Reach GuidePaw</h2>
            <p class="contact-muted mb-3">Quick ways to get in touch or open your saved social profile.</p>
            <a class="contact-link" href="feedback.php">
                <span><span class="contact-icon">💬</span>Send feedback</span>
                <span>›</span>
            </a>
            <a class="contact-link" href="handler_profile.php">
                <span><span class="contact-icon">👤</span>Edit handler contact details</span>
                <span>›</span>
            </a>
            <?php if ($facebookUrl !== ''): ?>
                <a class="contact-link" href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <span><span class="contact-icon">📘</span>Open Facebook</span>
                    <span class="contact-url"><?= e($facebookUrl) ?></span>
                </a>
            <?php else: ?>
                <div class="contact-muted">No Facebook link has been saved on this account yet.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card contact-card">
        <div class="card-body">
            <h2 class="h5 mb-1">Other support</h2>
            <p class="contact-muted mb-3">For app issues, use the feedback page. For profile changes, update the handler profile first.</p>
            <a class="contact-link" href="settings.php">
                <span><span class="contact-icon">⚙️</span>Settings</span>
                <span>›</span>
            </a>
        </div>
    </section>
</main>
<script src="app.js"></script>
</body>
</html>
