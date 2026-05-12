<?php
session_start();

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/paywalls.php';
require_once __DIR__ . '/training_data.php';

$dbDriver = 'pgsql';
$dbHost = appEnv('DB_HOST', 'localhost');
$dbPort = appEnv('DB_PORT', '5432');
$dbName = appEnv('DB_DATABASE', 'psd_app_logs');
$dbUser = appEnv('DB_USERNAME', 'root');
$dbPass = appEnv('DB_PASSWORD', '');
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    if (appEnv('APP_ENV', 'production') === 'production') {
        die('Database connection failed. Check environment variables and database availability.');
    }
    die('Connection failed: ' . $e->getMessage());
}



function dbDriverName(): string {
    return 'pgsql';
}

function dbIsPgsql(): bool {
    return true;
}

function dbDateAdd(string $baseExpr, int $amount, string $unit): string {
    $amount = (int) $amount;
    $unit = strtoupper(trim($unit));
    if (!in_array($unit, ['SECOND','MINUTE','HOUR','DAY','MONTH','YEAR'], true)) {
        throw new InvalidArgumentException('Unsupported interval unit.');
    }
    return sprintf("%s + INTERVAL '%d %s'", $baseExpr, $amount, strtolower($unit));
}

function dbDateSub(string $baseExpr, int $amount, string $unit): string {
    return dbDateAdd($baseExpr, -1 * abs($amount), $unit);
}

function insertAndGetId(PDO $pdo, string $sqlWithoutReturning, array $params = []): int {
    $sql = $sqlWithoutReturning . ' RETURNING id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

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

function redirectToAuth(string $reason = 'login_required'): void {
    $target = userCount($GLOBALS['pdo']) === 0 ? 'register.php' : 'login.php';
    header('Location: ' . $target . '?msg=' . urlencode($reason));
    exit;
}

function userCount(PDO $pdo): int {
    static $count = null;
    if ($count !== null) {
        return $count;
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    return $count;
}

function logoutSessionState(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function gpEnsureRequiredHandlerProfileColumns(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = [
        'display_name' => 'TEXT',
        'home_address' => 'TEXT',
        'phone' => 'TEXT',
        'public_email' => 'TEXT',
        'facebook_url' => 'TEXT',
        'home_state' => 'TEXT',
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
            'home_address' => '123 GuidePaw Lane, Denver, CO 80202',
            'phone' => '555-0100',
            'public_email' => 'admin@guidepaw.app',
            'backup_contact_name' => 'GuidePaw Backup Contact',
            'backup_contact_phone' => '555-0101',
            'public_notes' => 'Generic beta admin contact profile. Update before production use.',
        ],
        [
            'usernames' => ['test acct', 'test_acct', 'test account', 'test'],
            'display_name' => 'Test Handler',
            'home_address' => '456 GuidePaw Lane, Denver, CO 80203',
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
            home_address = COALESCE(NULLIF(home_address, ''), ?),
            phone = COALESCE(NULLIF(phone, ''), ?),
            public_email = COALESCE(NULLIF(public_email, ''), NULLIF(email, ''), ?),
            home_state = COALESCE(NULLIF(home_state, ''), ?),
            backup_contact_name = COALESCE(NULLIF(backup_contact_name, ''), ?),
            backup_contact_phone = COALESCE(NULLIF(backup_contact_phone, ''), ?),
            public_notes = COALESCE(NULLIF(public_notes, ''), ?)
        WHERE lower(username) = ANY(?) AND lower(coalesce(username, '')) <> 'admin'
    ");

    foreach ($rows as $row) {
        $stmt->execute([
            $row['display_name'],
            $row['home_address'],
            $row['phone'],
            $row['public_email'],
            '',
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
        'home_address' => 'Home address',
        'phone' => 'Public phone',
        'public_email' => 'Public email',
    ];
}

function gpMissingRequiredHandlerProfileFields(array $user): array {
    $missing = [];
    foreach (gpRequiredHandlerProfileFields() as $field => $label) {
        if (trim((string) ($user[$field] ?? '')) === '') {
            $missing[$field] = $label;
        }
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

function checkLogin(): void {
    if (empty($_SESSION['user_id'])) {
        redirectToAuth();
    }

    $userId = (int) $_SESSION['user_id'];
    if ($userId <= 0) {
        logoutSessionState();
        redirectToAuth('session_invalid');
    }

    gpBackfillKnownRequiredHandlerProfiles($GLOBALS['pdo']);
    gpEnsureOnboardingColumns($GLOBALS['pdo']);
    gpEnsureUserTierColumn($GLOBALS['pdo']);

    $user = getUserRecord($GLOBALS['pdo'], $userId);
    if (!$user) {
        logoutSessionState();
        redirectToAuth('session_expired');
    }

    if (array_key_exists('account_status', $user) && (string) $user['account_status'] === 'deactivated') {
        logoutSessionState();
        redirectToAuth('account_deactivated');
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['dog_name'] = $user['dog_name'] ?? '';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['user_role'] = gpUserRole($user);
    $_SESSION['is_admin'] = in_array($_SESSION['user_role'], ['master_admin', 'basic_admin'], true) ? 1 : 0;

    gpEnforceHandlerProfileCompletion($GLOBALS['pdo'], $user);
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): void {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Security check failed. Please refresh and try again.');
    }
}

function getUserRecord(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
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

function getUpcomingVetReminders(PDO $pdo, int $userId, int $limit = 5): array {
    $stmt = $pdo->prepare("SELECT a.*, d.name AS dog_name, v.clinic_name, v.phone AS vet_phone
        FROM dog_vet_appointments a
        JOIN dogs d ON d.id = a.dog_id
        LEFT JOIN dog_vets v ON v.id = a.dog_vet_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NOT NULL
        WHERE (d.owner_user_id = ? OR dh.id IS NOT NULL)
          AND a.status = 'scheduled'
          AND (
                (a.reminder_at IS NOT NULL AND a.reminder_at <= " . dbDateAdd('CURRENT_TIMESTAMP', 7, 'DAY') . ")
                OR a.appointment_at <= " . dbDateAdd('CURRENT_TIMESTAMP', 7, 'DAY') . "
              )
        ORDER BY COALESCE(a.reminder_at, a.appointment_at) ASC
        LIMIT " . max(1, (int) $limit));
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll() ?: [];
}


function getDogMedications(PDO $pdo, int $dogId): array {
    $stmt = $pdo->prepare("SELECT * FROM dog_medications WHERE dog_id = ? ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'paused' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END, COALESCE(reminder_time, refill_date, start_date) ASC, id DESC");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll() ?: [];
}

function getDogCertificationItems(PDO $pdo, int $dogId): array {
    $stmt = $pdo->prepare("SELECT * FROM dog_certification_items WHERE dog_id = ? ORDER BY category ASC, sort_order ASC, id ASC");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll() ?: [];
}

function getLatestCertificationAssessment(PDO $pdo, int $dogId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM dog_certification_assessments WHERE dog_id = ? ORDER BY assessment_date DESC, id DESC LIMIT 1");
    $stmt->execute([$dogId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function seedCertificationChecklist(PDO $pdo, int $dogId): void {
    $existing = $pdo->prepare("SELECT COUNT(*) FROM dog_certification_items WHERE dog_id = ?");
    $existing->execute([$dogId]);
    if ((int)$existing->fetchColumn() > 0) return;
    $template = [
        'Public Access' => ['Calm entry and exit','Loose leash through public space','Sit or down at handler side','Ignore food on floor','Ignore friendly strangers','Settle quietly under table or chair','Ride elevator or automatic door calmly','Hold position during conversation/check-out'],
        'Task Work' => ['Primary trained task is reliable','Task cue response within 3 seconds','Task works in low distraction setting','Task works in moderate distraction setting','Task works in high distraction setting'],
        'Manners & Safety' => ['No jumping on people','No barking or whining in public','Housebroken and clean public behavior','Comfortable around carts, noises, and traffic','Recovery after startle within a short time'],
        'Travel / OTR' => ['Settles safely in cab','Truck stop potty routine is consistent','Handles fuel island / parking noise','Sleeps and resets well on the road'],
    ];
    $stmt = $pdo->prepare("INSERT INTO dog_certification_items (dog_id, category, item_name, description, is_required, sort_order) VALUES (?,?,?,?,?,?)");
    foreach ($template as $category => $items) foreach ($items as $idx => $item) $stmt->execute([$dogId, $category, $item, null, 1, $idx + 1]);
}

function getDogAlertItems(PDO $pdo, int $userId, int $dogId): array {
    $alerts = [];
    if (!hasDogAccess($pdo, $userId, $dogId)) return $alerts;
    $stmt = $pdo->prepare("SELECT MAX(log_date) FROM daily_logs WHERE dog_id = ?");
    $stmt->execute([$dogId]);
    $lastLog = $stmt->fetchColumn();
    if (!$lastLog) $alerts[] = ['level'=>'warning','title'=>'No training logs yet','detail'=>'Add the first training log for this dog to start trends and reminders.','action_url'=>'log_entry.php','action_label'=>'Add log'];
    elseif (strtotime($lastLog) < strtotime('-3 days')) $alerts[] = ['level'=>'warning','title'=>'Training gap','detail'=>'No log has been recorded in the last 3 days.','action_url'=>'log_entry.php','action_label'=>'Add log'];
    $stmt = $pdo->prepare("SELECT ROUND(AVG(focus_level),2) FROM (SELECT focus_level FROM daily_logs WHERE dog_id = ? ORDER BY log_date DESC LIMIT 7) recent_logs");
    $stmt->execute([$dogId]);
    $avgFocus = $stmt->fetchColumn();
    if ($avgFocus !== null && (float)$avgFocus < 3) $alerts[] = ['level'=>'danger','title'=>'Focus trend is slipping','detail'=>'Average focus across the latest 7 logs is ' . $avgFocus . '/5.','action_url'=>'log_entry.php','action_label'=>'Log training'];
    $stmt = $pdo->prepare("SELECT title, appointment_at FROM dog_vet_appointments WHERE dog_id = ? AND status='scheduled' AND appointment_at < CURRENT_TIMESTAMP ORDER BY appointment_at ASC LIMIT 1");
    $stmt->execute([$dogId]);
    if ($row = $stmt->fetch()) $alerts[] = ['level'=>'danger','title'=>'Overdue appointment','detail'=>$row['title'] . ' was scheduled for ' . date('M j, Y g:i A', strtotime($row['appointment_at'])) . '.','action_url'=>'appointments.php','action_label'=>'Open appointments'];
    $stmt = $pdo->prepare("SELECT title, appointment_at FROM dog_vet_appointments WHERE dog_id = ? AND status='scheduled' AND appointment_at BETWEEN CURRENT_TIMESTAMP AND " . dbDateAdd('CURRENT_TIMESTAMP', 3, 'DAY') . " ORDER BY appointment_at ASC LIMIT 1");
    $stmt->execute([$dogId]);
    if ($row = $stmt->fetch()) $alerts[] = ['level'=>'info','title'=>'Upcoming appointment','detail'=>$row['title'] . ' is due on ' . date('M j, Y g:i A', strtotime($row['appointment_at'])) . '.','action_url'=>'appointments.php','action_label'=>'Open appointments'];
    $stmt = $pdo->prepare("SELECT medication_name, refill_date FROM dog_medications WHERE dog_id = ? AND status='active' AND refill_date IS NOT NULL AND refill_date <= " . dbDateAdd('CURRENT_DATE', 5, 'DAY') . " ORDER BY refill_date ASC LIMIT 1");
    $stmt->execute([$dogId]);
    if ($row = $stmt->fetch()) { $when = strtotime($row['refill_date']) < strtotime('today') ? 'was due' : 'is due'; $alerts[] = ['level'=>'warning','title'=>'Medication refill ' . $when,'detail'=>$row['medication_name'] . ' ' . $when . ' ' . date('M j, Y', strtotime($row['refill_date'])) . '.','action_url'=>'medications.php','action_label'=>'Open medications']; }
    $stmt = $pdo->prepare("SELECT medication_name, reminder_time FROM dog_medications WHERE dog_id = ? AND status='active' AND reminder_time IS NOT NULL AND reminder_time <= " . dbDateAdd('CURRENT_TIMESTAMP', 24, 'HOUR') . " ORDER BY reminder_time ASC LIMIT 1");
    $stmt->execute([$dogId]);
    if ($row = $stmt->fetch()) $alerts[] = ['level'=>'info','title'=>'Medication reminder','detail'=>$row['medication_name'] . ' has a reminder at ' . date('M j, g:i A', strtotime($row['reminder_time'])) . '.','action_url'=>'medications.php','action_label'=>'Open medications'];
    $items = getDogCertificationItems($pdo, $dogId);
    if (!$items) $alerts[] = ['level'=>'info','title'=>'Certification checklist not started','detail'=>'Load the starter checklist to begin tracking public access and task reliability.','action_url'=>'certification.php','action_label'=>'Open certification'];
    else { $total = count($items); $proficient = count(array_filter($items, fn($i) => ($i['status'] ?? '') === 'proficient')); if ($total > 0 && ($proficient / $total) < 0.5) $alerts[] = ['level'=>'warning','title'=>'Certification progress under 50%','detail'=>$proficient . ' of ' . $total . ' checklist items are marked proficient.','action_url'=>'certification.php','action_label'=>'Open certification']; }
    $trainingItems = getDogTrainingItems($pdo, $dogId);
    if (!$trainingItems) {
        $alerts[] = ['level'=>'info','title'=>'Training ladder not loaded','detail'=>'Load the starter training ladder to track candidate screening, commands, CGC items, and service-task progression.','action_url'=>'training_program.php','action_label'=>'Open training'];
    } else {
        $inProgress = count(array_filter($trainingItems, fn($i) => in_array(($i['status'] ?? ''), ['in_progress','proofing'], true)));
        $mastered = count(array_filter($trainingItems, fn($i) => ($i['status'] ?? '') === 'mastered'));
        if ($inProgress === 0 && $mastered === 0) {
            $alerts[] = ['level'=>'warning','title'=>'Training ladder has not been started','detail'=>'Pick one or two foundation skills and start marking progress.','action_url'=>'training_program.php','action_label'=>'Open training'];
        }
        $cgcItems = array_values(array_filter($trainingItems, fn($i) => ($i['track_code'] ?? '') === 'akc_cgc'));
        if ($cgcItems) {
            $cgcMastered = count(array_filter($cgcItems, fn($i) => ($i['status'] ?? '') === 'mastered'));
            if ($cgcMastered >= 8) {
                $alerts[] = ['level'=>'info','title'=>'AKC CGC may be in reach','detail'=>'This dog has ' . $cgcMastered . ' CGC items marked mastered.','action_url'=>'training_program.php','action_label'=>'Open training'];
            }
        }
    }
    return $alerts;
}


function getDogTrainingProfile(PDO $pdo, int $dogId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM dog_training_profiles WHERE dog_id = ? LIMIT 1");
    $stmt->execute([$dogId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getDogTrainingItems(PDO $pdo, int $dogId): array {
    $stmt = $pdo->prepare("SELECT * FROM dog_training_items WHERE dog_id = ? ORDER BY level ASC, category ASC, sort_order ASC, id ASC");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll() ?: [];
}

function seedDogTrainingProgram(PDO $pdo, int $dogId): void {
    $check = $pdo->prepare("SELECT COUNT(*) FROM dog_training_items WHERE dog_id = ?");
    $check->execute([$dogId]);
    if ((int) $check->fetchColumn() > 0) {
        return;
    }

    $template = getTrainingProgramTemplate();
    $stmt = $pdo->prepare("INSERT INTO dog_training_items (dog_id, category, track_code, level, item_name, description, sort_order) VALUES (?,?,?,?,?,?,?)");
    foreach ($template as $category => $items) {
        foreach ($items as $idx => $item) {
            $stmt->execute([
                $dogId,
                $category,
                $item['track'] ?? null,
                (int) ($item['level'] ?? 1),
                $item['item_name'],
                $item['description'] ?? null,
                $idx + 1,
            ]);
        }
    }
}

function upsertDogTrainingProfile(PDO $pdo, int $dogId, int $userId, array $data): void {
    $existing = getDogTrainingProfile($pdo, $dogId);
    $fields = [
        $data['training_mode'] ?? 'self_training',
        $data['trainer_name'] ?? null,
        $data['business_name'] ?? null,
        $data['credentials'] ?? null,
        $data['trainer_phone'] ?? null,
        $data['trainer_email'] ?? null,
        $data['trainer_website'] ?? null,
        $data['handler_experience'] ?? null,
        $data['handler_goals'] ?? null,
        $data['candidate_stage'] ?? 'prospect',
        $data['candidate_notes'] ?? null,
        $data['training_focus'] ?? null,
        $data['notes'] ?? null,
    ];
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE dog_training_profiles SET training_mode=?, trainer_name=?, business_name=?, credentials=?, trainer_phone=?, trainer_email=?, trainer_website=?, handler_experience=?, handler_goals=?, candidate_stage=?, candidate_notes=?, training_focus=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE dog_id=?");
        $stmt->execute(array_merge($fields, [$dogId]));
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO dog_training_profiles (dog_id, created_by_user_id, training_mode, trainer_name, business_name, credentials, trainer_phone, trainer_email, trainer_website, handler_experience, handler_goals, candidate_stage, candidate_notes, training_focus, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute(array_merge([$dogId, $userId], $fields));
}

function getTrainingSuggestions(PDO $pdo, int $userId, int $dogId): array {
    if (!hasDogAccess($pdo, $userId, $dogId)) {
        return [];
    }

    $profile = getDogTrainingProfile($pdo, $dogId) ?: ['training_mode' => 'self_training', 'candidate_stage' => 'prospect'];
    $items = getDogTrainingItems($pdo, $dogId);
    $suggestions = [];
    if (!$items) {
        $suggestions[] = 'Load the starter training ladder so the app can coach next steps, candidate screening, and test-track benchmarks.';
        return $suggestions;
    }

    $notStarted = array_values(array_filter($items, fn($item) => ($item['status'] ?? '') === 'not_started'));
    $inProgress = array_values(array_filter($items, fn($item) => ($item['status'] ?? '') === 'in_progress'));
    $needsProof = array_values(array_filter($items, fn($item) => ($item['status'] ?? '') === 'proofing'));
    $candidateItems = array_values(array_filter($items, fn($item) => ($item['track_code'] ?? '') === 'candidate_screen'));
    $candidateMastered = count(array_filter($candidateItems, fn($item) => ($item['status'] ?? '') === 'mastered'));
    $candidateStarted = count(array_filter($candidateItems, fn($item) => in_array(($item['status'] ?? ''), ['in_progress', 'proofing', 'mastered'], true)));

    $recentLogStmt = $pdo->prepare("SELECT focus_level, location_type, log_date FROM daily_logs WHERE dog_id = ? ORDER BY log_date DESC LIMIT 5");
    $recentLogStmt->execute([$dogId]);
    $recentLogs = $recentLogStmt->fetchAll() ?: [];
    $avgFocus = null;
    if ($recentLogs) {
        $avgFocus = array_sum(array_map(fn($r) => (int) $r['focus_level'], $recentLogs)) / count($recentLogs);
    }

    $candidateStage = $profile['candidate_stage'] ?? 'prospect';
    if (in_array($candidateStage, ['prospect', 'foundation'], true) && $candidateItems && $candidateMastered < 5) {
        $suggestions[] = 'Candidate screen first: keep checking recovery, neutrality, handling tolerance, and resilience before piling on specialized service tasks.';
    }
    if ($candidateItems && $candidateStarted === 0) {
        $suggestions[] = 'Start with one candidate-screen session this week so you know whether this dog is building the right raw material for service work.';
    }
    if (!empty($recentLogs) && $avgFocus !== null && $avgFocus < 3) {
        $suggestions[] = 'Recent focus is soft. Go back to short Level 1-2 reps like watch me, place, and loose-leash work before adding pressure.';
    }
    if ($notStarted) {
        $first = $notStarted[0];
        $suggestions[] = 'Next new skill: ' . $first['item_name'] . ' (' . $first['category'] . '). Keep sessions short and end on a win.';
    }
    if ($inProgress) {
        $first = $inProgress[0];
        $suggestions[] = 'Keep building ' . $first['item_name'] . ' in one easier setting and one real-world setting this week.';
    }
    if ($needsProof) {
        $first = $needsProof[0];
        $suggestions[] = 'Proof ' . $first['item_name'] . ' around higher distraction before marking it reliable.';
    }

    $trackCounts = [];
    foreach ($items as $item) {
        $track = $item['track_code'] ?? '';
        if (!$track) {
            continue;
        }
        $trackCounts[$track]['total'] = ($trackCounts[$track]['total'] ?? 0) + 1;
        if (($item['status'] ?? '') === 'mastered') {
            $trackCounts[$track]['mastered'] = ($trackCounts[$track]['mastered'] ?? 0) + 1;
        }
    }

    if (($trackCounts['akc_cgc']['mastered'] ?? 0) >= 8) {
        $suggestions[] = 'CGC track looks strong. Consider a mock AKC Canine Good Citizen run-through.';
    }
    if (($trackCounts['akc_cgca']['mastered'] ?? 0) >= 2 || ($trackCounts['akc_urban']['mastered'] ?? 0) >= 2) {
        $suggestions[] = 'Community and urban skills are coming along. Add one deliberate field trip to proof busier public behavior.';
    }
    if (($trackCounts['service_task']['mastered'] ?? 0) >= 2 && ($trackCounts['public_access_benchmark']['mastered'] ?? 0) < 2) {
        $suggestions[] = 'Task work is moving, but public access still needs reps. Pair one task drill with one calm public outing this week.';
    }

    if (($profile['training_mode'] ?? 'self_training') === 'self_training') {
        $suggestions[] = 'Self-training tip: film one short session this week and review timing, reward placement, and leash tension.';
        $suggestions[] = 'Self-training tip: work one skill in the cab, one at a stop, and one in a public space to avoid context-only learning.';
        $suggestions[] = 'Ad hoc self-training idea: run a 3-minute reset session with engagement, one easy win, one harder rep, then quit before fatigue.';
    } elseif (($profile['training_mode'] ?? '') === 'professional_trainer' && !empty($profile['trainer_name'])) {
        $suggestions[] = 'Trainer coordination: bring your log history and one focused question to ' . $profile['trainer_name'] . ' for the next session.';
        if (!empty($profile['business_name'])) {
            $suggestions[] = 'Professional trainer note: keep homework tied to ' . $profile['business_name'] . ' so between-session reps stay consistent.';
        }
    } else {
        $suggestions[] = 'Hybrid plan: let the trainer shape the hard pieces, then rehearse daily reps yourself between sessions.';
    }

    return array_slice(array_values(array_unique($suggestions)), 0, 8);
}


function dbNowExpression(): string {
    return 'CURRENT_TIMESTAMP';
}

function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?)");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function currentSchemaVersion(PDO $pdo): string {
    if (!tableExists($pdo, 'schema_migrations')) {
        return 'untracked';
    }
    $stmt = $pdo->query("SELECT COALESCE(MAX(version), 'none') FROM schema_migrations");
    return (string) $stmt->fetchColumn();
}

function appliedMigrationVersions(PDO $pdo): array {
    if (!tableExists($pdo, 'schema_migrations')) {
        return [];
    }
    return $pdo->query('SELECT version FROM schema_migrations ORDER BY version ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function availableMigrationFiles(string $driver): array {
    $dir = __DIR__ . '/../sql/migrations/' . 'pgsql';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function applyPendingMigrations(PDO $pdo): array {
    $driver = dbDriverName();
    $applied = array_flip(appliedMigrationVersions($pdo));
    $results = [];
    foreach (availableMigrationFiles($driver) as $path) {
        $version = basename($path);
        if (isset($applied[$version])) {
            continue;
        }
        $sql = trim((string) file_get_contents($path));
        if ($sql === '') {
            continue;
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, CURRENT_TIMESTAMP)');
            $stmt->execute([$version]);
            $pdo->commit();
            $results[] = $version;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
    return $results;
}
