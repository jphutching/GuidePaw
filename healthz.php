<?php
require_once __DIR__ . '/includes/db_connect.php';

try {
    $pdo->query('SELECT 1');
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'app' => appName(),
        'database' => 'ok',
        'time' => gmdate('c'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'app' => appName(),
        'database' => 'unavailable',
    ]);
}
