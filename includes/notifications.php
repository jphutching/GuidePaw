<?php
declare(strict_types=1);

function gpEnsureNotificationsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, related_dog_id INTEGER NULL REFERENCES dogs(id) ON DELETE SET NULL, notification_type TEXT NOT NULL DEFAULT 'info', priority TEXT NOT NULL DEFAULT 'normal', title TEXT NOT NULL, body TEXT NULL, action_url TEXT NULL, is_read BOOLEAN NOT NULL DEFAULT FALSE, metadata JSONB NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at TIMESTAMP NULL)");
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
