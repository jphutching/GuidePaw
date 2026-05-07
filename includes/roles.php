<?php
declare(strict_types=1);

function gpValidUserRoles(): array
{
    return ['admin', 'moderator', 'user'];
}

function gpEnsureUserRoleColumn(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role TEXT NOT NULL DEFAULT 'user'");
    $pdo->exec("UPDATE users SET user_role = 'admin' WHERE COALESCE(is_admin, 0) = 1 AND COALESCE(NULLIF(user_role, ''), 'user') <> 'admin'");
    $pdo->exec("UPDATE users SET user_role = 'user' WHERE user_role IS NULL OR trim(user_role) = ''");
    $ensured = true;
}

function gpNormalizeUserRole(?string $role): string
{
    $role = strtolower(trim((string) $role));
    return in_array($role, gpValidUserRoles(), true) ? $role : 'user';
}

function gpUserRole(array $user): string
{
    if (!empty($user['is_admin'])) return 'admin';
    return gpNormalizeUserRole($user['user_role'] ?? 'user');
}

function gpCurrentUserRole(PDO $pdo): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return 'user';
    gpEnsureUserRoleColumn($pdo);
    $stmt = $pdo->prepare('SELECT is_admin, user_role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $role = gpUserRole($user);
    $_SESSION['user_role'] = $role;
    $_SESSION['is_admin'] = $role === 'admin' ? 1 : 0;
    return $role;
}

function gpCurrentUserIsAdmin(PDO $pdo): bool
{
    return gpCurrentUserRole($pdo) === 'admin';
}

function gpCurrentUserIsModerator(PDO $pdo): bool
{
    return in_array(gpCurrentUserRole($pdo), ['admin', 'moderator'], true);
}

function currentUserIsAdmin(): bool
{
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        return !empty($_SESSION['is_admin']);
    }
    return gpCurrentUserIsAdmin($GLOBALS['pdo']);
}

function currentUserIsModerator(): bool
{
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        return in_array(($_SESSION['user_role'] ?? 'user'), ['admin', 'moderator'], true) || !empty($_SESSION['is_admin']);
    }
    return gpCurrentUserIsModerator($GLOBALS['pdo']);
}

function requireRole(string $role): void
{
    requireLogin();
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        require_once __DIR__ . '/db_connect.php';
    }
    $role = gpNormalizeUserRole($role);
    $current = gpCurrentUserRole($GLOBALS['pdo']);
    $allowed = $role === 'admin' ? ['admin'] : ($role === 'moderator' ? ['admin', 'moderator'] : ['admin', 'moderator', 'user']);
    if (!in_array($current, $allowed, true)) {
        error_log('GuidePaw unauthorized role access attempt: required=' . $role . ' current=' . $current . ' uri=' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        header('Location: index.php?msg=role_required');
        exit;
    }
}
