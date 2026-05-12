<?php
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/tactical_access.php';

checkLogin();
if (!function_exists('currentUserIsAdmin') || !currentUserIsAdmin()) {
    http_response_code(403);
    die('Admin access required.');
}

gpTacticalAccessEnsureSchema($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    $requestId = (int) ($_POST['request_id'] ?? 0);

    try {
        if ($action === 'approve') {
            gpTacticalAccessApprove($pdo, $requestId, (int) $_SESSION['user_id']);
            $message = 'Request approved.';
        } elseif ($action === 'deny') {
            gpTacticalAccessDeny($pdo, $requestId, (string) ($_POST['admin_notes'] ?? ''));
            $message = 'Request denied.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'denied', 'all'], true)) {
    $status = 'pending';
}
$requests = gpTacticalAccessRequests($pdo, $status);
$csrf = generateCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tactical Access Requests | GuidePaw</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body{background:#f3f6fb;color:#1f2937;padding-bottom:90px}
        .wrap{max-width:1200px;margin:0 auto;padding:18px}
        .card{background:#fff;border:1px solid #dfe3ea;border-radius:18px;padding:18px;margin:14px 0;box-shadow:0 8px 24px rgba(15,23,42,.08)}
        .meta{color:#6b7280;font-size:.92rem}
        .btn, button{display:inline-block;border:0;border-radius:12px;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;font-weight:800}
        .btn.secondary{background:transparent;color:#6b7280;border:1px solid #9ca3af}
        textarea{width:100%;min-height:88px;box-sizing:border-box}
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<main class="wrap">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div>
            <h1 class="h3 mb-1">Tactical Access Requests</h1>
            <p class="meta mb-0">Review special-access requests for verified working teams.</p>
        </div>
        <a class="btn secondary" href="admin.php">Admin Home</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success mt-3"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>

    <div class="card">
        <?php foreach (['pending', 'approved', 'denied', 'all'] as $s): ?>
            <a class="btn <?= $status === $s ? '' : 'secondary' ?>" href="?status=<?= e($s) ?>"><?= e(ucfirst($s)) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$requests): ?>
        <div class="card">No tactical access requests found.</div>
    <?php endif; ?>

    <?php foreach ($requests as $request): ?>
        <div class="card">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
                <div>
                    <h2 class="h5 mb-1"><?= e($request['full_name']) ?></h2>
                    <div class="meta">User: <?= e($request['username'] ?: ('User #' . $request['user_id'])) ?> · Email: <?= e($request['email']) ?> · Created: <?= e($request['created_at']) ?></div>
                </div>
                <span class="badge bg-secondary align-self-start"><?= e($request['status']) ?></span>
            </div>
            <div class="meta mt-2">Organization: <?= e($request['organization']) ?> · Role: <?= e($request['role_title']) ?> · Service: <?= e(ucfirst($request['service_type'])) ?></div>
            <?php if (!empty($request['verification_notes'])): ?>
                <div class="mt-3"><strong>Verification</strong><div><?= nl2br(e($request['verification_notes'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($request['reason'])): ?>
                <div class="mt-3"><strong>Reason</strong><div><?= nl2br(e($request['reason'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($request['admin_notes'])): ?>
                <div class="mt-3"><strong>Admin notes</strong><div><?= nl2br(e($request['admin_notes'])) ?></div></div>
            <?php endif; ?>

            <div class="mt-3 d-flex flex-wrap gap-2">
                <?php if ($request['status'] !== 'approved'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button>Approve</button>
                    </form>
                <?php endif; ?>
                <?php if ($request['status'] !== 'denied'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                        <input type="hidden" name="action" value="deny">
                        <textarea name="admin_notes" placeholder="Denial notes or follow-up"></textarea>
                        <button class="btn secondary mt-2">Deny</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
