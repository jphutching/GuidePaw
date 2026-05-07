<?php
declare(strict_types=1);

require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/sms_notifications.php';
require_once __DIR__ . '/notifications.php';

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
    return rtrim((string) gpEnv('APP_URL', 'https://beta.guidepaw.app'), '/') . '/dog_access.php?dog_id=' . (int) ($dog['id'] ?? 0);
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
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $body = gpDogAccessDisplayName($owner) . " added you to a GuidePaw dog profile.\n\n" .
        "Dog: {$dogName}\n" .
        "Role: {$role}\n" .
        "Permission: {$permission}\n" .
        "End date: " . ($endDate ?: 'not set') . "\n\n" .
        "Open: " . gpDogAccessLink($dog) . "\n";
    $sms = "GuidePaw: " . gpDogAccessDisplayName($owner) . " added you to {$dogName}. Role: {$role}. Open GuidePaw to review.";
    return gpDogAccessNotify($handler, "GuidePaw dog access: {$dogName}", $body, $sms, $dog, 'dog_access_granted', 'normal');
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
