<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$username = !empty($_SESSION['username']) ? (string) $_SESSION['username'] : null;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $category = trim((string)($_POST['category'] ?? 'bug'));
    $page = trim((string)($_POST['page_url'] ?? ''));
    $email = trim((string)($_POST['contact_email'] ?? ''));
    $details = trim((string)($_POST['details'] ?? ''));
    if ($details === '') {
        $error = 'Please describe the bug or request.';
    } else {
        $dir = appFeedbackPath();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $record = [
            'submitted_at' => gmdate('c'),
            'category' => $category,
            'page_url' => $page,
            'contact_email' => $email,
            'details' => $details,
            'username' => $username,
            'user_id' => $userId,
            'app_mode' => appMode(),
            'app_name' => appName(),
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
        $file = $dir . '/feedback.ndjson';
        file_put_contents($file, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        $message = 'Thanks — your report was saved.';
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?> - Feedback</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>

<?php require_once 'includes/beta_banner.php'; ?>
<div class="page-shell mt-4" style="max-width: 880px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Bug report / feature request</h2>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <p class="text-muted">Use this page to log issues, feature ideas, or friction points during development and beta testing.</p>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="bug">Bug</option>
                        <option value="feature">Feature request</option>
                        <option value="ux">UX friction</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Page or workflow</label>
                    <input type="text" name="page_url" class="form-control" placeholder="dogs.php, onboarding, backup restore, etc.">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact email (optional)</label>
                    <input type="email" name="contact_email" class="form-control" placeholder="name@example.com">
                </div>
                <div class="col-12">
                    <label class="form-label">Details</label>
                    <textarea name="details" class="form-control" rows="7" placeholder="What happened, what you expected, or what feature would help?"></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save report</button>
        </div>
    </form>
</div>
</body>
</html>
