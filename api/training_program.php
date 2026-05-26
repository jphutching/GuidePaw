<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];
$dogId  = isset($user['active_dog_id']) ? (int) $user['active_dog_id'] : 0;

function gpApiTrainingResolveDog(PDO $pdo, int $userId, int $dogId): ?array
{
    $stmt = $pdo->prepare("SELECT id, name FROM dogs WHERE id = ? AND owner_user_id = ?");
    $stmt->execute([$dogId, $userId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dog) return $dog;

    $stmt = $pdo->prepare("
        SELECT d.id, d.name FROM dogs d
        JOIN dog_handlers dh ON dh.dog_id = d.id
        WHERE d.id = ? AND dh.user_id = ? AND dh.status = 'accepted'
        LIMIT 1
    ");
    $stmt->execute([$dogId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function gpApiNormalizeTrainingItem(array $row): array
{
    return [
        'id'            => (int) $row['id'],
        'category'      => (string) ($row['category'] ?? ''),
        'track_code'    => (string) ($row['track_code'] ?? ''),
        'level'         => (int) ($row['level'] ?? 1),
        'item_name'     => (string) ($row['item_name'] ?? ''),
        'description'   => (string) ($row['description'] ?? ''),
        'status'        => (string) ($row['status'] ?? 'not_started'),
        'last_worked_at' => (string) ($row['last_worked_at'] ?? ''),
        'notes'         => (string) ($row['notes'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($dogId <= 0) {
        apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
    }
    $dog = gpApiTrainingResolveDog($pdo, $userId, $dogId);
    if (!$dog) {
        apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
    }

    $items    = getDogTrainingItems($pdo, $dogId);
    $total    = count($items);
    $mastered = count(array_filter($items, static fn($i) => ($i['status'] ?? '') === 'mastered'));
    $inProgress = count(array_filter($items, static fn($i) => ($i['status'] ?? '') === 'in_progress'));
    $proofing = count(array_filter($items, static fn($i) => ($i['status'] ?? '') === 'proofing'));

    apiJson([
        'success'     => true,
        'dog_name'    => (string) $dog['name'],
        'total'       => $total,
        'mastered'    => $mastered,
        'in_progress' => $inProgress,
        'proofing'    => $proofing,
        'items'       => array_map('gpApiNormalizeTrainingItem', $items),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? '');

if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
}

$stmt = $pdo->prepare("SELECT id FROM dogs WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$dogId, $userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'You do not have write access for this dog.'], 403);
}

if ($action === 'seed') {
    $added = seedDogTrainingProgram($pdo, $dogId);
    apiJson(['success' => true, 'added' => $added, 'message' => $added > 0 ? "Added $added items." : 'Training ladder already loaded.']);
}

if ($action === 'update_status') {
    $itemId = (int) ($input['item_id'] ?? 0);
    $status = (string) ($input['status'] ?? '');
    if ($itemId <= 0) {
        apiJson(['success' => false, 'message' => 'item_id is required.'], 422);
    }
    if (!in_array($status, ['not_started', 'in_progress', 'proofing', 'mastered'], true)) {
        apiJson(['success' => false, 'message' => 'Invalid status.'], 422);
    }
    $pdo->prepare("
        UPDATE dog_training_items
        SET status = ?, last_worked_at = CURRENT_TIMESTAMP
        WHERE id = ? AND dog_id = ?
    ")->execute([$status, $itemId, $dogId]);
    apiJson(['success' => true]);
}

apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
