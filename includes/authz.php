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

    if (empty($_SESSION['is_admin'])) {
        error_log('GuidePaw unauthorized admin access attempt: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        header('Location: index.php?msg=admin_required');
        exit;
    }
}
