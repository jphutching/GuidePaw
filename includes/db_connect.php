<?php
session_start();

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/paywalls.php';
require_once __DIR__ . '/training_data.php';
require_once __DIR__ . '/db_core.php';
require_once __DIR__ . '/address_helpers.php';
require_once __DIR__ . '/handler_profile_helpers.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/training_helpers.php';

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
