<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/training_helpers.php';
$user = requireApiUser($pdo);

function gpNormalizeTrainingLogRow(array $row): array
{
    $skills = [];
    if (!empty($row['skills_practiced'])) {
        $decoded = json_decode((string) $row['skills_practiced'], true);
        if (is_array($decoded)) {
            $skills = array_values(array_filter(array_map('strval', $decoded), static fn($value) => $value !== ''));
        }
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'dog_id' => (int) ($row['dog_id'] ?? 0),
        'handler_username' => (string) ($row['handler_username'] ?? ''),
        'log_date' => (string) ($row['log_date'] ?? ''),
        'location_name' => (string) ($row['location_name'] ?? ''),
        'location_city_state' => (string) ($row['location_city_state'] ?? ''),
        'location_type' => (string) ($row['location_type'] ?? ''),
        'focus_level' => (int) ($row['focus_level'] ?? 3),
        'skills_practiced' => $skills,
        'handler_notes' => (string) ($row['handler_notes'] ?? ''),
        'latitude' => array_key_exists('latitude', $row) && $row['latitude'] !== null ? (float) $row['latitude'] : null,
        'longitude' => array_key_exists('longitude', $row) && $row['longitude'] !== null ? (float) $row['longitude'] : null,
    ];
}

function gpFetchTrainingSuggestions(PDO $pdo, array $user, int $dogId): array
{
    if ($dogId <= 0) {
        return [];
    }
    return getTrainingSuggestions($pdo, (int) $user['id'], $dogId);
}

$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$allowedSkills = ['Focus / Watch me', 'Loose leash', 'Settle', 'Recall', 'Task work', 'CGC prep', 'Sit/Stay', 'Heel', 'Leave It', 'Under Tuck', 'DPT Task', 'PA Focus'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $logId = (int) ($_GET['log_id'] ?? 0);
    $requestedDogId = (int) ($_GET['dog_id'] ?? 0);
    $activeDogId = (int) ($user['active_dog_id'] ?? 0);
    $resolvedDogId = $requestedDogId > 0 ? $requestedDogId : $activeDogId;

    if ($logId > 0) {
        $stmt = $pdo->prepare('SELECT dl.*, u.username AS handler_username FROM daily_logs dl JOIN users u ON u.id = dl.user_id WHERE dl.id = ? LIMIT 1');
        $stmt->execute([$logId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            apiJson(['success' => false, 'message' => 'Log not found.'], 404);
        }
        $dogId = (int) ($row['dog_id'] ?? 0);
        if ($dogId <= 0 || !hasDogAccess($pdo, $user['id'], $dogId)) {
            apiJson(['success' => false, 'message' => 'No dog access.'], 403);
        }
        $log = gpNormalizeTrainingLogRow($row);
        apiJson([
            'success' => true,
            'active_dog_id' => $activeDogId,
            'log' => $log,
            'training_suggestions' => gpFetchTrainingSuggestions($pdo, $user, $dogId),
        ]);
    }

    if ($resolvedDogId > 0) {
        if (!hasDogAccess($pdo, $user['id'], $resolvedDogId)) {
            apiJson(['success' => false, 'message' => 'No dog access.'], 403);
        }
        $stmt = $pdo->prepare('SELECT dl.*, u.username AS handler_username FROM daily_logs dl JOIN users u ON u.id = dl.user_id WHERE dl.dog_id = ? AND (dl.user_id = ? OR EXISTS (SELECT 1 FROM dog_handlers dh WHERE dh.dog_id = dl.dog_id AND dh.user_id = ? AND dh.status = \'accepted\')) ORDER BY dl.log_date DESC LIMIT 50');
        $stmt->execute([$resolvedDogId, $user['id'], $user['id']]);
        $logs = array_map(static fn(array $row) => gpNormalizeTrainingLogRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        apiJson([
            'success' => true,
            'active_dog_id' => $activeDogId,
            'dog_id' => $resolvedDogId,
            'training_suggestions' => gpFetchTrainingSuggestions($pdo, $user, $resolvedDogId),
            'logs' => $logs,
        ]);
    }

    $stmt = $pdo->prepare('SELECT l.* FROM daily_logs l JOIN dogs d ON d.id = l.dog_id LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = \'accepted\' WHERE d.owner_user_id = ? OR dh.id IS NOT NULL ORDER BY l.log_date DESC LIMIT 50');
    $stmt->execute([$user['id'], $user['id']]);
    $logs = array_map(static fn(array $row) => gpNormalizeTrainingLogRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    apiJson([
        'success' => true,
        'active_dog_id' => $activeDogId,
        'training_suggestions' => [],
        'logs' => $logs,
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$dogId = (int)($input['dog_id'] ?? 0);
$logId = (int) ($input['id'] ?? 0);
$locationName = cleanText($input['location_name'] ?? '', 100);
if ($locationName === '') {
    apiJson(['success' => false, 'message' => 'Location name is required.'], 422);
}
$cityState = cleanText($input['location_city_state'] ?? '', 100);
$locationType = validateLocationType($input['location_type'] ?? 'Other', $allowedTypes);
$focus = validateFocusLevel($input['focus_level'] ?? 3);
$notes = cleanTextarea($input['handler_notes'] ?? '', 5000);
$skills = cleanSkills($input['skills'] ?? [], $allowedSkills);
$logDate = cleanDateTimeValue($input['log_date'] ?? '');
$latitude = validateLatitude($input['latitude'] ?? null);
$longitude = validateLongitude($input['longitude'] ?? null);

if ($logId > 0) {
    $stmt = $pdo->prepare('SELECT dog_id FROM daily_logs WHERE id = ? LIMIT 1');
    $stmt->execute([$logId]);
    $existingDogId = (int) $stmt->fetchColumn();
    if (!$existingDogId || !userCanEditDog($pdo, $user['id'], $existingDogId)) {
        apiJson(['success' => false, 'message' => 'No edit access for log.'], 403);
    }

    $stmt = $pdo->prepare('UPDATE daily_logs SET location_name = ?, log_date = COALESCE(?, log_date), location_city_state = ?, location_type = ?, focus_level = ?, skills_practiced = ?, handler_notes = ?, latitude = ?, longitude = ? WHERE id = ?');
    $stmt->execute([
        $locationName,
        $logDate,
        $cityState !== '' ? $cityState : null,
        $locationType,
        $focus,
        json_encode($skills),
        $notes,
        $latitude,
        $longitude,
        $logId,
    ]);

    apiJson([
        'success' => true,
        'message' => 'Log updated.',
        'log_id' => $logId,
        'training_suggestions' => gpFetchTrainingSuggestions($pdo, $user, $existingDogId),
    ]);
}

if (!$dogId || !userCanEditDog($pdo, $user['id'], $dogId)) {
    apiJson(['success' => false, 'message' => 'No edit access for dog.'], 403);
}

$stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, latitude, longitude, log_date) VALUES (?,?,?,?,?,?,?,?,?,?,COALESCE(?, CURRENT_TIMESTAMP)) RETURNING id');
$stmt->execute([
    $user['id'],
    $dogId,
    $locationName,
    $cityState !== '' ? $cityState : null,
    $locationType,
    $focus,
    json_encode($skills),
    $notes,
    $latitude,
    $longitude,
    $logDate,
]);
apiJson([
    'success' => true,
    'message' => 'Log created.',
    'log_id' => (int) $stmt->fetchColumn(),
    'training_suggestions' => gpFetchTrainingSuggestions($pdo, $user, $dogId),
]);
