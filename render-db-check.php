<?php
declare(strict_types=1);

header('Content-Type: text/plain');

function envv(string $key, ?string $fallback = null): ?string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $fallback : $value;
}

try {
    $databaseUrl = envv('DATABASE_URL');

    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);

        if ($parts === false) {
            throw new RuntimeException('DATABASE_URL could not be parsed.');
        }

        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? 5432;
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        $db = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
    } else {
        $host = envv('DB_HOST');
        $port = envv('DB_PORT', '5432');
        $db = envv('DB_DATABASE', envv('DB_NAME'));
        $user = envv('DB_USERNAME', envv('DB_USER'));
        $pass = envv('DB_PASSWORD');

        if (!$host || !$db || !$user || !$pass) {
            throw new RuntimeException('Missing database environment variables.');
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $result = $pdo->query('select current_database() as database, current_user as user, version() as version')->fetch();

    echo "GuidePaw database connection OK\n\n";
    echo "Database: " . $result['database'] . "\n";
    echo "User: " . $result['user'] . "\n";
    echo "PostgreSQL: " . $result['version'] . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "GuidePaw database connection failed\n\n";
    echo $e->getMessage() . "\n";
}
