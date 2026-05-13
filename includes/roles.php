<?php
declare(strict_types=1);

function gpValidUserRoles(): array
{
    return ['master_admin', 'basic_admin', 'moderator', 'pro_trainer', 'user'];
}

function gpNormalizeUserRole(?string $role): string
{
    $role = strtolower(trim((string) $role));
    $aliases = [
        'admin' => 'basic_admin',
        'basic admin' => 'basic_admin',
        'basic-admin' => 'basic_admin',
        'master admin' => 'master_admin',
        'master-admin' => 'master_admin',
        'pro trainer' => 'pro_trainer',
        'pro-trainer' => 'pro_trainer',
        'trainer' => 'pro_trainer',
        'moderator' => 'moderator',
        'mod' => 'moderator',
        'user' => 'user',
    ];
    if (isset($aliases[$role])) {
        return $aliases[$role];
    }
    return in_array($role, gpValidUserRoles(), true) ? $role : 'user';
}

function gpEnsureUserRoleColumn(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role TEXT NOT NULL DEFAULT 'user'");
    $pdo->exec("UPDATE users SET user_role = 'basic_admin' WHERE COALESCE(is_admin, 0) = 1 AND lower(coalesce(username, '')) <> 'admin' AND COALESCE(NULLIF(user_role, ''), 'user') NOT IN ('master_admin', 'basic_admin')");
    $pdo->exec("UPDATE users SET user_role = 'user' WHERE user_role IS NULL OR trim(user_role) = ''");
    $ensured = true;
}

function gpRoleRank(string $role): int
{
    return match (gpNormalizeUserRole($role)) {
        'master_admin' => 50,
        'basic_admin' => 40,
        'moderator' => 30,
        'pro_trainer' => 20,
        default => 10,
    };
}

function gpRoleDisplayLabel(?string $role): string
{
    return match (gpNormalizeUserRole($role)) {
        'master_admin' => 'Master admin',
        'basic_admin' => 'Basic admin',
        'moderator' => 'Moderator',
        'pro_trainer' => 'Pro trainer',
        default => 'User',
    };
}

function gpCanUserAccessRole(string $currentRole, string $requiredRole): bool
{
    $requiredRole = gpNormalizeUserRole($requiredRole);
    if ($requiredRole === 'basic_admin') {
        $requiredRole = 'basic_admin';
    } elseif ($requiredRole === 'moderator') {
        $requiredRole = 'moderator';
    }
    return gpRoleRank($currentRole) >= gpRoleRank($requiredRole);
}

function gpUserRole(array $user): string
{
    $username = strtolower(trim((string)($user['username'] ?? '')));
    if ($username === 'admin') {
        return 'master_admin';
    }

    $role = gpNormalizeUserRole($user['user_role'] ?? 'user');
    if (!empty($user['is_admin']) && $role !== 'master_admin') {
        return 'basic_admin';
    }

    return $role;
}

function gpCurrentUserRole(PDO $pdo): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return 'user';
    gpEnsureUserRoleColumn($pdo);
    $stmt = $pdo->prepare('SELECT username, is_admin, user_role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $role = gpUserRole($user);
    $_SESSION['user_role'] = $role;
    $_SESSION['is_admin'] = in_array($role, ['master_admin', 'basic_admin'], true) ? 1 : 0;
    return $role;
}

function gpCurrentUserIsAdmin(PDO $pdo): bool
{
    return in_array(gpCurrentUserRole($pdo), ['master_admin', 'basic_admin'], true);
}

function gpCurrentUserIsModerator(PDO $pdo): bool
{
    return in_array(gpCurrentUserRole($pdo), ['master_admin', 'basic_admin', 'moderator', 'pro_trainer'], true);
}

function gpCurrentUserIsMasterAdmin(PDO $pdo): bool
{
    return gpCurrentUserRole($pdo) === 'master_admin';
}

if (!function_exists('currentUserIsAdmin')) {
    function currentUserIsAdmin(): bool
    {
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return !empty($_SESSION['is_admin']);
        }
        return gpCurrentUserIsAdmin($GLOBALS['pdo']);
    }
}

if (!function_exists('currentUserIsModerator')) {
    function currentUserIsModerator(): bool
    {
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            $role = gpNormalizeUserRole((string) ($_SESSION['user_role'] ?? 'user'));
            return in_array($role, ['master_admin', 'basic_admin', 'moderator', 'pro_trainer'], true) || !empty($_SESSION['is_admin']);
        }
        return gpCurrentUserIsModerator($GLOBALS['pdo']);
    }
}

if (!function_exists('currentUserIsMasterAdmin')) {
    function currentUserIsMasterAdmin(): bool
    {
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return gpNormalizeUserRole((string) ($_SESSION['user_role'] ?? 'user')) === 'master_admin';
        }
        return gpCurrentUserIsMasterAdmin($GLOBALS['pdo']);
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        require_once __DIR__ . '/db_connect.php';
    }
    $role = gpNormalizeUserRole($role);
    $current = gpCurrentUserRole($GLOBALS['pdo']);
    $requiredRank = $role === 'master_admin' ? gpRoleRank('basic_admin') : gpRoleRank($role);
    if (gpRoleRank($current) < $requiredRank) {
        error_log('GuidePaw unauthorized role access attempt: required=' . $role . ' current=' . $current . ' uri=' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        header('Location: index.php?msg=role_required');
        exit;
    }
}
