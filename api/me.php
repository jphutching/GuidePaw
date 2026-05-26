<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user      = requireApiUser($pdo);
$activeDogId = apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0));

$nextAppt = null;
if ($activeDogId) {
    $stmt = $pdo->prepare("
        SELECT title, appointment_at FROM dog_vet_appointments
        WHERE dog_id = ? AND status = 'scheduled' AND appointment_at >= CURRENT_TIMESTAMP
        ORDER BY appointment_at ASC LIMIT 1
    ");
    $stmt->execute([$activeDogId]);
    $nextAppt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

apiJson([
    'success'               => true,
    'user'                  => $user,
    'active_dog_id'         => $activeDogId,
    'next_appointment_at'   => $nextAppt ? (string) $nextAppt['appointment_at'] : null,
    'next_appointment_title'=> $nextAppt ? (string) $nextAppt['title'] : null,
]);
