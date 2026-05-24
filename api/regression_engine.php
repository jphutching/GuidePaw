<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/regression_engine.php';
require_once __DIR__ . '/../includes/validation.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

function gpApiNormalizeRegressionEvent(array $row): array
{
    return [
        'id'                 => (int) $row['id'],
        'status'             => (string) ($row['status'] ?? 'open'),
        'detected_reason'    => (string) ($row['detected_reason'] ?? ''),
        'recommended_action' => (string) ($row['recommended_action'] ?? ''),
        'module_title'       => (string) ($row['module_title'] ?? ''),
        'goal_category'      => (string) ($row['goal_category'] ?? ''),
        'created_at'         => (string) ($row['created_at'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dog = requireActiveDog($pdo, $userId);
    $dogId = (int) $dog['id'];
    $events = gpRegressionEngineOpenEvents($pdo, $userId, $dogId, 20);
    $openCount = gpRegressionEngineOpenCount($pdo, $userId, $dogId);
    apiJson([
        'success'    => true,
        'dog_id'     => $dogId,
        'dog_name'   => (string) ($dog['name'] ?? ''),
        'open_count' => $openCount,
        'events'     => array_map('gpApiNormalizeRegressionEvent', $events),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? '');

if ($action !== 'update_event') {
    apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
}

$dog   = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];

if (!userCanEditDog($pdo, $userId, $dogId)) {
    apiJson(['success' => false, 'message' => 'You have read-only access for this dog.'], 403);
}

if (!gpRegressionEngineTableReady($pdo)) {
    apiJson(['success' => false, 'message' => 'Regression engine storage has not been deployed yet.'], 503);
}

$eventId = (int) ($input['event_id'] ?? 0);
if ($eventId <= 0) {
    apiJson(['success' => false, 'message' => 'event_id is required.'], 422);
}

gpRegressionEngineUpdateEvent($pdo, $userId, $dogId, $eventId, [
    'status'             => $input['status'] ?? 'open',
    'recommended_action' => $input['recommended_action'] ?? '',
]);

$events    = gpRegressionEngineOpenEvents($pdo, $userId, $dogId, 20);
$openCount = gpRegressionEngineOpenCount($pdo, $userId, $dogId);
apiJson([
    'success'    => true,
    'message'    => 'Regression event updated.',
    'dog_id'     => $dogId,
    'dog_name'   => (string) ($dog['name'] ?? ''),
    'open_count' => $openCount,
    'events'     => array_map('gpApiNormalizeRegressionEvent', $events),
]);
