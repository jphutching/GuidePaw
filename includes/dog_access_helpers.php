<?php

function upsertDogHandlerLink(PDO $pdo, int $dogId, int $userId, int $invitedByUserId, ?string $role, string $permissionLevel, string $status = 'accepted'): void {
    $canonicalRole = gpCanonicalDogHandlerRole($role, false);
    $requestedStatus = strtolower(trim($status));
    $dbStatus = 'accepted';
    $acceptedAt = null;
    $revokedAt = null;
    if ($requestedStatus === 'pending') {
        $acceptedAt = null;
    } elseif ($requestedStatus === 'revoked' || $requestedStatus === 'declined' || $requestedStatus === 'expired') {
        $dbStatus = 'revoked';
        $revokedAt = date('Y-m-d H:i:s');
    } else {
        $acceptedAt = date('Y-m-d H:i:s');
    }
    $stmt = $pdo->prepare("INSERT INTO dog_handlers (dog_id, user_id, invited_by_user_id, role, permission_level, status, accepted_at, revoked_at) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT (dog_id, user_id) DO UPDATE SET invited_by_user_id = EXCLUDED.invited_by_user_id, role = EXCLUDED.role, permission_level = EXCLUDED.permission_level, status = EXCLUDED.status, accepted_at = EXCLUDED.accepted_at, revoked_at = EXCLUDED.revoked_at");
    $stmt->execute([$dogId, $userId, $invitedByUserId, $canonicalRole, $permissionLevel, $dbStatus, $acceptedAt, $revokedAt]);
}

function hasDogAccess(PDO $pdo, int $userId, int $dogId): bool {
    $stmt = $pdo->prepare("SELECT 1
        FROM dogs d
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE d.id = ? AND (d.owner_user_id = ? OR dh.id IS NOT NULL)
        LIMIT 1");
    $stmt->execute([$userId, $dogId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function userOwnsDog(PDO $pdo, int $userId, int $dogId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM dogs WHERE id = ? AND owner_user_id = ? LIMIT 1');
    $stmt->execute([$dogId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function dogIsActiveLifecycle(PDO $pdo, int $dogId): bool {
    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(lifecycle_status, ''), 'active') FROM dogs WHERE id = ? LIMIT 1");
    $stmt->execute([$dogId]);
    $status = (string) ($stmt->fetchColumn() ?: '');
    return in_array($status, ['active', 'in_training'], true);
}

function requireDogOwner(PDO $pdo, int $userId, int $dogId): void {
    if (!userOwnsDog($pdo, $userId, $dogId)) {
        http_response_code(403);
        die('Only the dog owner can perform this action.');
    }
}

function getAccessibleDogs(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT DISTINCT d.*, u.username AS owner_username,
            CASE
                WHEN d.owner_user_id = ? THEN 'owner'
                WHEN dh.permission_level = 'edit' THEN 'editor'
                ELSE 'viewer'
            END AS access_role
        FROM dogs d
        JOIN users u ON u.id = d.owner_user_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE d.owner_user_id = ? OR dh.id IS NOT NULL
        ORDER BY d.name ASC, d.id ASC");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll() ?: [];
}

function setActiveDogId(PDO $pdo, int $userId, int $dogId): bool {
    if (!hasDogAccess($pdo, $userId, $dogId) || !dogIsActiveLifecycle($pdo, $dogId)) {
        return false;
    }
    $_SESSION['active_dog_id'] = $dogId;
    return true;
}

function getActiveDogId(PDO $pdo, int $userId): ?int {
    if (!empty($_SESSION['active_dog_id']) && hasDogAccess($pdo, $userId, (int) $_SESSION['active_dog_id']) && dogIsActiveLifecycle($pdo, (int) $_SESSION['active_dog_id'])) {
        return (int) $_SESSION['active_dog_id'];
    }

    $stmt = $pdo->prepare("SELECT d.id
        FROM dogs d
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE (d.owner_user_id = ? OR dh.id IS NOT NULL)
          AND COALESCE(NULLIF(d.lifecycle_status, ''), 'active') IN ('active', 'in_training')
        ORDER BY d.name ASC, d.id ASC");
    $stmt->execute([$userId, $userId]);
    $dogs = array_map(static fn($row) => (int) $row['id'], $stmt->fetchAll() ?: []);
    if (!$dogs) {
        unset($_SESSION['active_dog_id']);
        return null;
    }

    $_SESSION['active_dog_id'] = (int) $dogs[0];
    return (int) $dogs[0];
}

function getActiveDog(PDO $pdo, int $userId): ?array {
    $dogId = getActiveDogId($pdo, $userId);
    if (!$dogId) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username,
            CASE
                WHEN d.owner_user_id = ? THEN 'owner'
                WHEN dh.permission_level = 'edit' THEN 'editor'
                ELSE 'viewer'
            END AS access_role
        FROM dogs d
        JOIN users u ON u.id = d.owner_user_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE d.id = ?
        LIMIT 1");
    $stmt->execute([$userId, $userId, $dogId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function requireActiveDog(PDO $pdo, int $userId): array {
    $dog = getActiveDog($pdo, $userId);
    if (!$dog) {
        header('Location: dogs.php?status=need_dog');
        exit;
    }
    return $dog;
}

function userCanEditDog(PDO $pdo, int $userId, int $dogId): bool {
    $stmt = $pdo->prepare("SELECT 1
        FROM dogs d
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE d.id = ?
          AND (d.owner_user_id = ? OR dh.permission_level = 'edit')
        LIMIT 1");
    $stmt->execute([$userId, $dogId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function requireDogEditor(PDO $pdo, int $userId, int $dogId): void {
    if (!userCanEditDog($pdo, $userId, $dogId)) {
        http_response_code(403);
        die('You do not have permission to edit this dog profile.');
    }
}
