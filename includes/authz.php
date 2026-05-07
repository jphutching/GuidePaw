<?php

require_once __DIR__ . '/roles.php';

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
    requireRole('admin');
}

function requireModerator(): void
{
    requireRole('moderator');
}
