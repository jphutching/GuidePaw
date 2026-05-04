<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once 'includes/beta_access.php';

betaRequireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'approve') {
            $sendEmail = !empty($_POST['send_email']);
            $result = betaApproveRequest($pdo, (int) $_POST['request_id'], (int) $_SESSION['user_id'], $sendEmail);
            $message = 'Approved. Token: ' . $result['token'];
            if ($sendEmail && $result['email_sent']) {
                $message .= ' Email sent.';
            } elseif ($sendEmail && $result['email_error']) {
                $message .= ' Email failed: ' . $result['email_error'];
            }
        } elseif ($action === 'deny') {
            betaDenyRequest($pdo, (int) $_POST['request_id'], $_POST['admin_notes'] ?? '');
            $message = 'Request denied.';
        } elseif ($action === 'toggle_beta') {
            betaSet($pdo, 'beta_access_enabled', !empty($_POST['beta_access_enabled']) ? 'true' : 'false');
            betaSet($pdo, 'public_registration_enabled', !empty($_POST['public_registration_enabled']) ? 'true' : 'false');
            betaSet($pdo, 'beta_auto_email_enabled', !empty($_POST['beta_auto_email_enabled']) ? 'true' : 'false');
            $message = 'Beta settings updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'denied', 'redeemed', 'expired', 'all'], true)) {
    $status = 'pending';
}

if ($status === 'all') {
    $stmt = $pdo->query("SELECT * FROM beta_access_requests ORDER BY created_at DESC LIMIT 200");
} else {
    $stmt = $pdo->prepare("SELECT * FROM beta_access_requests WHERE status = ? ORDER BY created_at DESC LIMIT 200");
    $stmt->execute([$status]);
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$betaEnabled = betaBool($pdo, 'beta_access_enabled', true);
$publicRegistration = betaBool($pdo, 'public_registration_enabled', false);
$autoEmail = betaBool($pdo, 'beta_auto_email_enabled', false);
$csrf = generateCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Beta Access Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php guidepawBrandHeader(); ?>

<main class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">Beta Access Requests</h1>
        <a class="btn btn-outline-secondary" href="admin.php">Admin Home</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="card card-body mb-4">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="toggle_beta">
        <h2 class="h5">Access Mode</h2>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="beta_access_enabled" value="1" id="beta_access_enabled" <?= $betaEnabled ? 'checked' : '' ?>>
            <label class="form-check-label" for="beta_access_enabled">Beta token system enabled</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="public_registration_enabled" value="1" id="public_registration_enabled" <?= $publicRegistration ? 'checked' : '' ?>>
            <label class="form-check-label" for="public_registration_enabled">Public registration enabled without beta token</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="beta_auto_email_enabled" value="1" id="beta_auto_email_enabled" <?= $autoEmail ? 'checked' : '' ?>>
            <label class="form-check-label" for="beta_auto_email_enabled">Default to sending approval emails</label>
        </div>
        <button class="btn btn-primary" style="max-width:220px;">Save settings</button>
    </form>

    <div class="mb-3">
        <?php foreach (['pending','approved','redeemed','denied','all'] as $s): ?>
            <a class="btn btn-sm <?= $status === $s ? 'btn-primary' : 'btn-outline-primary' ?>" href="?status=<?= e($s) ?>"><?= e(ucfirst($s)) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive bg-white shadow-sm">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>Created</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Token</th>
                    <th>Reason</th>
                    <th style="min-width: 260px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td><?= e($r['full_name']) ?></td>
                        <td><?= e($r['email']) ?></td>
                        <td><?= e($r['phone'] ?? '') ?></td>
                        <td><span class="badge bg-secondary"><?= e($r['status']) ?></span></td>
                        <td><?= e($r['token_preview'] ?? '') ?></td>
                        <td><?= e(mb_strimwidth((string) ($r['reason'] ?? ''), 0, 120, '...')) ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending' || $r['status'] === 'approved' || $r['status'] === 'denied' || $r['status'] === 'redeemed' || $r['status'] === 'redeemed'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="send_email" value="1" <?= $autoEmail ? 'checked' : '' ?>>
                                        <label class="form-check-label small">Send email</label>
                                    </div>
                                    <button class="btn btn-sm btn-success">Approve / Reissue New Token</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'denied' && $r['status'] !== 'redeemed'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="action" value="deny">
                                    <button class="btn btn-sm btn-outline-danger">Deny</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$requests): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
