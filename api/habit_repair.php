<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/behavior_incidents.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

$protocols = [
    'potty_accidents' => [
        'key'   => 'potty_accidents',
        'title' => 'Potty Accident Reset',
        'time'  => 'Start in under 3 minutes',
        'steps' => [
            'Rule out a medical issue if accidents are sudden, frequent, painful, or unusual.',
            'Restart potty breaks after waking, eating, drinking, play, arrival, and before driving.',
            'Reward heavily outside immediately after potty.',
            'Restrict freedom after a failed potty attempt.',
            'Clean accidents with enzyme cleaner.',
            'Track time, location, and trigger.',
            'After 72 clean hours, slowly expand freedom.',
        ],
    ],
    'pulling' => [
        'key'   => 'pulling',
        'title' => 'Pulling Reset',
        'time'  => '2 to 5 minutes',
        'steps' => [
            'Use a shorter route with fewer distractions.',
            'Stop rehearsing pulling.',
            'Reward check-ins before the leash gets tight.',
            'Turn or pause before the dog reaches the end of the leash.',
            'Use sniffing as a reward when the leash is loose.',
            'Progress when the leash is loose for about 80 percent of the route.',
        ],
    ],
    'jumping' => [
        'key'   => 'jumping',
        'title' => 'Jumping Reset',
        'time'  => '2 minutes',
        'steps' => [
            'Prevent rehearsals with leash, gate, crate, or distance.',
            'Do not give attention while paws are up.',
            'Reward four paws on the floor.',
            'Practice calm greetings with low excitement.',
            'Use sit or settle as the replacement behavior.',
        ],
    ],
    'barking' => [
        'key'   => 'barking',
        'title' => 'Barking Reset',
        'time'  => '2 to 5 minutes',
        'steps' => [
            'Identify the trigger.',
            'Increase distance from the trigger.',
            'Reward quiet check-ins.',
            'Redirect before barking escalates.',
            'Use cab window or visual management when needed.',
            'Avoid yelling because it can increase arousal.',
        ],
    ],
    'cab_settling' => [
        'key'   => 'cab_settling',
        'title' => 'Cab Settling Reset',
        'time'  => '3 minutes',
        'steps' => [
            'Start after potty and light movement.',
            'Send dog to mat, bed, crate, or safe cab rest spot.',
            'Reward calm breathing and lying down.',
            'Add safe chew or enrichment.',
            'Keep the first session short.',
            'Gradually increase duration after easy wins.',
        ],
    ],
];

function gpNormalizeBehaviorIncidentRow(array $row): array
{
    return [
        'id'                  => (int) $row['id'],
        'dog_id'              => (int) $row['dog_id'],
        'dog_name'            => (string) ($row['dog_name'] ?? ''),
        'incident_type'       => (string) ($row['incident_type'] ?? ''),
        'context_environment' => (string) ($row['context_environment'] ?? ''),
        'trigger_description' => (string) ($row['trigger_description'] ?? ''),
        'severity'            => (int) ($row['severity'] ?? 2),
        'notes'               => (string) ($row['notes'] ?? ''),
        'status'              => (string) ($row['status'] ?? 'active'),
        'created_at'          => (string) ($row['created_at'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $incidents = getRecentBehaviorIncidents($pdo, $userId, 10);
    apiJson([
        'success'   => true,
        'protocols' => array_values($protocols),
        'incidents' => array_map('gpNormalizeBehaviorIncidentRow', $incidents),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? 'create');

if ($action === 'archive') {
    $incidentId = (int) ($input['incident_id'] ?? 0);
    if ($incidentId <= 0) {
        apiJson(['success' => false, 'message' => 'incident_id required.'], 422);
    }
    $stmt = $pdo->prepare("UPDATE behavior_incidents SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
    $stmt->execute([$incidentId, $userId]);
    apiJson(['success' => $stmt->rowCount() > 0, 'message' => 'Incident archived.']);
}

$dogId = (int) ($input['dog_id'] ?? 0);
if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'dog_id is required.'], 422);
}
if (!array_key_exists($input['incident_type'] ?? '', $protocols)) {
    $input['incident_type'] = 'potty_accidents';
}

$newId = createBehaviorIncident($pdo, $userId, $input);
apiJson(['success' => true, 'message' => 'Incident logged.', 'incident_id' => $newId]);
