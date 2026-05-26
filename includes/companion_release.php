<?php
require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/seo.php';

if (!function_exists('gpCompanionReleaseInfo')) {
    function gpCompanionReleaseInfo(): array
    {
        // Update these alongside app/build.gradle when bumping the Android version
        $versionName = trim((string) gpEnv('GUIDEPAW_COMPANION_VERSION_NAME', '0.064'));
        $versionCode = (int) gpEnv('GUIDEPAW_COMPANION_VERSION_CODE', '64');
        $apkPath = trim((string) gpEnv('GUIDEPAW_COMPANION_APK_PATH', 'downloads/GuidePaw_Companion_v' . $versionName . '.apk'));
        $downloadUrl = guidepawSeoAbsoluteUrl($apkPath);

        return [
            'success' => true,
            'app_name' => 'GuidePaw Companion',
            'version_name' => $versionName,
            'version_code' => $versionCode,
            'apk_url' => $downloadUrl,
            'apk_file' => basename($apkPath),
            'release_notes' => 'Dog age shown on Overview and Dogs list; birthday countdown appears in the 7 days before.',
            'published_at' => gmdate('c'),
        ];
    }
}
