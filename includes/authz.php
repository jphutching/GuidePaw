<?php

function requireLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php?msg=login_required');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();

    $sessionAdmin = !empty($_SESSION['is_admin']);

    $dbAdmin = false;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && !empty($_SESSION['user_id'])) {
        $stmt = $GLOBALS['pdo']->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $dbAdmin = ((int)$stmt->fetchColumn() === 1);
        $_SESSION['is_admin'] = $dbAdmin ? 1 : 0;
    }

    if (!$sessionAdmin && !$dbAdmin) {
        error_log('GuidePaw unauthorized admin access attempt: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        header('Location: index.php?msg=admin_required');
        exit;
    }

    if (!$dbAdmin && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        error_log('GuidePaw stale admin session rejected: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        header('Location: index.php?msg=admin_required');
        exit;
    }
}
