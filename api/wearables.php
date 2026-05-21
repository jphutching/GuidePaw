<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/wearable_integrations.php';
require_once __DIR__ . '/../includes/validation.php';

$user = requireApiUser($pdo);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function gpWearableApiDogId(PDO $pdo, int $userId, ?int $requestedDogId = null): int
{
    if ($requestedDogId !== null && $requestedDogId > 0) {
        if (!hasDogAccess($pdo, $userId, $requestedDogId)) {
            apiJson(['success' => false, 'message' => 'No access to that dog.'], 403);
        }
        return $requestedDogId;
    }

    $activeDogId = getActiveDogId($pdo, $userId);
    if ($activeDogId !== null && $activeDogId > 0 && hasDogAccess($pdo, $userId, $activeDogId)) {
        return (int) $activeDogId;
    }

    apiJson(['success' => false, 'message' => 'Choose a dog before syncing wearable data.'], 422);
}

if ($method === 'GET') {
    $dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : null;
    $resolvedDogId = gpWearableApiDogId($pdo, $user['id'], $dogId);
    $events = gpWearableRecentEvents($pdo, $user['id'], $resolvedDogId, 12);
    $setup = gpWearableCurrentSetup($pdo, $user['id'], $resolvedDogId);

    apiJson([
        'success' => true,
        'user' => $user,
        'dog_id' => $resolvedDogId,
        'setup' => $setup,
        'summary' => gpWearableTrendSummary($events),
        'events' => $events,
    ]);
}

if ($method !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$normalized = gpWearableNormalizeRecordInput($input, 'health_connect');
$resolvedDogId = gpWearableApiDogId($pdo, $user['id'], !empty($normalized['dog_id']) ? (int) $normalized['dog_id'] : null);
$normalized['dog_id'] = $resolvedDogId;
$normalized['raw_payload'] = $normalized['raw_payload'] ?? json_encode($input, JSON_UNESCAPED_SLASHES);

$eventId = gpWearableRecordEvent($pdo, $user['id'], $normalized);
writeAuditLog($pdo, 'wearable_sync_recorded', 'wearable_sync_events', $eventId, 'Wearable API snapshot recorded.');

$events = gpWearableRecentEvents($pdo, $user['id'], $resolvedDogId, 5);
apiJson([
    'success' => true,
    'message' => 'Wearable snapshot recorded.',
    'event_id' => $eventId,
    'dog_id' => $resolvedDogId,
    'event' => $events[0] ?? null,
    'setup' => gpWearableCurrentSetup($pdo, $user['id'], $resolvedDogId),
    'summary' => gpWearableTrendSummary($events),
]);
