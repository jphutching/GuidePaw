<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/training_assistant.php';
require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/paywalls.php';
require_once __DIR__ . '/../includes/paywall_catalog.php';

$user = requireApiUser($pdo);

if (!featureEnabled($pdo, 'ai_training_assistant_enabled')) {
    apiJson(['success' => false, 'message' => 'AI Training Assistant is not currently enabled.'], 503);
}

gpPaywallCatalogEnsureSchema($pdo);
$requiredTier = gpPaywallCatalogItemTier($pdo, 'ai_training_assistant', 'pro');
if (!gpCurrentUserHasTierAccess($pdo, $requiredTier)) {
    apiJson([
        'success'          => false,
        'message'          => 'AI Training Assistant requires the ' . ucfirst($requiredTier) . ' plan.',
        'requires_upgrade' => true,
        'required_tier'    => $requiredTier,
    ], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $topics = gpTrainingAssistantTopics();
    $mapped = [];
    foreach ($topics as $key => $t) {
        $mapped[] = ['key' => $key, 'label' => $t['label'], 'icon' => $t['icon'], 'summary' => $t['summary']];
    }
    apiJson(['success' => true, 'topics' => $mapped]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$analysis = gpTrainingAssistantAnalyze($input);

apiJson(['success' => true, 'plan' => $analysis]);
