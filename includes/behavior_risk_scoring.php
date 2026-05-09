<?php
declare(strict_types=1);

function gpBehaviorRiskScoringEnabled(PDO $pdo): bool
{
    return function_exists('featureEnabled') ? featureEnabled($pdo, 'behavior_risk_scoring_enabled') : true;
}

function gpBehaviorRiskAssessment(PDO $pdo, int $userId, ?int $dogId = null): array
{
    $score = 0;
    $reasons = [];
    $recommendations = [];

    $incidentSql = "
        SELECT b.*, d.name AS dog_name
        FROM behavior_incidents b
        JOIN dogs d ON d.id = b.dog_id
        WHERE b.user_id = ?
    ";
    $params = [$userId];
    if ($dogId !== null) {
        $incidentSql .= " AND b.dog_id = ?";
        $params[] = $dogId;
    }
    $incidentSql .= " AND COALESCE(b.status, 'active') = 'active' ORDER BY b.created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($incidentSql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($incidents as $incident) {
        $severity = max(1, min(5, (int) ($incident['severity'] ?? 2)));
        $ageDays = max(0, (int) floor((time() - strtotime((string) ($incident['created_at'] ?? 'now'))) / 86400));
        $recencyWeight = $ageDays <= 7 ? 1.4 : ($ageDays <= 30 ? 1.0 : 0.7);
        $incidentPoints = (int) round($severity * 10 * $recencyWeight);
        $score += $incidentPoints;
    }

    if ($incidents) {
        $reasons[] = count($incidents) . ' active behavior incident' . (count($incidents) === 1 ? '' : 's');
    }

    $regressionSql = "
        SELECT COUNT(*)
        FROM regression_events
        WHERE user_id = ? AND status = 'open'
    ";
    $params = [$userId];
    if ($dogId !== null) {
        $regressionSql .= " AND dog_id = ?";
        $params[] = $dogId;
    }
    $stmt = $pdo->prepare($regressionSql);
    $stmt->execute($params);
    $openRegressions = (int) $stmt->fetchColumn();
    if ($openRegressions > 0) {
        $score += $openRegressions * 12;
        $reasons[] = $openRegressions . ' open regression event' . ($openRegressions === 1 ? '' : 's');
        $recommendations[] = 'Use the habit-repair or coach-review path before increasing difficulty.';
    }

    $candidate = gpLatestCandidateAssessment($pdo, $userId, $dogId);
    if ($candidate) {
        $focusLevel = (int) ($candidate['focus_level_recommended'] ?? 0);
        $candidatePenalty = match (true) {
            $focusLevel <= 0 => 28,
            $focusLevel === 1 => 18,
            $focusLevel === 2 => 10,
            default => 0,
        };
        $score += $candidatePenalty;
        $reasons[] = 'candidate focus level ' . $focusLevel;

        $candidateAverage = averageCandidateScore([
            'confidence_score' => (int) ($candidate['confidence_score'] ?? 3),
            'startle_recovery_score' => (int) ($candidate['startle_recovery_score'] ?? 3),
            'handler_engagement_score' => (int) ($candidate['handler_engagement_score'] ?? 3),
            'food_motivation_score' => (int) ($candidate['food_motivation_score'] ?? 3),
            'toy_motivation_score' => (int) ($candidate['toy_motivation_score'] ?? 3),
            'settle_score' => (int) ($candidate['settle_score'] ?? 3),
            'human_neutrality_score' => (int) ($candidate['human_neutrality_score'] ?? 3),
            'dog_neutrality_score' => (int) ($candidate['dog_neutrality_score'] ?? 3),
            'environment_score' => (int) ($candidate['environment_score'] ?? 3),
            'handling_score' => (int) ($candidate['handling_score'] ?? 3),
        ]);

        if ($candidateAverage < 3.0) {
            $score += 12;
            $recommendations[] = 'Build neutrality, recovery, and handler engagement before public pressure.';
        }

        if (!empty($candidate['safety_flags'])) {
            $score += 18;
            $reasons[] = 'candidate safety flags present';
            $recommendations[] = 'Treat the current path as higher risk until safety flags are addressed.';
        }
    }

    $score = max(0, min(100, $score));
    $band = 'low';
    if ($score >= 70) {
        $band = 'high';
        $recommendations[] = 'Pause progression and review the current behavior plan.';
    } elseif ($score >= 40) {
        $band = 'moderate';
        $recommendations[] = 'Keep the work short, controlled, and easy to repeat.';
    } else {
        $recommendations[] = 'Current risk is manageable. Continue with normal progression and monitoring.';
    }

    return [
        'score' => $score,
        'band' => $band,
        'reasons' => array_values(array_unique($reasons)),
        'recommendations' => array_values(array_unique($recommendations)),
        'incidents' => $incidents,
        'open_regressions' => $openRegressions,
        'candidate' => $candidate,
    ];
}

