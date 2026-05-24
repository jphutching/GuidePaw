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
                <div class="small text-uppercase text-muted fw-semibold">What it is</div>
                <h2 class="h5 mb-2">Native Android — no browser wrappers</h2>
                <p class="muted mb-3">All screens are pure Compose. Training tools, risk assessment, regression tracking, and candidate scoring are fully native — no web views for handler work.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Start with breed research</a>
                    <a class="btn btn-outline-primary fw-bold" href="<?php echo htmlspecialchars($apkFile, ENT_QUOTES); ?>">Download debug APK v<?php echo htmlspecialchars($apkVersion, ENT_QUOTES); ?></a>
                    <a class="btn btn-outline-primary fw-bold" href="training_program.php">See training tools</a>
                </div>
                <p class="small mt-3 mb-0 text-secondary">Because this is a sideloaded test build, Android or your browser may warn that the APK is not from a verified store. That warning is expected until we publish a signed release build or Play Store release.</p>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">What's in the app</div>
                <h2 class="h3">Full handler workflow — native</h2>
                <p>All five training-tool screens are native Compose. Pull down to refresh any list.</p>
                <ul class="checklist mb-0">
                    <li>Training logs — submit and review session history</li>
                    <li>Goal Intake — define training goals by category with success criteria and reinforcement plan</li>
                    <li>Habit Repair — protocol lookup and behavior incident log</li>
                    <li>Behavior Risk — risk band assessment with per-dog scoring</li>
                    <li>Regression Engine — open event tracking with inline status updates and reset plan editor</li>
                    <li>Candidate Assessment — 10-factor scoring tool for prospect dogs</li>
                    <li>Dogs — switch active dog, view profiles and public notes</li>
                    <li>Wearable summaries and device setup</li>
                    <li>Notifications and dog-access invite management</li>
                    <li>Feedback submission and in-app update checks</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Wearables baked in</div>
                <h2 class="h4">One device layer, not a separate bridge</h2>
                <p class="mb-0">Health Connect, tracker imports, and vendor feeds roll into the same timeline as training. The app uses the data alongside logs — the handler does not manage a separate wearable page.</p>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="panel p-4">
            <div class="text-uppercase text-muted fw-semibold small mb-2">Download</div>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div style="min-width:260px; max-width:720px;">
                    <h2 class="h3 mb-2">GuidePaw Companion debug APK</h2>
                    <p class="mb-0">This is the current test build. It is not a Google Play release — sideloading is required. The app is fully native Compose: training logs, all five training-tool screens, wearables, notifications, and feedback — no XML layouts and no web views.</p>
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
