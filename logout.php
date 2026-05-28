<?php
require_once 'includes/db_connect.php';

$_SESSION = [];
session_destroy();

// Clear the session cookie — required when login used "Remember me" (30-day cookie)
if (isset($_COOKIE[session_name()])) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

header('Location: login.php');
exit;
