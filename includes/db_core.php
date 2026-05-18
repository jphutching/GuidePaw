<?php

function dbDriverName(): string {
    return 'pgsql';
}

function dbIsPgsql(): bool {
    return true;
}

function dbDateAdd(string $baseExpr, int $amount, string $unit): string {
    $amount = (int) $amount;
    $unit = strtoupper(trim($unit));
    if (!in_array($unit, ['SECOND','MINUTE','HOUR','DAY','MONTH','YEAR'], true)) {
        throw new InvalidArgumentException('Unsupported interval unit.');
    }
    return sprintf("%s + INTERVAL '%d %s'", $baseExpr, $amount, strtolower($unit));
}

function dbDateSub(string $baseExpr, int $amount, string $unit): string {
    return dbDateAdd($baseExpr, -1 * abs($amount), $unit);
}

function insertAndGetId(PDO $pdo, string $sqlWithoutReturning, array $params = []): int {
    $sql = $sqlWithoutReturning . ' RETURNING id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function dbNowExpression(): string {
    return 'CURRENT_TIMESTAMP';
}

function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?)");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function currentSchemaVersion(PDO $pdo): string {
    if (!tableExists($pdo, 'schema_migrations')) {
        return 'untracked';
    }
    $stmt = $pdo->query("SELECT COALESCE(MAX(version), 'none') FROM schema_migrations");
    return (string) $stmt->fetchColumn();
}

function appliedMigrationVersions(PDO $pdo): array {
    if (!tableExists($pdo, 'schema_migrations')) {
        return [];
    }
    return $pdo->query('SELECT version FROM schema_migrations ORDER BY version ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function availableMigrationFiles(string $driver): array {
    $dir = __DIR__ . '/../sql/migrations/' . 'pgsql';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function applyPendingMigrations(PDO $pdo): array {
    $driver = dbDriverName();
    $applied = array_flip(appliedMigrationVersions($pdo));
    $results = [];
    foreach (availableMigrationFiles($driver) as $path) {
        $version = basename($path);
        if (isset($applied[$version])) {
            continue;
        }
        $sql = trim((string) file_get_contents($path));
        if ($sql === '') {
            continue;
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, CURRENT_TIMESTAMP)');
            $stmt->execute([$version]);
            $pdo->commit();
            $results[] = $version;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
    return $results;
}
