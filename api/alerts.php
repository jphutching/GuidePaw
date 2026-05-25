<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user  = requireApiUser($pdo);
$dogId = (int) ($user['active_dog_id'] ?? 0);

if ($dogId <= 0) {
    apiJson(['success' => true, 'dog_id' => null, 'alerts' => []]);
}

require_once __DIR__ . '/../includes/dog_care_helpers.php';

$alerts = getDogAlertItems($pdo, (int) $user['id'], $dogId);

// Make action_url absolute for native clients
foreach ($alerts as &$alert) {
    $base = rtrim((string) appEnv('APP_URL', 'https://guidepaw.app'), '/');
    $alert['action_url'] = $base . '/' . ltrim((string) ($alert['action_url'] ?? ''), '/');
}
unset($alert);

apiJson([
    'success' => true,
    'dog_id'  => $dogId,
    'alerts'  => array_values($alerts),
]);
