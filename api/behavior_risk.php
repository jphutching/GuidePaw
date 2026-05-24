<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/candidate_scoring.php';
require_once __DIR__ . '/../includes/behavior_risk_scoring.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$dogId      = (int) ($_GET['dog_id'] ?? 0);
$assessment = gpBehaviorRiskAssessment($pdo, $userId, $dogId > 0 ? $dogId : null);

$incidents = array_map(static function (array $row): array {
    return [
        'id'                  => (int) $row['id'],
        'dog_id'              => (int) $row['dog_id'],
        'dog_name'            => (string) ($row['dog_name'] ?? ''),
        'incident_type'       => (string) ($row['incident_type'] ?? ''),
        'severity'            => (int) ($row['severity'] ?? 2),
        'trigger_description' => (string) ($row['trigger_description'] ?? ''),
        'context_environment' => (string) ($row['context_environment'] ?? ''),
        'created_at'          => (string) ($row['created_at'] ?? ''),
    ];
}, $assessment['incidents'] ?? []);

$candidate           = $assessment['candidate'];
$normalizedCandidate = $candidate !== null ? [
    'dog_name'                => (string) ($candidate['dog_name'] ?? ''),
    'focus_level_recommended' => (int) ($candidate['focus_level_recommended'] ?? 0),
    'recommendation'          => (string) ($candidate['recommendation'] ?? ''),
    'safety_flags'            => (string) ($candidate['safety_flags'] ?? ''),
] : null;

apiJson([
    'success'          => true,
    'dog_id'           => $dogId > 0 ? $dogId : null,
    'score'            => (int) $assessment['score'],
    'band'             => (string) $assessment['band'],
    'open_regressions' => (int) $assessment['open_regressions'],
    'reasons'          => $assessment['reasons'],
    'recommendations'  => $assessment['recommendations'],
    'incidents'        => $incidents,
    'candidate'        => $normalizedCandidate,
]);
