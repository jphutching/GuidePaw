<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = strtolower(trim($_POST['username']));
    $key = trim($_POST['recovery_key']);
    $new_pass_input = (string) ($_POST['new_password'] ?? '');
    $confirm_pass_input = (string) ($_POST['confirm_new_password'] ?? '');

    if ($new_pass_input === '' || $key === '' || $user === '') {
        $error = "Username, recovery key, and new password are required.";
    } elseif (strlen($new_pass_input) < 10) {
        $error = "New password must be at least 10 characters.";
    } elseif ($new_pass_input !== $confirm_pass_input) {
        $error = "Password confirmation does not match.";
    } else {
        $new_pass = password_hash($new_pass_input, PASSWORD_DEFAULT);

        // Verify key matches the specific user
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ? AND recovery_key = ?");
        $stmt->execute([$new_pass, $user, $key]);

        if ($stmt->rowCount() > 0) {
            header("Location: login.php?msg=reset_success");
            exit;
        } else {
            $error = "Incorrect Username or Recovery Key.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container p-5 bg-light">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
    <form method="POST" class="card p-4 mx-auto shadow" style="max-width:400px;">
        <h4 class="mb-3">Account Recovery</h4>
        <p class="small text-muted mb-4">Enter your details and the 10-character recovery key provided during registration. Confirm the new password before saving.</p>
        
        <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small'>" . e($error) . "</div>"; ?>
        
        <div class="mb-3">
            <input type="text" name="username" placeholder="Username" class="form-control" required>
        </div>
        <div class="mb-3">
            <input type="text" name="recovery_key" placeholder="Recovery Key (e.g. A1B2C3D4E5)" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="reset-password">New Password</label>
            <div class="input-group">
                <input type="password" id="reset-password" name="new_password" placeholder="New Password" class="form-control" required autocomplete="new-password" minlength="10">
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="reset-password" aria-label="Show password">Show</button>
            </div>
            <div class="form-text">Use at least 10 characters.</div>
        </div>
        <div class="mb-4">
            <label class="form-label" for="reset-password-confirm">Verify Password</label>
            <div class="input-group">
                <input type="password" id="reset-password-confirm" name="confirm_new_password" placeholder="Verify Password" class="form-control" required autocomplete="new-password" minlength="10">
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="reset-password-confirm" aria-label="Show password">Show</button>
            </div>
            <div id="reset-password-note" class="small text-muted mt-2">Re-enter the same password to confirm it before saving.</div>
        </div>
        
        <button class="btn btn-danger w-100 py-2">Reset Password</button>
        <a href="login.php" class="btn btn-link w-100 mt-2 text-decoration-none small text-muted">Back to Login</a>
    </form>
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
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    var password = document.getElementById('reset-password');
    var confirm = document.getElementById('reset-password-confirm');
    var note = document.getElementById('reset-password-note');

    if (!password || !confirm || !note) return;

    function updateMatchNote() {
        if (!confirm.value) {
            note.textContent = 'Re-enter the same password to confirm it before saving.';
            note.className = 'small text-muted mt-2';
            confirm.setCustomValidity('');
            return;
        }

        if (password.value === confirm.value) {
            note.textContent = 'Passwords match.';
            note.className = 'small text-success fw-semibold mt-2';
            confirm.setCustomValidity('');
        } else {
            note.textContent = 'Passwords do not match yet.';
            note.className = 'small text-danger fw-semibold mt-2';
            confirm.setCustomValidity('Password confirmation does not match.');
        }
    }

    password.addEventListener('input', updateMatchNote);
    confirm.addEventListener('input', updateMatchNote);
})();
</script>
</body>
</html>
