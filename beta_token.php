<?php
require 'includes/db_connect.php';
require_once 'includes/beta_access.php';

$error = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $token !== '') {
    if (!betaBool($pdo, 'beta_access_enabled', true)) {
        $error = 'Beta access is currently closed.';
    } else {
        $request = betaFindValidToken($pdo, $token);
        if ($request) {
            $_SESSION['beta_access_request_id'] = (int) $request['id'];
            $_SESSION['beta_access_email'] = $request['email'];
            $_SESSION['beta_access_full_name'] = $request['full_name'];
            header('Location: register.php');
            exit;
        }
        $error = 'Invalid, expired, or already-used beta token.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Beta Token</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 640px;">
    <div class="card shadow p-4">
        <h1 class="h3">Validate Beta Access Token</h1>
        <p class="text-muted">Enter the token from your approval email to create your handler account.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input class="form-control mb-3" name="token" required value="<?= e($token) ?>" placeholder="GPB-...">
            <button class="btn btn-primary w-100">Continue to account creation</button>
        </form>
        <div class="mt-3">
            <a href="beta_request.php">Need access? Request beta access</a>
        </div>
    </div>
</main>
</body>
</html>
