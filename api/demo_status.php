<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/demo_mode.php';

$user = requireApiUser($pdo);
$userId = (int) $user['id'];

if (!gpIsDemoUser($pdo, $userId)) {
    apiJson(['success' => false, 'message' => 'Not a demo account.'], 403);
}

$s = $pdo->prepare("SELECT last_reset_at FROM demo_sessions WHERE user_id = ?");
$s->execute([$userId]);
$row = $s->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    apiJson(['success' => true, 'seconds_remaining' => GP_DEMO_RESET_SECONDS, 'reset_seconds' => GP_DEMO_RESET_SECONDS]);
}

$elapsed = time() - strtotime($row['last_reset_at']);
$remaining = max(0, GP_DEMO_RESET_SECONDS - $elapsed);

apiJson([
    'success'          => true,
    'seconds_remaining' => $remaining,
    'reset_seconds'    => GP_DEMO_RESET_SECONDS,
]);
