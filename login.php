<?php 
require 'includes/db_connect.php'; 

if (!empty($_SESSION['user_id'])) {
    $existingUser = getUserRecord($pdo, (int) $_SESSION['user_id']);
    if ($existingUser) {
        header('Location: index.php');
        exit;
    }
    logoutSessionState();
}

if (userCount($pdo) === 0) {
    header('Location: register.php?msg=setup_required');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Normalize input to lowercase to match registration
    $user_input = strtolower(trim($_POST['username']));
    $pass_input = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user_input]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass_input, $user['password_hash'])) {
        // Redirect to 2FA verification if enabled
        if ($user['is_2fa_enabled']) {
            unset($_SESSION['temp_secret']);
            $_SESSION['2fa_pending_id'] = (int) $user['id'];
            $_SESSION['2fa_pending_dog'] = $user['dog_name'];
            header("Location: verify_2fa.php");
            exit;
        }

        // Standard direct login
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['dog_name'] = $user['dog_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = !empty($user['is_admin']) ? 1 : 0;
        getActiveDogId($pdo, (int) $user['id']);
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0d6efd">
    <link rel="manifest" href="manifest.json">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container p-5 bg-light">
<?php require_once 'includes/beta_banner.php'; ?>
    <form method="POST" class="card p-4 mx-auto shadow" style="max-width:400px;">
        <h3 class="text-center mb-4">Driver Login</h3>
        <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small'>$error</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'reset_success') echo "<div class='alert alert-success py-2 small'>Password updated. Please login.</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'session_expired') echo "<div class='alert alert-warning py-2 small'>Your previous session no longer matches this database. Please sign in again.</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'session_invalid') echo "<div class='alert alert-warning py-2 small'>Your session was invalid. Please sign in again.</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'login_required') echo "<div class='alert alert-info py-2 small'>Please sign in to continue.</div>"; ?>

        <div class="mb-3">
            <input type="text" name="username" class="form-control" placeholder="Username" required autocomplete="username">
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
        </div>
        <button class="btn btn-success w-100 py-2">Login</button>
        
        <div class="mt-4 d-flex justify-content-between">
            <a href="reset_password.php" class="text-decoration-none small text-muted">Forgot Password?</a>
            <a href="register.php" class="text-decoration-none small">New Account</a>
        </div>
    </form>
    <script src="app.js"></script>
</body>
</html>
