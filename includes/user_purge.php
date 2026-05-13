<?php
declare(strict_types=1);

function gpPurgeQident(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function gpPurgeTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = ?
        )
    ");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function gpPurgeColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = ?
              AND column_name = ?
        )
    ");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function gpPurgeTableColumnMap(PDO $pdo): array
{
    $purgeColumns = [
        'actor_user_id',
        'assessed_by_user_id',
        'created_by_user_id',
        'from_user_id',
        'invited_by_user_id',
        'linked_user_id',
        'owner_user_id',
        'requested_by_user_id',
        'reviewer_user_id',
        'target_user_id',
        'to_user_id',
        'uploaded_by_user_id',
        'user_id',
    ];

    $stmt = $pdo->query("
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name NOT IN ('users')
          AND (
                column_name = 'dog_id'
             OR column_name = ANY (ARRAY[" . implode(',', array_map(static fn($column) => "'" . str_replace("'", "''", $column) . "'", $purgeColumns)) . "])
          )
        ORDER BY table_name ASC, column_name ASC
    ");

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $table = (string) ($row['table_name'] ?? '');
        $column = (string) ($row['column_name'] ?? '');
        if ($table === '' || $column === '') {
            continue;
        }
        if (!isset($map[$table])) {
            $map[$table] = [];
        }
        $map[$table][] = $column;
    }

    return $map;
}

function gpPurgeDeleteTableRows(PDO $pdo, string $table, array $columns, int $userId, array $dogIds): int
{
    $clauses = [];
    $params = [];
    $dogIdPlaceholders = $dogIds ? implode(',', array_fill(0, count($dogIds), '?')) : '';

    foreach ($columns as $column) {
        if ($column === 'dog_id') {
            if ($dogIds) {
                $clauses[] = gpPurgeQident($column) . ' IN (' . $dogIdPlaceholders . ')';
                $params = array_merge($params, array_values($dogIds));
            }
            continue;
        }

        $clauses[] = gpPurgeQident($column) . ' = ?';
        $params[] = $userId;
    }

    if (!$clauses) {
        return 0;
    }

    $sql = 'DELETE FROM ' . gpPurgeQident($table) . ' WHERE ' . implode(' OR ', $clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function gpPurgeUserAccount(PDO $pdo, int $userId, int $adminId): array
{
    $target = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ? LIMIT 1');
    $target->execute([$userId]);
    $user = $target->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $dogsStmt = $pdo->prepare('SELECT id FROM dogs WHERE owner_user_id = ? ORDER BY id');
    $dogsStmt->execute([$userId]);
    $dogIds = array_map('intval', $dogsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $map = gpPurgeTableColumnMap($pdo);
    $deleteOrder = array_keys($map);
    sort($deleteOrder, SORT_STRING);
    if (($dogsIndex = array_search('dogs', $deleteOrder, true)) !== false) {
        unset($deleteOrder[$dogsIndex]);
        $deleteOrder[] = 'dogs';
    }

    $deletedCounts = [];
    foreach ($deleteOrder as $table) {
        $columns = $map[$table] ?? [];
        if (!$columns) {
            continue;
        }
        $deleted = gpPurgeDeleteTableRows($pdo, $table, $columns, $userId, $dogIds);
        if ($deleted > 0) {
            $deletedCounts[$table] = $deleted;
        }
    }

    if (gpPurgeTableExists($pdo, 'admin_audit_log')) {
        $clauses = [];
        $params = [];
        if (gpPurgeColumnExists($pdo, 'admin_audit_log', 'target_type') && gpPurgeColumnExists($pdo, 'admin_audit_log', 'target_id')) {
            $clauses[] = "(target_type = 'users' AND target_id = ?)";
            $params[] = $userId;
        }
        if (gpPurgeColumnExists($pdo, 'admin_audit_log', 'user_id')) {
            $clauses[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($clauses) {
            $stmt = $pdo->prepare('DELETE FROM admin_audit_log WHERE ' . implode(' OR ', $clauses));
            $stmt->execute($params);
            if ($stmt->rowCount() > 0) {
                $deletedCounts['admin_audit_log'] = ($deletedCounts['admin_audit_log'] ?? 0) + $stmt->rowCount();
            }
        }
    }

    $userDelete = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $userDelete->execute([$userId]);
    $deletedCounts['users'] = $userDelete->rowCount();

    return [
        'user' => $user,
        'dog_ids' => $dogIds,
        'deleted_counts' => $deletedCounts,
    ];
}
