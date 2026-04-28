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

    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        require_once __DIR__ . '/db_connect.php';
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $GLOBALS['pdo']->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $isAdmin = ((int)$stmt->fetchColumn() === 1);

    $_SESSION['is_admin'] = $isAdmin ? 1 : 0;

    if (!$isAdmin) {
        error_log('GuidePaw unauthorized admin access attempt: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        header('Location: index.php?msg=admin_required');
        exit;
    }
}
