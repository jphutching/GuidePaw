<?php
require_once 'includes/db_connect.php';
require_once 'includes/public_dog_profile_token.php';
require_once 'includes/public_contact_defaults.php';
require_once 'includes/qr_tracking.php';
require_once 'includes/app_config.php';
require_once 'includes/seo.php';
require_once 'includes/support_badges.php';

$dogId = isset($_GET['dog']) ? (int) $_GET['dog'] : 0;
$token = trim((string) ($_GET['token'] ?? ''));

if ($dogId <= 0 || $token === '' || !publicDogProfileTokenValid($dogId, $token)) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Profile not found</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="card p-4 shadow-sm"><h1 class="h4">Profile not found</h1><p class="text-muted mb-0">This public dog profile link is invalid or expired.</p></div></main></body></html>';
    exit;
}

function publicDogColumnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dogs' AND column_name = ? LIMIT 1");
    $stmt->execute([$column]);
    return (bool) $stmt->fetchColumn();
}

$possibleColumns = [
    'id', 'user_id', 'owner_user_id', 'name', 'breed', 'weight_lbs', 'access_role', 'microchip_id', 'chip_number', 'chip_registry',
    'microchip_registry', 'photo_url', 'profile_photo_url', 'handler_photo_url', 'public_notes', 'emergency_notes',
    'handler_name', 'handler_address', 'handler_phone', 'handler_email', 'backup_contact_name', 'backup_contact_phone', 'found_dog_instructions',
    'service_tasks', 'medical_alert_notes', 'critical_allergies', 'created_at', 'updated_at'
];
$selectColumns = [];
foreach ($possibleColumns as $column) {
    if ($column === 'id' || publicDogColumnExists($pdo, $column)) {
        $selectColumns[] = $column;
    }
}
if (!in_array('id', $selectColumns, true)) {
    $selectColumns[] = 'id';
}

$sql = 'SELECT ' . implode(', ', array_map(static fn($c) => '"' . str_replace('"', '""', $c) . '"', $selectColumns)) . ' FROM dogs WHERE id = ? LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([$dogId]);
$dog = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dog) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Profile not found</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="card p-4 shadow-sm"><h1 class="h4">Profile not found</h1><p class="text-muted mb-0">This public dog profile does not exist.</p></div></main></body></html>';
    exit;
}

$ownerId = gpDogOwnerIdFromPublicDog($dog);
$user = $ownerId > 0 ? gpFetchUserPublicContact($pdo, $ownerId) : [];
$publicContact = gpDogPublicContactDefaults($pdo, $dog, $user);
$supportBadge = gpSupportBadgeForUser($pdo, $user);

$vet = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM dog_vets WHERE dog_id = ? ORDER BY is_primary DESC, clinic_name ASC LIMIT 1');
    $stmt->execute([$dogId]);
    $vet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $vet = null;
}

$dogName = $dog['name'] ?? 'Service Dog';
$handlerName = $publicContact['handler_name'] ?? 'Handler';
$handlerEmail = $publicContact['handler_email'] ?? '';
$handlerPhone = $publicContact['handler_phone'] ?? '';
$backupName = $publicContact['backup_contact_name'] ?? '';
$backupPhone = $publicContact['backup_contact_phone'] ?? '';
$chipNumber = $dog['chip_number'] ?? ($dog['microchip_id'] ?? '');
$chipRegistry = $dog['chip_registry'] ?? ($dog['microchip_registry'] ?? '');
$dogPhoto = $dog['profile_photo_url'] ?? ($dog['photo_url'] ?? '');
$handlerPhoto = $publicContact['handler_photo_url'] ?? '';
$homeState = $publicContact['home_state'] ?? '';
$tasks = $dog['service_tasks'] ?? '';
$publicNotes = $publicContact['public_notes'] ?? '';
$foundInstructions = $dog['found_dog_instructions'] ?? '';
$criticalAllergies = $dog['critical_allergies'] ?? ($dog['medical_alert_notes'] ?? '');
$reportUrl = 'report_found_dog.php?dog=' . (int) $dogId . '&token=' . rawurlencode($token);
gpEnsureDogQrTrackingTable($pdo);
gpLogDogQrScan($pdo, (int) $dogId);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php guidepawSeoHead([
    'title' => $dogName . ' · Public Dog Profile',
    'description' => 'Public service dog contact profile for ' . $dogName . '. Share found-dog location reports, view handler contact details, and check public notes.',
    'robots' => 'noindex,nofollow',
    'type' => 'profile',
    'image' => $dogPhoto !== '' ? $dogPhoto : '/assets/brand/guidepaw-logo.png',
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f1f5f9; color:#0f172a; }
.profile-shell { max-width: 760px; margin: 0 auto; padding: 1rem; }
.hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 26px; padding: 1.25rem; box-shadow:0 12px 28px rgba(15,23,42,.18); }
.cardx { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:22px; padding:1.1rem; box-shadow:0 8px 22px rgba(15,23,42,.08); }
.photo { width: 112px; height: 112px; border-radius: 24px; object-fit: cover; background:rgba(255,255,255,.18); border:2px solid rgba(255,255,255,.55); }
.label { font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; font-weight:800; color:#64748b; }
.value { font-weight:700; }
.btn-call { border-radius:16px; padding:.85rem 1rem; font-weight:800; }
.warning-note { border-left: 4px solid #dc3545; background:#fff5f5; border-radius:14px; padding:.85rem; }
.found-card { border: 1px solid #bfdbfe; background: #eff6ff; }
.inherited-hint { font-size:.78rem; color:#64748b; margin-top:.35rem; }
.support-badge-card { border:1px solid rgba(59,130,246,.16); background:#fff; border-radius:22px; padding:1rem; box-shadow:0 8px 22px rgba(15,23,42,.08); }
.support-badge-card img { width:96px; height:96px; object-fit:contain; flex:0 0 auto; }
</style>
</head>
<body>
<main class="profile-shell">
    <section class="hero mb-3">
        <div class="d-flex gap-3 align-items-center">
            <?php if ($dogPhoto): ?>
                <img src="<?= e($dogPhoto) ?>" alt="<?= e($dogName) ?>" class="photo">
            <?php else: ?>
                <div class="photo d-flex align-items-center justify-content-center fs-1">🐕</div>
            <?php endif; ?>
            <div>
                <div class="small opacity-75">GuidePaw public service dog profile</div>
                <h1 class="mb-1"><?= e($dogName) ?></h1>
                <div class="opacity-75"><?= e($dog['breed'] ?? 'Breed not listed') ?><?= !empty($dog['access_role']) ? ' • ' . e(ucfirst((string) $dog['access_role'])) : '' ?></div>
            </div>
        </div>
    </section>

    <?php if ($supportBadge): ?>
        <section class="support-badge-card mb-3">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <img src="<?= e($supportBadge['image']) ?>" alt="<?= e($supportBadge['label']) ?>">
                <div class="flex-grow-1">
                    <div class="label mb-1">Support badge</div>
                    <h2 class="h5 mb-1"><?= e($supportBadge['label']) ?></h2>
                    <div class="text-muted small">
                        <?php if (!empty($supportBadge['lifetime'])): ?>
                            Active for life.
                        <?php elseif (!empty($supportBadge['expires_at'])): ?>
                            Active until <?= e((string) $supportBadge['expires_at']) ?>.
                        <?php else: ?>
                            Active support badge.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="cardx found-card mb-3">
        <h2 class="h5 mb-2">Found or saw <?= e($dogName) ?>?</h2>
        <p class="text-muted mb-3">Send the handler a one-time location report. Your location is not continuously tracked.</p>
        <a class="btn btn-primary btn-call w-100" href="<?= e($reportUrl) ?>">Share Found Location</a>
    </section>

    <?php if ($foundInstructions): ?>
        <section class="cardx mb-3 warning-note">
            <h2 class="h5 mb-2">If Found / Emergency Instructions</h2>
            <div><?= nl2br(e($foundInstructions)) ?></div>
        </section>
    <?php endif; ?>

    <section class="cardx mb-3">
        <h2 class="h5 mb-3">Handler Contact</h2>
        <div class="d-flex gap-3 align-items-center mb-3">
            <?php if ($handlerPhoto): ?>
                <img src="<?= e($handlerPhoto) ?>" alt="<?= e($handlerName) ?>" style="width:72px;height:72px;border-radius:18px;object-fit:cover;">
            <?php endif; ?>
            <div>
                <div class="label">Handler</div>
                <div class="value"><?= e($handlerName) ?></div>
                <?php if (($publicContact['handler_email_source'] ?? '') === 'handler_profile' || ($publicContact['handler_phone_source'] ?? '') === 'handler_profile'): ?>
                    <div class="inherited-hint">Using handler profile defaults.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-grid gap-2">
            <?php if ($handlerPhone): ?><a class="btn btn-success btn-call" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $handlerPhone)) ?>">Call Handler</a><?php endif; ?>
            <?php if ($handlerEmail): ?><a class="btn btn-outline-primary btn-call" href="mailto:<?= e($handlerEmail) ?>">Email Handler</a><?php endif; ?>
            <?php if ($backupPhone): ?><a class="btn btn-outline-success btn-call" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $backupPhone)) ?>">Call Backup<?= $backupName ? ': ' . e($backupName) : '' ?></a><?php endif; ?>
            <?php if (!$handlerPhone && !$handlerEmail && !$backupPhone): ?><div class="text-muted">No public contact method has been added yet.</div><?php endif; ?>
        </div>
        <?php if ($homeState): ?>
            <div class="mt-3">
                <div class="label">Home state</div>
                <div class="value"><?= e($homeState) ?></div>
                <?php if (($publicContact['home_state_source'] ?? '') === 'handler_profile'): ?>
                    <div class="inherited-hint">Using handler profile defaults.</div>
                <?php endif; ?>
                <div class="inherited-hint">Used as the ADA card fallback and for lost-dog contact context when GPS is unavailable.</div>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($vet): ?>
        <section class="cardx mb-3"><h2 class="h5 mb-3">Primary Vet</h2><div class="row g-3"><div class="col-12 col-md-6"><div class="label">Clinic</div><div class="value"><?= e($vet['clinic_name'] ?? 'Not listed') ?></div></div><div class="col-12 col-md-6"><div class="label">Veterinarian</div><div class="value"><?= !empty($vet['vet_name']) ? e($vet['vet_name']) : 'Not listed' ?></div></div><?php if (!empty($vet['phone'])): ?><div class="col-12"><a class="btn btn-outline-success btn-call w-100" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $vet['phone'])) ?>">Call Vet: <?= e($vet['phone']) ?></a></div><?php endif; ?><?php if (!empty($vet['email'])): ?><div class="col-12"><a class="btn btn-outline-primary btn-call w-100" href="mailto:<?= e($vet['email']) ?>">Email Vet</a></div><?php endif; ?><?php if (!empty($vet['address_text'])): ?><div class="col-12"><div class="label">Address</div><div><?= nl2br(e($vet['address_text'])) ?></div></div><?php endif; ?><?php if (!empty($vet['notes'])): ?><div class="col-12"><div class="label">Vet notes</div><div><?= nl2br(e($vet['notes'])) ?></div></div><?php endif; ?></div></section>
    <?php endif; ?>

    <section class="cardx mb-3"><h2 class="h5 mb-3">Identification</h2><div class="row g-3"><div class="col-12 col-md-6"><div class="label">Dog name</div><div class="value"><?= e($dogName) ?></div></div><div class="col-12 col-md-6"><div class="label">Breed</div><div class="value"><?= e($dog['breed'] ?? 'Not listed') ?></div></div><div class="col-12 col-md-6"><div class="label">Microchip number</div><div class="value"><?= $chipNumber ? e($chipNumber) : 'Not listed' ?></div></div><div class="col-12 col-md-6"><div class="label">Chip registry</div><div class="value"><?= $chipRegistry ? e($chipRegistry) : 'Not listed' ?></div></div></div></section>

    <?php if ($criticalAllergies): ?><section class="cardx mb-3 warning-note"><h2 class="h5 mb-2">Critical Medical / Allergy Note</h2><div><?= nl2br(e($criticalAllergies)) ?></div></section><?php endif; ?>
    <?php if ($tasks || $publicNotes): ?><section class="cardx mb-3"><h2 class="h5 mb-3">Public Notes</h2><?php if ($tasks): ?><div class="mb-3"><div class="label">Service tasks</div><div><?= nl2br(e($tasks)) ?></div></div><?php endif; ?><?php if ($publicNotes): ?><div><div class="label">Handler notes</div><div><?= nl2br(e($publicNotes)) ?></div></div><?php endif; ?></section><?php endif; ?>

    <section class="cardx mb-3">
        <h2 class="h5 mb-2">Choosing a breed?</h2>
        <p class="text-muted mb-3">Use the public questionnaire to compare breed groups by size, energy, grooming, and the kind of work you need.</p>
        <div class="d-grid gap-2">
            <a class="btn btn-outline-primary btn-call w-100" href="breed_questionnaire.php">Open Breed Questionnaire</a>
            <a class="btn btn-outline-secondary btn-call w-100" href="breed_comparison_hub.php">Open Breed Comparison Hub</a>
            <a class="btn btn-outline-dark btn-call w-100" href="faq.php">Read FAQ</a>
            <a class="btn btn-outline-dark btn-call w-100" href="air_travel_rights.php">Air Travel Rights</a>
        </div>
        <div class="inherited-hint mt-2">Use the air-travel guide for service-dog flight rules and the separate note for dogs in training.</div>
    </section>

    <section class="cardx small text-muted mb-3">This public profile is intended to help return or identify a service dog and contact the handler or vet. It does not display private training logs, medical records, account data, or full app history.</section>
</main>
</body>
</html>
