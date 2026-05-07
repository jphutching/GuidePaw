<?php
require_once __DIR__ . '/includes/db_connect.php';
checkLogin();

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'dogs.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . $target);
exit;
