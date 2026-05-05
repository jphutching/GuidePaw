<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once 'includes/beta_access.php';
require_once 'includes/beta_notifications.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $confirmEmail = strtolower(trim($_POST['confirm_email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($fullName === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($email !== $confirmEmail) {
        $error = 'Email confirmation does not match.';
    } else {
        $requestId = betaCreateRequest($pdo, $fullName, $email, $phone, $reason);
        betaNotifyAdminOfBetaRequest($pdo, $requestId);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Request GuidePaw Beta Access</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php guidepawBrandHeader(); ?>

<main class="container py-5" style="max-width: 720px;">
    <div class="card shadow p-4">
        <h1 class="h3 mb-2">Request GuidePaw Beta Access</h1>
        <p class="text-muted">Submit your request and the GuidePaw admin will review it. Approved requests receive a one-time beta access token by email.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Request received. Watch your email for approval details.
            </div>
            <a class="btn btn-primary" href="https://guidepaw.app">Back to GuidePaw.app</a>
            <a class="btn btn-outline-secondary" href="login.php">Beta Login</a>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" novalidate>
                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input class="form-control" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm email</label>
                    <input class="form-control" type="email" name="confirm_email" required value="<?= e($_POST['confirm_email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone number optional</label>
                    <input class="form-control" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Why do you want to test GuidePaw? optional</label>
                    <textarea class="form-control" name="reason" rows="4"><?= e($_POST['reason'] ?? '') ?></textarea>
                </div>
                <button class="btn btn-primary">Submit request</button>
                <a class="btn btn-outline-secondary" href="login.php">Already approved? Login</a>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
