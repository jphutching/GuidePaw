<?php
require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/seo.php';

if (!function_exists('gpCompanionReleaseInfo')) {
    function gpCompanionReleaseInfo(): array
    {
        $versionName = trim((string) gpEnv('GUIDEPAW_COMPANION_VERSION_NAME', '0.044'));
        $versionCode = (int) gpEnv('GUIDEPAW_COMPANION_VERSION_CODE', '44');
        $apkPath = trim((string) gpEnv('GUIDEPAW_COMPANION_APK_PATH', 'downloads/GuidePaw_Companion_v' . $versionName . '.apk'));
        $downloadUrl = guidepawSeoAbsoluteUrl($apkPath);

        return [
            'success' => true,
            'app_name' => 'GuidePaw Companion',
            'version_name' => $versionName,
            'version_code' => $versionCode,
            'apk_url' => $downloadUrl,
            'apk_file' => basename($apkPath),
            'release_notes' => 'Native Health & Docs screen — vet contacts with tappable phone numbers, add vet form, document list (type/title/provider/open file), upload prompt to web; new api/health_docs.php; added to Care menu.',
            'published_at' => gmdate('c'),
        ];
    }
}
