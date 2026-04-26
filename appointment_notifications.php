<?php
require_once 'includes/db_connect.php';
checkLogin();
header('Content-Type: application/json');

$userId = (int) $_SESSION['user_id'];
$windowHours = isset($_GET['hours']) ? max(1, min(48, (int) $_GET['hours'])) : 24;
$lookbackHours = 6;

$sql = "
    SELECT * FROM (
        SELECT
            a.id AS appointment_id,
            d.id AS dog_id,
            d.name AS dog_name,
            a.title,
            a.location_text,
            a.notes,
            a.appointment_at,
            a.reminder_at,
            v.clinic_name,
            v.vet_name,
            v.phone AS vet_phone,
            'reminder' AS event_type,
            a.reminder_at AS due_at
        FROM dog_vet_appointments a
        JOIN dogs d ON d.id = a.dog_id
        LEFT JOIN dog_vets v ON v.id = a.dog_vet_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted'
        WHERE (d.owner_user_id = ? OR dh.id IS NOT NULL)
          AND a.status = 'scheduled'
          AND a.reminder_at IS NOT NULL
          AND a.reminder_at BETWEEN " . dbDateSub('CURRENT_TIMESTAMP', $lookbackHours, 'HOUR') . " AND " . dbDateAdd('CURRENT_TIMESTAMP', $windowHours, 'HOUR') . "

        UNION ALL

        SELECT
            a.id AS appointment_id,
            d.id AS dog_id,
            d.name AS dog_name,
            a.title,
            a.location_text,
            a.notes,
            a.appointment_at,
            a.reminder_at,
            v.clinic_name,
            v.vet_name,
            v.phone AS vet_phone,
            'appointment' AS event_type,
            a.appointment_at AS due_at
        FROM dog_vet_appointments a
        JOIN dogs d ON d.id = a.dog_id
        LEFT JOIN dog_vets v ON v.id = a.dog_vet_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted'
        WHERE (d.owner_user_id = ? OR dh.id IS NOT NULL)
          AND a.status = 'scheduled'
          AND a.appointment_at BETWEEN " . dbDateSub('CURRENT_TIMESTAMP', $lookbackHours, 'HOUR') . " AND " . dbDateAdd('CURRENT_TIMESTAMP', $windowHours, 'HOUR') . "
    ) reminder_events
    ORDER BY due_at ASC
    LIMIT 30
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId, $userId, $userId, $userId]);
$rows = $stmt->fetchAll() ?: [];

echo json_encode([
    'success' => true,
    'generated_at' => date(DATE_ATOM),
    'events' => array_map(static function (array $row): array {
        return [
            'appointment_id' => (int) $row['appointment_id'],
            'dog_id' => (int) $row['dog_id'],
            'dog_name' => $row['dog_name'],
            'title' => $row['title'],
            'location_text' => $row['location_text'],
            'notes' => $row['notes'],
            'appointment_at' => $row['appointment_at'],
            'reminder_at' => $row['reminder_at'],
            'clinic_name' => $row['clinic_name'],
            'vet_name' => $row['vet_name'],
            'vet_phone' => $row['vet_phone'],
            'event_type' => $row['event_type'],
            'due_at' => $row['due_at'],
        ];
    }, $rows),
]);
