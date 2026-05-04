<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once 'includes/beta_access.php';

$publicRegistration = betaBool($pdo, 'public_registration_enabled', false);
$betaEnabled = betaBool($pdo, 'beta_access_enabled', true);

$betaRequestId = $_SESSION['beta_access_request_id'] ?? null;
$prefillEmail = $_SESSION['beta_access_email'] ?? '';
$prefillName = $_SESSION['beta_access_full_name'] ?? '';

if (!$publicRegistration && (!$betaEnabled || !$betaRequestId)) {
    header('Location: beta_token.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $confirmEmail = strtolower(trim($_POST['confirm_email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Name, email, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($email !== $confirmEmail) {
        $error = 'Email confirmation does not match.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif (!$publicRegistration && strtolower($email) !== strtolower((string) $prefillEmail)) {
        $error = 'This token is assigned to a different email address.';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE lower(username) = lower(?) OR lower(COALESCE(email, '')) = lower(?) LIMIT 1");
            $check->execute([$email, $email]);
            if ($check->fetchColumn()) {
                $error = 'An account already exists for this email.';
            } else {
                $pdo->beginTransaction();
                $userId = betaInsertUserFlexible($pdo, [
                    'email' => $email,
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'password' => $password,
                    'beta_request_id' => $betaRequestId,
                ]);

                if (!$publicRegistration && $betaRequestId) {
                    betaMarkRedeemed($pdo, (int) $betaRequestId, $userId);
                }

                $pdo->commit();

                unset($_SESSION['beta_access_request_id'], $_SESSION['beta_access_email'], $_SESSION['beta_access_full_name']);
                $success = true;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = appEnv('APP_ENV', 'production') === 'production'
                ? 'Account creation failed. Please contact admin@guidepaw.app.'
                : $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create GuidePaw Handler Account</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* GUIDEPAW_PASSWORD_UX_V1 */
        .password-toggle-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            white-space: nowrap;
        }
        .password-match-note {
            font-size: .875rem;
            margin-top: .35rem;
        }
    </style>
</head>
<body class="bg-light">
<?php guidepawBrandHeader(); ?>

<main class="container py-5" style="max-width: 680px;">
    <div class="card shadow p-4">
        <?php if ($success): ?>
            <h1 class="h3">Account created</h1>
            <p>Your handler account is ready. Dog profiles can be added after login.</p>
            <a class="btn btn-primary" href="login.php">Login</a>
        <?php else: ?>
            <h1 class="h3">Create Handler Account</h1>
            <p class="text-muted">Create your handler profile first. Dog profiles are set up after login.</p>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" novalidate>
                <div class="mb-3">
                    <label class="form-label">Handler full name</label>
                    <input class="form-control" name="full_name" required value="<?= e($_POST['full_name'] ?? $prefillName) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone number</label>
                    <input class="form-control" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email / login</label>
                    <input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? $prefillEmail) ?>" <?= $prefillEmail ? 'readonly' : '' ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm email</label>
                    <input class="form-control" type="email" name="confirm_email" required value="<?= e($_POST['confirm_email'] ?? $prefillEmail) ?>" <?= $prefillEmail ? 'readonly' : '' ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="register-password">Password</label>
                    <div class="input-group">
                        <input id="register-password" class="form-control" type="password" name="password" required autocomplete="new-password" minlength="10">
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-toggle-password="register-password" aria-label="Show password">Show</button>
                    </div>
                    <div class="form-text">Use at least 10 characters.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="register-confirm-password">Verify password</label>
                    <div class="input-group">
                        <input id="register-confirm-password" class="form-control" type="password" name="confirm_password" required autocomplete="new-password" minlength="10">
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-toggle-password="register-confirm-password" aria-label="Show password">Show</button>
                    </div>
                    <div id="password-match-note" class="password-match-note text-muted">Re-enter the same password to confirm it before creating the account.</div>
                </div>
                <button class="btn btn-primary w-100">Create account</button>
            </form>
        <?php endif; ?>
    </div>
</main>
<script>
// GUIDEPAW_PASSWORD_UX_V1
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!input) return;
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
});

(function () {
    var password = document.getElementById('register-password');
    var confirm = document.getElementById('register-confirm-password');
    var note = document.getElementById('password-match-note');

    if (!password || !confirm || !note) return;

    function updateMatchNote() {
        if (!confirm.value) {
            note.textContent = 'Re-enter the same password to confirm it before creating the account.';
            note.className = 'password-match-note text-muted';
            confirm.setCustomValidity('');
            return;
        }

        if (password.value === confirm.value) {
            note.textContent = 'Passwords match.';
            note.className = 'password-match-note text-success fw-semibold';
            confirm.setCustomValidity('');
        } else {
            note.textContent = 'Passwords do not match yet.';
            note.className = 'password-match-note text-danger fw-semibold';
            confirm.setCustomValidity('Password confirmation does not match.');
        }
    }

    password.addEventListener('input', updateMatchNote);
    confirm.addEventListener('input', updateMatchNote);
})();
</script>
</body>
</html>
