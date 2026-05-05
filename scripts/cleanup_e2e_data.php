<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/includes/db_connect.php';

$options = getopt('', ['yes', 'dry-run', 'keep-files']);
$deleteMode = isset($options['yes']);
$dryRun = !$deleteMode || isset($options['dry-run']);
$keepFiles = isset($options['keep-files']);

function e2eQident(string $name): string {
    return '"' . str_replace('"', '""', $name) . '"';
}

function e2ePlaceholders(array $values): string {
    return implode(',', array_fill(0, count($values), '?'));
}

function e2eTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = ?
        LIMIT 1
    ");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function e2eColumnExists(PDO $pdo, string $table, string $column): bool {
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

if (!e2eTableExists($pdo, 'dogs')) {
    fwrite(STDERR, "Could not find dogs table. Cleanup stopped.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $dogNameColumn = e2eColumnExists($pdo, 'dogs', 'name') ? 'name' : null;
    if (!$dogNameColumn) {
        throw new RuntimeException("Could not find dogs.name column.");
    }

    $dogStmt = $pdo->query("
        SELECT id, name
        FROM dogs
        WHERE name LIKE 'E2E Test Dog%'
        ORDER BY id
    ");
    $dogs = $dogStmt->fetchAll(PDO::FETCH_ASSOC);
    $dogIds = array_map(static fn($row) => (int) $row['id'], $dogs);

    $logIds = [];
    $mediaUrls = [];

    if (e2eTableExists($pdo, 'daily_logs')) {
        $conditions = [];
        $params = [];

        if (e2eColumnExists($pdo, 'daily_logs', 'location_name')) {
            $conditions[] = "location_name LIKE ?";
            $params[] = 'E2E Test Location%';
        }

        if (e2eColumnExists($pdo, 'daily_logs', 'handler_notes')) {
            $conditions[] = "handler_notes LIKE ?";
            $params[] = 'E2E test training log%';
        }

        if ($dogIds && e2eColumnExists($pdo, 'daily_logs', 'dog_id')) {
            $conditions[] = "dog_id IN (" . e2ePlaceholders($dogIds) . ")";
            $params = array_merge($params, $dogIds);
        }

        if ($conditions) {
            $sql = "SELECT id" .
                (e2eColumnExists($pdo, 'daily_logs', 'media_url') ? ", media_url" : ", NULL AS media_url") .
                " FROM daily_logs WHERE " . implode(" OR ", $conditions);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($logs as $log) {
                $logIds[] = (int) $log['id'];
                if (!empty($log['media_url'])) {
                    $mediaUrls[] = (string) $log['media_url'];
                }
            }
        }
    }

    $deletedCounts = [];

    if ($dogIds) {
        $tablesStmt = $pdo->query("
            SELECT table_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND column_name = 'dog_id'
            ORDER BY CASE WHEN table_name = 'dogs' THEN 2 ELSE 1 END, table_name
        ");
        $dogIdTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($dogIdTables as $table) {
            if ($table === 'dogs') {
                continue;
            }

            $sql = "DELETE FROM " . e2eQident($table) . " WHERE dog_id IN (" . e2ePlaceholders($dogIds) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($dogIds);
            $deletedCounts[$table] = ($deletedCounts[$table] ?? 0) + $stmt->rowCount();
        }
    }

    if ($logIds && e2eTableExists($pdo, 'daily_logs')) {
        $sql = "DELETE FROM daily_logs WHERE id IN (" . e2ePlaceholders($logIds) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($logIds);
        $deletedCounts['daily_logs'] = ($deletedCounts['daily_logs'] ?? 0) + $stmt->rowCount();
    }

    if ($dogIds) {
        $sql = "DELETE FROM dogs WHERE id IN (" . e2ePlaceholders($dogIds) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dogIds);
        $deletedCounts['dogs'] = ($deletedCounts['dogs'] ?? 0) + $stmt->rowCount();
    }

    echo "E2E cleanup scan complete.\n\n";

    echo "Matched E2E dogs: " . count($dogs) . "\n";
    foreach ($dogs as $dog) {
        echo " - dog #{$dog['id']}: {$dog['name']}\n";
    }

    echo "\nMatched E2E logs/media: " . count($logIds) . "\n";

    echo "\nRows that would be deleted:\n";
    if ($deletedCounts) {
        foreach ($deletedCounts as $table => $count) {
            echo " - {$table}: {$count}\n";
        }
    } else {
        echo " - none\n";
    }

    echo "\nMedia files that would be removed: " . count($mediaUrls) . "\n";
    foreach (array_unique($mediaUrls) as $url) {
        echo " - {$url}\n";
    }

    if ($dryRun) {
        $pdo->rollBack();
        echo "\nDRY RUN ONLY. Nothing was deleted.\n";
        echo "Run with --yes to delete matched E2E data.\n";
        exit(0);
    }

    $pdo->commit();

    if (!$keepFiles) {
        $removedFiles = 0;
        foreach (array_unique($mediaUrls) as $url) {
            if (!str_starts_with($url, 'uploads/')) {
                continue;
            }

            $path = $root . '/' . $url;
            if (is_file($path)) {
                unlink($path);
                $removedFiles++;
            }
        }

        echo "\nDeleted matched E2E database rows.\n";
        echo "Removed media files: {$removedFiles}\n";
    } else {
        echo "\nDeleted matched E2E database rows. Media files were kept because --keep-files was used.\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Cleanup failed: " . $e->getMessage() . "\n");
    exit(1);
}
