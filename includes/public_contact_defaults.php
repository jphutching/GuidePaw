<?php
declare(strict_types=1);

function gpPublicContactColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function gpFirstPublicValue(...$values): string
{
    foreach ($values as $value) {
        $value = trim((string) ($value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function gpFetchUserPublicContact(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $possible = ['id', 'username', 'email', 'display_name', 'home_street', 'home_apt', 'home_city', 'home_address', 'phone', 'public_email', 'home_state', 'home_zip', 'profile_photo_url', 'backup_contact_name', 'backup_contact_phone', 'public_notes'];
    $columns = [];
    foreach ($possible as $column) {
        if ($column === 'id' || gpPublicContactColumnExists($pdo, 'users', $column)) {
            $columns[] = $column;
        }
    }

    $sql = 'SELECT ' . implode(', ', array_map(static fn($c) => '"' . str_replace('"', '""', $c) . '"', $columns ?: ['id'])) . ' FROM users WHERE id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function gpDogOwnerIdFromPublicDog(array $dog): int
{
    return !empty($dog['owner_user_id']) ? (int) $dog['owner_user_id'] : (!empty($dog['user_id']) ? (int) $dog['user_id'] : 0);
}

function gpDogPublicContactDefaults(PDO $pdo, array $dog, ?array $owner = null): array
{
    $ownerId = gpDogOwnerIdFromPublicDog($dog);
    if ($owner === null || !$owner) {
        $owner = gpFetchUserPublicContact($pdo, $ownerId);
    }

    $handlerName = gpFirstPublicValue($dog['handler_name'] ?? '', $owner['display_name'] ?? '', $owner['username'] ?? '', 'Handler');
    $handlerAddress = gpFirstPublicValue(
        gpComposePostalAddress($dog, 'handler_'),
        $dog['handler_address'] ?? '',
        gpComposePostalAddress($owner, 'home_'),
        $owner['home_address'] ?? ''
    );
    $handlerPhone = gpFirstPublicValue($dog['handler_phone'] ?? '', $owner['phone'] ?? '');
    $handlerEmail = gpFirstPublicValue($dog['handler_email'] ?? '', $owner['public_email'] ?? '', $owner['email'] ?? '');
    $homeState = gpFirstPublicValue($dog['home_state'] ?? '', $owner['home_state'] ?? '');
    $handlerPhoto = gpFirstPublicValue($dog['handler_photo_url'] ?? '', $owner['profile_photo_url'] ?? '');
    $backupName = gpFirstPublicValue($dog['backup_contact_name'] ?? '', $owner['backup_contact_name'] ?? '');
    $backupPhone = gpFirstPublicValue($dog['backup_contact_phone'] ?? '', $owner['backup_contact_phone'] ?? '');
    $publicNotes = gpFirstPublicValue($dog['public_notes'] ?? '', $owner['public_notes'] ?? '', $dog['emergency_notes'] ?? '');

    return [
        'owner' => $owner,
        'handler_name' => $handlerName,
        'handler_address' => $handlerAddress,
        'handler_phone' => $handlerPhone,
        'handler_email' => $handlerEmail,
        'home_state' => $homeState,
        'handler_photo_url' => $handlerPhoto,
        'backup_contact_name' => $backupName,
        'backup_contact_phone' => $backupPhone,
        'public_notes' => $publicNotes,
        'handler_email_source' => trim((string) ($dog['handler_email'] ?? '')) !== '' ? 'dog_profile' : (trim((string) ($owner['public_email'] ?? '')) !== '' ? 'handler_profile' : (trim((string) ($owner['email'] ?? '')) !== '' ? 'owner_account' : 'missing')),
        'handler_address_source' => trim((string) gpComposePostalAddress($dog, 'handler_')) !== '' ? 'dog_profile' : (trim((string) ($dog['handler_address'] ?? '')) !== '' ? 'dog_profile' : (trim((string) gpComposePostalAddress($owner, 'home_')) !== '' ? 'handler_profile' : (trim((string) ($owner['home_address'] ?? '')) !== '' ? 'handler_profile' : 'missing'))),
        'handler_phone_source' => trim((string) ($dog['handler_phone'] ?? '')) !== '' ? 'dog_profile' : (trim((string) ($owner['phone'] ?? '')) !== '' ? 'handler_profile' : 'missing'),
        'home_state_source' => trim((string) ($dog['home_state'] ?? '')) !== '' ? 'dog_profile' : (trim((string) ($owner['home_state'] ?? '')) !== '' ? 'handler_profile' : 'missing'),
    ];
}
