<?php

function createBehaviorIncident(PDO $pdo, int $userId, array $data): int
{
    $stmt = $pdo->prepare("
        INSERT INTO behavior_incidents
        (user_id, dog_id, incident_type, context_environment, trigger_description, severity, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");

    $stmt->execute([
        $userId,
        (int)$data['dog_id'],
        trim($data['incident_type'] ?? ''),
        trim($data['context_environment'] ?? ''),
        trim($data['trigger_description'] ?? ''),
        max(1, min(5, (int)($data['severity'] ?? 2))),
        trim($data['notes'] ?? '')
    ]);

    return (int)$stmt->fetchColumn();
}

function getRecentBehaviorIncidents(PDO $pdo, int $userId, int $limit = 8): array
{
    $stmt = $pdo->prepare("
        SELECT b.*, d.name AS dog_name
        FROM behavior_incidents b
        JOIN dogs d ON d.id = b.dog_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
