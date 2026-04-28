<?php

function candidateScoreKeys(): array
{
    return [
        'confidence_score',
        'startle_recovery_score',
        'handler_engagement_score',
        'food_motivation_score',
        'toy_motivation_score',
        'settle_score',
        'human_neutrality_score',
        'dog_neutrality_score',
        'environment_score',
        'handling_score'
    ];
}

function clampCandidateScore($value): int
{
    return max(1, min(5, (int)$value));
}

function averageCandidateScore(array $scores): float
{
    $keys = candidateScoreKeys();
    $total = 0;

    foreach ($keys as $key) {
        $total += clampCandidateScore($scores[$key] ?? 3);
    }

    return $total / count($keys);
}

function recommendCandidateFocusLevel(float $averageScore, string $safetyFlags = ''): array
{
    $safetyText = strtolower(trim($safetyFlags));
    $severeFlags = [
        'bite',
        'severe aggression',
        'human aggression',
        'dog aggression',
        'severe fear',
        'shutdown',
        'uncontrolled lunging',
        'cannot recover',
        'panic',
        'snap',
        'attack'
    ];

    foreach ($severeFlags as $flag) {
        if ($safetyText !== '' && str_contains($safetyText, $flag)) {
            return [
                'focus_level' => 0,
                'recommendation' => 'Not suitable for PSD/service path at this time. Professional review recommended before advancing.'
            ];
        }
    }

    if ($averageScore >= 4.0) {
        return [
            'focus_level' => 3,
            'recommendation' => 'Service-dog candidate. Continue with structured foundations and public-readiness checks.'
        ];
    }

    if ($averageScore >= 3.0) {
        return [
            'focus_level' => 2,
            'recommendation' => 'PSD foundations or travel companion path. Build confidence, neutrality, and handler engagement.'
        ];
    }

    return [
        'focus_level' => 0,
        'recommendation' => 'Pet manners and confidence building first. Reassess after foundation work.'
    ];
}
