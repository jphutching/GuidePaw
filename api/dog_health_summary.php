<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];
$dogId  = isset($user['active_dog_id']) ? (int) $user['active_dog_id'] : 0;

function gpDogHealthResolveDog(PDO $pdo, int $userId, int $dogId): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, weight_lbs, notes FROM dogs WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$dogId, $userId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dog) {
        return $dog;
    }

    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.weight_lbs, d.notes
        FROM dogs d
        JOIN dog_handlers dh ON dh.dog_id = d.id
        WHERE d.id = ? AND dh.user_id = ? AND dh.status = 'accepted'
        LIMIT 1
    ");
    $stmt->execute([$dogId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function gpDogHealthFormatDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return substr($value, 0, 10) ?: null;
    }
    return date('Y-m-d', $timestamp);
}

if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
}

$dog = gpDogHealthResolveDog($pdo, $userId, $dogId);
if (!$dog) {
    apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
}

// Active medications (full list)
$medStmt = $pdo->prepare("
    SELECT id, medication_name, dosage, status, schedule_text, refill_date,
           prescribing_provider, instructions, notes, created_at
    FROM dog_medications
    WHERE dog_id = ? AND status = 'active'
    ORDER BY COALESCE(refill_date, start_date) ASC, id DESC
");
$medStmt->execute([$dogId]);
$activeMeds = $medStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Primary vet
$vetStmt = $pdo->prepare("
    SELECT clinic_name, vet_name, phone, notes
    FROM dog_vets
    WHERE dog_id = ?
    ORDER BY is_primary DESC, id ASC
    LIMIT 1
");
$vetStmt->execute([$dogId]);
$primaryVet = $vetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Upcoming appointments (next 3 scheduled)
$upcomingStmt = $pdo->prepare("
    SELECT a.id, a.title, a.status, a.appointment_at, a.location_text, a.notes,
           COALESCE(v.clinic_name, '') AS clinic_name,
           COALESCE(v.phone, '')       AS vet_phone
    FROM dog_vet_appointments a
    LEFT JOIN dog_vets v ON v.id = a.dog_vet_id
    WHERE a.dog_id = ? AND a.status = 'scheduled' AND a.appointment_at >= CURRENT_TIMESTAMP
    ORDER BY a.appointment_at ASC
    LIMIT 3
");
$upcomingStmt->execute([$dogId]);
$upcomingAppts = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Recent completed appointments (last 5)
$recentStmt = $pdo->prepare("
    SELECT a.id, a.title, a.status, a.appointment_at, a.location_text, a.notes,
           COALESCE(v.clinic_name, '') AS clinic_name,
           COALESCE(v.phone, '')       AS vet_phone
    FROM dog_vet_appointments a
    LEFT JOIN dog_vets v ON v.id = a.dog_vet_id
    WHERE a.dog_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_at DESC
    LIMIT 5
");
$recentStmt->execute([$dogId]);
$recentAppts = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$lastCheckupDate = !empty($recentAppts)
    ? gpDogHealthFormatDate((string) ($recentAppts[0]['appointment_at'] ?? ''))
    : null;

$nextApptAt    = !empty($upcomingAppts) ? (string) $upcomingAppts[0]['appointment_at'] : null;
$nextApptTitle = !empty($upcomingAppts) ? (string) $upcomingAppts[0]['title'] : '';

function gpNormalizeAppt(array $r): array
{
    return [
        'id'             => (int) $r['id'],
        'title'          => (string) $r['title'],
        'status'         => (string) $r['status'],
        'appointment_at' => (string) $r['appointment_at'],
        'location_text'  => (string) ($r['location_text'] ?? ''),
        'notes'          => (string) ($r['notes'] ?? ''),
        'clinic_name'    => (string) ($r['clinic_name'] ?? ''),
        'vet_phone'      => (string) ($r['vet_phone'] ?? ''),
    ];
}

function gpNormalizeMed(array $r): array
{
    return [
        'id'                   => (int) $r['id'],
        'medication_name'      => (string) $r['medication_name'],
        'dosage'               => (string) ($r['dosage'] ?? ''),
        'status'               => (string) $r['status'],
        'schedule_text'        => (string) ($r['schedule_text'] ?? ''),
        'refill_date'          => (string) ($r['refill_date'] ?? ''),
        'prescribing_provider' => (string) ($r['prescribing_provider'] ?? ''),
        'instructions'         => (string) ($r['instructions'] ?? ''),
        'notes'                => (string) ($r['notes'] ?? ''),
        'created_at'           => (string) ($r['created_at'] ?? ''),
    ];
}

apiJson([
    'success'                  => true,
    'dog_id'                   => (int) $dog['id'],
    'dog_name'                 => (string) $dog['name'],
    'weight_lbs'               => $dog['weight_lbs'] !== null ? (float) $dog['weight_lbs'] : null,
    'last_checkup_date'        => $lastCheckupDate,
    'active_medication_count'  => count($activeMeds),
    'primary_vet_clinic'       => $primaryVet ? (string) ($primaryVet['clinic_name'] ?? '') : '',
    'primary_vet_name'         => $primaryVet ? (string) ($primaryVet['vet_name'] ?? '') : '',
    'primary_vet_phone'        => $primaryVet ? (string) ($primaryVet['phone'] ?? '') : '',
    'next_appointment_at'      => $nextApptAt,
    'next_appointment_title'   => $nextApptTitle,
    'active_medications'       => array_map('gpNormalizeMed', $activeMeds),
    'upcoming_appointments'    => array_map('gpNormalizeAppt', $upcomingAppts),
    'recent_appointments'      => array_map('gpNormalizeAppt', $recentAppts),
]);
