<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once __DIR__ . '/includes/roles.php';

checkLogin();
if (!currentUserIsAdmin()) {
    http_response_code(403);
    die('Admin access required.');
}

gpEnsureUserRoleColumn($pdo);

function auTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?)");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}
function auColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?)");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}
function auFetchAll(PDO $pdo, string $sql, array $params = []): array { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }
function auFetchOne(PDO $pdo, string $sql, array $params = []): ?array { $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null; }
function auUserLabel(array $user): string { return trim((string)($user['email'] ?? '')) !== '' ? (string) $user['email'] : (string) ($user['username'] ?? ('user #' . $user['id'])); }
function auIsBuiltInAdmin(array $user): bool { return strtolower(trim((string)($user['username'] ?? ''))) === 'admin'; }
function auRoleBadge(string $role): string { $role = gpNormalizeUserRole($role); $class = $role === 'admin' ? 'text-bg-danger' : ($role === 'moderator' ? 'text-bg-warning' : 'text-bg-secondary'); return '<span class="badge ' . $class . '">' . e(ucfirst($role)) . '</span>'; }
function auDeleteWhere(PDO $pdo, string $table, string $column, array $ids): int { if (!$ids || !auTableExists($pdo, $table) || !auColumnExists($pdo, $table, $column)) return 0; $placeholders = implode(',', array_fill(0, count($ids), '?')); $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})"); $stmt->execute(array_values($ids)); return $stmt->rowCount(); }
function auCollectUserData(PDO $pdo, int $userId): array { return ['exported_at' => gmdate('c'), 'user_id' => $userId, 'user' => auFetchOne($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]), 'records' => []]; }
function auExportUserData(PDO $pdo, int $userId): void { $data = auCollectUserData($pdo, $userId); if (!$data['user']) { http_response_code(404); die('User not found.'); } $label = preg_replace('/[^a-zA-Z0-9._-]+/', '_', auUserLabel($data['user'])); header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="guidepaw-user-export-' . $userId . '-' . $label . '-' . gmdate('Ymd-His') . '.json"'); echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit; }
function auDeactivateUser(PDO $pdo, int $userId, int $adminId, string $note): void { $stmt = $pdo->prepare("UPDATE users SET account_status = 'deactivated', deactivated_at = CURRENT_TIMESTAMP, deactivated_by_user_id = ?, deletion_note = NULLIF(?, '') WHERE id = ?"); $stmt->execute([$adminId, trim($note), $userId]); }
function auReactivateUser(PDO $pdo, int $userId): void { $stmt = $pdo->prepare("UPDATE users SET account_status = 'active', deactivated_at = NULL, deactivated_by_user_id = NULL WHERE id = ?"); $stmt->execute([$userId]); }
function auSetUserRole(PDO $pdo, int $userId, string $role): void { $role = gpNormalizeUserRole($role); $isAdmin = $role === 'admin' ? 1 : 0; $stmt = $pdo->prepare('UPDATE users SET user_role = ?, is_admin = ? WHERE id = ?'); $stmt->execute([$role, $isAdmin, $userId]); }
function auPurgeUser(PDO $pdo, int $userId): array { throw new RuntimeException('Purge is temporarily disabled on this compact admin page pending full retention review. Use deactivate for beta safety.'); }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($action === 'export' && $userId > 0) auExportUserData($pdo, $userId);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $target = auFetchOne($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
    $confirm = trim($_POST['confirm'] ?? '');
    $note = trim($_POST['note'] ?? '');
    try {
        if (!$target) throw new RuntimeException('User not found.');
        if ($userId === (int)$_SESSION['user_id']) throw new RuntimeException('You cannot modify your own account from this page.');
        if (auIsBuiltInAdmin($target)) throw new RuntimeException('The built-in admin account cannot be downgraded, deactivated, or purged.');
        if ($confirm !== auUserLabel($target)) throw new RuntimeException('Confirmation did not match. Type the user email/username exactly.');
        if ($action === 'set_role') { auSetUserRole($pdo, $userId, (string)($_POST['user_role'] ?? 'user')); $message = 'User role updated.'; }
        elseif ($action === 'deactivate') { auDeactivateUser($pdo, $userId, (int)$_SESSION['user_id'], $note); $message = 'User deactivated. Data was retained.'; }
        elseif ($action === 'reactivate') { auReactivateUser($pdo, $userId); $message = 'User reactivated.'; }
        elseif ($action === 'purge') { if (gpUserRole($target) === 'admin') throw new RuntimeException('Hard delete of an admin user is blocked for safety.'); $summary = auPurgeUser($pdo, $userId); $message = 'User purged. Summary: ' . json_encode($summary); }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($q !== '') { $where = "WHERE lower(coalesce(username, '')) LIKE lower(?) OR lower(coalesce(email, '')) LIKE lower(?) OR lower(coalesce(full_name, '')) LIKE lower(?)"; $params = ["%{$q}%", "%{$q}%", "%{$q}%"]; }
$users = auFetchAll($pdo, "SELECT id, username, email, full_name, phone, dog_name, is_admin, user_role, COALESCE(account_status, 'active') AS account_status, deactivated_at, created_at FROM users {$where} ORDER BY id DESC LIMIT 250", $params);
$csrf = generateCsrfToken();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Admin User Management | GuidePaw</title><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet"></head><body class="bg-light pb-5"><?php guidepawBrandHeader(); ?><?php require_once 'includes/mobile_nav.php'; ?>
<main class="container-fluid py-4"><div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Admin User Management</h1><p class="text-muted mb-0">Export, deactivate, reactivate, or change permission roles.</p></div><a class="btn btn-outline-secondary" href="admin.php">Admin Home</a></div>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="alert alert-info"><strong>Roles:</strong> Admin can access beta/admin checks and full admin tools. Moderator can support/review permitted tools. User can access regular site features. The built-in <code>admin</code> account is protected.</div>
<form method="get" class="card card-body mb-3"><label class="form-label">Search users</label><div class="input-group"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="email, username, or name"><button class="btn btn-primary">Search</button><a class="btn btn-outline-secondary" href="admin_users.php">Reset</a></div></form>
<div class="table-responsive bg-white shadow-sm"><table class="table table-striped align-middle mb-0"><thead><tr><th>ID</th><th>User</th><th>Name / Phone</th><th>Dog</th><th>Status</th><th>Role</th><th>Created</th><th style="min-width:520px;">Actions</th></tr></thead><tbody>
<?php foreach ($users as $u): ?><?php $label = auUserLabel($u); $role = gpUserRole($u); $protected = auIsBuiltInAdmin($u); ?><tr><td><?= (int)$u['id'] ?></td><td><strong><?= e($u['username'] ?? '') ?></strong><?= $protected ? ' <span class="badge text-bg-danger">Protected</span>' : '' ?><br><span class="text-muted"><?= e($u['email'] ?? '') ?></span></td><td><?= e($u['full_name'] ?? '') ?><br><span class="text-muted"><?= e($u['phone'] ?? '') ?></span></td><td><?= e($u['dog_name'] ?? '') ?></td><td><span class="badge <?= $u['account_status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($u['account_status']) ?></span><?php if ($u['deactivated_at']): ?><br><small><?= e($u['deactivated_at']) ?></small><?php endif; ?></td><td><?= auRoleBadge($role) ?></td><td><?= e($u['created_at'] ?? '') ?></td><td><div class="d-flex flex-column gap-2"><a class="btn btn-sm btn-outline-primary" href="admin_users.php?action=export&user_id=<?= (int)$u['id'] ?>">Download user data JSON</a><?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?><span class="text-muted small">Current admin account cannot be changed here.</span><?php elseif ($protected): ?><span class="text-danger small fw-bold">Built-in admin cannot be downgraded, disabled, or purged.</span><?php else: ?>
<form method="post" class="border rounded p-2 bg-light"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="set_role"><label class="form-label small mb-1">Change role. Type exactly: <code><?= e($label) ?></code></label><div class="row g-1"><div class="col-md-4"><select class="form-select form-select-sm" name="user_role"><option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option><option value="moderator" <?= $role === 'moderator' ? 'selected' : '' ?>>Moderator</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option></select></div><div class="col-md-5"><input class="form-control form-control-sm" name="confirm" placeholder="<?= e($label) ?>"></div><div class="col-md-3"><button class="btn btn-sm btn-outline-dark w-100">Save role</button></div></div></form>
<form method="post" class="border rounded p-2 bg-light"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="<?= $u['account_status'] === 'active' ? 'deactivate' : 'reactivate' ?>"><label class="form-label small mb-1">Type exactly: <code><?= e($label) ?></code></label><input class="form-control form-control-sm mb-1" name="confirm" placeholder="<?= e($label) ?>"><input class="form-control form-control-sm mb-1" name="note" placeholder="Optional note"><button class="btn btn-sm <?= $u['account_status'] === 'active' ? 'btn-warning' : 'btn-success' ?>"><?= $u['account_status'] === 'active' ? 'Deactivate / retain data' : 'Reactivate' ?></button></form>
<?php endif; ?></div></td></tr><?php endforeach; ?><?php if (!$users): ?><tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr><?php endif; ?></tbody></table></div></main></body></html>
