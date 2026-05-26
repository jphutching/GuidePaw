<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user      = requireApiUser($pdo);
$activeDogId = apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0));

$nextAppt  = null;
$nextRefill = null;
$activeMedCount = 0;
if ($activeDogId) {
    $stmt = $pdo->prepare("
        SELECT title, appointment_at FROM dog_vet_appointments
        WHERE dog_id = ? AND status = 'scheduled' AND appointment_at >= CURRENT_TIMESTAMP
        ORDER BY appointment_at ASC LIMIT 1
    ");
    $stmt->execute([$activeDogId]);
    $nextAppt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
        SELECT medication_name, refill_date FROM dog_medications
        WHERE dog_id = ? AND status = 'active'
        ORDER BY refill_date ASC NULLS LAST LIMIT 1
    ");
    $stmt->execute([$activeDogId]);
    $nextRefill = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dog_medications WHERE dog_id = ? AND status = 'active'");
    $stmt->execute([$activeDogId]);
    $activeMedCount = (int) $stmt->fetchColumn();
}

apiJson([
    'success'                => true,
    'user'                   => $user,
    'active_dog_id'          => $activeDogId,
    'next_appointment_at'    => $nextAppt  ? (string) $nextAppt['appointment_at']  : null,
    'next_appointment_title' => $nextAppt  ? (string) $nextAppt['title']            : null,
    'next_refill_date'       => $nextRefill && $nextRefill['refill_date'] ? (string) $nextRefill['refill_date'] : null,
    'next_refill_med_name'   => $nextRefill ? (string) $nextRefill['medication_name'] : null,
    'active_med_count'       => $activeMedCount,
]);
