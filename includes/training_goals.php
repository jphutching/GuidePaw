<?php

function getActiveTrainingGoals(PDO $pdo, int $userId, int $limit = 5): array
{
    $stmt = $pdo->prepare("
        SELECT g.*, d.name AS dog_name
        FROM training_goals g
        JOIN dogs d ON d.id = g.dog_id
        WHERE g.user_id = ? AND g.status = 'active'
        ORDER BY g.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentTrainingGoals(PDO $pdo, int $userId, int $limit = 8): array
{
    $stmt = $pdo->prepare("
        SELECT g.*, d.name AS dog_name
        FROM training_goals g
        JOIN dogs d ON d.id = g.dog_id
        WHERE g.user_id = ? AND g.status = 'active'
        ORDER BY g.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createTrainingGoal(PDO $pdo, int $userId, array $data): int
{
    $stmt = $pdo->prepare("
        INSERT INTO training_goals
        (
            dog_id,
            user_id,
            goal_category,
            current_problem,
            desired_behavior,
            context_environment,
            trigger_description,
            handler_time_budget_minutes,
            reinforcer_preference,
            safety_risk,
            success_criteria,
            maintenance_plan,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        RETURNING id
    ");

    $stmt->execute([
        (int)$data['dog_id'],
        $userId,
        trim($data['goal_category'] ?? ''),
        trim($data['current_problem'] ?? ''),
        trim($data['desired_behavior'] ?? ''),
        trim($data['context_environment'] ?? ''),
        trim($data['trigger_description'] ?? ''),
        (int)($data['handler_time_budget_minutes'] ?? 3),
        trim($data['reinforcer_preference'] ?? ''),
        !empty($data['safety_risk']) ? 1 : 0,
        trim($data['success_criteria'] ?? ''),
        trim($data['maintenance_plan'] ?? '')
    ]);

    return (int)$stmt->fetchColumn();
}

function buildEasyWinFromGoal(?array $goal): string
{
    if (!$goal) {
        return 'Say your dog’s name once. Reward the moment they look at you. Repeat 3 times.';
    }

    $behavior = trim($goal['desired_behavior'] ?? '');
    if ($behavior === '') {
        $behavior = trim($goal['goal_category'] ?? 'foundation skill');
    }

    return 'Work 3 minutes on: ' . $behavior;
}
