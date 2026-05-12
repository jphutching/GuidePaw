<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';
checkLogin();

$supportUrl = trim((string) gpEnv('GUIDEPAW_SUPPORT_FUNDING_URL', ''));
$merchUrl = trim((string) gpEnv('GUIDEPAW_MERCH_STORE_URL', ''));
$discordUrl = trim((string) gpEnv('GUIDEPAW_DISCORD_INVITE_URL', ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Support Funding · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .support-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .support-link { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-top:1px solid rgba(15,23,42,.08); color:#1f2937; text-decoration:none; }
    .support-link:first-of-type { border-top:0; }
    .support-muted { color:#6b7280; font-size:.92rem; }
    .support-badge { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.25rem .7rem; background:#eef2ff; color:#4338ca; font-size:.8rem; font-weight:900; }
    .support-callout { border: 1px dashed rgba(13,110,253,.28); background:#f8fbff; border-radius: 16px; padding: 1rem; }
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">💙 Support GuidePaw</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <section class="card support-card mb-3">
        <div class="card-body">
            <div class="support-badge mb-2">One-time help or ongoing support</div>
            <h2 class="h5 mb-2">Help keep GuidePaw running</h2>
            <p class="support-muted mb-3">This page is where handlers and supporters can back the project without hunting through the app. It can point at a real funding link, merch store, or community channel.</p>

            <?php if ($supportUrl !== ''): ?>
                <a class="btn btn-primary btn-lg w-100 mb-3" href="<?= e($supportUrl) ?>" target="_blank" rel="noopener noreferrer">Support GuidePaw</a>
            <?php else: ?>
                <div class="support-callout mb-3">
                    <strong>Funding link not configured yet.</strong>
                    <div class="support-muted mt-1">Set <code>GUIDEPAW_SUPPORT_FUNDING_URL</code> to point this button at your funding page, checkout, or sponsor form.</div>
                </div>
            <?php endif; ?>

            <a class="support-link" href="community.php">
                <span><span class="me-2">🤝</span>Open Community</span>
                <span>›</span>
            </a>
            <?php if ($merchUrl !== ''): ?>
                <a class="support-link" href="<?= e($merchUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <span><span class="me-2">🛍️</span>Browse merch</span>
                    <span>›</span>
                </a>
            <?php endif; ?>
            <?php if ($discordUrl !== ''): ?>
                <a class="support-link" href="<?= e($discordUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <span><span class="me-2">💬</span>Join Discord</span>
                    <span>›</span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="card support-card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">What support helps cover</h2>
            <p class="support-muted mb-3">Support funding is meant for the project itself, not for the free handler workflow. The first handler account and first dog stay free.</p>
            <ul class="mb-0">
                <li>Project hosting and maintenance</li>
                <li>New handler tools and accessibility work</li>
                <li>Optional add-ons like QR tracking, extra dogs, or premium surfaces</li>
                <li>Community features like the forum and support channels</li>
            </ul>
        </div>
    </section>

    <section class="card support-card">
        <div class="card-body">
            <h2 class="h5 mb-1">Suggested support paths</h2>
            <div class="support-muted mb-3">Pick the path that fits your setup. The app can route all of them from one page.</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="support-callout h-100">
                        <div class="fw-bold mb-1">One-time gift</div>
                        <div class="support-muted">A single contribution to help with ongoing development.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="support-callout h-100">
                        <div class="fw-bold mb-1">Monthly support</div>
                        <div class="support-muted">A recurring contribution for users who want to keep the project moving.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="support-callout h-100">
                        <div class="fw-bold mb-1">Merch support</div>
                        <div class="support-muted">Swag and community items can also help fund the app.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="app.js"></script>
</body>
</html>
