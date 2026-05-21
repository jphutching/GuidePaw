<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/smtp_mailer.php';

function betaSetting(PDO $pdo, string $key, string $fallback = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM beta_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $fallback : (string) $value;
}

function betaBool(PDO $pdo, string $key, bool $fallback = false): bool
{
    $value = strtolower(trim(betaSetting($pdo, $key, $fallback ? 'true' : 'false')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function betaSet(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO beta_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (setting_key)
        DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$key, $value]);
}

function betaGenerateToken(): string
{
    return 'GPB-' . strtoupper(bin2hex(random_bytes(16)));
}

function betaTokenHash(string $token): string
{
    return hash('sha256', strtoupper(trim($token)));
}

function betaFindValidToken(PDO $pdo, string $token): ?array
{
    $hash = betaTokenHash($token);
    $stmt = $pdo->prepare("
        SELECT *
        FROM beta_access_requests
        WHERE token_hash = ?
          AND status = 'approved'
          AND redeemed_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function betaRequireAdmin(): void
{
    checkLogin();
    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        die('Admin access required.');
    }
}

function betaCreateRequest(PDO $pdo, string $fullName, string $email, ?string $phone, ?string $reason): int
{
    $fullName = trim($fullName);
    $email = strtolower(trim($email));
    $phone = trim((string) $phone);
    $reason = trim((string) $reason);

    $stmt = $pdo->prepare("
        INSERT INTO beta_access_requests (full_name, email, phone, reason, status, updated_at)
        VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), 'pending', CURRENT_TIMESTAMP)
        ON CONFLICT (email)
        DO UPDATE SET
            full_name = EXCLUDED.full_name,
            phone = EXCLUDED.phone,
            reason = EXCLUDED.reason,
            updated_at = CURRENT_TIMESTAMP
        RETURNING id
    ");
    $stmt->execute([$fullName, $email, $phone, $reason]);
    return (int) $stmt->fetchColumn();
}

function betaApprovalEmail(string $fullName, string $token): string
{
    $baseUrl = rtrim(appUrl() ?: 'https://guidepaw.app', '/');
    return "Hi {$fullName},\n\n" .
        "Your GuidePaw beta access request has been approved.\n\n" .
        "Use this beta access token to create your handler account:\n\n" .
        "{$token}\n\n" .
        "Open this page to validate your token and register:\n" .
        $baseUrl . "/beta_token.php?token=" . rawurlencode($token) . "\n\n" .
        "After your handler account is created, dog profiles can be added after login.\n\n" .
        "GuidePaw\n";
}

function betaApproveRequest(PDO $pdo, int $requestId, int $adminUserId, bool $sendEmail = true): array
{
    $token = betaGenerateToken();
    $hash = betaTokenHash($token);
    $preview = substr($token, 0, 8) . '...' . substr($token, -4);

    $stmt = $pdo->prepare("
        UPDATE beta_access_requests
        SET status = 'approved',
            token_hash = ?,
            token_preview = ?,
            approved_by_user_id = ?,
            approved_at = CURRENT_TIMESTAMP,
            denied_at = NULL,
            redeemed_at = NULL,
            linked_user_id = NULL,
            email_sent_at = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
        RETURNING *
    ");
    $stmt->execute([$hash, $preview, $adminUserId, $requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Beta request not found.');
    }

    $emailSent = false;
    $emailError = null;

    if ($sendEmail) {
        try {
            $subject = 'Your GuidePaw beta access token';
            $body = betaApprovalEmail($row['full_name'], $token);
            $emailSent = gpSendMail($row['email'], $subject, $body);
            if ($emailSent) {
                $mark = $pdo->prepare("UPDATE beta_access_requests SET email_sent_at = CURRENT_TIMESTAMP WHERE id = ?");
                $mark->execute([$requestId]);
            }
        } catch (Throwable $e) {
            $emailError = $e->getMessage();
        }
    }

    return [
        'request' => $row,
        'token' => $token,
        'email_sent' => $emailSent,
        'email_error' => $emailError,
    ];
}

function betaDenyRequest(PDO $pdo, int $requestId, ?string $notes = null): void
{
    $stmt = $pdo->prepare("
        UPDATE beta_access_requests
        SET status = 'denied',
            denied_at = CURRENT_TIMESTAMP,
            admin_notes = NULLIF(?, ''),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([trim((string) $notes), $requestId]);
}

function betaMarkRedeemed(PDO $pdo, int $requestId, int $userId): void
{
    $stmt = $pdo->prepare("
        UPDATE beta_access_requests
        SET status = 'redeemed',
            redeemed_at = CURRENT_TIMESTAMP,
            linked_user_id = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$userId, $requestId]);
}

function betaTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT column_name, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = ?
    ");
    $stmt->execute([$table]);
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[$row['column_name']] = $row;
    }
    return $cols;
}

function betaInsertUserFlexible(PDO $pdo, array $data): int
{
    $cols = betaTableColumns($pdo, 'users');

    $email = strtolower(trim($data['email']));
    $fullName = trim($data['full_name']);
    $phone = trim($data['phone'] ?? '');
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    $recovery = strtoupper(bin2hex(random_bytes(5)));
    $homeStreet = trim((string) ($data['home_street'] ?? ''));
    $homeApt = trim((string) ($data['home_apt'] ?? ''));
    $homeCity = trim((string) ($data['home_city'] ?? ''));
    $homeState = strtoupper(trim((string) ($data['home_state'] ?? '')));
    $homeZip = trim((string) ($data['home_zip'] ?? ''));
    $homeAddress = trim((string) ($data['home_address'] ?? ''));
    if ($homeAddress === '') {
        $homeAddress = gpComposePostalAddress([
            'home_street' => $homeStreet,
            'home_apt' => $homeApt,
            'home_city' => $homeCity,
            'home_state' => $homeState,
            'home_zip' => $homeZip,
        ]);
    }

    $values = [];

    $candidateValues = [
        'username' => $email,
        'email' => $email,
        'full_name' => $fullName,
        'home_street' => $homeStreet,
        'home_apt' => $homeApt,
        'home_city' => $homeCity,
        'home_address' => $homeAddress,
        'handler_name' => $fullName,
        'name' => $fullName,
        'phone' => $phone,
        'phone_number' => $phone,
        'home_state' => $homeState,
        'home_zip' => $homeZip,
        'password_hash' => $passwordHash,
        'password' => $passwordHash,
        'recovery_key' => $recovery,
        'dog_name' => 'Pending setup',
        'breed' => null,
        'chip_number' => null,
        'is_admin' => 0,
        'beta_request_id' => $data['beta_request_id'] ?? null,
    ];

    foreach ($candidateValues as $col => $value) {
        if (array_key_exists($col, $cols)) {
            $values[$col] = $value;
        }
    }

    if (!isset($values['username']) && array_key_exists('email', $cols)) {
        $values['email'] = $email;
    }

    foreach ($cols as $col => $meta) {
        if ($col === 'id' || array_key_exists($col, $values)) {
            continue;
        }
        $nullable = strtoupper((string) $meta['is_nullable']) === 'YES';
        $hasDefault = $meta['column_default'] !== null;
        if (!$nullable && !$hasDefault) {
            if (str_contains($col, 'dog')) {
                $values[$col] = 'Pending setup';
            } elseif (str_contains($col, 'address')) {
                $values[$col] = $homeAddress;
            } elseif (str_contains($col, 'email')) {
                $values[$col] = $email;
            } elseif (str_contains($col, 'name')) {
                $values[$col] = $fullName;
            } elseif (str_contains($col, 'password')) {
                $values[$col] = $passwordHash;
            } else {
                $values[$col] = '';
            }
        }
    }

    $columns = array_keys($values);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ') RETURNING id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($values));
    return (int) $stmt->fetchColumn();
}
