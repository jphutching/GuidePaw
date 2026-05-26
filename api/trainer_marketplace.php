<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/trainer_marketplace.php';
require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/paywalls.php';
require_once __DIR__ . '/../includes/paywall_catalog.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

if (!featureEnabled($pdo, 'trainer_marketplace_enabled')) {
    apiJson(['success' => false, 'message' => 'Trainer directory is not currently enabled.'], 503);
}

gpPaywallCatalogEnsureSchema($pdo);
$requiredTier = gpPaywallCatalogItemTier($pdo, 'trainer_marketplace', 'plus');
if (!gpCurrentUserHasTierAccess($pdo, $requiredTier)) {
    apiJson([
        'success'          => false,
        'message'          => 'Trainer directory requires the ' . ucfirst($requiredTier) . ' plan.',
        'requires_upgrade' => true,
        'required_tier'    => $requiredTier,
    ], 403);
}

$entries = gpTrainerMarketplaceEntries($pdo, $userId);
$summary = gpTrainerMarketplaceSummary($entries);

// Normalize entries for JSON — dogs sub-array already serialisable
$apiEntries = array_map(static function (array $e): array {
    return [
        'trainer_name'       => (string) ($e['trainer_name']       ?? ''),
        'business_name'      => (string) ($e['business_name']      ?? ''),
        'credentials'        => (string) ($e['credentials']        ?? ''),
        'trainer_phone'      => (string) ($e['trainer_phone']      ?? ''),
        'trainer_email'      => (string) ($e['trainer_email']      ?? ''),
        'trainer_website'    => (string) ($e['trainer_website']    ?? ''),
        'training_focus'     => (string) ($e['training_focus']     ?? ''),
        'handler_experience' => (string) ($e['handler_experience'] ?? ''),
        'candidate_stage'    => (string) ($e['candidate_stage']    ?? ''),
        'notes'              => (string) ($e['notes']              ?? ''),
        'dogs'               => array_map(static fn($d) => [
            'dog_id'        => (int)    ($d['dog_id']        ?? 0),
            'dog_name'      => (string) ($d['dog_name']      ?? ''),
            'training_mode' => (string) ($d['training_mode'] ?? ''),
        ], (array) ($e['dogs'] ?? [])),
    ];
}, $entries);

apiJson([
    'success'       => true,
    'trainer_count' => (int) ($summary['trainer_count'] ?? 0),
    'entries'       => $apiEntries,
]);
