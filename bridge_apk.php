<?php
require_once __DIR__ . '/includes/brand_header.php';

$apkPath = __DIR__ . '/android/guidepaw-bridge/app/build/outputs/apk/debug/app-debug.apk';
if (is_file($apkPath) && isset($_GET['download'])) {
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="GuidePaw-Bridge-debug.apk"');
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
    <title>GuidePaw Bridge APK</title>
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
        <h1>GuidePaw Bridge APK</h1>
        <p class="small">The Android bridge APK is ready to download here once the debug build exists on this machine. If the file has not been built yet, open <code>android/guidepaw-bridge</code> in Android Studio and build the debug APK first.</p>
        <p class="small">This is the phone companion that reads Health Connect and syncs wearable summaries back to GuidePaw.</p>
        <?php if (is_file($apkPath)): ?>
            <p><a class="button" href="bridge_apk.php?download=1">Download Bridge APK</a></p>
        <?php else: ?>
            <p class="small">No APK file is present yet. Open <code>android/guidepaw-bridge</code> in Android Studio and build the debug APK, then come back here to download it.</p>
        <?php endif; ?>
        <p><a class="button" href="wearable_integrations.php">Back to wearable setup</a></p>
    </div>
</div>
</body>
</html>
