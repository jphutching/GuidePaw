<?php
require 'includes/db_connect.php';
require_once 'includes/two_factor.php';

if (empty($_SESSION['2fa_pending_id'])) {
    header('Location: login.php');
    exit;
}

$pendingUserId = (int) $_SESSION['2fa_pending_id'];
$stmt = $pdo->prepare('SELECT id, username, dog_name, google_2fa_secret, is_2fa_enabled, recovery_key FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user || empty($user['is_2fa_enabled']) || empty($user['google_2fa_secret'])) {
    unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_pending_dog']);
    header('Location: login.php?msg=session_invalid');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $recoveryKey = $_POST['recovery_key'] ?? '';
    $totpValid = verifyTotpCode((string) $user['google_2fa_secret'], $code);
    $recoveryValid = canUseRecoveryKey($user, $recoveryKey);

    if ($totpValid || $recoveryValid) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['dog_name'] = $user['dog_name'] ?? 'Dog';
        $_SESSION['username'] = $user['username'] ?? '';
        getActiveDogId($pdo, (int) $user['id']);
        unset($_SESSION['2fa_pending_id'], $_SESSION['2fa_pending_dog']);
        header('Location: index.php');
        exit;
    }

    $error = 'The verification code or recovery key was not valid.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify 2FA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container p-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
    <form method="POST" class="card p-4 mx-auto shadow" style="max-width:400px;">
        <h3 class="text-center mb-3">2FA Verification</h3>
        <p class="small text-muted">Enter the 6-digit code from your authenticator app. You can also use your recovery key.</p>
        <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
        <div class="mb-3">
            <input type="text" name="code" class="form-control" placeholder="6-digit code" inputmode="numeric" autocomplete="one-time-code">
        </div>
        <div class="mb-3">
            <input type="text" name="recovery_key" class="form-control" placeholder="Recovery key (optional)">
        </div>
        <button class="btn btn-primary w-100">Verify</button>
        <a href="login.php" class="btn btn-link w-100 mt-2">Back</a>
    </form>
</body>
</html>
