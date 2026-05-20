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

function apiTokensColumnExists(PDO $pdo, string $column): bool {
    $column = strtolower(trim($column));
    if ($column === '') {
        return false;
    }

    $stmt = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'api_tokens'
    ");
    $columns = array_fill_keys(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
    return !empty($columns[$column]);
}

function apiEnsureTokenActiveDogColumns(PDO $pdo): void {
    $needsActiveDogId = !apiTokensColumnExists($pdo, 'active_dog_id');
    $needsActiveDogUpdatedAt = !apiTokensColumnExists($pdo, 'active_dog_updated_at');
    if (!$needsActiveDogId && !$needsActiveDogUpdatedAt) {
        return;
    }

    if ($needsActiveDogId) {
        $pdo->exec("ALTER TABLE api_tokens ADD COLUMN IF NOT EXISTS active_dog_id INTEGER NULL REFERENCES dogs(id) ON DELETE SET NULL");
    }
    if ($needsActiveDogUpdatedAt) {
        $pdo->exec("ALTER TABLE api_tokens ADD COLUMN IF NOT EXISTS active_dog_updated_at TIMESTAMP NULL");
    }
}

function issueApiToken(PDO $pdo, int $userId, string $label = 'Mobile Token'): array {
    $plain = bin2hex(random_bytes(24));
    $hash = hash('sha256', $plain);
    $prefix = substr($plain, 0, 8);

    $ttlDays = max(1, (int) appEnv('API_TOKEN_TTL_DAYS', '90'));
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $ttlDays . ' days')->format('Y-m-d H:i:s');

    apiEnsureTokenActiveDogColumns($pdo);

    $columns = ['user_id', 'token_label', 'token_prefix', 'token_hash', 'last_used_at', 'expires_at', 'revoked_at'];
    $values = [$userId, $label, $prefix, $hash, null, $expiresAt, null];
    if (apiTokensColumnExists($pdo, 'active_dog_id')) {
        $columns[] = 'active_dog_id';
        $values[] = null;
    }
    if (apiTokensColumnExists($pdo, 'active_dog_updated_at')) {
        $columns[] = 'active_dog_updated_at';
        $values[] = null;
    }
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO api_tokens (' . implode(', ', $columns) . ', created_at) VALUES (' . $placeholders . ', CURRENT_TIMESTAMP)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    return ['token' => $plain, 'prefix' => $prefix, 'expires_at' => $expiresAt];
}

function findApiTokenByPlainText(PDO $pdo, string $token): ?array {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);
    $activeDogSelect = apiTokensColumnExists($pdo, 'active_dog_id') ? 't.active_dog_id,' : 'NULL AS active_dog_id,';
    $activeDogUpdatedSelect = apiTokensColumnExists($pdo, 'active_dog_updated_at') ? 't.active_dog_updated_at,' : 'NULL AS active_dog_updated_at,';
    $stmt = $pdo->prepare('SELECT t.id, t.user_id, t.token_label, t.token_prefix, t.expires_at, t.revoked_at, t.created_at, ' . $activeDogSelect . ' ' . $activeDogUpdatedSelect . ' u.username FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? LIMIT 1');
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
    $activeDogSelect = apiTokensColumnExists($pdo, 'active_dog_id') ? 't.active_dog_id,' : 'NULL AS active_dog_id,';
    $stmt = $pdo->prepare('SELECT t.id, t.user_id, ' . $activeDogSelect . ' u.username FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at > CURRENT_TIMESTAMP) LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        apiJson(['success' => false, 'message' => 'Invalid API token.'], 401);
    }
    $update = $pdo->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([(int) $row['id']]);
    return ['id' => (int) $row['user_id'], 'username' => $row['username'], 'token_id' => (int) $row['id'], 'active_dog_id' => isset($row['active_dog_id']) ? (int) $row['active_dog_id'] : null];
}

function apiGetActiveDogId(PDO $pdo, int $tokenId): ?int {
    if (!apiTokensColumnExists($pdo, 'active_dog_id')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT active_dog_id FROM api_tokens WHERE id = ? LIMIT 1');
    $stmt->execute([$tokenId]);
    $dogId = $stmt->fetchColumn();
    return $dogId !== false && $dogId !== null ? (int) $dogId : null;
}

function apiSetActiveDogId(PDO $pdo, int $tokenId, int $userId, int $dogId): bool {
    if (!apiTokensColumnExists($pdo, 'active_dog_id') || !apiTokensColumnExists($pdo, 'active_dog_updated_at')) {
        apiEnsureTokenActiveDogColumns($pdo);
    }
    if (!apiTokensColumnExists($pdo, 'active_dog_id') || !apiTokensColumnExists($pdo, 'active_dog_updated_at')) {
        return false;
    }
    if ($dogId <= 0 || !hasDogAccess($pdo, $userId, $dogId) || !dogIsActiveLifecycle($pdo, $dogId)) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE api_tokens SET active_dog_id = ?, active_dog_updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $stmt->execute([$dogId, $tokenId, $userId]);
    return $stmt->rowCount() > 0;
}
