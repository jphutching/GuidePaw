<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/dog_access_notifications.php';
require_once 'includes/db_connect.php';
require_once 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$errors = [];
$status = $_GET['status'] ?? '';

function gpDogAccessEnsureSchema(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE dogs ADD COLUMN IF NOT EXISTS lifecycle_status TEXT NOT NULL DEFAULT 'active'");
    $pdo->exec("ALTER TABLE dogs ADD COLUMN IF NOT EXISTS lifecycle_note TEXT");
    $pdo->exec("ALTER TABLE dogs ADD COLUMN IF NOT EXISTS retired_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS access_starts_at DATE NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS access_ends_at DATE NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS revoked_at TIMESTAMP NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dog_transfer_requests (id SERIAL PRIMARY KEY, dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE, from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, to_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, keep_previous_owner_access BOOLEAN NOT NULL DEFAULT TRUE, note TEXT NULL, status TEXT NOT NULL DEFAULT 'pending', requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, responded_at TIMESTAMP NULL)");
}

function gpDogAccessFetchDog(PDO $pdo, int $dogId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username FROM dogs d JOIN users u ON u.id = d.owner_user_id LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL WHERE d.id = ? AND (d.owner_user_id = ? OR dh.id IS NOT NULL) LIMIT 1");
    $stmt->execute([$userId, $dogId, $userId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dog ?: null;
}

function gpDogAccessFetchDogAny(PDO $pdo, int $dogId): ?array
{
    $stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username FROM dogs d JOIN users u ON u.id = d.owner_user_id WHERE d.id = ? LIMIT 1");
    $stmt->execute([$dogId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dog ?: null;
}

function gpDogAccessFetchUser(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function gpDogAccessFindUser(PDO $pdo, string $identity): ?array
{
    $identity = trim($identity);
    if ($identity === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE lower(username) = lower(?) OR lower(email) = lower(?) LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function gpDogAccessOwnerOnly(PDO $pdo, int $userId, array $dog): bool { return userOwnsDog($pdo, $userId, (int) ($dog['id'] ?? 0)); }
function gpDogAccessCanEdit(PDO $pdo, array $dog, int $userId): bool { return gpDogAccessOwnerOnly($pdo, $userId, $dog) || userCanEditDog($pdo, $userId, (int) $dog['id']); }
function gpDogAccessStatusLabels(): array { return ['active'=>'Active','in_training'=>'In training','retired'=>'Retired','archived'=>'Archived','deceased'=>'Deceased','transferred'=>'Transferred']; }
function gpDogAccessCurrentStatuses(): array { return ['active', 'in_training']; }

function gpDogAccessSelectReplacementActiveDog(PDO $pdo, int $userId, int $excludeDogId = 0): void
{
    $stmt = $pdo->prepare("SELECT DISTINCT d.id
        FROM dogs d
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE (d.owner_user_id = ? OR dh.id IS NOT NULL)
          AND d.id <> ?
          AND COALESCE(NULLIF(d.lifecycle_status, ''), 'active') IN ('active', 'in_training')
        ORDER BY d.name ASC, d.id ASC
        LIMIT 1");
    $stmt->execute([$userId, $userId, $excludeDogId]);
    $replacement = (int) ($stmt->fetchColumn() ?: 0);
    if ($replacement > 0) {
        $_SESSION['active_dog_id'] = $replacement;
    } else {
        unset($_SESSION['active_dog_id']);
    }
}

function gpDogAccessFetchHandlers(PDO $pdo, int $dogId): array
{
    $stmt = $pdo->prepare("SELECT dh.*, u.username, u.email, u.display_name, CASE WHEN dh.status = 'accepted' AND dh.accepted_at IS NULL THEN 'pending' ELSE dh.status END AS access_status FROM dog_handlers dh JOIN users u ON u.id = dh.user_id WHERE dh.dog_id = ? ORDER BY CASE WHEN dh.status = 'accepted' AND dh.accepted_at IS NULL THEN 1 WHEN dh.status = 'accepted' THEN 2 WHEN dh.status = 'revoked' THEN 3 ELSE 4 END, u.username ASC");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDogAccessIncomingTransfers(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT tr.*, d.name AS dog_name, from_u.username AS from_username FROM dog_transfer_requests tr JOIN dogs d ON d.id = tr.dog_id JOIN users from_u ON from_u.id = tr.from_user_id WHERE tr.to_user_id = ? AND tr.status = 'pending' ORDER BY tr.requested_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

gpDogAccessEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['accept_transfer', 'decline_transfer'], true)) {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM dog_transfer_requests WHERE id = ? AND to_user_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$requestId, $userId, 'pending']);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            $errors[] = 'Transfer request was not found or is no longer pending.';
        } elseif ($action === 'decline_transfer') {
            $stmt = $pdo->prepare("UPDATE dog_transfer_requests SET status = 'declined', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$requestId]);
            $dogForNotify = gpDogAccessFetchDogAny($pdo, (int) $request['dog_id']) ?: [];
            $fromUser = gpDogAccessFetchUser($pdo, (int) $request['from_user_id']) ?: [];
            $toUser = gpDogAccessFetchUser($pdo, $userId) ?: [];
            gpDogAccessNotifyTransferResult($dogForNotify, $fromUser, $toUser, 'declined');
            header('Location: dog_access.php?status=transfer_declined');
            exit;
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE dogs SET owner_user_id = ?, lifecycle_status = ?, lifecycle_note = COALESCE(lifecycle_note, ?) WHERE id = ?');
                $stmt->execute([$userId, 'active', 'Ownership transferred through GuidePaw.', (int) $request['dog_id']]);
                if (!empty($request['keep_previous_owner_access'])) {
                    upsertDogHandlerLink($pdo, (int) $request['dog_id'], (int) $request['from_user_id'], $userId, 'collaborator', 'edit', 'accepted');
                } else {
                    $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP WHERE dog_id = ? AND user_id = ?");
                    $stmt->execute([(int) $request['dog_id'], (int) $request['from_user_id']]);
                }
                $stmt = $pdo->prepare("UPDATE dog_transfer_requests SET status = 'accepted', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$requestId]);
                $pdo->commit();
                $dogForNotify = gpDogAccessFetchDogAny($pdo, (int) $request['dog_id']) ?: [];
                $fromUser = gpDogAccessFetchUser($pdo, (int) $request['from_user_id']) ?: [];
                $toUser = gpDogAccessFetchUser($pdo, $userId) ?: [];
                gpDogAccessNotifyTransferResult($dogForNotify, $fromUser, $toUser, 'accepted');
                setActiveDogId($pdo, $userId, (int) $request['dog_id']);
                header('Location: dog_access.php?dog_id=' . (int) $request['dog_id'] . '&status=transfer_accepted');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
    }

    $dogId = (int) ($_POST['dog_id'] ?? 0);
    $dog = gpDogAccessFetchDog($pdo, $dogId, $userId);
    if (!$dog) { $errors[] = 'Dog profile was not found or you do not have access.'; }

    if (!$errors && $action === 'update_status') {
        if (!gpDogAccessCanEdit($pdo, $dog, $userId)) { $errors[] = 'You do not have permission to update this dog status.'; }
        else {
            $allowed = array_keys(gpDogAccessStatusLabels());
            $newStatus = $_POST['lifecycle_status'] ?? 'active';
            if (!in_array($newStatus, $allowed, true)) $newStatus = 'active';
            $note = cleanTextarea($_POST['lifecycle_note'] ?? '', 1200);
            $stmt = $pdo->prepare("UPDATE dogs SET lifecycle_status = ?, lifecycle_note = ?, retired_at = CASE WHEN ? IN ('retired','archived','deceased','transferred') THEN COALESCE(retired_at, CURRENT_TIMESTAMP) ELSE NULL END WHERE id = ?");
            $stmt->execute([$newStatus, $note ?: null, $newStatus, $dogId]);
            if (!in_array($newStatus, gpDogAccessCurrentStatuses(), true) && (int)($_SESSION['active_dog_id'] ?? 0) === $dogId) {
                gpDogAccessSelectReplacementActiveDog($pdo, $userId, $dogId);
            }
            header('Location: dog_access.php?dog_id=' . $dogId . '&status=dog_status_updated');
            exit;
        }
    }

    if (!$errors && $action === 'grant_access') {
        if (!userOwnsDog($pdo, $userId, (int) $dog['id'])) { $errors[] = 'Only the dog owner can grant handler access.'; }
        else {
            $target = gpDogAccessFindUser($pdo, (string) ($_POST['handler_identity'] ?? ''));
            if (!$target) { $errors[] = 'No GuidePaw user matched that username or email.'; }
            elseif ((int) $target['id'] === $userId) { $errors[] = 'You already own this dog profile.'; }
            else {
                $permission = ($_POST['permission_level'] ?? 'view') === 'edit' ? 'edit' : 'view';
                $role = cleanText($_POST['role'] ?? 'co-op handler', 80) ?: 'co-op handler';
                $roleLabel = gpDogHandlerRoleLabel($role);
                $endsAt = cleanDateValue($_POST['access_ends_at'] ?? '');
                upsertDogHandlerLink($pdo, $dogId, (int) $target['id'], $userId, $role, $permission, 'pending');
                $stmt = $pdo->prepare('UPDATE dog_handlers SET access_ends_at = ? WHERE dog_id = ? AND user_id = ?');
                $stmt->execute([$endsAt ?: null, $dogId, (int) $target['id']]);
                $owner = gpDogAccessFetchUser($pdo, $userId) ?: [];
                gpDogAccessNotifySharedGranted($dog, $owner, $target, $roleLabel, $permission, $endsAt ?: null);
                header('Location: dog_access.php?dog_id=' . $dogId . '&status=handler_invite_sent');
                exit;
            }
        }
    }

    if (!$errors && $action === 'revoke_access') {
        if (!userOwnsDog($pdo, $userId, (int) $dog['id'])) { $errors[] = 'Only the dog owner can revoke handler access.'; }
        else {
            $handlerId = (int) ($_POST['handler_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND dog_id = ?");
            $stmt->execute([$handlerId, $dogId]);
            header('Location: dog_access.php?dog_id=' . $dogId . '&status=handler_access_revoked');
            exit;
        }
    }

    if (!$errors && $action === 'request_transfer') {
        if (!userOwnsDog($pdo, $userId, (int) $dog['id'])) { $errors[] = 'Only the current owner can transfer this dog profile.'; }
        else {
            $target = gpDogAccessFindUser($pdo, (string) ($_POST['transfer_identity'] ?? ''));
            if (!$target) { $errors[] = 'No GuidePaw user matched that username or email.'; }
            elseif ((int) $target['id'] === $userId) { $errors[] = 'You already own this dog profile.'; }
            else {
                $keepAccess = !empty($_POST['keep_previous_owner_access']) ? 1 : 0;
                $note = cleanTextarea($_POST['transfer_note'] ?? '', 800);
                $stmt = $pdo->prepare("UPDATE dog_transfer_requests SET status = 'cancelled', responded_at = CURRENT_TIMESTAMP WHERE dog_id = ? AND status = 'pending'");
                $stmt->execute([$dogId]);
                $stmt = $pdo->prepare('INSERT INTO dog_transfer_requests (dog_id, from_user_id, to_user_id, keep_previous_owner_access, note) VALUES (?,?,?,?,?)');
                $stmt->execute([$dogId, $userId, (int) $target['id'], $keepAccess, $note ?: null]);
                $fromUser = gpDogAccessFetchUser($pdo, $userId) ?: [];
                gpDogAccessNotifyTransferSent($dog, $fromUser, $target, $note ?: '');
                $adminBody = gpDogAccessDisplayName($fromUser) . " sent a GuidePaw dog ownership transfer request.\n\n" .
                    "Dog: " . (string) ($dog['name'] ?? 'Dog') . "\n" .
                    "From: " . gpDogAccessDisplayName($fromUser) . "\n" .
                    "To: " . gpDogAccessDisplayName($target) . "\n" .
                    "Keep previous owner access: " . ($keepAccess ? 'yes' : 'no') . "\n" .
                    ($note !== '' ? "Note: {$note}\n" : '') .
                    "\nOpen Dog Access: " . gpDogAccessLink($dog) . "\n";
                $adminTelegram = "🐾 Dog transfer request\nDog: " . (string) ($dog['name'] ?? 'Dog') . "\nFrom: " . gpDogAccessDisplayName($fromUser) . "\nTo: " . gpDogAccessDisplayName($target) . "\nOpen Dog Access: " . gpDogAccessLink($dog);
                betaNotifyAdminAlert('GuidePaw dog transfer request: ' . (string) ($dog['name'] ?? 'Dog'), $adminBody, $adminTelegram);
                header('Location: dog_access.php?dog_id=' . $dogId . '&status=transfer_request_sent');
                exit;
            }
        }
    }
}

$dogs = getAccessibleDogs($pdo, $userId);
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : (int) (getActiveDogId($pdo, $userId) ?? 0);
if ($dogId <= 0 && $dogs) $dogId = (int) $dogs[0]['id'];
$dog = $dogId > 0 ? gpDogAccessFetchDog($pdo, $dogId, $userId) : null;
if ($dogId > 0 && !$dog) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dog Access &amp; Status · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3">
    <div class="alert alert-warning">Dog access profile not found or it is no longer available to this account.</div>
    <a class="btn btn-outline-secondary btn-sm" href="dogs.php">Manage Dogs</a>
</main>
</body>
</html>
<?php
    exit;
}
$handlers = $dog ? gpDogAccessFetchHandlers($pdo, (int) $dog['id']) : [];
$incomingTransfers = gpDogAccessIncomingTransfers($pdo, $userId);
$isOwner = $dog ? userOwnsDog($pdo, $userId, (int) $dog['id']) : false;
$canEdit = $dog ? gpDogAccessCanEdit($pdo, $dog, $userId) : false;
$csrf = generateCsrfToken();
$statusLabels = gpDogAccessStatusLabels();
?>
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dog Access & Status · <?= e(appName()) ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet"><style>.access-card{border-radius:20px;border:1px solid rgba(15,23,42,.08);box-shadow:0 8px 20px rgba(15,23,42,.07)}.status-pill{display:inline-flex;border-radius:999px;padding:.35rem .7rem;background:#eef6ff;color:#0d6efd;font-weight:900;font-size:.82rem;text-transform:uppercase;letter-spacing:.04em}.danger-zone{border:1px solid #fecaca;background:#fff7f7}</style></head>
<body class="pb-5 bg-light"><?php guidepawBrandHeader(); ?><?php require_once 'includes/beta_banner.php'; ?><?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3"><div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-0">Dog Access & Status</h1><div class="text-muted small">Co-op handlers, retirement/archive status, and ownership transfer.</div></div><a class="btn btn-outline-secondary btn-sm" href="dogs.php">Manage Dogs</a></div>
<?php if ($status): ?><div class="alert alert-info"><?= e(str_replace('_', ' ', $status)) ?></div><?php endif; ?><?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($incomingTransfers): ?><section class="card access-card mb-3"><div class="card-body"><h2 class="h5">Incoming Transfer Requests</h2><?php foreach ($incomingTransfers as $tr): ?><div class="border rounded-3 p-3 mb-2 bg-white"><div class="fw-bold"><?= e($tr['dog_name']) ?></div><div class="small text-muted">From <?= e($tr['from_username']) ?> · Requested <?= e(date('M j, Y', strtotime((string) $tr['requested_at']))) ?></div><?php if (!empty($tr['note'])): ?><div class="mt-2 small"><?= nl2br(e($tr['note'])) ?></div><?php endif; ?><div class="d-flex gap-2 mt-3"><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="accept_transfer"><input type="hidden" name="request_id" value="<?= (int) $tr['id'] ?>"><button class="btn btn-success btn-sm">Accept Transfer</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="decline_transfer"><input type="hidden" name="request_id" value="<?= (int) $tr['id'] ?>"><button class="btn btn-outline-danger btn-sm">Decline</button></form></div></div><?php endforeach; ?></div></section><?php endif; ?>
<?php if (!$dog): ?><div class="card access-card"><div class="card-body"><p class="text-muted mb-0">No dog profile selected yet.</p></div></div><?php else: ?>
<section class="card access-card mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-3 flex-wrap align-items-start"><div><h2 class="mb-1"><?= e($dog['name']) ?></h2><div class="text-muted">Owner: <?= e($dog['owner_username']) ?></div></div><span class="status-pill"><?= e($statusLabels[$dog['lifecycle_status'] ?? 'active'] ?? 'Active') ?></span></div><?php if (!empty($dog['lifecycle_note'])): ?><div class="alert alert-secondary mt-3 mb-0"><?= nl2br(e($dog['lifecycle_note'])) ?></div><?php endif; ?></div></section>
<section class="card access-card mb-3"><div class="card-body"><h2 class="h5">Status / Retire / Archive</h2><p class="small text-muted">Retired or archived dogs stay in history but should not be treated as current active working profiles.</p><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="dog_id" value="<?= (int) $dog['id'] ?>"><div class="col-md-4"><label class="form-label">Dog status</label><select name="lifecycle_status" class="form-select" <?= $canEdit ? '' : 'disabled' ?>><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($dog['lifecycle_status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-8"><label class="form-label">Status note</label><input type="text" name="lifecycle_note" class="form-control" value="<?= e($dog['lifecycle_note'] ?? '') ?>" placeholder="Optional note, reason, or retirement details" <?= $canEdit ? '' : 'disabled' ?>></div><?php if ($canEdit): ?><div class="col-12"><button class="btn btn-primary">Save Status</button></div><?php endif; ?></form></div></section>
<section class="card access-card mb-3"><div class="card-body"><h2 class="h5">Shared Handlers / Co-op Training</h2><p class="small text-muted">Invite trainers, helpers, family, or co-op handlers. Shared access stays pending until the recipient accepts.</p><?php if (!$handlers): ?><div class="text-muted small mb-3">No shared handlers yet.</div><?php else: ?><div class="table-responsive mb-3"><table class="table table-sm align-middle"><thead><tr><th>Handler</th><th>Role</th><th>Permission</th><th>Ends</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($handlers as $handler): ?><tr><td><?= e($handler['display_name'] ?: $handler['username']) ?><div class="small text-muted"><?= e($handler['email'] ?? '') ?></div></td><td><?= e($handler['role'] ?? '') ?></td><td><?= e($handler['permission_level'] ?? '') ?></td><td><?= e($handler['access_ends_at'] ?? '') ?></td><td><?= e($handler['access_status'] ?? '') ?></td><td><?php if ($isOwner && ($handler['access_status'] ?? '') !== 'revoked'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="revoke_access"><input type="hidden" name="dog_id" value="<?= (int) $dog['id'] ?>"><input type="hidden" name="handler_id" value="<?= (int) $handler['id'] ?>"><button class="btn btn-outline-danger btn-sm">Revoke</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php if ($isOwner): ?><form method="post" class="row g-2"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="grant_access"><input type="hidden" name="dog_id" value="<?= (int) $dog['id'] ?>"><div class="col-md-5"><label class="form-label">GuidePaw username or email</label><input class="form-control" name="handler_identity" required></div><div class="col-md-3"><label class="form-label">Role</label><input class="form-control" name="role" value="Co-op handler"></div><div class="col-md-2"><label class="form-label">Permission</label><select class="form-select" name="permission_level"><option value="view">Viewer</option><option value="edit">Contributor / Editor</option></select></div><div class="col-md-2"><label class="form-label">End date</label><input class="form-control" type="date" name="access_ends_at"></div><div class="col-12"><button class="btn btn-success w-100">Send Invite</button></div></form><?php else: ?><div class="text-muted small">Only the owner can send or revoke co-op handler invites.</div><?php endif; ?></div></section>
<section class="card access-card danger-zone mb-3"><div class="card-body"><h2 class="h5">Transfer Ownership</h2><p class="small text-muted">Permanent ownership transfer requires the receiving GuidePaw user to accept. Dog history stays attached to the dog profile.</p><?php if ($isOwner): ?><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="request_transfer"><input type="hidden" name="dog_id" value="<?= (int) $dog['id'] ?>"><div class="col-md-6"><label class="form-label">Receiving username or email</label><input class="form-control" name="transfer_identity" required></div><div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="keep_previous_owner_access" id="keepAccess" checked><label class="form-check-label" for="keepAccess">Keep me as editor after transfer</label></div></div><div class="col-12"><label class="form-label">Transfer note</label><textarea class="form-control" name="transfer_note" rows="2" placeholder="Optional note for the receiving handler"></textarea></div><div class="col-12"><button class="btn btn-danger w-100">Send Transfer Request</button></div></form><?php else: ?><div class="text-muted small">Only the current owner can transfer this dog profile.</div><?php endif; ?></div></section><?php endif; ?></main><?php guidepawFormUx(); ?><script src="app.js"></script></body></html>
