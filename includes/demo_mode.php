<?php
// Demo account sandbox — auto-restores original data 15 minutes after login.
// Writes during a session go to real tables normally; on expiry everything resets.

define('GP_DEMO_RESET_SECONDS', 900); // 15 minutes

// Tables to snapshot/restore, in INSERT order (delete runs reversed).
// Format: [table, delete_by, id_col, fk_cols_to_null_on_restore]
define('GP_DEMO_TABLES', [
    'dogs'                   => ['delete' => 'user',  'id' => 'id'],
    'dog_training_profiles'  => ['delete' => 'dog',   'id' => 'id'],
    'daily_logs'             => ['delete' => 'dog',   'id' => 'id'],
    'training_goals'         => ['delete' => 'dog',   'id' => 'id'],
    'dog_medications'        => ['delete' => 'dog',   'id' => 'id'],
    'dog_vets'               => ['delete' => 'dog',   'id' => 'id'],
    'dog_vet_appointments'   => ['delete' => 'dog',   'id' => 'id'],
    'dog_certification_items'=> ['delete' => 'dog',   'id' => 'id'],
    'regression_events'      => ['delete' => 'dog',   'id' => 'id'],
]);

// Profile fields on users that are restorable (excludes auth fields).
define('GP_DEMO_USER_FIELDS', [
    'full_name','display_name','phone','public_notes',
    'backup_contact_name','backup_contact_phone',
    'home_street','home_apt','home_city','home_state','home_zip','home_address',
    'public_email','facebook_url','sms_phone','sms_notifications_enabled','user_tier',
]);

function gpIsDemoUser(PDO $pdo, int $userId): bool {
    static $cache = [];
    if (!array_key_exists($userId, $cache)) {
        $s = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND username LIKE 'demo.%' LIMIT 1");
        $s->execute([$userId]);
        $cache[$userId] = (bool) $s->fetchColumn();
    }
    return $cache[$userId];
}

function gpCheckDemoSession(PDO $pdo, int $userId): void {
    if (!gpIsDemoUser($pdo, $userId)) {
        return;
    }
    $s = $pdo->prepare("SELECT last_reset_at FROM demo_sessions WHERE user_id = ?");
    $s->execute([$userId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->prepare(
            "INSERT INTO demo_sessions (user_id) VALUES (?)
             ON CONFLICT (user_id) DO UPDATE SET started_at = NOW(), last_reset_at = NOW()"
        )->execute([$userId]);
        return;
    }

    $elapsed = time() - strtotime($row['last_reset_at']);
    if ($elapsed >= GP_DEMO_RESET_SECONDS) {
        gpRestoreDemoUser($pdo, $userId);
    }
}

function gpRestoreDemoUser(PDO $pdo, int $userId): void {
    try {
        $pdo->beginTransaction();

        // Collect ALL dog IDs: current + snapshot (user may have added/deleted dogs)
        $currentDogIds = gpDemoGetIds(
            $pdo, "SELECT id FROM dogs WHERE owner_user_id = ?", [$userId]
        );
        $snapshotDogIds = gpDemoGetIds(
            $pdo,
            "SELECT (row_data->>'id')::int FROM demo_snapshots
              WHERE snapshot_user_id = ? AND table_name = 'dogs'",
            [$userId]
        );
        $allDogIds = array_values(array_unique(array_merge($currentDogIds, $snapshotDogIds)));

        // Delete in reverse order (dog_vet_appointments first, dogs last)
        foreach (array_reverse(array_keys(GP_DEMO_TABLES)) as $table) {
            $cfg = GP_DEMO_TABLES[$table];
            if ($cfg['delete'] === 'dog' && $allDogIds) {
                $in = implode(',', array_map('intval', $allDogIds));
                $pdo->exec("DELETE FROM {$table} WHERE dog_id IN ({$in})");
            } elseif ($cfg['delete'] === 'user') {
                $pdo->prepare("DELETE FROM {$table} WHERE owner_user_id = ?")->execute([$userId]);
            }
        }

        // Restore user profile fields
        $profSnaps = gpDemoGetSnapshots($pdo, $userId, 'users_profile');
        if ($profSnaps) {
            $data   = $profSnaps[0];
            $fields = array_intersect(GP_DEMO_USER_FIELDS, array_keys($data));
            $sets   = implode(', ', array_map(fn($f) => "{$f} = ?", $fields));
            $vals   = array_map(fn($f) => $data[$f], $fields);
            $vals[] = $userId;
            $pdo->prepare("UPDATE users SET {$sets} WHERE id = ?")->execute($vals);
        }

        // Re-insert from snapshots in forward order
        foreach (array_keys(GP_DEMO_TABLES) as $table) {
            $rows = gpDemoGetSnapshots($pdo, $userId, $table);
            foreach ($rows as $row) {
                gpDemoInsertRow($pdo, $table, $row);
            }
        }

        // Restore active_dog_id on API tokens (cascade NULL'd it when dogs were deleted)
        if ($snapshotDogIds) {
            $firstDogId = $snapshotDogIds[0];
            $pdo->prepare(
                "UPDATE api_tokens SET active_dog_id = ? WHERE user_id = ? AND active_dog_id IS NULL"
            )->execute([$firstDogId, $userId]);
        }

        $pdo->prepare(
            "UPDATE demo_sessions SET last_reset_at = NOW(), started_at = NOW() WHERE user_id = ?"
        )->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("GuidePaw demo restore failed for user {$userId}: " . $e->getMessage());
    }
}

function gpCaptureDemoSnapshot(PDO $pdo, int $userId): void {
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM demo_snapshots WHERE snapshot_user_id = ?")->execute([$userId]);

        // User profile
        $fields = implode(', ', GP_DEMO_USER_FIELDS);
        $s = $pdo->prepare("SELECT {$fields} FROM users WHERE id = ?");
        $s->execute([$userId]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            gpDemoInsertSnapshot($pdo, $userId, 'users_profile', $row);
        }

        // All other tables
        $dogIds = gpDemoGetIds($pdo, "SELECT id FROM dogs WHERE owner_user_id = ?", [$userId]);

        foreach (array_keys(GP_DEMO_TABLES) as $table) {
            $cfg = GP_DEMO_TABLES[$table];
            if ($cfg['delete'] === 'user') {
                $s = $pdo->prepare("SELECT * FROM {$table} WHERE owner_user_id = ?");
                $s->execute([$userId]);
            } elseif ($cfg['delete'] === 'dog' && $dogIds) {
                $in = implode(',', array_map('intval', $dogIds));
                $s  = $pdo->query("SELECT * FROM {$table} WHERE dog_id IN ({$in})");
            } else {
                continue;
            }
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
                gpDemoInsertSnapshot($pdo, $userId, $table, $row);
            }
        }

        $pdo->prepare(
            "INSERT INTO demo_sessions (user_id) VALUES (?)
             ON CONFLICT (user_id) DO UPDATE SET last_reset_at = NOW(), started_at = NOW()"
        )->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// ── Internal helpers ────────────────────────────────────────────────────────

function gpDemoGetSnapshots(PDO $pdo, int $userId, string $table): array {
    $s = $pdo->prepare(
        "SELECT row_data FROM demo_snapshots
          WHERE snapshot_user_id = ? AND table_name = ?
          ORDER BY id"
    );
    $s->execute([$userId, $table]);
    return array_map(fn($r) => json_decode($r['row_data'], true), $s->fetchAll(PDO::FETCH_ASSOC));
}

function gpDemoInsertSnapshot(PDO $pdo, int $userId, string $table, array $row): void {
    $pdo->prepare(
        "INSERT INTO demo_snapshots (snapshot_user_id, table_name, row_data) VALUES (?, ?, ?::jsonb)"
    )->execute([$userId, $table, json_encode($row)]);
}

function gpDemoInsertRow(PDO $pdo, string $table, array $row): void {
    $cols  = implode(', ', array_keys($row));
    $marks = implode(', ', array_fill(0, count($row), '?'));
    $vals  = array_values($row);
    // OVERRIDING SYSTEM VALUE lets us re-insert with explicit identity column values
    $pdo->prepare(
        "INSERT INTO {$table} ({$cols}) OVERRIDING SYSTEM VALUE VALUES ({$marks})
         ON CONFLICT (id) DO NOTHING"
    )->execute($vals);
}

function gpDemoGetIds(PDO $pdo, string $sql, array $params): array {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}
