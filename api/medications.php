<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/dog_care_helpers.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];
$dogId  = isset($user['active_dog_id']) ? (int) $user['active_dog_id'] : 0;

function gpApiNormalizeMedication(array $row): array
{
    return [
        'id'                   => (int) $row['id'],
        'medication_name'      => (string) ($row['medication_name'] ?? ''),
        'dosage'               => (string) ($row['dosage'] ?? ''),
        'status'               => (string) ($row['status'] ?? 'active'),
        'schedule_text'        => (string) ($row['schedule_text'] ?? ''),
        'refill_date'          => (string) ($row['refill_date'] ?? ''),
        'prescribing_provider' => (string) ($row['prescribing_provider'] ?? ''),
        'instructions'         => (string) ($row['instructions'] ?? ''),
        'notes'                => (string) ($row['notes'] ?? ''),
        'created_at'           => (string) ($row['created_at'] ?? ''),
    ];
}

function gpMedsResolveDog(PDO $pdo, int $userId, int $dogId): ?array
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($dogId <= 0) {
        apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
    }
    $dog = gpMedsResolveDog($pdo, $userId, $dogId);
    if (!$dog) {
        apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
    }
    $medications = getDogMedications($pdo, $dogId);
    apiJson([
        'success'     => true,
        'dog_name'    => (string) $dog['name'],
        'medications' => array_map('gpApiNormalizeMedication', $medications),
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

// Write access requires ownership
$stmt = $pdo->prepare("SELECT id FROM dogs WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$dogId, $userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'You do not have write access for this dog.'], 403);
}

if ($action === 'add_medication') {
    $name = trim((string) ($input['medication_name'] ?? ''));
    if ($name === '') {
        apiJson(['success' => false, 'message' => 'Medication name is required.'], 422);
    }
    $dosage      = trim((string) ($input['dosage'] ?? ''));
    $schedule    = trim((string) ($input['schedule_text'] ?? ''));
    $status      = (string) ($input['status'] ?? 'active');
    if (!in_array($status, ['active', 'paused', 'completed'], true)) {
        $status = 'active';
    }
    $refillDate  = trim((string) ($input['refill_date'] ?? ''));
    $provider    = trim((string) ($input['prescribing_provider'] ?? ''));
    $instructions = trim((string) ($input['instructions'] ?? ''));
    $notes       = trim((string) ($input['notes'] ?? ''));

    $stmt = $pdo->prepare("
        INSERT INTO dog_medications
            (dog_id, created_by_user_id, medication_name, dosage, schedule_text,
             status, refill_date, prescribing_provider, instructions, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $dogId, $userId,
        $name,
        $dosage ?: null,
        $schedule ?: null,
        $status,
        $refillDate ?: null,
        $provider ?: null,
        $instructions ?: null,
        $notes ?: null,
    ]);
    apiJson(['success' => true, 'message' => 'Medication saved.']);
}

if ($action === 'set_status') {
    $medId  = (int) ($input['med_id'] ?? 0);
    $status = (string) ($input['status'] ?? '');
    if ($medId <= 0) {
        apiJson(['success' => false, 'message' => 'med_id is required.'], 422);
    }
    if (!in_array($status, ['active', 'paused', 'completed'], true)) {
        apiJson(['success' => false, 'message' => 'Invalid status.'], 422);
    }
    $stmt = $pdo->prepare("UPDATE dog_medications SET status = ? WHERE id = ? AND dog_id = ?");
    $stmt->execute([$status, $medId, $dogId]);
    apiJson(['success' => true, 'message' => 'Status updated.']);
}

apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
