<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/public_dog_profile_token.php';
require_once __DIR__ . '/../includes/found_dog_reports.php';
require_once __DIR__ . '/../includes/support_badges.php';

$user = requireApiUser($pdo);
$dogId = (int) ($_GET['dog_id'] ?? 0);
if ($dogId <= 0) {
    $dogId = (int) ($user['active_dog_id'] ?? 0);
}
if ($dogId <= 0 || !hasDogAccess($pdo, $user['id'], $dogId)) {
    apiJson(['success' => false, 'message' => 'No dog access.'], 403);
}

$dog = gpFoundDogFetchPublicDog($pdo, $dogId);
if (!$dog) {
    apiJson(['success' => false, 'message' => 'Dog not found.'], 404);
}

$publicUrl = publicDogProfileUrl($dogId);
$reportToken = publicDogProfileToken($dogId);
$reportUrl = rtrim((string) gpEnv('APP_URL', 'https://guidepaw.app'), '/') . '/report_found_dog.php?dog=' . $dogId . '&token=' . rawurlencode($reportToken);
$reportApiUrl = rtrim((string) gpEnv('APP_URL', 'https://guidepaw.app'), '/') . '/api/found_dog_reports.php';
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($publicUrl);
$publicContact = $dog['_public_contact_defaults'] ?? [];
$supportBadge = gpSupportBadgeForUser($pdo, $dog['_owner_public_contact'] ?? []);

apiJson([
    'success' => true,
    'dog_id' => $dogId,
    'public_url' => $publicUrl,
    'qr_url' => $qrUrl,
    'report_url' => $reportUrl,
    'report_api_url' => $reportApiUrl,
    'report_token' => $reportToken,
    'dog' => [
        'name' => (string) ($dog['name'] ?? 'Service Dog'),
        'breed' => (string) ($dog['breed'] ?? ''),
        'access_role' => (string) ($dog['access_role'] ?? ''),
        'support_badge' => $supportBadge,
        'handler_name' => (string) ($publicContact['handler_name'] ?? ''),
        'handler_phone' => (string) ($publicContact['handler_phone'] ?? ''),
        'handler_email' => (string) ($publicContact['handler_email'] ?? ''),
        'backup_contact_name' => (string) ($publicContact['backup_contact_name'] ?? ''),
        'backup_contact_phone' => (string) ($publicContact['backup_contact_phone'] ?? ''),
        'home_state' => (string) ($publicContact['home_state'] ?? ''),
        'public_notes' => (string) ($publicContact['public_notes'] ?? ''),
        'found_dog_instructions' => (string) ($dog['found_dog_instructions'] ?? ''),
        'critical_allergies' => (string) ($dog['critical_allergies'] ?? ($dog['medical_alert_notes'] ?? '')),
        'service_tasks' => (string) ($dog['service_tasks'] ?? ''),
        'profile_photo_url' => (string) ($dog['profile_photo_url'] ?? ($dog['photo_url'] ?? '')),
    ],
]);
