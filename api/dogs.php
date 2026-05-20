<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user = requireApiUser($pdo);
$dogs = getAccessibleDogs($pdo, $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if ($action !== 'set_active_dog') {
        apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
    }
    $dogId = (int) ($input['dog_id'] ?? 0);
    if ($dogId <= 0) {
        apiJson(['success' => false, 'message' => 'Dog ID is required.'], 422);
    }
    if (!apiSetActiveDogId($pdo, (int) ($user['token_id'] ?? 0), $user['id'], $dogId)) {
        apiJson(['success' => false, 'message' => 'No access to that dog or dog is not active.'], 403);
    }
    apiJson([
        'success' => true,
        'message' => 'Active dog updated.',
        'active_dog_id' => $dogId,
        'dogs' => $dogs,
    ]);
}

apiJson([
    'success' => true,
    'active_dog_id' => apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0)),
    'dogs' => $dogs,
]);
