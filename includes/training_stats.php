<?php

function getTrainingCoreStats(PDO $pdo, int $userId): array
{
    $stats = [
        'active_goals' => 0,
        'sessions_7d' => 0,
        'avg_success_rate_7d' => null,
        'open_regressions' => 0,
        'latest_focus_level' => null,
        'latest_candidate_dog' => null,
    ];

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM training_goals
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$userId]);
    $stats['active_goals'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS session_count,
            CASE
                WHEN SUM(reps_attempted) > 0
                THEN ROUND((SUM(reps_successful)::numeric / SUM(reps_attempted)::numeric) * 100)
                ELSE NULL
            END AS avg_success_rate
        FROM training_sessions
        WHERE user_id = ?
          AND COALESCE(status, 'active') = 'active'
          AND created_at >= CURRENT_TIMESTAMP - INTERVAL '7 days'
    ");
    $stmt->execute([$userId]);
    $sessionStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['sessions_7d'] = (int)($sessionStats['session_count'] ?? 0);
    $stats['avg_success_rate_7d'] = $sessionStats['avg_success_rate'] !== null
        ? (int)$sessionStats['avg_success_rate']
        : null;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM regression_events
        WHERE user_id = ? AND status = 'open'
    ");
    $stmt->execute([$userId]);
    $stats['open_regressions'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT a.focus_level_recommended, d.name AS dog_name
        FROM dog_candidate_assessments a
        JOIN dogs d ON d.id = a.dog_id
        WHERE d.owner_user_id = ?
          AND COALESCE(a.status, 'active') = 'active'
        ORDER BY a.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($candidate) {
        $stats['latest_focus_level'] = $candidate['focus_level_recommended'];
        $stats['latest_candidate_dog'] = $candidate['dog_name'];
    }

    return $stats;
}

function formatTrainingStatValue($value, string $fallback = '—'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return (string)$value;
}
