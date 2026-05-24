<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];
$dogId  = isset($user['active_dog_id']) ? (int) $user['active_dog_id'] : 0;

function gpApiNormalizeAppointment(array $row): array
{
    return [
        'id'             => (int) $row['id'],
        'title'          => (string) ($row['title'] ?? ''),
        'status'         => (string) ($row['status'] ?? 'scheduled'),
        'appointment_at' => (string) ($row['appointment_at'] ?? ''),
        'reminder_at'    => (string) ($row['reminder_at'] ?? ''),
        'location_text'  => (string) ($row['location_text'] ?? ''),
        'notes'          => (string) ($row['notes'] ?? ''),
        'clinic_name'    => (string) ($row['clinic_name'] ?? ''),
        'vet_phone'      => (string) ($row['vet_phone'] ?? ''),
        'dog_vet_id'     => isset($row['dog_vet_id']) ? (int) $row['dog_vet_id'] : null,
    ];
}

function gpApiNormalizeVet(array $row): array
{
    return [
        'id'          => (int) $row['id'],
        'clinic_name' => (string) ($row['clinic_name'] ?? ''),
        'vet_name'    => (string) ($row['vet_name'] ?? ''),
        'phone'       => (string) ($row['phone'] ?? ''),
    ];
}

function gpApptResolveDog(PDO $pdo, int $userId, int $dogId): ?array
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
    $dog = gpApptResolveDog($pdo, $userId, $dogId);
    if (!$dog) {
        apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
    }

    $vetsStmt = $pdo->prepare("SELECT id, clinic_name, vet_name, phone FROM dog_vets WHERE dog_id = ? ORDER BY is_primary DESC, clinic_name ASC");
    $vetsStmt->execute([$dogId]);
    $vets = $vetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $apptStmt = $pdo->prepare("
        SELECT a.*, v.clinic_name, v.phone AS vet_phone
        FROM dog_vet_appointments a
        LEFT JOIN dog_vets v ON v.id = a.dog_vet_id
        WHERE a.dog_id = ?
        ORDER BY a.appointment_at ASC
    ");
    $apptStmt->execute([$dogId]);
    $appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    apiJson([
        'success'      => true,
        'dog_name'     => (string) $dog['name'],
        'vets'         => array_map('gpApiNormalizeVet', $vets),
        'appointments' => array_map('gpApiNormalizeAppointment', $appointments),
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

if ($action === 'add_appointment') {
    $title         = trim((string) ($input['title'] ?? ''));
    $appointmentAt = trim((string) ($input['appointment_at'] ?? ''));
    if ($title === '' || $appointmentAt === '') {
        apiJson(['success' => false, 'message' => 'Title and appointment time are required.'], 422);
    }
    $reminderAt  = trim((string) ($input['reminder_at'] ?? ''));
    $location    = trim((string) ($input['location_text'] ?? ''));
    $notes       = trim((string) ($input['notes'] ?? ''));
    $vetId       = (int) ($input['dog_vet_id'] ?? 0) ?: null;

    $pdo->prepare("
        INSERT INTO dog_vet_appointments
            (dog_id, dog_vet_id, created_by_user_id, title, appointment_at, reminder_at, location_text, notes)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([
        $dogId, $vetId, $userId,
        $title,
        $appointmentAt,
        $reminderAt ?: null,
        $location ?: null,
        $notes ?: null,
    ]);
    apiJson(['success' => true, 'message' => 'Appointment saved.']);
}

if ($action === 'mark_status') {
    $apptId    = (int) ($input['appointment_id'] ?? 0);
    $newStatus = (string) ($input['new_status'] ?? '');
    if ($apptId <= 0) {
        apiJson(['success' => false, 'message' => 'appointment_id is required.'], 422);
    }
    if (!in_array($newStatus, ['scheduled', 'completed', 'cancelled'], true)) {
        apiJson(['success' => false, 'message' => 'Invalid status.'], 422);
    }
    $pdo->prepare("UPDATE dog_vet_appointments SET status = ? WHERE id = ? AND dog_id = ?")
        ->execute([$newStatus, $apptId, $dogId]);
    apiJson(['success' => true, 'message' => 'Appointment updated.']);
}

apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
