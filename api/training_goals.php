<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/training_goals.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

$allowedCategories = ['potty', 'leash', 'barking', 'cab_calm', 'jumping', 'public_manners', 'psd_foundation', 'other'];

function gpNormalizeGoalRow(array $row): array
{
    return [
        'id'                          => (int) $row['id'],
        'dog_id'                      => (int) $row['dog_id'],
        'dog_name'                    => (string) ($row['dog_name'] ?? ''),
        'goal_category'               => (string) ($row['goal_category'] ?? ''),
        'current_problem'             => (string) ($row['current_problem'] ?? ''),
        'desired_behavior'            => (string) ($row['desired_behavior'] ?? ''),
        'context_environment'         => (string) ($row['context_environment'] ?? ''),
        'trigger_description'         => (string) ($row['trigger_description'] ?? ''),
        'handler_time_budget_minutes' => (int) ($row['handler_time_budget_minutes'] ?? 3),
        'reinforcer_preference'       => (string) ($row['reinforcer_preference'] ?? ''),
        'safety_risk'                 => (bool) ($row['safety_risk'] ?? false),
        'success_criteria'            => (string) ($row['success_criteria'] ?? ''),
        'maintenance_plan'            => (string) ($row['maintenance_plan'] ?? ''),
        'status'                      => (string) ($row['status'] ?? 'active'),
        'created_at'                  => (string) ($row['created_at'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $statusFilter = normalizeTrainingGoalStatusFilter($_GET['status'] ?? 'active');
    $goals        = getRecentTrainingGoals($pdo, $userId, 20, $statusFilter);
    apiJson([
        'success'       => true,
        'status_filter' => $statusFilter,
        'goals'         => array_map('gpNormalizeGoalRow', $goals),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? 'create');

if ($action === 'archive' || $action === 'restore') {
    $goalId    = (int) ($input['goal_id'] ?? 0);
    $newStatus = ($action === 'archive') ? 'archived' : 'active';
    if ($goalId <= 0) {
        apiJson(['success' => false, 'message' => 'goal_id required.'], 422);
    }
    $ok = updateTrainingGoalStatus($pdo, $goalId, $userId, $newStatus);
    apiJson(['success' => $ok, 'message' => $ok ? "Goal {$newStatus}." : 'Goal not found or no access.']);
}

$dogId = (int) ($input['dog_id'] ?? 0);
if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'dog_id is required.'], 422);
}
if (cleanTextarea($input['current_problem'] ?? '', 2000) === '') {
    apiJson(['success' => false, 'message' => 'current_problem is required.'], 422);
}
if (cleanTextarea($input['success_criteria'] ?? '', 2000) === '') {
    apiJson(['success' => false, 'message' => 'success_criteria is required.'], 422);
}
if (!in_array($input['goal_category'] ?? '', $allowedCategories, true)) {
    $input['goal_category'] = 'other';
}

$newId = createTrainingGoal($pdo, $userId, $input);
apiJson(['success' => true, 'message' => 'Training goal saved.', 'goal_id' => $newId]);
