<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/two_factor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = strtolower(trim((string) ($input['username'] ?? '')));
$password = (string) ($input['password'] ?? '');
$label = trim((string) ($input['token_label'] ?? 'Mobile App')) ?: 'Mobile App';
$totpCode = (string) ($input['totp_code'] ?? $input['code'] ?? '');
$recoveryKey = (string) ($input['recovery_key'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    apiJson(['success' => false, 'message' => 'Invalid credentials.'], 401);
}

if (!empty($user['is_2fa_enabled'])) {
    $secret = (string) ($user['google_2fa_secret'] ?? '');
    $hasValidTotp = $secret !== '' && verifyTotpCode($secret, $totpCode);
    $hasValidRecoveryKey = canUseRecoveryKey($user, $recoveryKey);

    if (!$hasValidTotp && !$hasValidRecoveryKey) {
        apiJson([
            'success' => false,
            'message' => 'Two-factor authentication code required.',
            'requires_2fa' => true,
        ], 401);
    }
}

$issued = issueApiToken($pdo, (int) $user['id'], $label);
apiJson([
    'success' => true,
    'token' => $issued['token'],
    'expires_at' => $issued['expires_at'],
    'user' => [
        'id' => (int) $user['id'],
        'username' => $user['username'],
    ],
]);
