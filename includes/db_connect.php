<?php
session_start();

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/paywalls.php';
require_once __DIR__ . '/training_data.php';
require_once __DIR__ . '/dog_age_helpers.php';
require_once __DIR__ . '/db_core.php';
require_once __DIR__ . '/address_helpers.php';
require_once __DIR__ . '/handler_profile_helpers.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/training_helpers.php';
require_once __DIR__ . '/dog_access_helpers.php';
require_once __DIR__ . '/dog_care_helpers.php';

$dbDriver = 'pgsql';
// Accept DATABASE_URL (Render auto-injects this when fromDatabase is configured)
if ($dbUrl = appEnv('DATABASE_URL', '')) {
    $parts = parse_url($dbUrl);
    $dbHost = $parts['host'];
    $dbPort = (string)($parts['port'] ?? 5432);
    $dbName = ltrim($parts['path'] ?? '', '/');
    $dbUser = $parts['user'] ?? '';
    $dbPass = rawurldecode($parts['pass'] ?? '');
} else {
    $dbHost = appEnv('DB_HOST', 'localhost');
    $dbPort = appEnv('DB_PORT', '5432');
    $dbName = appEnv('DB_DATABASE', 'psd_app_logs');
    $dbUser = appEnv('DB_USERNAME', 'root');
    $dbPass = appEnv('DB_PASSWORD', '');
}
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    if (appEnv('APP_ENV', 'production') === 'production') {
        die('Database connection failed. Check environment variables and database availability.');
    }
    die('Connection failed: ' . $e->getMessage());
}
