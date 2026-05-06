<?php
declare(strict_types=1);

require_once __DIR__ . '/smtp_mailer.php';

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

function gpDogAccessNotify(array $recipient, string $subject, string $body): bool
{
    if (!gpDogAccessNotificationsEnabled()) {
        return false;
    }
    $email = gpDogAccessRecipientEmail($recipient);
    if ($email === '') {
        return false;
    }
    try {
        return gpSendMail($email, $subject, $body);
    } catch (Throwable $e) {
        error_log('GuidePaw dog access notification failed: ' . $e->getMessage());
        return false;
    }
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
    return gpDogAccessNotify($handler, "GuidePaw dog access: {$dogName}", $body);
}

function gpDogAccessNotifyTransferSent(array $dog, array $fromUser, array $toUser, string $note): bool
{
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $body = gpDogAccessDisplayName($fromUser) . " sent you a GuidePaw dog transfer request.\n\n" .
        "Dog: {$dogName}\n" .
        ($note !== '' ? "Note: {$note}\n\n" : "\n") .
        "Review: " . gpDogAccessLink($dog) . "\n";
    return gpDogAccessNotify($toUser, "GuidePaw transfer request: {$dogName}", $body);
}

function gpDogAccessNotifyTransferResult(array $dog, array $fromUser, array $toUser, string $result): bool
{
    $dogName = (string) ($dog['name'] ?? 'Dog');
    $body = gpDogAccessDisplayName($toUser) . " {$result} the GuidePaw dog transfer request.\n\n" .
        "Dog: {$dogName}\n" .
        "Open: " . gpDogAccessLink($dog) . "\n";
    return gpDogAccessNotify($fromUser, "GuidePaw transfer {$result}: {$dogName}", $body);
}
