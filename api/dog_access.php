<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/handler_profile_helpers.php';
require_once __DIR__ . '/../includes/dog_access_notifications.php';

function gpApiDogAccessEnsureSchema(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE dogs ADD COLUMN IF NOT EXISTS lifecycle_status TEXT NOT NULL DEFAULT 'active'");
    $pdo->exec("ALTER TABLE dogs ADD COLUMN IF NOT EXISTS lifecycle_note TEXT");
    $pdo->exec("ALTER TABLE dogs ADD COLUMN IF NOT EXISTS retired_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS access_starts_at DATE NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS access_ends_at DATE NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS revoked_at TIMESTAMP NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dog_transfer_requests (id SERIAL PRIMARY KEY, dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE, from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, to_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, keep_previous_owner_access BOOLEAN NOT NULL DEFAULT TRUE, note TEXT NULL, status TEXT NOT NULL DEFAULT 'pending', requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, responded_at TIMESTAMP NULL)");
}

function gpApiDogAccessFetchDog(PDO $pdo, int $dogId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username, u.display_name AS owner_display_name
        FROM dogs d
        JOIN users u ON u.id = d.owner_user_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE d.id = ? AND (d.owner_user_id = ? OR dh.id IS NOT NULL)
        LIMIT 1");
    $stmt->execute([$userId, $dogId, $userId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dog ?: null;
}

function gpApiDogAccessFetchDogAny(PDO $pdo, int $dogId): ?array
{
    $stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username, u.display_name AS owner_display_name
        FROM dogs d
        JOIN users u ON u.id = d.owner_user_id
        WHERE d.id = ?
        LIMIT 1");
    $stmt->execute([$dogId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dog ?: null;
}

function gpApiDogAccessFetchUser(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function gpApiDogAccessFindUser(PDO $pdo, string $identity): ?array
{
    $identity = trim($identity);
    if ($identity === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE lower(username) = lower(?) OR lower(email) = lower(?) LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function gpApiDogAccessStatusLabels(): array
{
    return ['active' => 'Active', 'in_training' => 'In training', 'retired' => 'Retired', 'archived' => 'Archived', 'deceased' => 'Deceased', 'transferred' => 'Transferred'];
}

function gpApiDogAccessFetchHandlers(PDO $pdo, int $dogId): array
{
    $stmt = $pdo->prepare("SELECT dh.*, u.username, u.email, u.display_name,
            CASE WHEN dh.status = 'accepted' AND dh.accepted_at IS NULL THEN 'pending' ELSE dh.status END AS access_status
        FROM dog_handlers dh
        JOIN users u ON u.id = dh.user_id
        WHERE dh.dog_id = ?
        ORDER BY CASE WHEN dh.status = 'accepted' AND dh.accepted_at IS NULL THEN 1 WHEN dh.status = 'accepted' THEN 2 WHEN dh.status = 'revoked' THEN 3 ELSE 4 END, u.username ASC");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpApiDogAccessIncomingTransfers(PDO $pdo, int $userId): array
{
    if (!function_exists('tableExists') || !tableExists($pdo, 'dog_transfer_requests')) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT tr.*, d.name AS dog_name, from_u.username AS from_username, from_u.display_name AS from_display_name
        FROM dog_transfer_requests tr
        JOIN dogs d ON d.id = tr.dog_id
        JOIN users from_u ON from_u.id = tr.from_user_id
        WHERE tr.to_user_id = ? AND tr.status = 'pending'
        ORDER BY tr.requested_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpApiDogAccessOverview(PDO $pdo, int $userId, int $tokenId, ?int $dogId = null): array
{
    gpApiDogAccessEnsureSchema($pdo);
    $selectedDogId = $dogId ?: (int) (getActiveDogId($pdo, $userId) ?? 0);
    if ($selectedDogId <= 0) {
        $dogs = getAccessibleDogs($pdo, $userId);
        $selectedDogId = isset($dogs[0]['id']) ? (int) $dogs[0]['id'] : 0;
    }
    $dog = $selectedDogId > 0 ? gpApiDogAccessFetchDog($pdo, $selectedDogId, $userId) : null;
    return [
        'success' => true,
        'active_dog_id' => apiGetActiveDogId($pdo, $tokenId) ?: $selectedDogId,
        'selected_dog_id' => $selectedDogId,
        'dog' => $dog ? [
            'id' => (int) $dog['id'],
            'name' => (string) ($dog['name'] ?? ''),
            'owner_username' => (string) ($dog['owner_username'] ?? ''),
            'owner_display_name' => (string) ($dog['owner_display_name'] ?? ''),
            'lifecycle_status' => (string) ($dog['lifecycle_status'] ?? 'active'),
            'lifecycle_note' => (string) ($dog['lifecycle_note'] ?? ''),
        ] : null,
        'is_owner' => $dog ? userOwnsDog($pdo, $userId, (int) $dog['id']) : false,
        'can_edit' => $dog ? userCanEditDog($pdo, $userId, (int) $dog['id']) : false,
        'status_labels' => gpApiDogAccessStatusLabels(),
        'handlers' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'username' => (string) ($row['username'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'permission_level' => (string) ($row['permission_level'] ?? ''),
                'access_ends_at' => (string) ($row['access_ends_at'] ?? ''),
                'access_status' => (string) ($row['access_status'] ?? ''),
            ];
        }, $dog ? gpApiDogAccessFetchHandlers($pdo, $selectedDogId) : []),
        'pending_invites' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'dog_id' => (int) $row['dog_id'],
                'dog_name' => (string) ($row['dog_name'] ?? ''),
                'owner_username' => (string) ($row['owner_username'] ?? ''),
                'owner_display_name' => (string) ($row['owner_display_name'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'permission_level' => (string) ($row['permission_level'] ?? ''),
                'access_ends_at' => (string) ($row['access_ends_at'] ?? ''),
                'access_status' => (string) ($row['access_status'] ?? ''),
            ];
        }, gpDogAccessPendingInvites($pdo, $userId)),
        'incoming_transfers' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'dog_id' => (int) $row['dog_id'],
                'dog_name' => (string) ($row['dog_name'] ?? ''),
                'from_username' => (string) ($row['from_username'] ?? ''),
                'from_display_name' => (string) ($row['from_display_name'] ?? ''),
                'keep_previous_owner_access' => !empty($row['keep_previous_owner_access']),
                'note' => (string) ($row['note'] ?? ''),
                'requested_at' => (string) ($row['requested_at'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
            ];
        }, gpApiDogAccessIncomingTransfers($pdo, $userId)),
    ];
}

function gpApiDogAccessReadDogId(array $input, int $fallbackDogId): int
{
    $dogId = (int) ($input['dog_id'] ?? 0);
    return $dogId > 0 ? $dogId : $fallbackDogId;
}

$user = requireApiUser($pdo);
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

gpApiDogAccessEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    apiJson(gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : null));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$action = strtolower(trim((string) ($input['action'] ?? '')));
$dogId = gpApiDogAccessReadDogId($input, (int) ($user['active_dog_id'] ?? 0));

try {
    if ($action === 'grant_access') {
        $dog = gpApiDogAccessFetchDog($pdo, $dogId, (int) $user['id']);
        if (!$dog || !userOwnsDog($pdo, (int) $user['id'], $dogId)) {
            apiJson(['success' => false, 'message' => 'Only the owner can invite shared handlers.'], 403);
        }
        $target = gpApiDogAccessFindUser($pdo, (string) ($input['handler_identity'] ?? ''));
        if (!$target) {
            apiJson(['success' => false, 'message' => 'No GuidePaw user matched that username or email.'], 422);
        }
        if ((int) $target['id'] === (int) $user['id']) {
            apiJson(['success' => false, 'message' => 'You already own this dog profile.'], 422);
        }
        $permission = (string) ($input['permission_level'] ?? 'view');
        $permission = $permission === 'edit' ? 'edit' : 'view';
        $role = cleanText((string) ($input['role'] ?? 'co-op handler'), 80) ?: 'co-op handler';
        $endsAt = cleanDateValue((string) ($input['access_ends_at'] ?? ''));
        upsertDogHandlerLink($pdo, $dogId, (int) $target['id'], (int) $user['id'], $role, $permission, 'pending');
        $stmt = $pdo->prepare('UPDATE dog_handlers SET access_ends_at = ? WHERE dog_id = ? AND user_id = ?');
        $stmt->execute([$endsAt ?: null, $dogId, (int) $target['id']]);
        $owner = gpApiDogAccessFetchUser($pdo, (int) $user['id']) ?: [];
        gpDogAccessNotifySharedGranted($dog, $owner, $target, gpDogHandlerRoleLabel($role), $permission, $endsAt ?: null);
        apiJson(array_merge(['success' => true, 'message' => 'Invite sent.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], $dogId)));
    }

    if ($action === 'revoke_access') {
        $handlerId = (int) ($input['handler_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT dh.*, d.owner_user_id FROM dog_handlers dh JOIN dogs d ON d.id = dh.dog_id WHERE dh.id = ? LIMIT 1');
        $stmt->execute([$handlerId]);
        $handler = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$handler || (int) $handler['owner_user_id'] !== (int) $user['id']) {
            apiJson(['success' => false, 'message' => 'Only the owner can revoke handler access.'], 403);
        }
        $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$handlerId]);
        apiJson(array_merge(['success' => true, 'message' => 'Handler access revoked.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], (int) $handler['dog_id'])));
    }

    if ($action === 'accept_dog_access_invite' || $action === 'decline_dog_access_invite') {
        $handlerId = (int) ($input['handler_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT dh.*, d.name AS dog_name, d.owner_user_id, owner.username AS owner_username, owner.display_name AS owner_display_name, owner.public_email AS owner_public_email, owner.email AS owner_email
            FROM dog_handlers dh
            JOIN dogs d ON d.id = dh.dog_id
            JOIN users owner ON owner.id = d.owner_user_id
            WHERE dh.id = ? AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NULL
            LIMIT 1");
        $stmt->execute([$handlerId, (int) $user['id']]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            apiJson(['success' => false, 'message' => 'Invite was not found or is no longer pending.'], 404);
        }
        $dog = gpApiDogAccessFetchDogAny($pdo, (int) $invite['dog_id']) ?: ['id' => (int) $invite['dog_id'], 'name' => (string) ($invite['dog_name'] ?? 'Dog')];
        $owner = gpApiDogAccessFetchUser($pdo, (int) ($invite['invited_by_user_id'] ?? 0)) ?: [
            'id' => (int) ($invite['owner_user_id'] ?? 0),
            'username' => (string) ($invite['owner_username'] ?? ''),
            'display_name' => (string) ($invite['owner_display_name'] ?? ''),
            'public_email' => (string) ($invite['owner_public_email'] ?? ''),
            'email' => (string) ($invite['owner_email'] ?? ''),
        ];
        $recipient = gpApiDogAccessFetchUser($pdo, (int) $user['id']) ?: [];
        $pdo->beginTransaction();
        try {
            if ($action === 'accept_dog_access_invite') {
                $role = gpDogHandlerRoleLabel((string) ($invite['role'] ?? 'co-op handler'));
                upsertDogHandlerLink($pdo, (int) $invite['dog_id'], (int) $user['id'], (int) $invite['invited_by_user_id'], $role, (string) ($invite['permission_level'] ?? 'view'), 'accepted');
                $stmt = $pdo->prepare('UPDATE dog_handlers SET access_ends_at = ? WHERE id = ?');
                $stmt->execute([$invite['access_ends_at'] ?? null, $handlerId]);
                $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
                $stmt->execute([(int) $user['id'], (int) $invite['dog_id']]);
                $pdo->commit();
                gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'accepted');
                apiJson(array_merge(['success' => true, 'message' => 'Invite accepted.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], (int) $invite['dog_id'])));
            }
            $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, accepted_at = NULL WHERE id = ?");
            $stmt->execute([$handlerId]);
            $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
            $stmt->execute([(int) $user['id'], (int) $invite['dog_id']]);
            $pdo->commit();
            gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'declined');
            apiJson(array_merge(['success' => true, 'message' => 'Invite declined.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], (int) $invite['dog_id'])));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    if ($action === 'request_transfer') {
        $dog = gpApiDogAccessFetchDog($pdo, $dogId, (int) $user['id']);
        if (!$dog || !userOwnsDog($pdo, (int) $user['id'], $dogId)) {
            apiJson(['success' => false, 'message' => 'Only the current owner can transfer this dog profile.'], 403);
        }
        $target = gpApiDogAccessFindUser($pdo, (string) ($input['transfer_identity'] ?? ''));
        if (!$target) {
            apiJson(['success' => false, 'message' => 'No GuidePaw user matched that username or email.'], 422);
        }
        if ((int) $target['id'] === (int) $user['id']) {
            apiJson(['success' => false, 'message' => 'You already own this dog profile.'], 422);
        }
        $keepAccess = !empty($input['keep_previous_owner_access']) ? 1 : 0;
        $note = cleanTextarea((string) ($input['transfer_note'] ?? ''), 800);
        $stmt = $pdo->prepare("UPDATE dog_transfer_requests SET status = 'cancelled', responded_at = CURRENT_TIMESTAMP WHERE dog_id = ? AND status = 'pending'");
        $stmt->execute([$dogId]);
        $stmt = $pdo->prepare('INSERT INTO dog_transfer_requests (dog_id, from_user_id, to_user_id, keep_previous_owner_access, note) VALUES (?,?,?,?,?)');
        $stmt->execute([$dogId, (int) $user['id'], (int) $target['id'], $keepAccess, $note ?: null]);
        $fromUser = gpApiDogAccessFetchUser($pdo, (int) $user['id']) ?: [];
        gpDogAccessNotifyTransferSent($dog, $fromUser, $target, $note ?: '');
        apiJson(array_merge(['success' => true, 'message' => 'Transfer request sent.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], $dogId)));
    }

    if ($action === 'accept_transfer' || $action === 'decline_transfer') {
        $requestId = (int) ($input['request_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM dog_transfer_requests WHERE id = ? AND to_user_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$requestId, (int) $user['id'], 'pending']);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            apiJson(['success' => false, 'message' => 'Transfer request was not found or is no longer pending.'], 404);
        }
        $dog = gpApiDogAccessFetchDogAny($pdo, (int) $request['dog_id']) ?: [];
        $fromUser = gpApiDogAccessFetchUser($pdo, (int) $request['from_user_id']) ?: [];
        $toUser = gpApiDogAccessFetchUser($pdo, (int) $user['id']) ?: [];
        if ($action === 'decline_transfer') {
            $stmt = $pdo->prepare("UPDATE dog_transfer_requests SET status = 'declined', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$requestId]);
            gpDogAccessNotifyTransferResult($dog, $fromUser, $toUser, 'declined');
            apiJson(array_merge(['success' => true, 'message' => 'Transfer declined.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], (int) $request['dog_id'])));
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE dogs SET owner_user_id = ?, lifecycle_status = ?, lifecycle_note = COALESCE(lifecycle_note, ?) WHERE id = ?');
            $stmt->execute([(int) $user['id'], 'active', 'Ownership transferred through GuidePaw.', (int) $request['dog_id']]);
            if (!empty($request['keep_previous_owner_access'])) {
                upsertDogHandlerLink($pdo, (int) $request['dog_id'], (int) $request['from_user_id'], (int) $user['id'], 'collaborator', 'edit', 'accepted');
            } else {
                $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP WHERE dog_id = ? AND user_id = ?");
                $stmt->execute([(int) $request['dog_id'], (int) $request['from_user_id']]);
            }
            $stmt = $pdo->prepare("UPDATE dog_transfer_requests SET status = 'accepted', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$requestId]);
            $pdo->commit();
            gpDogAccessNotifyTransferResult($dog, $fromUser, $toUser, 'accepted');
            setActiveDogId($pdo, (int) $user['id'], (int) $request['dog_id']);
            apiJson(array_merge(['success' => true, 'message' => 'Transfer accepted.'], gpApiDogAccessOverview($pdo, (int) $user['id'], (int) $user['token_id'], (int) $request['dog_id'])));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
} catch (Throwable $e) {
    apiJson(['success' => false, 'message' => 'Dog access update failed: ' . $e->getMessage()], 500);
}
