<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/demo_mode.php';

$user = requireApiUser($pdo);
$userId = (int) $user['id'];

if (!gpIsDemoUser($pdo, $userId)) {
    apiJson(['success' => false, 'message' => 'Not a demo account.'], 403);
}

gpRestoreDemoUser($pdo, $userId);

apiJson(['success' => true, 'message' => 'Demo data has been reset.', 'reset_seconds' => GP_DEMO_RESET_SECONDS]);
