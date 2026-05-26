<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : 0;
if (!$dogId) {
    $dogId = apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0));
}
if (!$dogId) {
    apiJson(['success' => false, 'message' => 'No active dog.'], 422);
}

$stmt = $pdo->prepare('SELECT * FROM dogs WHERE id = ? AND owner_user_id = ? LIMIT 1');
$stmt->execute([$dogId, $userId]);
$dog = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dog) {
    apiJson(['success' => false, 'message' => 'Dog not found or not accessible.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    apiJson([
        'success'            => true,
        'dog' => [
            'id'                  => (int) $dog['id'],
            'name'                => (string) ($dog['name'] ?? ''),
            'breed'               => (string) ($dog['breed'] ?? ''),
            'chip_number'         => (string) ($dog['chip_number'] ?? ''),
            'weight_lbs'          => $dog['weight_lbs'] !== null ? (float) $dog['weight_lbs'] : null,
            'date_of_birth'       => (string) ($dog['date_of_birth'] ?? ''),
            'birth_is_approximate'=> (bool) ($dog['birth_is_approximate'] ?? false),
            'notes'               => (string) ($dog['notes'] ?? ''),
            'lifecycle_status'    => (string) ($dog['lifecycle_status'] ?? 'active'),
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$name = cleanText((string) ($input['name'] ?? ''), 80);
if ($name === '') {
    apiJson(['success' => false, 'message' => 'Name is required.'], 422);
}

$breed      = cleanText((string) ($input['breed'] ?? ''), 120);
$chipNumber = cleanText((string) ($input['chip_number'] ?? ''), 80);
$weightRaw  = trim((string) ($input['weight_lbs'] ?? ''));
$weightLbs  = ($weightRaw !== '') ? (float) $weightRaw : null;
$dobRaw     = trim((string) ($input['date_of_birth'] ?? ''));
$dob        = ($dobRaw !== '' && ($ts = strtotime($dobRaw)) !== false) ? date('Y-m-d', $ts) : null;
$birthApprox = !empty($input['birth_is_approximate']) ? 1 : 0;
$notes      = cleanTextarea((string) ($input['notes'] ?? ''), 1200);

$pdo->prepare('UPDATE dogs SET name=?, breed=?, chip_number=?, weight_lbs=?, date_of_birth=?, birth_is_approximate=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND owner_user_id=?')
    ->execute([$name, $breed ?: null, $chipNumber ?: null, $weightLbs, $dob, $birthApprox, $notes ?: null, $dogId, $userId]);

apiJson(['success' => true, 'message' => 'Dog profile updated.']);
