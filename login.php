<?php 
require 'includes/db_connect.php'; 
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

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
    $user_input = strtolower(trim($_POST['username']));
    $pass_input = $_POST['password'];
    $rememberMe = !empty($_POST['remember_me']);
    gpEnsureOnboardingColumns($pdo);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user_input]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass_input, $user['password_hash'])) {
        if ($user['is_2fa_enabled']) {
            unset($_SESSION['temp_secret']);
            $_SESSION['2fa_pending_id'] = (int) $user['id'];
            $_SESSION['2fa_pending_dog'] = $user['dog_name'];
            $_SESSION['remember_me_after_2fa'] = $rememberMe ? 1 : 0;
            header("Location: verify_2fa.php");
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['dog_name'] = $user['dog_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = gpUserRole($user);
        $_SESSION['is_admin'] = in_array($_SESSION['user_role'], ['master_admin', 'basic_admin'], true) ? 1 : 0;
        $_SESSION['remember_me'] = $rememberMe ? 1 : 0;
        $_SESSION['login_expires_at'] = time() + ($rememberMe ? 60 * 60 * 24 * 30 : 60 * 60 * 12);

        if ($rememberMe) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie(session_name(), session_id(), [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        getActiveDogId($pdo, (int) $user['id']);
        gpEnsureOnboardingColumns($pdo);
        if (gpNeedsOnboarding($user)) {
            header('Location: onboarding_setup.php');
            exit;
        }
        header("Location: " . (in_array($_SESSION['user_role'], ['master_admin', 'basic_admin'], true) ? "admin.php" : "index.php"));
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
    <?php guidepawSeoHead([
        'title' => 'GuidePaw Login',
        'description' => 'Sign in to your GuidePaw dashboard, dogs, and training tools.',
        'robots' => 'noindex,nofollow',
        'type' => 'website',
        'image' => '/assets/brand/guidepaw-logo.png',
    ]); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container p-5 bg-light">
<?php require_once 'includes/beta_banner.php'; ?>
<?php guidepawBrandHeader(); ?>
    <form method="POST" class="card p-4 mx-auto shadow" style="max-width:400px;">
        <h3 class="text-center mb-4">Handler Login</h3>
        <p class="text-center text-muted small mb-4">Sign in to continue to your GuidePaw dashboard, dogs, and training tools.</p>
        <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small'>" . e($error) . "</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'reset_success') echo "<div class='alert alert-success py-2 small'>Password updated. Please login.</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'session_expired') echo "<div class='alert alert-warning py-2 small'>Your previous session no longer matches this database. Please sign in again.</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'session_invalid') echo "<div class='alert alert-warning py-2 small'>Your session was invalid. Please sign in again.</div>"; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'login_required') echo "<div class='alert alert-info py-2 small'>Please sign in to continue.</div>"; ?>

        <div class="mb-3">
            <input type="text" name="username" class="form-control" placeholder="Username" required autocomplete="username">
        </div>
        <div class="mb-3">
            <div class="input-group">
                <input type="password" id="passwordInput" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">Show</button>
            </div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember_me">
            <label class="form-check-label small" for="rememberMe">Remember me on this device for 30 days</label>
        </div>
        <button class="btn btn-success w-100 py-2">Login</button>
        
        <div class="mt-4 d-flex justify-content-between">
            <a href="reset_password.php" class="text-decoration-none small text-muted">Forgot Password?</a>
            <a href="register.php" class="text-decoration-none small">New Account</a>
        </div>
        <div class="mt-3 p-3 bg-light border rounded small">
            <div class="fw-semibold mb-1">Research a breed first?</div>
            <div class="text-muted mb-2">Use the public questionnaire with or without an account to narrow down breed ideas before you sign in.</div>
            <a href="breed_questionnaire.php" class="btn btn-outline-primary btn-sm">Open Breed Questionnaire</a>
        </div>
        <div class="mt-3 text-center">
            <a href="feedback.php" class="text-decoration-none small text-muted">Report an issue</a>
        </div>
    </form>
    <script>
    (function () {
        var btn = document.getElementById('togglePassword');
        var input = document.getElementById('passwordInput');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.textContent = hidden ? 'Hide' : 'Show';
        });
    })();
    </script>
    <script src="app.js"></script>
</body>
</html>
