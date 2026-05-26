<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/smtp_mailer.php';
require_once __DIR__ . '/includes/seo.php';
require 'includes/db_connect.php';

function gpEnsurePasswordResetTokensTable(PDO $pdo): void {
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
}

gpEnsurePasswordResetTokensTable($pdo);

$error   = '';
$success = '';
$mode    = 'request'; // 'request' | 'reset' | 'sent'

$tokenParam = trim($_GET['token'] ?? '');
if ($tokenParam !== '') {
    $mode = 'reset';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_reset') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $rawToken  = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                // Invalidate any existing unused tokens for this user
                $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL")
                    ->execute([$user['id']]);

                $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $tokenHash, $expiresAt]);

                $resetUrl = rtrim(appUrl(), '/') . '/reset_password.php?token=' . urlencode($rawToken);
                $subject  = 'GuidePaw — Password Reset';
                $body     = "Hi {$user['username']},\n\n"
                    . "Someone requested a password reset for your GuidePaw account.\n\n"
                    . "Click the link below to set a new password. This link expires in 1 hour.\n\n"
                    . $resetUrl . "\n\n"
                    . "If you did not request this, you can safely ignore this email.\n\n"
                    . "— GuidePaw";
                try { gpSendMail($email, $subject, $body); } catch (Throwable $e) { error_log('Password reset email failed: ' . $e->getMessage()); }
            }

            // Always show sent message (don't reveal whether email exists)
            $mode    = 'sent';
            $success = 'If that email address is registered, a reset link is on its way. Check your inbox.';
        }

    } elseif ($action === 'do_reset') {
        $mode      = 'reset';
        $rawToken  = trim($_POST['token'] ?? '');
        $newPass   = (string) ($_POST['new_password'] ?? '');
        $confirm   = (string) ($_POST['confirm_new_password'] ?? '');
        $tokenHash = hash('sha256', $rawToken);

        if ($rawToken === '' || $newPass === '') {
            $error = 'Invalid request.';
        } elseif (strlen($newPass) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif ($newPass !== $confirm) {
            $error = 'Password confirmation does not match.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT t.id, t.user_id FROM password_reset_tokens t
                 WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW()"
            );
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                $error = 'This reset link is invalid or has expired. Please request a new one.';
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([$hash, $row['user_id']]);
                $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")
                    ->execute([$row['id']]);
                header("Location: login.php?msg=reset_success");
                exit;
            }
        }
        // Keep raw token in scope for re-rendering the form
        $tokenParam = $rawToken;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php guidepawSeoHead([
        'title'   => 'Password Reset — GuidePaw',
        'robots'  => 'noindex,nofollow',
    ]); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container p-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>

<?php if ($mode === 'request' || $mode === 'sent'): ?>
<form method="POST" class="card p-4 mx-auto shadow" style="max-width:400px;">
    <input type="hidden" name="action" value="request_reset">
    <h4 class="mb-3">Reset Password</h4>
    <p class="small text-muted mb-4">Enter the email address on your account and we'll send you a reset link.</p>
    <?php if ($error !== '')   echo "<div class='alert alert-danger py-2 small'>" . e($error) . "</div>"; ?>
    <?php if ($success !== '') echo "<div class='alert alert-success py-2 small'>" . e($success) . "</div>"; ?>
    <?php if ($mode !== 'sent'): ?>
    <div class="mb-3">
        <input type="email" name="email" class="form-control" placeholder="Email address" required autocomplete="email">
    </div>
    <button class="btn btn-primary w-100 py-2">Send Reset Link</button>
    <?php endif; ?>
    <a href="login.php" class="btn btn-link w-100 mt-2 text-decoration-none small text-muted">Back to Login</a>
</form>

<?php elseif ($mode === 'reset'): ?>
<form method="POST" class="card p-4 mx-auto shadow" style="max-width:400px;">
    <input type="hidden" name="action" value="do_reset">
    <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
    <h4 class="mb-3">Set New Password</h4>
    <p class="small text-muted mb-4">Choose a new password for your account.</p>
    <?php if ($error !== '') echo "<div class='alert alert-danger py-2 small'>" . e($error) . "</div>"; ?>
    <div class="mb-3">
        <label class="form-label" for="reset-password">New Password</label>
        <div class="input-group">
            <input type="password" id="reset-password" name="new_password" class="form-control" required autocomplete="new-password" minlength="10" placeholder="At least 10 characters">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="reset-password">Show</button>
        </div>
    </div>
    <div class="mb-4">
        <label class="form-label" for="reset-password-confirm">Confirm Password</label>
        <div class="input-group">
            <input type="password" id="reset-password-confirm" name="confirm_new_password" class="form-control" required autocomplete="new-password" minlength="10" placeholder="Repeat new password">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="reset-password-confirm">Show</button>
        </div>
        <div id="reset-password-note" class="small text-muted mt-2">Re-enter the same password to confirm.</div>
    </div>
    <button class="btn btn-danger w-100 py-2">Set New Password</button>
    <a href="reset_password.php" class="btn btn-link w-100 mt-2 text-decoration-none small text-muted">Request new link</a>
</form>
<?php endif; ?>

<?php guidepawFormUx(); ?>
<script>
(function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) return;
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.textContent = showing ? 'Show' : 'Hide';
        });
    });

    var password = document.getElementById('reset-password');
    var confirm  = document.getElementById('reset-password-confirm');
    var note     = document.getElementById('reset-password-note');
    if (!password || !confirm || !note) return;

    function updateMatchNote() {
        if (!confirm.value) { note.textContent = 'Re-enter the same password to confirm.'; note.className = 'small text-muted mt-2'; confirm.setCustomValidity(''); return; }
        if (password.value === confirm.value) { note.textContent = 'Passwords match.'; note.className = 'small text-success fw-semibold mt-2'; confirm.setCustomValidity(''); }
        else { note.textContent = 'Passwords do not match yet.'; note.className = 'small text-danger fw-semibold mt-2'; confirm.setCustomValidity('Password confirmation does not match.'); }
    }
    password.addEventListener('input', updateMatchNote);
    confirm.addEventListener('input', updateMatchNote);
})();
</script>
</body>
</html>
