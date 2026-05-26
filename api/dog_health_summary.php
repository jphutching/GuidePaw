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

function gpDogHealthJoinText(array $parts, string $separator = ' • '): string
{
    $clean = [];
    foreach ($parts as $part) {
        $text = trim((string) $part);
        if ($text !== '') {
            $clean[] = $text;
        }
    }
    return implode($separator, $clean);
}

function gpDogHealthNormalizeRecord(array $row): array
{
    return [
        'date'      => (string) ($row['date'] ?? ''),
        'type'      => (string) ($row['type'] ?? ''),
        'notes'     => (string) ($row['notes'] ?? ''),
        'weight_lbs' => isset($row['weight_lbs']) && $row['weight_lbs'] !== null ? (float) $row['weight_lbs'] : null,
    ];
}

if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
}

$dog = gpDogHealthResolveDog($pdo, $userId, $dogId);
if (!$dog) {
    apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
}

$medStmt = $pdo->prepare("SELECT COUNT(*) FROM dog_medications WHERE dog_id = ? AND status = 'active'");
$medStmt->execute([$dogId]);
$activeMedicationCount = (int) $medStmt->fetchColumn();

$docStmt = $pdo->prepare("
    SELECT id, doc_type, title, provider_name, notes, created_at
    FROM dog_documents
    WHERE dog_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
$docStmt->execute([$dogId]);
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$apptStmt = $pdo->prepare("
    SELECT id, title, notes, appointment_at, location_text
    FROM dog_vet_appointments
    WHERE dog_id = ? AND status = 'completed'
    ORDER BY appointment_at DESC, id DESC
    LIMIT 5
");
$apptStmt->execute([$dogId]);
$appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$primaryVetStmt = $pdo->prepare("SELECT clinic_name, vet_name, phone, notes FROM dog_vets WHERE dog_id = ? ORDER BY is_primary DESC, id ASC LIMIT 1");
$primaryVetStmt->execute([$dogId]);
$primaryVet = $primaryVetStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$recentRecords = [];
foreach ($documents as $doc) {
    $recentRecords[] = [
        'date'       => gpDogHealthFormatDate((string) ($doc['created_at'] ?? '')) ?? '',
        'type'       => (string) ($doc['doc_type'] ?? 'vet_record'),
        'notes'      => gpDogHealthJoinText([
            $doc['title'] ?? '',
            $doc['provider_name'] ?? '',
            $doc['notes'] ?? '',
        ]),
        'weight_lbs' => $dog['weight_lbs'] !== null ? (float) $dog['weight_lbs'] : null,
    ];
}
foreach ($appointments as $appt) {
    $recentRecords[] = [
        'date'       => gpDogHealthFormatDate((string) ($appt['appointment_at'] ?? '')) ?? '',
        'type'       => 'vet_appointment',
        'notes'      => gpDogHealthJoinText([
            $appt['title'] ?? '',
            $appt['location_text'] ?? '',
            $appt['notes'] ?? '',
        ]),
        'weight_lbs' => $dog['weight_lbs'] !== null ? (float) $dog['weight_lbs'] : null,
    ];
}

usort($recentRecords, static function (array $left, array $right): int {
    return strcmp((string) ($right['date'] ?? ''), (string) ($left['date'] ?? ''));
});
$recentRecords = array_values(array_slice($recentRecords, 0, 8));

$lastCheckupDate = null;
if (!empty($appointments)) {
    $lastCheckupDate = gpDogHealthFormatDate((string) ($appointments[0]['appointment_at'] ?? ''));
} elseif (!empty($recentRecords)) {
    $lastCheckupDate = $recentRecords[0]['date'] ?: null;
}

$healthNotesCandidates = [
    $recentRecords[0]['notes'] ?? '',
    $primaryVet['notes'] ?? '',
    $dog['notes'] ?? '',
];
$healthNotes = '';
foreach ($healthNotesCandidates as $candidate) {
    $candidate = trim((string) $candidate);
    if ($candidate !== '') {
        $healthNotes = $candidate;
        break;
    }
}

apiJson([
    'success'                 => true,
    'dog_id'                  => (int) $dog['id'],
    'dog_name'                => (string) $dog['name'],
    'last_checkup_date'       => $lastCheckupDate,
    'weight_lbs'              => $dog['weight_lbs'] !== null ? (float) $dog['weight_lbs'] : null,
    'health_notes'            => $healthNotes,
    'active_medication_count' => $activeMedicationCount,
    'recent_records'          => array_map('gpDogHealthNormalizeRecord', $recentRecords),
]);
