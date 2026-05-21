<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/wearable_integrations.php';

$user = requireApiUser($pdo);

if (!featureEnabled($pdo, 'wearable_integrations_enabled')) {
    apiJson(['success' => false, 'message' => 'Wearable integrations are not enabled yet.'], 403);
}

$dogs = getAccessibleDogs($pdo, $user['id']);
$dogIds = array_map(static fn(array $dog): int => (int) ($dog['id'] ?? 0), $dogs);
$requestedDogId = (int) (($_GET['dog_id'] ?? $_POST['dog_id'] ?? 0));
$activeDogId = apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0));
if ($requestedDogId <= 0) {
    $requestedDogId = $activeDogId ?: (int) ($dogs[0]['id'] ?? 0);
}
if ($requestedDogId > 0 && !in_array($requestedDogId, $dogIds, true)) {
    $requestedDogId = $activeDogId ?: (int) ($dogs[0]['id'] ?? 0);
}

function gpWearableApiCatalogPayload(array $rows): array
{
    return array_values(array_map(static function (array $row): array {
        return [
            'slug' => (string) ($row['slug'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'vendor' => (string) ($row['vendor'] ?? ''),
            'pairing_mode' => (string) ($row['pairing_mode'] ?? ''),
            'data_focus' => (string) ($row['data_focus'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'device_type' => (string) ($row['device_type'] ?? ''),
        ];
    }, $rows));
}

function gpWearableApiEventPayload(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'dog_id' => isset($row['dog_id']) ? (int) $row['dog_id'] : null,
        'dog_name' => (string) ($row['dog_name'] ?? ''),
        'source' => (string) ($row['source'] ?? ''),
        'device_name' => (string) ($row['device_name'] ?? ''),
        'recorded_for_date' => (string) ($row['recorded_for_date'] ?? ''),
        'steps' => isset($row['steps']) ? (int) $row['steps'] : null,
        'active_minutes' => isset($row['active_minutes']) ? (int) $row['active_minutes'] : null,
        'rest_minutes' => isset($row['rest_minutes']) ? (int) $row['rest_minutes'] : null,
        'play_minutes' => isset($row['play_minutes']) ? (int) $row['play_minutes'] : null,
        'distance_miles' => isset($row['distance_miles']) ? (float) $row['distance_miles'] : null,
        'total_calories_burned' => isset($row['total_calories_burned']) ? (float) $row['total_calories_burned'] : null,
        'activity_intensity_minutes' => isset($row['activity_intensity_minutes']) ? (int) $row['activity_intensity_minutes'] : null,
        'avg_heart_rate' => isset($row['avg_heart_rate']) ? (int) $row['avg_heart_rate'] : null,
        'resting_heart_rate' => isset($row['resting_heart_rate']) ? (int) $row['resting_heart_rate'] : null,
        'sleep_hours' => isset($row['sleep_hours']) ? (float) $row['sleep_hours'] : null,
        'battery_percent' => isset($row['battery_percent']) ? (int) $row['battery_percent'] : null,
        'summary_text' => (string) ($row['summary_text'] ?? ''),
        'notes' => (string) ($row['notes'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = strtolower(trim((string) ($input['action'] ?? 'save_setup')));
    if ($action === 'set_active_dog') {
        $dogId = (int) ($input['dog_id'] ?? 0);
        if ($dogId <= 0 || !in_array($dogId, $dogIds, true)) {
            apiJson(['success' => false, 'message' => 'Dog ID is required.'], 422);
        }
        if (!apiSetActiveDogId($pdo, (int) ($user['token_id'] ?? 0), $user['id'], $dogId)) {
            apiJson(['success' => false, 'message' => 'No access to that dog or dog is not active.'], 403);
        }
        apiJson(['success' => true, 'message' => 'Active dog updated.', 'active_dog_id' => $dogId]);
    }

    if ($requestedDogId <= 0) {
        apiJson(['success' => false, 'message' => 'Pick a dog first.'], 422);
    }

    if ($action === 'save_setup') {
        gpWearableSaveSetup($pdo, $user['id'], $requestedDogId, [
            'handler_wearable_slug' => (string) ($input['handler_wearable_slug'] ?? ''),
            'dog_tracker_slug' => (string) ($input['dog_tracker_slug'] ?? ''),
            'sync_mode' => (string) ($input['sync_mode'] ?? ''),
            'notes' => (string) ($input['notes'] ?? ''),
        ]);
        writeAuditLog($pdo, 'wearable_setup_saved', 'user_wearable_device_setup', $requestedDogId, 'Wearable setup saved from mobile app.');
        apiJson(['success' => true, 'message' => 'Wearable setup saved.']);
    }

    if ($action === 'save_snapshot') {
        $parsed = gpWearableNormalizeRecordInput(array_merge($input, ['dog_id' => $requestedDogId]), 'manual');
        $eventId = gpWearableRecordEvent($pdo, $user['id'], $parsed);
        writeAuditLog($pdo, 'wearable_sync_recorded', 'wearable_sync_events', $eventId, 'Wearable snapshot recorded from mobile app.');
        apiJson(['success' => true, 'message' => 'Wearable snapshot saved.', 'event_id' => $eventId]);
    }

    apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
}

$catalog = gpWearableCatalogEntries($pdo);
$handlerWearables = array_values(array_filter($catalog, static fn(array $row): bool => ($row['device_type'] ?? '') === 'handler_wearable'));
$dogTrackers = array_values(array_filter($catalog, static fn(array $row): bool => ($row['device_type'] ?? '') === 'dog_tracker'));
$syncModes = gpWearableSyncModeOptions();
$currentSetup = $requestedDogId > 0 ? gpWearableCurrentSetup($pdo, $user['id'], $requestedDogId) : null;
$events = gpWearableRecentEvents($pdo, $user['id'], $requestedDogId > 0 ? $requestedDogId : null, 12);

apiJson([
    'success' => true,
    'active_dog_id' => $activeDogId ?: null,
    'dog_id' => $requestedDogId ?: null,
    'dogs' => $dogs,
    'current_setup' => $currentSetup,
    'summary' => gpWearableTrendSummary($events),
    'handler_wearables' => gpWearableApiCatalogPayload($handlerWearables),
    'dog_trackers' => gpWearableApiCatalogPayload($dogTrackers),
    'sync_modes' => array_map(static fn(array $mode): array => ['label' => (string) ($mode['label'] ?? ''), 'notes' => (string) ($mode['notes'] ?? '')], $syncModes),
    'recent_events' => array_map('gpWearableApiEventPayload', $events),
]);
