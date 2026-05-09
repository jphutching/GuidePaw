<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db_connect.php';

$options = getopt('', [
    'admin-username::',
    'admin-password::',
    'smoke-password::',
    'regular-usernames::',
    'regular-password::',
    'dry-run::',
]);

$adminUsername = strtolower(trim((string) ($options['admin-username'] ?? 'admin')));
$smokePassword = (string) ($options['smoke-password'] ?? '');
$adminPassword = $smokePassword !== '' ? $smokePassword : (string) ($options['admin-password'] ?? 'admin123');
$regularAliases = array_filter(array_map('trim', explode(',', (string) ($options['regular-usernames'] ?? 'test acct,test_acct,test account,test'))));
$regularAliases = array_map('strtolower', $regularAliases);
$regularPassword = $smokePassword !== '' ? $smokePassword : (string) ($options['regular-password'] ?? 'test123');
$dryRun = strtolower((string) ($options['dry-run'] ?? 'no')) === 'yes';

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function updateSmokeUser(PDO $pdo, string $label, array $aliases, string $password, bool $isAdmin, bool $dryRun): int
{
    $hasIsAdmin = columnExists($pdo, 'users', 'is_admin');
    $hasUserRole = columnExists($pdo, 'users', 'user_role');
    $has2FaEnabled = columnExists($pdo, 'users', 'is_2fa_enabled');
    $has2FaSecret = columnExists($pdo, 'users', 'google_2fa_secret');
    $hasPasswordHash = columnExists($pdo, 'users', 'password_hash');

    $placeholders = implode(', ', array_fill(0, count($aliases), '?'));
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE lower(username) IN ({$placeholders})
        ORDER BY CASE WHEN lower(username) = ? THEN 0 ELSE 1 END, id
        LIMIT 1
    ");
    $params = array_merge($aliases, [strtolower($label)]);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException("Smoke account not found: {$label}");
    }

    $sets = [];
    $values = [];

    if ($hasPasswordHash) {
        $sets[] = 'password_hash = ?';
        $values[] = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($has2FaEnabled) {
        $sets[] = 'is_2fa_enabled = 0';
    }
    if ($has2FaSecret) {
        $sets[] = 'google_2fa_secret = NULL';
    }
    if ($hasIsAdmin) {
        $sets[] = 'is_admin = ?';
        $values[] = $isAdmin ? 1 : 0;
    }
    if ($hasUserRole) {
        $sets[] = 'user_role = ?';
        $values[] = $isAdmin ? 'admin' : 'user';
    }

    if (!$sets) {
        throw new RuntimeException("No writable columns found for {$label}");
    }

    $values[] = (int) $user['id'];
    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
    if ($dryRun) {
        echo "[dry-run] {$label}: would update user id {$user['id']} ({$user['username']})\n";
        echo "[dry-run] SQL: {$sql}\n";
        return (int) $user['id'];
    }

    $update = $pdo->prepare($sql);
    $update->execute($values);
    echo "Updated {$label}: user id {$user['id']} ({$user['username']})\n";

    return (int) $user['id'];
}

try {
    $pdo->beginTransaction();
    updateSmokeUser($pdo, 'admin', [$adminUsername], $adminPassword, true, $dryRun);
    updateSmokeUser($pdo, 'test acct', $regularAliases, $regularPassword, false, $dryRun);
    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
