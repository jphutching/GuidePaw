<?php

function redirectToAuth(string $reason = 'login_required'): void {
    $target = userCount($GLOBALS['pdo']) === 0 ? 'register.php' : 'login.php';
    header('Location: ' . $target . '?msg=' . urlencode($reason));
    exit;
}

function userCount(PDO $pdo): int {
    static $count = null;
    if ($count !== null) {
        return $count;
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    return $count;
}

function logoutSessionState(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): void {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Security check failed. Please refresh and try again.');
    }
}

function getUserRecord(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function checkLogin(): void {
    if (empty($_SESSION['user_id'])) {
        redirectToAuth();
    }

    $userId = (int) $_SESSION['user_id'];
    if ($userId <= 0) {
        logoutSessionState();
        redirectToAuth('session_invalid');
    }

    gpBackfillKnownRequiredHandlerProfiles($GLOBALS['pdo']);
    gpEnsureOnboardingColumns($GLOBALS['pdo']);
    gpEnsureUserTierColumn($GLOBALS['pdo']);

    $user = getUserRecord($GLOBALS['pdo'], $userId);
    if (!$user) {
        logoutSessionState();
        redirectToAuth('session_expired');
    }

    if (array_key_exists('account_status', $user) && (string) $user['account_status'] === 'deactivated') {
        logoutSessionState();
        redirectToAuth('account_deactivated');
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['dog_name'] = $user['dog_name'] ?? '';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['user_role'] = gpUserRole($user);
    $_SESSION['is_admin'] = in_array($_SESSION['user_role'], ['master_admin', 'basic_admin'], true) ? 1 : 0;

    gpEnforceHandlerProfileCompletion($GLOBALS['pdo'], $user);
}
