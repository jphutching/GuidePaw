<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/dog_care_helpers.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];
$dogId  = isset($user['active_dog_id']) ? (int) $user['active_dog_id'] : 0;

function gpApiNormalizeCertItem(array $row): array
{
    return [
        'id'               => (int) $row['id'],
        'category'         => (string) ($row['category'] ?? ''),
        'item_name'        => (string) ($row['item_name'] ?? ''),
        'description'      => (string) ($row['description'] ?? ''),
        'status'           => (string) ($row['status'] ?? 'not_started'),
        'notes'            => (string) ($row['notes'] ?? ''),
        'last_assessed_at' => (string) ($row['last_assessed_at'] ?? ''),
    ];
}

function gpCertResolveDog(PDO $pdo, int $userId, int $dogId): ?array
{
    $stmt = $pdo->prepare("SELECT id, name FROM dogs WHERE id = ? AND owner_user_id = ?");
    $stmt->execute([$dogId, $userId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dog) return $dog;

    $stmt = $pdo->prepare("
        SELECT d.id, d.name FROM dogs d
        JOIN dog_handlers dh ON dh.dog_id = d.id
        WHERE d.id = ? AND dh.user_id = ? AND dh.status = 'accepted'
        LIMIT 1
    ");
    $stmt->execute([$dogId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($dogId <= 0) {
        apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
    }
    $dog = gpCertResolveDog($pdo, $userId, $dogId);
    if (!$dog) {
        apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
    }

    $items      = getDogCertificationItems($pdo, $dogId);
    $assessment = getLatestCertificationAssessment($pdo, $dogId);

    $total      = count($items);
    $proficient = count(array_filter($items, static fn ($i) => ($i['status'] ?? '') === 'proficient'));
    $inTraining = count(array_filter($items, static fn ($i) => ($i['status'] ?? '') === 'in_training'));
    $readyPct   = $total ? (int) round(($proficient / $total) * 100) : 0;

    apiJson([
        'success'       => true,
        'dog_name'      => (string) $dog['name'],
        'total'         => $total,
        'proficient'    => $proficient,
        'in_training'   => $inTraining,
        'readiness_pct' => $readyPct,
        'items'         => array_map('gpApiNormalizeCertItem', $items),
        'assessment'    => $assessment ? [
            'assessment_date'          => (string) ($assessment['assessment_date'] ?? ''),
            'public_access_score'      => isset($assessment['public_access_score']) && $assessment['public_access_score'] !== null ? (int) $assessment['public_access_score'] : null,
            'task_reliability_score'   => isset($assessment['task_reliability_score']) && $assessment['task_reliability_score'] !== null ? (int) $assessment['task_reliability_score'] : null,
            'obedience_score'          => isset($assessment['obedience_score']) && $assessment['obedience_score'] !== null ? (int) $assessment['obedience_score'] : null,
            'environmental_score'      => isset($assessment['environmental_score']) && $assessment['environmental_score'] !== null ? (int) $assessment['environmental_score'] : null,
            'notes'                    => (string) ($assessment['notes'] ?? ''),
        ] : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? '');

if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
}

$stmt = $pdo->prepare("SELECT id FROM dogs WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$dogId, $userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'You do not have write access for this dog.'], 403);
}

if ($action === 'seed_template') {
    seedCertificationChecklist($pdo, $dogId);
    apiJson(['success' => true, 'message' => 'Starter checklist loaded.']);
}

if ($action === 'update_item') {
    $itemId = (int) ($input['item_id'] ?? 0);
    $status = (string) ($input['status'] ?? 'not_started');
    $notes  = trim((string) ($input['notes'] ?? ''));
    if ($itemId <= 0) {
        apiJson(['success' => false, 'message' => 'item_id is required.'], 422);
    }
    if (!in_array($status, ['not_started', 'in_training', 'proficient'], true)) {
        $status = 'not_started';
    }
    $pdo->prepare("
        UPDATE dog_certification_items
        SET status = ?, notes = ?, last_assessed_at = CURRENT_TIMESTAMP
        WHERE id = ? AND dog_id = ?
    ")->execute([$status, $notes ?: null, $itemId, $dogId]);
    apiJson(['success' => true, 'message' => 'Item updated.']);
}

if ($action === 'add_assessment') {
    $date     = trim((string) ($input['assessment_date'] ?? '')) ?: date('Y-m-d');
    $public   = min(100, max(0, (int) ($input['public_access_score'] ?? 0)));
    $task     = min(100, max(0, (int) ($input['task_reliability_score'] ?? 0)));
    $obedience = min(100, max(0, (int) ($input['obedience_score'] ?? 0)));
    $env      = min(100, max(0, (int) ($input['environmental_score'] ?? 0)));
    $notes    = trim((string) ($input['notes'] ?? ''));

    $pdo->prepare("
        INSERT INTO dog_certification_assessments
            (dog_id, assessed_by_user_id, assessment_date,
             public_access_score, task_reliability_score, obedience_score, environmental_score, notes)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$dogId, $userId, $date,
        $public ?: null, $task ?: null, $obedience ?: null, $env ?: null, $notes ?: null]);
    apiJson(['success' => true, 'message' => 'Assessment saved.']);
}

apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
