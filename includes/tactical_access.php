<?php
declare(strict_types=1);

function gpTacticalAccessServiceTypes(): array
{
    return [
        'security' => 'Security',
        'police' => 'Police',
        'fire' => 'Fire / EMS',
        'military' => 'Military',
        'sar' => 'Search and Rescue',
        'other' => 'Other',
    ];
}

function gpTacticalAccessEnsureSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tactical_access_requests (
            id BIGSERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            full_name VARCHAR(160) NOT NULL,
            email VARCHAR(254) NOT NULL,
            organization VARCHAR(180) NOT NULL,
            role_title VARCHAR(120) NOT NULL,
            service_type VARCHAR(40) NOT NULL,
            verification_notes TEXT,
            reason TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            approved_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            approved_at TIMESTAMP WITHOUT TIME ZONE,
            denied_at TIMESTAMP WITHOUT TIME ZONE,
            admin_notes TEXT,
            created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT tactical_access_requests_status_check CHECK (status IN ('pending', 'approved', 'denied'))
        )
    ");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_tactical_access_requests_user_id ON tactical_access_requests (user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tactical_access_requests_status ON tactical_access_requests (status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tactical_access_requests_email ON tactical_access_requests (email)");

    $ensured = true;
}

function gpTacticalAccessCurrentRequest(PDO $pdo, int $userId): ?array
{
    gpTacticalAccessEnsureSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM tactical_access_requests
        WHERE user_id = ?
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gpTacticalAccessUserHasApprovedRequest(PDO $pdo, int $userId): bool
{
    gpTacticalAccessEnsureSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT 1
        FROM tactical_access_requests
        WHERE user_id = ?
          AND status = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function gpTacticalAccessCanCurrentUserView(PDO $pdo, int $userId): bool
{
    if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()) {
        return true;
    }
    return gpTacticalAccessUserHasApprovedRequest($pdo, $userId);
}

function gpTacticalAccessUpsertRequest(PDO $pdo, int $userId, array $data): int
{
    gpTacticalAccessEnsureSchema($pdo);

    $serviceTypes = gpTacticalAccessServiceTypes();
    $serviceType = (string) ($data['service_type'] ?? 'other');
    if (!isset($serviceTypes[$serviceType])) {
        $serviceType = 'other';
    }

    $stmt = $pdo->prepare("
        INSERT INTO tactical_access_requests (
            user_id,
            full_name,
            email,
            organization,
            role_title,
            service_type,
            verification_notes,
            reason,
            status,
            approved_by_user_id,
            approved_at,
            denied_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), 'pending', NULL, NULL, NULL, CURRENT_TIMESTAMP)
        ON CONFLICT (user_id)
        DO UPDATE SET
            full_name = EXCLUDED.full_name,
            email = EXCLUDED.email,
            organization = EXCLUDED.organization,
            role_title = EXCLUDED.role_title,
            service_type = EXCLUDED.service_type,
            verification_notes = EXCLUDED.verification_notes,
            reason = EXCLUDED.reason,
            status = 'pending',
            approved_by_user_id = NULL,
            approved_at = NULL,
            denied_at = NULL,
            admin_notes = NULL,
            updated_at = CURRENT_TIMESTAMP
        RETURNING id
    ");
    $stmt->execute([
        $userId,
        trim((string) ($data['full_name'] ?? '')),
        strtolower(trim((string) ($data['email'] ?? ''))),
        trim((string) ($data['organization'] ?? '')),
        trim((string) ($data['role_title'] ?? '')),
        $serviceType,
        trim((string) ($data['verification_notes'] ?? '')),
        trim((string) ($data['reason'] ?? '')),
    ]);
    return (int) $stmt->fetchColumn();
}

function gpTacticalAccessApprove(PDO $pdo, int $requestId, int $adminUserId): void
{
    gpTacticalAccessEnsureSchema($pdo);

    $stmt = $pdo->prepare("
        UPDATE tactical_access_requests
        SET status = 'approved',
            approved_by_user_id = ?,
            approved_at = CURRENT_TIMESTAMP,
            denied_at = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$adminUserId, $requestId]);
}

function gpTacticalAccessDeny(PDO $pdo, int $requestId, string $notes = ''): void
{
    gpTacticalAccessEnsureSchema($pdo);

    $stmt = $pdo->prepare("
        UPDATE tactical_access_requests
        SET status = 'denied',
            approved_by_user_id = NULL,
            approved_at = NULL,
            denied_at = CURRENT_TIMESTAMP,
            admin_notes = NULLIF(?, ''),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([trim($notes), $requestId]);
}

function gpTacticalAccessRequests(PDO $pdo, string $status = 'pending'): array
{
    gpTacticalAccessEnsureSchema($pdo);
    $allowed = ['pending', 'approved', 'denied', 'all'];
    if (!in_array($status, $allowed, true)) {
        $status = 'pending';
    }

    if ($status === 'all') {
        $stmt = $pdo->query("
            SELECT t.*, u.username, u.full_name AS account_name, u.email AS account_email
            FROM tactical_access_requests t
            LEFT JOIN users u ON u.id = t.user_id
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT 200
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.full_name AS account_name, u.email AS account_email
        FROM tactical_access_requests t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.status = ?
        ORDER BY t.updated_at DESC, t.id DESC
        LIMIT 200
    ");
    $stmt->execute([$status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

