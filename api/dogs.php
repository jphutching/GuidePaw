<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user = requireApiUser($pdo);
$dogs = getAccessibleDogs($pdo, $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/validation.php';
    require_once __DIR__ . '/../includes/paywall_catalog.php';
    $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    if ($action === 'set_active_dog') {
        $dogId = (int) ($input['dog_id'] ?? 0);
        if ($dogId <= 0) {
            apiJson(['success' => false, 'message' => 'Dog ID is required.'], 422);
        }
        if (!apiSetActiveDogId($pdo, (int) ($user['token_id'] ?? 0), $user['id'], $dogId)) {
            apiJson(['success' => false, 'message' => 'No access to that dog or dog is not active.'], 403);
        }
        apiJson([
            'success'       => true,
            'message'       => 'Active dog updated.',
            'active_dog_id' => $dogId,
            'dogs'          => getAccessibleDogs($pdo, $user['id']),
        ]);
    }

    if ($action === 'create_dog') {
        if (!gpUserCanCreateAnotherDog($pdo, (int) $user['id'])) {
            apiJson(['success' => false, 'message' => 'Dog limit reached for your current plan. Upgrade to add more dogs.'], 403);
        }
        $name  = cleanText((string) ($input['name'] ?? ''), 80);
        if ($name === '') {
            apiJson(['success' => false, 'message' => 'Dog name is required.'], 422);
        }
        $breed      = cleanText((string) ($input['breed'] ?? ''), 120);
        $chipNumber = cleanText((string) ($input['chip_number'] ?? ''), 80);
        $weightRaw  = trim((string) ($input['weight_lbs'] ?? ''));
        $weightLbs  = ($weightRaw !== '') ? (float) $weightRaw : null;
        $dobRaw     = trim((string) ($input['date_of_birth'] ?? ''));
        $dob        = ($dobRaw !== '' && ($ts = strtotime($dobRaw)) !== false) ? date('Y-m-d', $ts) : null;
        $notes      = cleanTextarea((string) ($input['notes'] ?? ''), 1200);

        $newDogId = insertAndGetId($pdo, 'INSERT INTO dogs (owner_user_id, name, breed, chip_number, weight_lbs, date_of_birth, notes) VALUES (?,?,?,?,?,?,?)', [(int) $user['id'], $name, $breed ?: null, $chipNumber ?: null, $weightLbs, $dob, $notes ?: null]);
        apiSetActiveDogId($pdo, (int) ($user['token_id'] ?? 0), $user['id'], $newDogId);
        apiJson([
            'success'       => true,
            'message'       => "$name added.",
            'dog_id'        => $newDogId,
            'active_dog_id' => $newDogId,
            'dogs'          => getAccessibleDogs($pdo, $user['id']),
        ]);
    }

    apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
}

apiJson([
    'success' => true,
    'active_dog_id' => apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0)),
    'dogs' => $dogs,
]);
