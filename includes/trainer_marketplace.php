<?php
declare(strict_types=1);

function gpTrainerMarketplaceEntries(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            d.id AS dog_id,
            d.name AS dog_name,
            p.training_mode,
            p.trainer_name,
            p.business_name,
            p.credentials,
            p.trainer_phone,
            p.trainer_email,
            p.trainer_website,
            p.handler_experience,
            p.handler_goals,
            p.candidate_stage,
            p.candidate_notes,
            p.training_focus,
            p.notes,
            p.updated_at
        FROM dog_training_profiles p
        JOIN dogs d ON d.id = p.dog_id
        WHERE d.owner_user_id = ?
          AND COALESCE(p.training_mode, 'self_training') IN ('professional_trainer', 'hybrid')
        ORDER BY COALESCE(NULLIF(TRIM(p.trainer_name), ''), NULLIF(TRIM(p.business_name), ''), d.name) ASC,
                 p.updated_at DESC,
                 d.name ASC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $entries = [];
    foreach ($rows as $row) {
        $key = strtolower(trim((string) ($row['trainer_name'] ?? '')));
        if ($key === '') {
            $key = strtolower(trim((string) ($row['business_name'] ?? '')));
        }
        if ($key === '') {
            $key = 'dog:' . (string) $row['dog_id'];
        }

        if (!isset($entries[$key])) {
            $entries[$key] = [
                'trainer_name' => trim((string) ($row['trainer_name'] ?? '')),
                'business_name' => trim((string) ($row['business_name'] ?? '')),
                'credentials' => trim((string) ($row['credentials'] ?? '')),
                'trainer_phone' => trim((string) ($row['trainer_phone'] ?? '')),
                'trainer_email' => trim((string) ($row['trainer_email'] ?? '')),
                'trainer_website' => trim((string) ($row['trainer_website'] ?? '')),
                'handler_experience' => trim((string) ($row['handler_experience'] ?? '')),
                'handler_goals' => trim((string) ($row['handler_goals'] ?? '')),
                'candidate_stage' => trim((string) ($row['candidate_stage'] ?? '')),
                'candidate_notes' => trim((string) ($row['candidate_notes'] ?? '')),
                'training_focus' => trim((string) ($row['training_focus'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
                'updated_at' => $row['updated_at'] ?? null,
                'dogs' => [],
            ];
        }

        $entries[$key]['dogs'][] = [
            'dog_id' => (int) $row['dog_id'],
            'dog_name' => (string) $row['dog_name'],
            'training_mode' => (string) ($row['training_mode'] ?? 'professional_trainer'),
        ];
    }

    return array_values($entries);
}

function gpTrainerMarketplaceSummary(array $entries): array
{
    $summary = [
        'trainer_count' => count($entries),
        'dog_count' => 0,
    ];

    foreach ($entries as $entry) {
        $summary['dog_count'] += count($entry['dogs'] ?? []);
    }

    return $summary;
}

