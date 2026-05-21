<?php
declare(strict_types=1);

require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/sms_notifications.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/beta_notifications.php';

function gpDogAccessNotificationsEnabled(): bool
{
    $value = strtolower(trim((string) gpEnv('DOG_ACCESS_NOTIFY_EMAIL_ENABLED', 'true')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function gpDogAccessRecipientEmail(array $user): string
{
    $email = trim((string) ($user['public_email'] ?? ''));
    if ($email === '') {
        $email = trim((string) ($user['email'] ?? ''));
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function gpDogAccessDisplayName(array $user): string
{
    $name = trim((string) ($user['display_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($user['username'] ?? ''));
    }
    return $name !== '' ? $name : 'GuidePaw handler';
}

function gpDogAccessLink(array $dog): string
{
    return rtrim((string) gpEnv('APP_URL', 'https://guidepaw.app'), '/') . '/dog_access.php?dog_id=' . (int) ($dog['id'] ?? 0);
}

function gpDogAccessFetchDogById(PDO $pdo, int $dogId): ?array
{
    $stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username, u.display_name AS owner_display_name, u.public_email AS owner_public_email, u.email AS owner_email FROM dogs d JOIN users u ON u.id = d.owner_user_id WHERE d.id = ? LIMIT 1');
    $stmt->execute([$dogId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dog ?: null;
}

function gpDogAccessFetchUserById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function gpDogAccessPendingInvites(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT dh.*, d.name AS dog_name, d.owner_user_id, owner.username AS owner_username, owner.display_name AS owner_display_name, owner.public_email AS owner_public_email, owner.email AS owner_email
        FROM dog_handlers dh
        JOIN dogs d ON d.id = dh.dog_id
        JOIN users owner ON owner.id = d.owner_user_id
        WHERE dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NULL
        ORDER BY dh.id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDogAccessNotifyInApp(array $recipient, string $subject, string $body, array $dog, string $type = 'dog_access', string $priority = 'normal'): bool
{
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        return false;
    }
    $userId = (int) ($recipient['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }
    try {
        return gpCreateNotification($GLOBALS['pdo'], $userId, $subject, $body, gpDogAccessLink($dog), $type, $priority, (int) ($dog['id'] ?? 0));
    } catch (Throwable $e) {
        error_log('GuidePaw in-app dog access notification failed: ' . $e->getMessage());
        return false;
    }
}

function gpDogAccessNotifyInvite(array $dog, array $owner, array $handler, string $role, string $permission, ?string $endDate): bool
{
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $roleLabel = gpDogHandlerRoleLabel($role);
    $subject = "GuidePaw dog access invite: {$dogName}";
    $body = gpDogAccessDisplayName($owner) . " invited you to shared access for a GuidePaw dog profile.\n\n" .
        "Dog: {$dogName}\n" .
        "Role: {$roleLabel}\n" .
        "Permission: {$permission}\n" .
        "End date: " . ($endDate ?: 'not set') . "\n\n" .
        "Open your Notifications page to accept or decline.\n";
    $sms = "GuidePaw: " . gpDogAccessDisplayName($owner) . " invited you to {$dogName}. Open Notifications to accept or decline.";

    $sent = false;

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        try {
            $sent = gpCreateNotification(
                $GLOBALS['pdo'],
                (int) ($handler['id'] ?? 0),
                $subject,
                $body,
                'notifications.php',
                'dog_access_invite',
                'high',
                (int) ($dog['id'] ?? 0),
                [
                    'owner_username' => (string) ($owner['username'] ?? ''),
                    'role' => $roleLabel,
                    'permission_level' => $permission,
                    'access_ends_at' => $endDate,
                ]
            ) || $sent;
        } catch (Throwable $e) {
            error_log('GuidePaw dog access invite in-app notification failed: ' . $e->getMessage());
        }
    }

    if (gpDogAccessNotificationsEnabled()) {
        $email = gpDogAccessRecipientEmail($handler);
        if ($email !== '') {
            try {
                $sent = gpSendMail($email, $subject, $body) || $sent;
            } catch (Throwable $e) {
                error_log('GuidePaw dog access invite email notification failed: ' . $e->getMessage());
            }
        }
    }

    if ($sms !== '') {
        try {
            $sent = gpSmsNotifyUser($handler, $sms, 'DOG_ACCESS_NOTIFY_SMS_ENABLED') || $sent;
        } catch (Throwable $e) {
            error_log('GuidePaw dog access invite SMS notification failed: ' . $e->getMessage());
        }
    }

    return $sent;
}

function gpDogAccessNotify(array $recipient, string $subject, string $body, string $smsBody = '', array $dog = [], string $type = 'dog_access', string $priority = 'normal'): bool
{
    $sent = false;

    if ($dog) {
        $sent = gpDogAccessNotifyInApp($recipient, $subject, $body, $dog, $type, $priority) || $sent;
    }

    if (gpDogAccessNotificationsEnabled()) {
        $email = gpDogAccessRecipientEmail($recipient);
        if ($email !== '') {
            try {
                $sent = gpSendMail($email, $subject, $body) || $sent;
            } catch (Throwable $e) {
                error_log('GuidePaw dog access email notification failed: ' . $e->getMessage());
            }
        }
    }

    if ($smsBody !== '') {
        try {
            $sent = gpSmsNotifyUser($recipient, $smsBody, 'DOG_ACCESS_NOTIFY_SMS_ENABLED') || $sent;
        } catch (Throwable $e) {
            error_log('GuidePaw dog access SMS notification failed: ' . $e->getMessage());
        }
    }

    return $sent;
}

function gpDogAccessNotifySharedGranted(array $dog, array $owner, array $handler, string $role, string $permission, ?string $endDate): bool
{
    return gpDogAccessNotifyInvite($dog, $owner, $handler, $role, $permission, $endDate);
}

function gpDogAccessNotifySharedInviteResult(array $dog, array $owner, array $handler, string $result): bool
{
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $body = gpDogAccessDisplayName($handler) . " {$result} the shared access invite.\n\n" .
        "Dog: {$dogName}\n" .
        "Open: " . gpDogAccessLink($dog) . "\n";
    $sms = "GuidePaw: " . gpDogAccessDisplayName($handler) . " {$result} the shared access invite for {$dogName}.";
    return gpDogAccessNotify($owner, "GuidePaw shared access {$result}: {$dogName}", $body, $sms, $dog, 'dog_access_invite_result', 'normal');
}

function gpDogAccessNotifyTransferSent(array $dog, array $fromUser, array $toUser, string $note): bool
{
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $body = gpDogAccessDisplayName($fromUser) . " sent you a GuidePaw dog transfer request.\n\n" .
        "Dog: {$dogName}\n" .
        ($note !== '' ? "Note: {$note}\n\n" : "\n") .
        "Review: " . gpDogAccessLink($dog) . "\n";
    $sms = "GuidePaw: " . gpDogAccessDisplayName($fromUser) . " sent you a dog transfer request for {$dogName}. Open GuidePaw to accept or decline.";
    return gpDogAccessNotify($toUser, "GuidePaw transfer request: {$dogName}", $body, $sms, $dog, 'dog_transfer_request', 'high');
}

function gpDogAccessNotifyTransferResult(array $dog, array $fromUser, array $toUser, string $result): bool
{
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $body = gpDogAccessDisplayName($toUser) . " {$result} the GuidePaw dog transfer request.\n\n" .
        "Dog: {$dogName}\n" .
        "Open: " . gpDogAccessLink($dog) . "\n";
    $sms = "GuidePaw: " . gpDogAccessDisplayName($toUser) . " {$result} the dog transfer request for {$dogName}.";
    return gpDogAccessNotify($fromUser, "GuidePaw transfer {$result}: {$dogName}", $body, $sms, $dog, 'dog_transfer_result', 'normal');
}
