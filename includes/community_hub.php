<?php
declare(strict_types=1);

require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/support_badges.php';

if (!function_exists('gpCommunityNormalizeUrl')) {
    function gpCommunityNormalizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }
}

if (!function_exists('gpCommunityDiscordUrl')) {
    function gpCommunityDiscordUrl(): string
    {
        return gpCommunityNormalizeUrl((string) (gpEnv('GUIDEPAW_DISCORD_INVITE_URL', gpEnv('GUIDEPAW_DISCORD_URL', '')) ?? ''));
    }
}

if (!function_exists('gpCommunityMerchUrl')) {
    function gpCommunityMerchUrl(): string
    {
        return gpCommunityNormalizeUrl((string) (gpEnv('GUIDEPAW_MERCH_STORE_URL', gpEnv('GUIDEPAW_SWAG_STORE_URL', '')) ?? ''));
    }
}

if (!function_exists('gpCommunitySwagItems')) {
    function gpCommunitySwagItems(): array
    {
        return [
            [
                'name' => 'GuidePaw Tee',
                'emoji' => '👕',
                'price' => '$24',
                'description' => 'Soft everyday tee with the GuidePaw mark.',
            ],
            [
                'name' => 'Handler Cap',
                'emoji' => '🧢',
                'price' => '$22',
                'description' => 'Low-profile cap for training days and errands.',
            ],
            [
                'name' => 'Swag Sticker Pack',
                'emoji' => '✨',
                'price' => '$8',
                'description' => 'Weather-safe stickers for water bottles, laptops, or crates.',
            ],
            [
                'name' => 'Training Hoodie',
                'emoji' => '🧥',
                'price' => '$42',
                'description' => 'Warm hoodie for long sessions, travel, and cool weather.',
            ],
        ];
    }
}

if (!function_exists('gpCommunityForumEnsureSchema')) {
    function gpCommunityForumEnsureSchema(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS community_forum_threads (
                id BIGSERIAL PRIMARY KEY,
                created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                category TEXT NOT NULL DEFAULT 'general',
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
                is_locked BOOLEAN NOT NULL DEFAULT FALSE,
                is_archived BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("ALTER TABLE community_forum_threads ADD COLUMN IF NOT EXISTS is_archived BOOLEAN NOT NULL DEFAULT FALSE");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS community_forum_posts (
                id BIGSERIAL PRIMARY KEY,
                thread_id BIGINT NOT NULL REFERENCES community_forum_threads(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_community_forum_threads_created_at ON community_forum_threads (created_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_community_forum_posts_thread_id ON community_forum_posts (thread_id, created_at ASC)");

        $ensured = true;
    }
}

if (!function_exists('gpCommunityForumCategories')) {
    function gpCommunityForumCategories(): array
    {
        return [
            'general' => 'General',
            'training' => 'Training',
            'swag' => 'Swag',
            'events' => 'Events',
            'help' => 'Help',
        ];
    }
}

if (!function_exists('gpCommunityForumListThreads')) {
    function gpCommunityForumListThreads(PDO $pdo, int $limit = 25, string $query = ''): array
    {
        gpCommunityForumEnsureSchema($pdo);
        $limit = max(1, min(100, (int) $limit));
        $query = trim($query);
        $params = [];
        $searchSql = '';
        if ($query !== '') {
            $needle = '%' . strtolower($query) . '%';
            $searchSql = "
                WHERE COALESCE(t.is_archived, FALSE) = FALSE AND (
                    LOWER(t.title) LIKE ?
                    OR LOWER(t.body) LIKE ?
                    OR LOWER(t.category) LIKE ?
                    OR LOWER(COALESCE(u.display_name, '')) LIKE ?
                    OR LOWER(COALESCE(u.username, '')) LIKE ?
                    OR LOWER(COALESCE(u.email, '')) LIKE ?
                )
            ";
            $params = [$needle, $needle, $needle, $needle, $needle, $needle];
        } else {
            $searchSql = "WHERE COALESCE(t.is_archived, FALSE) = FALSE";
        }
        $stmt = $pdo->prepare("
            SELECT t.*,
                   COALESCE((SELECT COUNT(*) FROM community_forum_posts p WHERE p.thread_id = t.id), 0) AS reply_count,
                   COALESCE(u.display_name, u.username, 'Handler') AS creator_name,
                   u.username AS creator_username,
                   u.email AS creator_email,
                   u.user_role AS creator_role,
                   u.is_admin AS creator_is_admin
            FROM community_forum_threads t
            LEFT JOIN users u ON u.id = t.created_by_user_id
            {$searchSql}
            ORDER BY t.is_pinned DESC, t.updated_at DESC, t.created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('gpCommunityForumGetThread')) {
    function gpCommunityForumGetThread(PDO $pdo, int $threadId): ?array
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("
            SELECT t.*,
                   COALESCE(u.display_name, u.username, 'Handler') AS creator_name,
                   u.username AS creator_username,
                   u.email AS creator_email,
                   u.user_role AS creator_role,
                   u.is_admin AS creator_is_admin
            FROM community_forum_threads t
            LEFT JOIN users u ON u.id = t.created_by_user_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$threadId]);
        $thread = $stmt->fetch();
        return $thread ?: null;
    }
}

if (!function_exists('gpCommunityForumGetPosts')) {
    function gpCommunityForumGetPosts(PDO $pdo, int $threadId): array
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("
            SELECT p.*, COALESCE(u.display_name, u.username, 'Handler') AS author_name,
                   u.username AS author_username,
                   u.email AS author_email,
                   u.user_role AS author_role,
                   u.is_admin AS author_is_admin
            FROM community_forum_posts p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.thread_id = ?
            ORDER BY p.created_at ASC, p.id ASC
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('gpCommunityForumCreateThread')) {
    function gpCommunityForumCreateThread(PDO $pdo, int $userId, string $category, string $title, string $body): int
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO community_forum_threads (created_by_user_id, category, title, body, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute([$userId, $category, $title, $body]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('gpCommunityForumAddReply')) {
    function gpCommunityForumAddReply(PDO $pdo, int $threadId, int $userId, string $body): void
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("INSERT INTO community_forum_posts (thread_id, user_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$threadId, $userId, $body]);
        $stmt = $pdo->prepare("UPDATE community_forum_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$threadId]);
    }
}

if (!function_exists('gpCommunityForumDeleteReply')) {
    function gpCommunityForumDeleteReply(PDO $pdo, int $replyId): void
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("DELETE FROM community_forum_posts WHERE id = ?");
        $stmt->execute([$replyId]);
    }
}

if (!function_exists('gpCommunityForumSetPinned')) {
    function gpCommunityForumSetPinned(PDO $pdo, int $threadId, bool $pinned): void
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("UPDATE community_forum_threads SET is_pinned = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$pinned ? 1 : 0, $threadId]);
    }
}

if (!function_exists('gpCommunityForumSetLocked')) {
    function gpCommunityForumSetLocked(PDO $pdo, int $threadId, bool $locked): void
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("UPDATE community_forum_threads SET is_locked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$locked ? 1 : 0, $threadId]);
    }
}

if (!function_exists('gpCommunityForumSetArchived')) {
    function gpCommunityForumSetArchived(PDO $pdo, int $threadId, bool $archived): void
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("UPDATE community_forum_threads SET is_archived = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$archived ? 1 : 0, $threadId]);
    }
}

if (!function_exists('gpCommunityForumDeleteThread')) {
    function gpCommunityForumDeleteThread(PDO $pdo, int $threadId): void
    {
        gpCommunityForumEnsureSchema($pdo);
        $stmt = $pdo->prepare("DELETE FROM community_forum_threads WHERE id = ?");
        $stmt->execute([$threadId]);
    }
}
