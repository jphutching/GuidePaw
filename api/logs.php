<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';
$user = requireApiUser($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dogId = (int)($_GET['dog_id'] ?? 0);
    if ($dogId && !hasDogAccess($pdo, $user['id'], $dogId)) {
        apiJson(['success' => false, 'message' => 'No dog access.'], 403);
    }
    if ($dogId) {
        $stmt = $pdo->prepare('SELECT * FROM daily_logs WHERE dog_id = ? ORDER BY log_date DESC LIMIT 50');
        $stmt->execute([$dogId]);
    } else {
        $stmt = $pdo->prepare('SELECT l.* FROM daily_logs l JOIN dogs d ON d.id = l.dog_id LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = \'accepted\' WHERE d.owner_user_id = ? OR dh.id IS NOT NULL ORDER BY l.log_date DESC LIMIT 50');
        $stmt->execute([$user['id'], $user['id']]);
    }
    apiJson(['success' => true, 'logs' => $stmt->fetchAll() ?: []]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$dogId = (int)($input['dog_id'] ?? 0);
if (!$dogId || !userCanEditDog($pdo, $user['id'], $dogId)) {
    apiJson(['success' => false, 'message' => 'No edit access for dog.'], 403);
}
$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$locationName = cleanText($input['location_name'] ?? '', 100);
if ($locationName === '') {
    apiJson(['success' => false, 'message' => 'Location name is required.'], 422);
}
$cityState = cleanText($input['location_city_state'] ?? '', 100);
$locationType = validateLocationType($input['location_type'] ?? 'Other', $allowedTypes);
$focus = validateFocusLevel($input['focus_level'] ?? 3);
$notes = cleanTextarea($input['handler_notes'] ?? '', 5000);
$skills = is_array($input['skills'] ?? null) ? array_values(array_unique(array_map('strval', $input['skills']))) : [];
$stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, log_date) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
$stmt->execute([$user['id'], $dogId, $locationName, $cityState, $locationType, $focus, json_encode($skills), $notes]);
apiJson(['success' => true, 'message' => 'Log created.']);
