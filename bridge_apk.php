<?php
require_once __DIR__ . '/includes/brand_header.php';

$apkCandidates = [
    __DIR__ . '/bridge/GuidePaw-Companion-debug.apk',
    __DIR__ . '/android/guidepaw-bridge/app/build/outputs/apk/debug/app-debug.apk',
    __DIR__ . '/android/guidepaw-bridge/app/build/intermediates/apk/debug/app-debug.apk',
    __DIR__ . '/bridge/GuidePaw-Bridge-debug.apk',
];
$apkPath = '';
foreach ($apkCandidates as $candidate) {
    if (is_file($candidate)) {
        $apkPath = $candidate;
        break;
    }
}

if ($apkPath !== '' && isset($_GET['download'])) {
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="GuidePaw-Companion-debug.apk"');
    header('Content-Length: ' . filesize($apkPath));
    readfile($apkPath);
    exit;
}

http_response_code(200);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GuidePaw Companion APK</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #d8dee4; border-radius: 12px; padding: 18px; }
        .small { color: #5b6472; font-size: 14px; }
        a.button { display: inline-block; padding: 10px 14px; border-radius: 10px; background: #0d6efd; color: #fff; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<div class="wrap">
    <div class="card">
        <h1>GuidePaw Companion APK</h1>
        <p class="small">This is the full Android companion build for GuidePaw. It includes account, dogs, logs, public guide viewing, notifications, billing, profile tools, and wearable sync.</p>
        <p class="small"><strong>Disclaimer:</strong> this build is not a final Google Play release. It may or may not work properly yet, and it is meant for testing only.</p>
        <p class="small">
            The published APK file is served from
            <a href="/bridge/GuidePaw-Companion-debug.apk">/bridge/GuidePaw-Companion-debug.apk</a>
            on the site. In the repo, the latest debug output is under
            <code>android/guidepaw-bridge/app/build/outputs/apk/debug/app-debug.apk</code>.
        </p>
        <p class="small">
            Prerequisites on the phone:
            <a class="button" style="margin-right:8px;" href="https://play.google.com/store/apps/details?id=com.sec.android.app.shealth" target="_blank" rel="noopener noreferrer">Samsung Health</a>
            <a class="button" href="https://play.google.com/store/apps/details?id=com.google.android.apps.healthdata" target="_blank" rel="noopener noreferrer">Health Connect</a>
        </p>
        <?php if ($apkPath !== ''): ?>
            <p><a class="button" href="bridge_apk.php?download=1">Download Companion APK</a></p>
        <?php else: ?>
            <p class="small">The APK is not published on this server yet. Once it is available, use the download button above to install it on the phone you are connecting.</p>
        <?php endif; ?>
        <p><a class="button" href="wearable_integrations.php">Back to wearable setup</a></p>
    </div>
</div>
</body>
</html>
