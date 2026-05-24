<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/candidate_scoring.php';
require_once __DIR__ . '/../includes/audit_log.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

function gpApiNormalizeCandidateAssessment(array $row): array
{
    $scoreKeys = candidateScoreKeys();
    $total = 0;
    foreach ($scoreKeys as $key) {
        $total += (int) ($row[$key] ?? 3);
    }
    $avg = $total / count($scoreKeys);

    return [
        'id'                      => (int) $row['id'],
        'dog_id'                  => (int) $row['dog_id'],
        'dog_name'                => (string) ($row['dog_name'] ?? ''),
        'focus_level_recommended' => (int) ($row['focus_level_recommended'] ?? 0),
        'recommendation'          => (string) ($row['recommendation'] ?? ''),
        'safety_flags'            => (string) ($row['safety_flags'] ?? ''),
        'health_notes'            => (string) ($row['health_notes'] ?? ''),
        'average_score'           => round($avg, 1),
        'created_at'              => (string) ($row['created_at'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
    $dogsStmt->execute([$userId]);
    $dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $recentStmt = $pdo->prepare("
        SELECT a.*, d.name AS dog_name
        FROM dog_candidate_assessments a
        JOIN dogs d ON d.id = a.dog_id
        WHERE d.owner_user_id = ?
          AND COALESCE(a.status, 'active') = 'active'
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recentStmt->execute([$userId]);
    $assessments = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    apiJson([
        'success'     => true,
        'dogs'        => array_map(fn($d) => ['id' => (int) $d['id'], 'name' => (string) $d['name']], $dogs),
        'assessments' => array_map('gpApiNormalizeCandidateAssessment', $assessments),
        'score_labels' => candidateScoreLabels(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? 'create');

if ($action === 'archive') {
    $assessmentId = (int) ($input['assessment_id'] ?? 0);
    if ($assessmentId <= 0) {
        apiJson(['success' => false, 'message' => 'assessment_id is required.'], 422);
    }
    $stmt = $pdo->prepare("
        UPDATE dog_candidate_assessments a
        SET status = 'archived', updated_at = CURRENT_TIMESTAMP
        FROM dogs d
        WHERE a.id = ? AND a.dog_id = d.id AND d.owner_user_id = ?
    ");
    $stmt->execute([$assessmentId, $userId]);
    writeAuditLog($pdo, 'candidate_assessment_archived', 'dog_candidate_assessments', $assessmentId, 'Archived via API.');
    apiJson(['success' => true, 'message' => 'Assessment archived.']);
}

if ($action !== 'create') {
    apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
}

$dogId = (int) ($input['dog_id'] ?? 0);
if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'dog_id is required.'], 422);
}

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM dogs WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$dogId, $userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'Dog not found.'], 404);
}

$scoreKeys = candidateScoreKeys();
$scores = [];
foreach ($scoreKeys as $key) {
    $scores[$key] = clampCandidateScore($input[$key] ?? 3);
}

$avg = averageCandidateScore($scores);
$safetyFlags = trim((string) ($input['safety_flags'] ?? ''));
$recommendation = recommendCandidateFocusLevel($avg, $safetyFlags);

$stmt = $pdo->prepare("
    INSERT INTO dog_candidate_assessments
    (dog_id, focus_level_recommended, health_notes,
     confidence_score, startle_recovery_score, handler_engagement_score,
     food_motivation_score, toy_motivation_score, settle_score,
     human_neutrality_score, dog_neutrality_score, environment_score,
     handling_score, safety_flags, recommendation)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $dogId,
    $recommendation['focus_level'],
    trim((string) ($input['health_notes'] ?? '')),
    $scores['confidence_score'],
    $scores['startle_recovery_score'],
    $scores['handler_engagement_score'],
    $scores['food_motivation_score'],
    $scores['toy_motivation_score'],
    $scores['settle_score'],
    $scores['human_neutrality_score'],
    $scores['dog_neutrality_score'],
    $scores['environment_score'],
    $scores['handling_score'],
    $safetyFlags ?: null,
    $recommendation['recommendation'],
]);

apiJson([
    'success'         => true,
    'message'         => 'Candidate assessment saved.',
    'average_score'   => round($avg, 1),
    'focus_level'     => $recommendation['focus_level'],
    'recommendation'  => $recommendation['recommendation'],
]);
