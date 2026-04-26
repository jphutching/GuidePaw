<?php
require 'includes/db_connect.php';
require_once 'includes/two_factor.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT username, google_2fa_secret, is_2fa_enabled, recovery_key FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    logoutSessionState();
    header('Location: login.php?msg=session_expired');
    exit;
}

$message = null;
$error = null;

if (isset($_POST['disable_2fa'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
    $stmt = $pdo->prepare('UPDATE users SET google_2fa_secret = NULL, is_2fa_enabled = 0 WHERE id = ?');
    $stmt->execute([$userId]);
    unset($_SESSION['temp_secret']);
    header('Location: settings.php?msg=2fa_disabled');
    exit;
}

if (!isset($_SESSION['temp_secret']) || !is_string($_SESSION['temp_secret']) || strlen($_SESSION['temp_secret']) < 16) {
    $_SESSION['temp_secret'] = !empty($user['is_2fa_enabled']) && !empty($user['google_2fa_secret'])
        ? (string) $user['google_2fa_secret']
        : generateTotpSecret();
}

$secret = $_SESSION['temp_secret'];
$otpLabel = appShortName() . ':' . ($user['username'] ?? ('user-' . $userId));
$otpUrl = buildTotpOtpAuthUrl($otpLabel, $secret, appShortName());

if (isset($_POST['verify'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
    $code = $_POST['code'] ?? '';
    if (verifyTotpCode($secret, $code)) {
        $stmt = $pdo->prepare('UPDATE users SET google_2fa_secret = ?, is_2fa_enabled = 1 WHERE id = ?');
        $stmt->execute([$secret, $userId]);
        unset($_SESSION['temp_secret']);
        header('Location: settings.php?msg=2fa_enabled');
        exit;
    }
    $error = 'That code did not match your authenticator app. Please try again.';
}

$csrf = generateCsrfToken();
$isEnabled = !empty($user['is_2fa_enabled']) && !empty($user['google_2fa_secret']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup 2FA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="container p-4 bg-light">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
    <div class="card shadow-sm mx-auto" style="max-width: 520px;">
        <div class="card-body">
            <h3 class="mb-3 text-center"><?= $isEnabled ? 'Manage 2FA' : 'Setup 2FA' ?></h3>
            <p class="text-muted small">Add this secret to Google Authenticator, 1Password, Authy, or another TOTP app. Then enter the current 6-digit code to enable sign-in protection.</p>

            <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="alert alert-success small"><?= e($message) ?></div><?php endif; ?>

            <div class="bg-white border rounded p-3 mb-3">
                <div class="small text-muted">Account label</div>
                <div class="fw-semibold"><?= e($otpLabel) ?></div>
                <div class="small text-muted mt-3">Secret key</div>
                <code class="d-block fs-5"><?= e($secret) ?></code>
                <div class="small text-muted mt-3">Manual OTP URI</div>
                <textarea class="form-control form-control-sm" rows="3" readonly><?= e($otpUrl) ?></textarea>
            </div>

            <form method="POST" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label">6-digit verification code</label>
                    <input type="text" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                </div>
                <button type="submit" name="verify" class="btn btn-primary w-100"><?= $isEnabled ? 'Re-verify 2FA' : 'Enable 2FA' ?></button>
            </form>

            <?php if ($isEnabled): ?>
                <form method="POST" onsubmit="return confirm('Disable 2FA for this account?');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" name="disable_2fa" class="btn btn-outline-danger w-100">Disable 2FA</button>
                </form>
            <?php endif; ?>

            <a href="settings.php" class="btn btn-link w-100 mt-3">Back to settings</a>
        </div>
    </div>
</body>
</html>
