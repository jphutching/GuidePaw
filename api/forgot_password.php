<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/smtp_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim($body['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiJson(['success' => false, 'message' => 'A valid email address is required.'], 400);
}

// Ensure table exists (lazy-create for environments without migration runner)
if (!tableExists($pdo, 'password_reset_tokens')) {
    $pdo->exec("CREATE TABLE password_reset_tokens (
        id          SERIAL PRIMARY KEY,
        user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash  VARCHAR(64) NOT NULL UNIQUE,
        expires_at  TIMESTAMP NOT NULL,
        used_at     TIMESTAMP,
        created_at  TIMESTAMP NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE INDEX idx_prt_token_hash ON password_reset_tokens(token_hash)");
    $pdo->exec("CREATE INDEX idx_prt_user_id ON password_reset_tokens(user_id)");
}

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE LOWER(email) = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL")
        ->execute([$user['id']]);

    $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
        ->execute([$user['id'], $tokenHash, $expiresAt]);

    $resetUrl = rtrim(gpAppUrl(), '/') . '/reset_password.php?token=' . urlencode($rawToken);
    $subject  = 'GuidePaw — Password Reset';
    $body     = "Hi {$user['username']},\n\n"
        . "Someone requested a password reset for your GuidePaw account.\n\n"
        . "Click the link below to set a new password. This link expires in 1 hour.\n\n"
        . $resetUrl . "\n\n"
        . "If you did not request this, you can safely ignore this email.\n\n"
        . "— GuidePaw";
    try { gpSendMail($email, $subject, $body); } catch (Throwable $e) { error_log('Password reset email failed: ' . $e->getMessage()); }
}

// Always return success — don't reveal whether the email is registered
apiJson(['success' => true, 'message' => 'If that email address is registered, a reset link is on its way. Check your inbox.']);
