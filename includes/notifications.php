<?php
declare(strict_types=1);

function gpEnsureNotificationsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, related_dog_id INTEGER NULL REFERENCES dogs(id) ON DELETE SET NULL, notification_type TEXT NOT NULL DEFAULT 'info', priority TEXT NOT NULL DEFAULT 'normal', title TEXT NOT NULL, body TEXT NULL, action_url TEXT NULL, is_read BOOLEAN NOT NULL DEFAULT FALSE, metadata JSONB NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at TIMESTAMP NULL)");
}

function gpEnsureNotificationPreferencesTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notification_preferences (
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        preference_key TEXT NOT NULL,
        preference_value BOOLEAN NOT NULL DEFAULT TRUE,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, preference_key)
    )");
}

function gpCreateNotification(PDO $pdo, int $userId, string $title, string $body = '', string $actionUrl = '', string $type = 'info', string $priority = 'normal', ?int $dogId = null, array $metadata = []): bool
{
    if ($userId <= 0 || trim($title) === '') return false;
    gpEnsureNotificationsTable($pdo);
    $stmt = $pdo->prepare('INSERT INTO user_notifications (user_id, related_dog_id, notification_type, priority, title, body, action_url, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb)');
    return $stmt->execute([$userId, $dogId, substr($type, 0, 80), substr($priority, 0, 40), substr($title, 0, 180), $body !== '' ? $body : null, $actionUrl !== '' ? $actionUrl : null, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}']);
}

function gpUnreadNotificationCount(PDO $pdo, int $userId): int
{
    if ($userId <= 0) return 0;
    gpEnsureNotificationsTable($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = FALSE');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function gpFetchUserNotifications(PDO $pdo, int $userId, int $limit = 80): array
{
    if ($userId <= 0) return [];
    gpEnsureNotificationsTable($pdo);
    $stmt = $pdo->prepare('SELECT n.*, d.name AS dog_name FROM user_notifications n LEFT JOIN dogs d ON d.id = n.related_dog_id WHERE n.user_id = ? ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpNotificationCategoryOptions(): array
{
    return [
        'access' => 'Access',
        'care' => 'Care',
        'admin' => 'Admin',
        'general' => 'General',
    ];
}

function gpNotificationCategoryForType(string $type): string
{
    $type = strtolower(trim($type));
    if ($type === '') {
        return 'general';
    }
    if (str_starts_with($type, 'dog_access') || str_starts_with($type, 'dog_transfer')) {
        return 'access';
    }
    if (str_starts_with($type, 'found_dog')) {
        return 'care';
    }
    if (str_starts_with($type, 'beta_') || str_starts_with($type, 'admin_') || str_starts_with($type, 'system_')) {
        return 'admin';
    }
    return 'general';
}

function gpDefaultNotificationPreferences(): array
{
    $prefs = [];
    foreach (array_keys(gpNotificationCategoryOptions()) as $category) {
        $prefs[$category] = true;
    }
    return $prefs;
}

function gpFetchNotificationPreferences(PDO $pdo, int $userId): array
{
    $prefs = gpDefaultNotificationPreferences();
    if ($userId <= 0) {
        return $prefs;
    }
    gpEnsureNotificationPreferencesTable($pdo);
    $stmt = $pdo->prepare('SELECT preference_key, preference_value FROM user_notification_preferences WHERE user_id = ?');
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = strtolower(trim((string) ($row['preference_key'] ?? '')));
        if (array_key_exists($key, $prefs)) {
            $prefs[$key] = !empty($row['preference_value']);
        }
    }
    return $prefs;
}

function gpSaveNotificationPreferences(PDO $pdo, int $userId, array $prefs): void
{
    if ($userId <= 0) {
        return;
    }
    gpEnsureNotificationPreferencesTable($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO user_notification_preferences (user_id, preference_key, preference_value, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (user_id, preference_key)
        DO UPDATE SET preference_value = EXCLUDED.preference_value, updated_at = CURRENT_TIMESTAMP
    ");
    foreach (array_keys(gpNotificationCategoryOptions()) as $key) {
        $stmt->execute([$userId, $key, !empty($prefs[$key]) ? 1 : 0]);
    }
}

function gpNotificationVisibleByPreferences(array $notification, array $prefs): bool
{
    $category = gpNotificationCategoryForType((string) ($notification['notification_type'] ?? 'info'));
    return !empty($prefs[$category] ?? true);
}

function gpDeleteNotifications(PDO $pdo, int $userId, array $ids): int
{
    if ($userId <= 0) {
        return 0;
    }
    gpEnsureNotificationsTable($pdo);
    $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM user_notifications WHERE user_id = ? AND id IN ({$placeholders})");
    $stmt->execute(array_merge([$userId], $ids));
    return $stmt->rowCount();
}

function gpMarkNotificationsRead(PDO $pdo, int $userId, array $ids = []): int
{
    if ($userId <= 0) return 0;
    gpEnsureNotificationsTable($pdo);
    if (!$ids) {
        $stmt = $pdo->prepare('UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND is_read = FALSE');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
    $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
    if (!$ids) return 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND id IN ({$placeholders})");
    $stmt->execute(array_merge([$userId], $ids));
    return $stmt->rowCount();
}
