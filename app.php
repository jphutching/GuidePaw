<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/companion_release.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'GuidePaw Companion App | Training, Logs, Dogs, and Wearables';
$description = 'GuidePaw Companion is the Android app for normal handler work: training logs, goals, dogs, profiles, notifications, public read-only pages, and built-in wearable data.';
$release = gpCompanionReleaseInfo();
$apkVersion = (string) $release['version_name'];
$apkFile = (string) ($release['apk_url'] ?? '');
$schema = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => 'GuidePaw Companion App',
        'description' => $description,
        'url' => guidepawSeoAbsoluteUrl('/app.php'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => appShortName(),
            'url' => guidepawSeoAbsoluteUrl('/'),
        ],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php guidepawSeoHead([
    'title' => $title,
    'description' => $description,
    'robots' => 'index,follow',
    'canonical' => guidepawSeoAbsoluteUrl('/app.php'),
    'image' => '/assets/brand/guidepaw-logo.png',
    'json_ld' => $schema,
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f1f5f9; color:#0f172a; }
    .page-shell { max-width: 1060px; margin: 0 auto; padding: 1rem 1rem 3rem; }
    .hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
    .panel { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:20px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
    .muted { color:#64748b; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.4rem .75rem; background:rgba(255,255,255,.12); font-weight:700; }
    .checklist li + li { margin-top:.5rem; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:700px;">
                <div class="pill mb-3">Native Android companion app</div>
                <h1 class="display-6 fw-bold mb-3">GuidePaw Companion</h1>
                <p class="lead mb-0">The native mobile app for normal handler work: training first, with wearables baked in so the app sees the dog, the activity, and the rest cycle together.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:320px;">
                <div class="small text-uppercase text-muted fw-semibold">What it replaces</div>
                <h2 class="h5 mb-2">Daily handler tasks</h2>
                <p class="muted mb-3">The companion app is a native build, not a browser wrapper. It covers the training work you already do on the website, without admin screens.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Start with breed research</a>
                    <a class="btn btn-outline-primary fw-bold" href="<?php echo htmlspecialchars($apkFile, ENT_QUOTES); ?>">Download debug APK v<?php echo htmlspecialchars($apkVersion, ENT_QUOTES); ?></a>
                    <a class="btn btn-outline-primary fw-bold" href="training_program.php">See training tools</a>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Minimum parity</div>
                <h2 class="h3">Training is the floor</h2>
                <p>When the companion app is ready, it should let a normal handler do the same training work they can already do on the website.</p>
                <ul class="checklist mb-0">
                    <li>Training logs and session history</li>
                    <li>Goal intake and goal builder</li>
                    <li>Habit repair and tactical training</li>
                    <li>Behavior risk and regression views</li>
                    <li>Dog switching, profiles, and public notes</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Wearables baked in</div>
                <h2 class="h4">One device layer, not a separate bridge</h2>
                <p class="mb-0">Health Connect, tracker imports, and vendor feeds should roll into the same timeline as training. The app should use the data, not force the handler to manage a separate wearable page.</p>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="landing-card p-4">
            <div class="text-uppercase text-muted fw-semibold small mb-2">Download</div>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div style="min-width:260px; max-width:720px;">
                    <h2 class="h3 mb-2">GuidePaw Companion debug APK</h2>
                    <p class="mb-0">This is the current test build for the companion app. It is not a final Google Play release, and it may change while the Android side is still being filled out.</p>
                </div>
                <div>
                    <a class="btn btn-primary fw-bold" href="<?php echo htmlspecialchars($apkFile, ENT_QUOTES); ?>">Download APK v<?php echo htmlspecialchars($apkVersion, ENT_QUOTES); ?></a>
                </div>
            </div>
            <p class="small muted mt-3 mb-0">The companion app checks for newer releases and will prompt you to update when a new APK is available.</p>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-md-6">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Scope</div>
                <h2 class="h4">What belongs in the app</h2>
                <ul class="mb-0">
                    <li>Dogs, logs, training, and reminders</li>
                    <li>Handler profile and contact details</li>
                    <li>Public read-only breed, FAQ, and legal pages</li>
                    <li>Notifications and wearable summaries</li>
                    <li>Support status and add-ons for the normal user</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Out of scope</div>
                <h2 class="h4">What stays web-only</h2>
                <ul class="mb-0">
                    <li>Admin dashboards</li>
                    <li>Audit tools and moderation controls</li>
                    <li>Business cost pages</li>
                    <li>Feature flag management</li>
                    <li>Back office operations</li>
                </ul>
            </div>
        </div>
    </section>
</main>
</body>
</html>
