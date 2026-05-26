<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, token_label, token_prefix, created_at, last_used_at, revoked_at FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $tokens = array_map(static fn($t) => [
        'id'           => (int) $t['id'],
        'label'        => (string) ($t['token_label'] ?? ''),
        'prefix'       => (string) ($t['token_prefix'] ?? ''),
        'created_at'   => (string) ($t['created_at'] ?? ''),
        'last_used_at' => $t['last_used_at'] ? (string) $t['last_used_at'] : null,
        'is_active'    => empty($t['revoked_at']),
    ], $rows);
    apiJson(['success' => true, 'tokens' => $tokens]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    if ($action === 'revoke') {
        $tokenId = (int) ($input['token_id'] ?? 0);
        if (!$tokenId) {
            apiJson(['success' => false, 'message' => 'token_id required.'], 422);
        }
        $pdo->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND revoked_at IS NULL')
            ->execute([$tokenId, $userId]);
        apiJson(['success' => true, 'message' => 'Token revoked.']);
    }

    apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
}

apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
