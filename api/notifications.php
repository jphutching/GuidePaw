<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/dog_access_notifications.php';
require_once __DIR__ . '/../includes/dog_access_helpers.php';
require_once __DIR__ . '/../includes/handler_profile_helpers.php';

function gpApiNotificationRow(array $notification): array
{
    $type = (string) ($notification['notification_type'] ?? 'info');
    return [
        'id' => (int) ($notification['id'] ?? 0),
        'related_dog_id' => (int) ($notification['related_dog_id'] ?? 0),
        'dog_name' => (string) ($notification['dog_name'] ?? ''),
        'notification_type' => $type,
        'category' => gpNotificationCategoryForType($type),
        'priority' => (string) ($notification['priority'] ?? 'normal'),
        'title' => (string) ($notification['title'] ?? ''),
        'body' => (string) ($notification['body'] ?? ''),
        'action_url' => (string) ($notification['action_url'] ?? ''),
        'is_read' => !empty($notification['is_read']),
        'created_at' => (string) ($notification['created_at'] ?? ''),
        'read_at' => (string) ($notification['read_at'] ?? ''),
    ];
}

function gpApiCurrentUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $userId, 'username' => ''];
}

function gpApiNotificationIdsFromInput($input): array
{
    if (is_array($input)) {
        return array_values(array_filter(array_map('intval', $input), static fn(int $id): bool => $id > 0));
    }
    $text = trim((string) $input);
    if ($text === '') {
        return [];
    }
    return array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $text) ?: []), static fn(int $id): bool => $id > 0));
}

function gpApiNotificationCategoriesFromInput($input): array
{
    $allowed = array_keys(gpNotificationCategoryOptions());
    $keys = is_array($input) ? $input : (preg_split('/[,\s]+/', trim((string) $input)) ?: []);
    $prefs = array_fill_keys($allowed, false);
    foreach ($keys as $key) {
        $key = strtolower(trim((string) $key));
        if (array_key_exists($key, $prefs)) {
            $prefs[$key] = true;
        }
    }
    return $prefs;
}

function gpApiNotificationsResponse(PDO $pdo, int $userId): array
{
    $notifications = gpFetchUserNotifications($pdo, $userId, 100);
    $preferences = gpFetchNotificationPreferences($pdo, $userId);
    $visibleNotifications = array_values(array_filter(
        $notifications,
        static fn(array $notice): bool => gpNotificationVisibleByPreferences($notice, $preferences)
    ));
    $pendingInvites = gpDogAccessPendingInvites($pdo, $userId);

    return [
        'success' => true,
        'user_id' => $userId,
        'username' => (string) (gpApiCurrentUser($pdo, $userId)['username'] ?? ''),
        'active_dog_id' => (int) (getActiveDogId($pdo, $userId) ?: 0),
        'unread_count' => gpUnreadNotificationCount($pdo, $userId),
        'visible_unread_count' => count(array_filter($visibleNotifications, static fn(array $notice): bool => empty($notice['is_read']))),
        'hidden_count' => max(0, count($notifications) - count($visibleNotifications)),
        'preferences' => $preferences,
        'notifications' => array_map('gpApiNotificationRow', $visibleNotifications),
        'pending_invites' => $pendingInvites,
    ];
}

$user = requireApiUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    apiJson(gpApiNotificationsResponse($pdo, $userId));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = strtolower(trim((string) ($input['action'] ?? '')));
if ($action === '') {
    apiJson(['success' => false, 'message' => 'Missing action.'], 422);
}

try {
    if ($action === 'save_notification_preferences') {
        $prefs = gpApiNotificationCategoriesFromInput($input['categories'] ?? []);
        gpSaveNotificationPreferences($pdo, $userId, $prefs);
        apiJson(array_merge(['success' => true, 'message' => 'Notification preferences saved.'], gpApiNotificationsResponse($pdo, $userId)));
    }

    if ($action === 'mark_all_read') {
        gpMarkNotificationsRead($pdo, $userId);
        apiJson(array_merge(['success' => true, 'message' => 'Notifications marked read.'], gpApiNotificationsResponse($pdo, $userId)));
    }

    if ($action === 'mark_read') {
        $notificationIds = gpApiNotificationIdsFromInput($input['notification_id'] ?? ($input['notification_ids'] ?? []));
        if (!$notificationIds) {
            apiJson(['success' => false, 'message' => 'Missing notification id.'], 422);
        }
        gpMarkNotificationsRead($pdo, $userId, $notificationIds);
        apiJson(array_merge(['success' => true, 'message' => 'Notification marked read.'], gpApiNotificationsResponse($pdo, $userId)));
    }

    if ($action === 'delete_selected_notifications') {
        $notificationIds = gpApiNotificationIdsFromInput($input['notification_ids'] ?? []);
        if (!$notificationIds) {
            apiJson(['success' => false, 'message' => 'Select at least one notification.'], 422);
        }
        $deleted = gpDeleteNotifications($pdo, $userId, $notificationIds);
        apiJson(array_merge(
            ['success' => true, 'message' => 'Deleted ' . $deleted . ' notification' . ($deleted === 1 ? '' : 's') . '.'],
            gpApiNotificationsResponse($pdo, $userId)
        ));
    }

    if (in_array($action, ['accept_dog_access_invite', 'decline_dog_access_invite'], true)) {
        $handlerId = (int) ($input['handler_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT dh.*, d.name AS dog_name, d.owner_user_id, owner.username AS owner_username, owner.display_name AS owner_display_name, owner.public_email AS owner_public_email, owner.email AS owner_email
            FROM dog_handlers dh
            JOIN dogs d ON d.id = dh.dog_id
            JOIN users owner ON owner.id = d.owner_user_id
            WHERE dh.id = ? AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NULL
            LIMIT 1");
        $stmt->execute([$handlerId, $userId]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            apiJson(['success' => false, 'message' => 'Invite was not found or is no longer pending.'], 404);
        }
        $dog = gpDogAccessFetchDogById($pdo, (int) $invite['dog_id']) ?: ['id' => (int) $invite['dog_id'], 'name' => (string) ($invite['dog_name'] ?? 'Dog')];
        $owner = gpDogAccessFetchUserById($pdo, (int) ($invite['invited_by_user_id'] ?? 0)) ?: [
            'id' => (int) ($invite['owner_user_id'] ?? 0),
            'username' => (string) ($invite['owner_username'] ?? ''),
            'display_name' => (string) ($invite['owner_display_name'] ?? ''),
            'public_email' => (string) ($invite['owner_public_email'] ?? ''),
            'email' => (string) ($invite['owner_email'] ?? ''),
        ];
        $recipient = gpDogAccessFetchUserById($pdo, $userId) ?: [];
        $pdo->beginTransaction();
        try {
            if ($action === 'accept_dog_access_invite') {
                $role = gpDogHandlerRoleLabel((string) ($invite['role'] ?? 'co-op handler'));
                upsertDogHandlerLink($pdo, (int) $invite['dog_id'], $userId, (int) $invite['invited_by_user_id'], $role, (string) ($invite['permission_level'] ?? 'view'), 'accepted');
                $stmt = $pdo->prepare('UPDATE dog_handlers SET access_ends_at = ? WHERE id = ?');
                $stmt->execute([$invite['access_ends_at'] ?? null, $handlerId]);
                $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
                $stmt->execute([$userId, (int) $invite['dog_id']]);
                $pdo->commit();
                gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'accepted');
                apiJson(array_merge(['success' => true, 'message' => 'Dog access invite accepted.'], gpApiNotificationsResponse($pdo, $userId)));
            }

            $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, accepted_at = NULL WHERE id = ?");
            $stmt->execute([$handlerId]);
            $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
            $stmt->execute([$userId, (int) $invite['dog_id']]);
            $pdo->commit();
            gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'declined');
            apiJson(array_merge(['success' => true, 'message' => 'Dog access invite declined.'], gpApiNotificationsResponse($pdo, $userId)));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
} catch (Throwable $e) {
    apiJson(['success' => false, 'message' => $e->getMessage()], 500);
}
