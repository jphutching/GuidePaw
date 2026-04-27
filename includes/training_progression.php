<?php

function calculateSuccessRate(int $attempted, int $successful): float
{
    if ($attempted <= 0) {
        return 0.0;
    }

    return max(0.0, min(1.0, $successful / $attempted));
}

function shouldProgress(int $attempted, int $successful, ?int $stressLevel, ?int $handlerConfidence): bool
{
    $rate = calculateSuccessRate($attempted, $successful);

    return $attempted >= 5
        && $rate >= 0.80
        && ($stressLevel === null || $stressLevel <= 2)
        && ($handlerConfidence === null || $handlerConfidence >= 4);
}

function shouldRepeat(int $attempted, int $successful, ?int $stressLevel): bool
{
    $rate = calculateSuccessRate($attempted, $successful);

    return $attempted > 0
        && $rate >= 0.50
        && $rate < 0.80
        && ($stressLevel === null || $stressLevel <= 3);
}

function shouldRegress(int $attempted, int $successful, ?int $stressLevel, bool $safetyFlagActive = false): bool
{
    if ($safetyFlagActive) {
        return true;
    }

    if ($stressLevel !== null && $stressLevel >= 4) {
        return true;
    }

    if ($attempted <= 0) {
        return false;
    }

    return calculateSuccessRate($attempted, $successful) < 0.50;
}

function recommendRecoveryStep(?int $stressLevel, bool $safetyFlagActive = false): string
{
    if ($safetyFlagActive) {
        return 'Pause training and request professional review before continuing.';
    }

    if ($stressLevel !== null && $stressLevel >= 4) {
        return 'Make it easier today: quieter place, shorter session, higher-value reward, and only one tiny win.';
    }

    return 'Return to the previous easier step for 3 days. Shorten the session and reward heavily for simple success.';
}

function progressionStatus(int $attempted, int $successful, ?int $stressLevel, ?int $handlerConfidence, bool $safetyFlagActive = false): string
{
    if ($safetyFlagActive) {
        return 'paused_for_review';
    }

    if (shouldRegress($attempted, $successful, $stressLevel, false)) {
        return 'regression_detected';
    }

    if (shouldProgress($attempted, $successful, $stressLevel, $handlerConfidence)) {
        return 'ready_to_progress';
    }

    if (shouldRepeat($attempted, $successful, $stressLevel)) {
        return 'repeat_current_step';
    }

    return 'active';
}

function createRegressionEvent(PDO $pdo, int $userId, int $dogId, ?int $goalId, ?int $moduleId, string $reason, string $recommendedAction): int
{
    $stmt = $pdo->prepare("
        INSERT INTO regression_events
        (user_id, dog_id, goal_id, module_id, detected_reason, recommended_action, status)
        VALUES (?, ?, ?, ?, ?, ?, 'open')
        RETURNING id
    ");

    $stmt->execute([
        $userId,
        $dogId,
        $goalId,
        $moduleId,
        $reason,
        $recommendedAction
    ]);

    return (int)$stmt->fetchColumn();
}
