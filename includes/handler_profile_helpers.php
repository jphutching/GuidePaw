<?php

function gpCanonicalDogHandlerRole(?string $role, bool $isOwner = false): string {
    if ($isOwner) {
        return 'owner';
    }
    return 'collaborator';
}

function gpDogHandlerRoleLabel(?string $role): string {
    $role = strtolower(trim((string) $role));
    return match ($role) {
        'owner' => 'owner',
        'collaborator' => 'collaborator',
        'co-op handler', 'co op handler', 'co-op', 'co op', 'previous owner', 'editor', 'viewer' => 'collaborator',
        default => 'collaborator',
    };
}

function gpDogHandlerDisplayStatus(array $handler): string {
    $status = strtolower(trim((string) ($handler['status'] ?? '')));
    $acceptedAt = trim((string) ($handler['accepted_at'] ?? ''));
    if ($status === 'accepted' && $acceptedAt === '') {
        return 'pending';
    }
    return $status !== '' ? $status : 'unknown';
}

function gpEnsureRequiredHandlerProfileColumns(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = [
        'display_name' => 'TEXT',
        'home_street' => 'TEXT',
        'home_apt' => 'TEXT',
        'home_city' => 'TEXT',
        'home_address' => 'TEXT',
        'phone' => 'TEXT',
        'public_email' => 'TEXT',
        'facebook_url' => 'TEXT',
        'home_state' => 'TEXT',
        'home_zip' => 'TEXT',
        'profile_photo_url' => 'TEXT',
        'backup_contact_name' => 'TEXT',
        'backup_contact_phone' => 'TEXT',
        'public_notes' => 'TEXT',
    ];
    foreach ($columns as $column => $type) {
        $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
    }

    $ensured = true;
}

function gpEnsureOnboardingColumns(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'users'
          AND column_name = 'onboarding_completed_at'
        LIMIT 1
    ");
    $stmt->execute();
    $exists = (bool) $stmt->fetchColumn();

    if (!$exists) {
        $pdo->exec('ALTER TABLE users ADD COLUMN onboarding_completed_at TIMESTAMP');
        $pdo->exec("UPDATE users SET onboarding_completed_at = CURRENT_TIMESTAMP WHERE onboarding_completed_at IS NULL AND created_at < CURRENT_TIMESTAMP - INTERVAL '1 hour' AND lower(coalesce(username, '')) <> 'admin'");
    }

    $ensured = true;
}

function gpBackfillKnownRequiredHandlerProfiles(PDO $pdo): void {
    static $backfilled = false;
    if ($backfilled) {
        return;
    }
    gpEnsureRequiredHandlerProfileColumns($pdo);

    $rows = [
        [
            'usernames' => ['admin'],
            'display_name' => 'GuidePaw Admin',
            'home_street' => '123 GuidePaw Lane',
            'home_apt' => '',
            'home_city' => 'Denver',
            'home_state' => 'CO',
            'home_zip' => '80202',
            'home_address' => "123 GuidePaw Lane\nDenver, CO 80202",
            'phone' => '555-0100',
            'public_email' => 'admin@guidepaw.app',
            'backup_contact_name' => 'GuidePaw Backup Contact',
            'backup_contact_phone' => '555-0101',
            'public_notes' => 'Generic beta admin contact profile. Update before production use.',
        ],
        [
            'usernames' => ['test acct', 'test_acct', 'test account', 'test'],
            'display_name' => 'Test Handler',
            'home_street' => '456 GuidePaw Lane',
            'home_apt' => '',
            'home_city' => 'Denver',
            'home_state' => 'CO',
            'home_zip' => '80203',
            'home_address' => "456 GuidePaw Lane\nDenver, CO 80203",
            'phone' => '555-0102',
            'public_email' => 'test@example.com',
            'backup_contact_name' => 'Test Backup Contact',
            'backup_contact_phone' => '555-0103',
            'public_notes' => 'Generic beta test contact profile. Update before production use.',
        ],
    ];

    $stmt = $pdo->prepare("UPDATE users
        SET
            display_name = COALESCE(NULLIF(display_name, ''), ?),
            home_street = COALESCE(NULLIF(home_street, ''), ?),
            home_apt = COALESCE(NULLIF(home_apt, ''), ?),
            home_city = COALESCE(NULLIF(home_city, ''), ?),
            home_address = COALESCE(NULLIF(home_address, ''), ?),
            phone = COALESCE(NULLIF(phone, ''), ?),
            public_email = COALESCE(NULLIF(public_email, ''), NULLIF(email, ''), ?),
            home_state = COALESCE(NULLIF(home_state, ''), ?),
            home_zip = COALESCE(NULLIF(home_zip, ''), ?),
            backup_contact_name = COALESCE(NULLIF(backup_contact_name, ''), ?),
            backup_contact_phone = COALESCE(NULLIF(backup_contact_phone, ''), ?),
            public_notes = COALESCE(NULLIF(public_notes, ''), ?)
        WHERE lower(username) = ANY(?) AND lower(coalesce(username, '')) <> 'admin'
    ");

    foreach ($rows as $row) {
        $stmt->execute([
            $row['display_name'],
            $row['home_street'],
            $row['home_apt'],
            $row['home_city'],
            $row['home_address'],
            $row['phone'],
            $row['public_email'],
            $row['home_state'],
            $row['home_zip'],
            $row['backup_contact_name'],
            $row['backup_contact_phone'],
            $row['public_notes'],
            '{' . implode(',', array_map(static fn($v) => '"' . str_replace('"', '\\"', strtolower($v)) . '"', $row['usernames'])) . '}',
        ]);
    }

    $backfilled = true;
}

function gpRequiredHandlerProfileFields(): array {
    return [
        'display_name' => 'Display name',
        'home_street' => 'Home street',
        'home_city' => 'Home city',
        'home_state' => 'Home state',
        'home_zip' => 'Home ZIP',
        'phone' => 'Public phone',
        'public_email' => 'Public email',
    ];
}

function gpMissingRequiredHandlerProfileFields(array $user): array {
    $missing = [];
    $validStates = [
        'AL' => true,'AK' => true,'AZ' => true,'AR' => true,'CA' => true,'CO' => true,'CT' => true,'DE' => true,'FL' => true,'GA' => true,
        'HI' => true,'ID' => true,'IL' => true,'IN' => true,'IA' => true,'KS' => true,'KY' => true,'LA' => true,'ME' => true,'MD' => true,
        'MA' => true,'MI' => true,'MN' => true,'MS' => true,'MO' => true,'MT' => true,'NE' => true,'NV' => true,'NH' => true,'NJ' => true,
        'NM' => true,'NY' => true,'NC' => true,'ND' => true,'OH' => true,'OK' => true,'OR' => true,'PA' => true,'RI' => true,'SC' => true,
        'SD' => true,'TN' => true,'TX' => true,'UT' => true,'VT' => true,'VA' => true,'WA' => true,'WV' => true,'WI' => true,'WY' => true,
        'DC' => true,
    ];
    foreach (gpRequiredHandlerProfileFields() as $field => $label) {
        if (trim((string) ($user[$field] ?? '')) === '') {
            $missing[$field] = $label;
        }
    }
    if (!empty($user['home_state']) && !isset($validStates[strtoupper(trim((string) $user['home_state']))])) {
        $missing['home_state'] = 'Valid home state';
    }
    if (!empty($user['public_email']) && !filter_var((string) $user['public_email'], FILTER_VALIDATE_EMAIL)) {
        $missing['public_email'] = 'Valid public email';
    }
    return $missing;
}

function gpCurrentPageName(): string {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    return basename($path) ?: 'index.php';
}

function gpProfileCompletionGateAllowedPage(): bool {
    $allowed = [
        'handler_profile.php',
        'logout.php',
        'login.php',
        'register.php',
        'forgot_password.php',
        'reset_password.php',
    ];
    return in_array(gpCurrentPageName(), $allowed, true);
}

function gpOnboardingGateAllowedPage(): bool {
    if (gpCurrentPageName() !== 'onboarding_setup.php') {
        return in_array(gpCurrentPageName(), [
            'logout.php',
            'login.php',
            'register.php',
            'reset_password.php',
            'handler_profile.php',
        ], true);
    }

    return (string) ($_GET['preview'] ?? '') === '1';
}

function gpEnforceHandlerProfileCompletion(PDO $pdo, array $user): void {
    if (strtolower(trim((string) ($user['username'] ?? ''))) === 'admin') {
        return;
    }
    if (gpProfileCompletionGateAllowedPage()) {
        return;
    }

    $missing = gpMissingRequiredHandlerProfileFields($user);
    if (!$missing) {
        return;
    }

    $_SESSION['handler_profile_required_missing'] = array_values($missing);
    $returnTo = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: handler_profile.php?required=1&return_to=' . rawurlencode((string) $returnTo));
    exit;
}

function gpNeedsOnboarding(array $user): bool {
    if (strtolower(trim((string) ($user['username'] ?? ''))) === 'admin') {
        return false;
    }
    return trim((string) ($user['onboarding_completed_at'] ?? '')) === '';
}

function gpEnforceOnboardingCompletion(PDO $pdo, array $user): void {
    gpEnsureOnboardingColumns($pdo);

    if (gpOnboardingGateAllowedPage()) {
        return;
    }

    if (!gpNeedsOnboarding($user)) {
        return;
    }

    $_SESSION['onboarding_return_to'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: onboarding_setup.php?required=1');
    exit;
}

function gpMarkOnboardingComplete(PDO $pdo, int $userId): void {
    gpEnsureOnboardingColumns($pdo);
    $stmt = $pdo->prepare('UPDATE users SET onboarding_completed_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$userId]);
}
