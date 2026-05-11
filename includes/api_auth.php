<?php
require_once __DIR__ . '/db_connect.php';

function apiJson(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $m)) {
        return trim($m[1]);
    }
    $headerToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    return $headerToken !== '' ? trim($headerToken) : null;
}

function issueApiToken(PDO $pdo, int $userId, string $label = 'Mobile Token'): array {
    $plain = bin2hex(random_bytes(24));
    $hash = hash('sha256', $plain);
    $prefix = substr($plain, 0, 8);

    $ttlDays = max(1, (int) appEnv('API_TOKEN_TTL_DAYS', '90'));
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $ttlDays . ' days')->format('Y-m-d H:i:s');

    $sql = 'INSERT INTO api_tokens (user_id, token_label, token_prefix, token_hash, last_used_at, expires_at, revoked_at, created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $label, $prefix, $hash, null, $expiresAt, null]);

    return ['token' => $plain, 'prefix' => $prefix, 'expires_at' => $expiresAt];
}

function findApiTokenByPlainText(PDO $pdo, string $token): ?array {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT t.id, t.user_id, t.token_label, t.token_prefix, t.expires_at, t.revoked_at, t.created_at, u.username FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function requireApiUser(PDO $pdo): array {
    $token = getBearerToken();
    if (!$token) {
        apiJson(['success' => false, 'message' => 'Missing API token.'], 401);
    }
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT t.id, t.user_id, u.username FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at > CURRENT_TIMESTAMP) LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        apiJson(['success' => false, 'message' => 'Invalid API token.'], 401);
    }
    $update = $pdo->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([(int) $row['id']]);
    return ['id' => (int) $row['user_id'], 'username' => $row['username']];
}
