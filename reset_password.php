<?php
require 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = strtolower(trim($_POST['username']));
    $key = trim($_POST['recovery_key']);
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

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
        <p class="small text-muted mb-4">Enter your details and the 10-character recovery key provided during registration.</p>
        
        <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small'>$error</div>"; ?>
        
        <div class="mb-3">
            <input type="text" name="username" placeholder="Username" class="form-control" required>
        </div>
        <div class="mb-3">
            <input type="text" name="recovery_key" placeholder="Recovery Key (e.g. A1B2C3D4E5)" class="form-control" required>
        </div>
        <div class="mb-4">
            <input type="password" name="new_password" placeholder="New Password" class="form-control" required>
        </div>
        
        <button class="btn btn-danger w-100 py-2">Reset Password</button>
        <a href="login.php" class="btn btn-link w-100 mt-2 text-decoration-none small text-muted">Back to Login</a>
    </form>
</body>
</html>
