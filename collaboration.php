<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$activeDog = requireActiveDog($pdo, $userId);
$statusMsg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_code') {
        requireDogEditor($pdo, $userId, (int) $activeDog['id']);
        $permission = ($_POST['permission'] ?? 'edit') === 'view' ? 'view' : 'edit';
        $hours = max(1, min(72, (int) ($_POST['expires_hours'] ?? 24)));
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $expiresAt = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $stmt = $pdo->prepare("INSERT INTO handler_handshakes (dog_id, code, created_by_user_id, requested_permission, expires_at) VALUES (?,?,?,?,?)");
        $stmt->execute([$activeDog['id'], $code, $userId, $permission, $expiresAt]);
        $statusMsg = 'Handshake code created: ' . $code;
    }

    if ($action === 'claim_code') {
        $code = strtoupper(cleanText($_POST['code'] ?? '', 12));
        $stmt = $pdo->prepare("SELECT * FROM handler_handshakes WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $handshake = $stmt->fetch();
        if (!$handshake) {
            $errors[] = 'Code not found.';
        } elseif (strtotime($handshake['expires_at']) < time()) {
            $pdo->prepare("UPDATE handler_handshakes SET status='expired', decided_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$handshake['id']]);
            $errors[] = 'That code has expired.';
        } elseif ((int) $handshake['created_by_user_id'] === $userId) {
            $errors[] = 'You cannot claim your own collaboration code.';
        } elseif ($handshake['status'] !== 'open') {
            $errors[] = 'That code has already been used.';
        } else {
            $pdo->prepare("UPDATE handler_handshakes SET requested_by_user_id=?, status='requested', requested_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$userId, $handshake['id']]);
            $statusMsg = 'Request sent. The owning handler still needs to approve it.';
        }
    }

    if (in_array($action, ['approve', 'decline'], true)) {
        $handshakeId = (int) ($_POST['handshake_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT hh.*, d.owner_user_id FROM handler_handshakes hh JOIN dogs d ON d.id = hh.dog_id WHERE hh.id=? LIMIT 1");
        $stmt->execute([$handshakeId]);
        $handshake = $stmt->fetch();
        if (!$handshake || (int) $handshake['owner_user_id'] !== $userId) {
            $errors[] = 'Handshake not found.';
        } else {
            if ($action === 'approve' && !empty($handshake['requested_by_user_id'])) {
                upsertDogHandlerLink($pdo, (int) $handshake['dog_id'], (int) $handshake['requested_by_user_id'], $userId, 'collaborator', (string) $handshake['requested_permission'], 'accepted');
                $pdo->prepare("UPDATE handler_handshakes SET status='approved', decided_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$handshakeId]);
                $statusMsg = 'Handler approved.';
            } else {
                $pdo->prepare("UPDATE handler_handshakes SET status='declined', decided_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$handshakeId]);
                $statusMsg = 'Request declined.';
            }
        }
    }
}

$csrf = generateCsrfToken();
$activeDogId = (int) $activeDog['id'];
$linksStmt = $pdo->prepare("SELECT dh.*, u.username FROM dog_handlers dh JOIN users u ON u.id = dh.user_id WHERE dh.dog_id=? AND dh.status='accepted' ORDER BY u.username ASC");
$linksStmt->execute([$activeDogId]);
$collaborators = $linksStmt->fetchAll();

$codesStmt = $pdo->prepare("SELECT * FROM handler_handshakes WHERE dog_id=? AND created_by_user_id=? ORDER BY created_at DESC LIMIT 10");
$codesStmt->execute([$activeDogId, $userId]);
$codes = $codesStmt->fetchAll();

$incomingStmt = $pdo->prepare("SELECT hh.*, u.username AS requester_name FROM handler_handshakes hh LEFT JOIN users u ON u.id=hh.requested_by_user_id JOIN dogs d ON d.id=hh.dog_id WHERE d.owner_user_id=? AND hh.dog_id=? AND hh.status='requested' ORDER BY hh.requested_at DESC");
$incomingStmt->execute([$userId, $activeDogId]);
$incoming = $incomingStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Handler Collaboration</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet"></head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>

<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-0">🤝 Handler Collaboration</h2><small class="text-muted">Handshake-based sharing for <?= e($activeDog['name']) ?></small></div><a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a></div>
    <?php if ($statusMsg): ?><div class="alert alert-success"><?= e($statusMsg) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <h5 class="card-title">Create a Handshake Code</h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_code">
                    <div class="col-6"><label class="form-label">Permission</label><select name="permission" class="form-select"><option value="edit">Can add/edit</option><option value="view">View only</option></select></div>
                    <div class="col-6"><label class="form-label">Expires in</label><select name="expires_hours" class="form-select"><option value="12">12 hours</option><option value="24" selected>24 hours</option><option value="48">48 hours</option><option value="72">72 hours</option></select></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Generate Code</button></div>
                </form>
            </div></div>

            <div class="card shadow-sm"><div class="card-body">
                <h5 class="card-title">Claim a Shared Dog Code</h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="claim_code">
                    <div class="col-12"><label class="form-label">Code</label><input type="text" name="code" class="form-control text-uppercase" maxlength="12" placeholder="AB12CD34" required></div>
                    <div class="col-12"><button class="btn btn-outline-primary w-100">Request Access</button></div>
                </form>
            </div></div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <h5 class="card-title">Current Collaborators</h5>
                <?php if (!$collaborators): ?><p class="text-muted mb-0">No collaborators yet.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($collaborators as $c): ?><div class="list-group-item px-0 d-flex justify-content-between"><div><div class="fw-semibold"><?= e($c['username']) ?></div><div class="small text-muted"><?= e($c['permission_level']) ?> access</div></div><span class="badge bg-secondary align-self-start"><?= e($c['role']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
            </div></div>

            <div class="card shadow-sm mb-3"><div class="card-body">
                <h5 class="card-title">Pending Requests</h5>
                <?php if (!$incoming): ?><p class="text-muted mb-0">No pending handshake requests.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($incoming as $req): ?><div class="list-group-item px-0"><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold"><?= e($req['requester_name'] ?: 'Unknown handler') ?></div><div class="small text-muted">Requested <?= e($req['requested_permission']) ?> access • Code <?= e($req['code']) ?></div></div><div class="d-flex gap-2"><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="handshake_id" value="<?= (int) $req['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm">Approve</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="handshake_id" value="<?= (int) $req['id'] ?>"><input type="hidden" name="action" value="decline"><button class="btn btn-outline-danger btn-sm">Decline</button></form></div></div></div><?php endforeach; ?></div><?php endif; ?>
            </div></div>

            <div class="card shadow-sm"><div class="card-body">
                <h5 class="card-title">Recent Handshake Codes</h5>
                <?php if (!$codes): ?><p class="text-muted mb-0">No codes created yet.</p><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Code</th><th>Permission</th><th>Status</th><th>Expires</th></tr></thead><tbody><?php foreach ($codes as $row): ?><tr><td><strong><?= e($row['code']) ?></strong></td><td><?= e($row['requested_permission']) ?></td><td><?= e($row['status']) ?></td><td><?= e(date('M d, g:i A', strtotime($row['expires_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
</body></html>
