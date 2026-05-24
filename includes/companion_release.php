<?php
require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/seo.php';

if (!function_exists('gpCompanionReleaseInfo')) {
    function gpCompanionReleaseInfo(): array
    {
        $versionName = trim((string) gpEnv('GUIDEPAW_COMPANION_VERSION_NAME', '0.035'));
        $versionCode = (int) gpEnv('GUIDEPAW_COMPANION_VERSION_CODE', '35');
        $apkPath = trim((string) gpEnv('GUIDEPAW_COMPANION_APK_PATH', 'downloads/GuidePaw_Companion_v' . $versionName . '.apk'));
        $downloadUrl = guidepawSeoAbsoluteUrl($apkPath);

        return [
            'success' => true,
            'app_name' => 'GuidePaw Companion',
            'version_name' => $versionName,
            'version_code' => $versionCode,
            'apk_url' => $downloadUrl,
            'apk_file' => basename($apkPath),
            'release_notes' => 'Pull-to-refresh on Overview screen — pull down triggers the same full dashboard refresh as the Refresh button.',
            'published_at' => gmdate('c'),
        ];
    }
}
