<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require 'includes/validation.php';
require 'includes/dog_breeds.php';
require_once 'includes/ada_state_laws.php';
require_once 'includes/training_data.php';
require_once 'includes/public_dog_profile_token.php';
require_once 'includes/public_contact_defaults.php';
require_once 'includes/profile_image_tools.php';
require_once 'includes/support_badges.php';
checkLogin();

function gpEnsureDogPublicProfileColumns(PDO $pdo): void
{
    $columns = [
        'chip_registry' => 'TEXT', 'profile_photo_url' => 'TEXT', 'handler_photo_url' => 'TEXT',
        'handler_name' => 'TEXT', 'handler_street' => 'TEXT', 'handler_apt' => 'TEXT', 'handler_city' => 'TEXT', 'handler_address' => 'TEXT', 'handler_phone' => 'TEXT', 'handler_email' => 'TEXT', 'handler_state' => 'TEXT', 'handler_zip' => 'TEXT',
        'backup_contact_name' => 'TEXT', 'backup_contact_phone' => 'TEXT', 'found_dog_instructions' => 'TEXT',
        'public_notes' => 'TEXT', 'service_tasks' => 'TEXT', 'critical_allergies' => 'TEXT',
    ];
    foreach ($columns as $column => $type) {
        $pdo->exec('ALTER TABLE dogs ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
    }
}

function gpEnsureHandlerProfileColumnsForDogProfile(PDO $pdo): void
{
    $columns = [
        'display_name' => 'TEXT', 'home_street' => 'TEXT', 'home_apt' => 'TEXT', 'home_city' => 'TEXT', 'home_address' => 'TEXT', 'phone' => 'TEXT', 'public_email' => 'TEXT', 'home_state' => 'TEXT', 'home_zip' => 'TEXT', 'profile_photo_url' => 'TEXT',
        'backup_contact_name' => 'TEXT', 'backup_contact_phone' => 'TEXT', 'public_notes' => 'TEXT',
    ];
    foreach ($columns as $column => $type) {
        $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
    }
}

function gpDogProfileSourceLabel(string $source): string
{
    return match ($source) {
        'dog_profile' => 'Dog Profile',
        'handler_profile' => 'Handler Profile default',
        'owner_account' => 'Owner account fallback',
        default => 'Missing',
    };
}

gpEnsureDogPublicProfileColumns($pdo);
gpEnsureHandlerProfileColumnsForDogProfile($pdo);

$userId = (int) $_SESSION['user_id'];
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : getActiveDogId($pdo, $userId);
if (!$dogId || !hasDogAccess($pdo, $userId, $dogId)) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 820px;">
    <div class="alert alert-warning">Dog profile not found or not available to this account.</div>
    <a href="dogs.php" class="btn btn-outline-secondary">Dogs</a>
</div>
</body>
</html>
<?php
    exit;
}
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$errors = [];
$breedCatalog = getDogBreedsCatalog();
$chipLinks = getMicrochipResourceLinks();
$states = adaStateNames();

$stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username, u.email AS owner_email FROM dogs d JOIN users u ON u.id=d.owner_user_id WHERE d.id=?');
$stmt->execute([$dogId]);
$dog = $stmt->fetch();

$handlerStmt = $pdo->prepare('SELECT username, email, display_name, home_street, home_apt, home_city, home_address, phone, public_email, home_state, home_zip, profile_photo_url, backup_contact_name, backup_contact_phone, public_notes FROM users WHERE id = ? LIMIT 1');
$handlerStmt->execute([$userId]);
$handlerProfile = $handlerStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$handlerProfileLegacyAddress = gpParseLegacyPostalAddress((string) ($handlerProfile['home_address'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? 'save_profile';

    if ($action === 'use_handler_defaults') {
        $defaultName = cleanText((string) ($handlerProfile['display_name'] ?? $handlerProfile['username'] ?? ''), 120);
        $defaultStreet = cleanText(gpFirstPublicValue((string) ($handlerProfile['home_street'] ?? ''), (string) ($handlerProfileLegacyAddress['street'] ?? '')), 120);
        $defaultApt = cleanText(gpFirstPublicValue((string) ($handlerProfile['home_apt'] ?? ''), (string) ($handlerProfileLegacyAddress['apt'] ?? '')), 80);
        $defaultCity = cleanText(gpFirstPublicValue((string) ($handlerProfile['home_city'] ?? ''), (string) ($handlerProfileLegacyAddress['city'] ?? '')), 120);
        $defaultState = strtoupper(trim(gpFirstPublicValue((string) ($handlerProfile['home_state'] ?? ''), (string) ($handlerProfileLegacyAddress['state'] ?? ''))));
        $defaultZip = cleanText(gpFirstPublicValue((string) ($handlerProfile['home_zip'] ?? ''), (string) ($handlerProfileLegacyAddress['zip'] ?? '')), 20);
        $defaultAddress = cleanText(gpComposePostalAddress([
            'home_street' => $defaultStreet,
            'home_apt' => $defaultApt,
            'home_city' => $defaultCity,
            'home_state' => $defaultState,
            'home_zip' => $defaultZip,
        ]), 255);
        $defaultPhone = cleanText((string) ($handlerProfile['phone'] ?? ''), 80);
        $defaultEmail = cleanText((string) ($handlerProfile['public_email'] ?? $handlerProfile['email'] ?? ''), 160);
        $defaultPhoto = cleanText((string) ($handlerProfile['profile_photo_url'] ?? ''), 255);
        $backupName = cleanText((string) ($handlerProfile['backup_contact_name'] ?? ''), 120);
        $backupPhone = cleanText((string) ($handlerProfile['backup_contact_phone'] ?? ''), 80);
        $publicNotes = cleanTextarea((string) ($handlerProfile['public_notes'] ?? ''), 1200);

        $stmt = $pdo->prepare('UPDATE dogs SET handler_name=?, handler_street=?, handler_apt=?, handler_city=?, handler_address=?, handler_phone=?, handler_email=?, handler_state=?, handler_zip=?, handler_photo_url=?, backup_contact_name=?, backup_contact_phone=?, public_notes=? WHERE id=?');
        $stmt->execute([
            $defaultName ?: null,
            $defaultStreet ?: null,
            $defaultApt ?: null,
            $defaultCity ?: null,
            $defaultAddress ?: null,
            $defaultPhone ?: null,
            $defaultEmail ?: null,
            $defaultState ?: null,
            $defaultZip ?: null,
            $defaultPhoto ?: null,
            $backupName ?: null,
            $backupPhone ?: null,
            $publicNotes ?: ($dog['public_notes'] ?? null),
            $dogId,
        ]);
        header('Location: dog_profile.php?dog_id=' . $dogId . '&status=handler_defaults');
        exit;
    }

    $name = cleanText($_POST['name'] ?? '', 80);
    $breed = cleanText($_POST['breed'] ?? '', 120);
    $chip = cleanText($_POST['chip_number'] ?? '', 80);
    $chipRegistry = cleanText($_POST['chip_registry'] ?? '', 160);
    $weight = ($_POST['weight_lbs'] ?? '') !== '' ? round((float) $_POST['weight_lbs'], 2) : null;
    $dob = cleanDateValue($_POST['date_of_birth'] ?? '');
    $birthApprox = !empty($_POST['birth_is_approximate']) ? 1 : 0;
    $approxAge = $dob !== '' ? gpApproxAgeYearsFromBirthDate($dob) : (($_POST['approx_age_years'] ?? '') !== '' ? round((float) $_POST['approx_age_years'], 1) : null);
    $notes = cleanTextarea($_POST['notes'] ?? '', 2000);
    $handlerName = cleanText($_POST['handler_name'] ?? '', 120);
    $handlerStreet = cleanText($_POST['handler_street'] ?? '', 120);
    $handlerApt = cleanText($_POST['handler_apt'] ?? '', 80);
    $handlerCity = cleanText($_POST['handler_city'] ?? '', 120);
    $handlerState = strtoupper(trim((string) ($_POST['handler_state'] ?? '')));
    $handlerZip = cleanText($_POST['handler_zip'] ?? '', 20);
    $handlerPhone = cleanText($_POST['handler_phone'] ?? '', 80);
    $handlerEmail = cleanText($_POST['handler_email'] ?? '', 160);
    $backupName = cleanText($_POST['backup_contact_name'] ?? '', 120);
    $backupPhone = cleanText($_POST['backup_contact_phone'] ?? '', 80);
    $foundInstructions = cleanTextarea($_POST['found_dog_instructions'] ?? '', 1200);
    $publicNotes = cleanTextarea($_POST['public_notes'] ?? '', 1200);
    $serviceTasks = cleanTextarea($_POST['service_tasks'] ?? '', 1200);
    $criticalAllergies = cleanTextarea($_POST['critical_allergies'] ?? '', 1200);

    $dogPhoto = gpSaveCroppedProfileImage('profile_photo_cropped', $dog['profile_photo_url'] ?? null, $errors);
    $handlerPhoto = gpSaveCroppedProfileImage('handler_photo_cropped', $dog['handler_photo_url'] ?? null, $errors);

    if ($name === '') {
        $errors[] = 'Dog name is required.';
    }
    $handlerAddressFields = [$handlerStreet, $handlerCity, $handlerState, $handlerZip];
    if (array_filter($handlerAddressFields, static fn($value) => trim((string) $value) !== '') && (trim($handlerStreet) === '' || trim($handlerCity) === '' || trim($handlerState) === '' || trim($handlerZip) === '')) {
        $errors[] = 'Handler address requires street, city, state, and ZIP when entered.';
    }
    if ($handlerEmail !== '' && !filter_var($handlerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Handler email must be valid.';
    }

    if (!$errors) {
        $handlerAddress = cleanText(gpComposePostalAddress([
            'handler_street' => $handlerStreet,
            'handler_apt' => $handlerApt,
            'handler_city' => $handlerCity,
            'handler_state' => $handlerState,
            'handler_zip' => $handlerZip,
        ]), 255);
        $stmt = $pdo->prepare('UPDATE dogs SET name=?, breed=?, chip_number=?, chip_registry=?, weight_lbs=?, date_of_birth=?, birth_is_approximate=?, approx_age_years=?, notes=?, profile_photo_url=?, handler_photo_url=?, handler_name=?, handler_street=?, handler_apt=?, handler_city=?, handler_address=?, handler_phone=?, handler_email=?, handler_state=?, handler_zip=?, backup_contact_name=?, backup_contact_phone=?, found_dog_instructions=?, public_notes=?, service_tasks=?, critical_allergies=? WHERE id=?');
        $stmt->execute([
            $name, $breed ?: null, $chip ?: null, $chipRegistry ?: null, $weight, $dob, $birthApprox, $approxAge, $notes ?: null,
            $dogPhoto ?: null, $handlerPhoto ?: null, $handlerName ?: null, $handlerStreet ?: null, $handlerApt ?: null, $handlerCity ?: null, $handlerAddress ?: null, $handlerPhone ?: null, $handlerEmail ?: null, $handlerState ?: null, $handlerZip ?: null,
            $backupName ?: null, $backupPhone ?: null, $foundInstructions ?: null, $publicNotes ?: null, $serviceTasks ?: null, $criticalAllergies ?: null, $dogId
        ]);
        if ((int) getActiveDogId($pdo, $userId) !== $dogId) {
            setActiveDogId($pdo, $userId, $dogId);
        }
        header('Location: dog_profile.php?dog_id=' . $dogId . '&status=saved');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username, u.email AS owner_email FROM dogs d JOIN users u ON u.id=d.owner_user_id WHERE d.id=?');
$stmt->execute([$dogId]);
$dog = $stmt->fetch();
if (!$dog) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 820px;">
    <div class="alert alert-warning">Dog profile not found or it is no longer available to this account.</div>
    <a href="dogs.php" class="btn btn-outline-secondary">Dogs</a>
</div>
</body>
</html>
<?php
    exit;
}
$publicContact = gpDogPublicContactDefaults($pdo, $dog);
$supportBadge = gpSupportBadgeForUser($pdo, $publicContact['owner'] ?? []);
$csrf = generateCsrfToken();
$publicUrl = publicDogProfileUrl((int) $dog['id']);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($publicUrl);
$dogLegacyHandlerAddress = gpParseLegacyPostalAddress((string) ($dog['handler_address'] ?? ''));
$publicContactHandlerAddress = gpParseLegacyPostalAddress((string) ($publicContact['handler_address'] ?? ''));
$handlerNameValue = trim((string) ($dog['handler_name'] ?? '')) !== '' ? (string) $dog['handler_name'] : (string) ($publicContact['handler_name'] ?? '');
$handlerStreetValue = trim((string) ($dog['handler_street'] ?? '')) !== '' ? (string) $dog['handler_street'] : gpFirstPublicValue((string) ($dogLegacyHandlerAddress['street'] ?? ''), (string) ($publicContactHandlerAddress['street'] ?? ''));
$handlerAptValue = trim((string) ($dog['handler_apt'] ?? '')) !== '' ? (string) $dog['handler_apt'] : gpFirstPublicValue((string) ($dogLegacyHandlerAddress['apt'] ?? ''), (string) ($publicContactHandlerAddress['apt'] ?? ''));
$handlerCityValue = trim((string) ($dog['handler_city'] ?? '')) !== '' ? (string) $dog['handler_city'] : gpFirstPublicValue((string) ($dogLegacyHandlerAddress['city'] ?? ''), (string) ($publicContactHandlerAddress['city'] ?? ''));
$handlerStateValue = strtoupper(trim((string) ($dog['handler_state'] ?? ''))) !== '' ? strtoupper(trim((string) $dog['handler_state'])) : strtoupper(trim(gpFirstPublicValue((string) ($dogLegacyHandlerAddress['state'] ?? ''), (string) ($publicContactHandlerAddress['state'] ?? ''))));
$handlerZipValue = trim((string) ($dog['handler_zip'] ?? '')) !== '' ? (string) $dog['handler_zip'] : gpFirstPublicValue((string) ($dogLegacyHandlerAddress['zip'] ?? ''), (string) ($publicContactHandlerAddress['zip'] ?? ''));
$handlerAddressValue = gpComposePostalAddress([
    'handler_street' => $handlerStreetValue,
    'handler_apt' => $handlerAptValue,
    'handler_city' => $handlerCityValue,
    'handler_state' => $handlerStateValue,
    'handler_zip' => $handlerZipValue,
]);
$handlerPhoneValue = trim((string) ($dog['handler_phone'] ?? '')) !== '' ? (string) $dog['handler_phone'] : (string) ($publicContact['handler_phone'] ?? '');
$handlerEmailValue = trim((string) ($dog['handler_email'] ?? '')) !== '' ? (string) $dog['handler_email'] : (string) ($publicContact['handler_email'] ?? '');
$backupNameValue = trim((string) ($dog['backup_contact_name'] ?? '')) !== '' ? (string) $dog['backup_contact_name'] : (string) ($publicContact['backup_contact_name'] ?? '');
$backupPhoneValue = trim((string) ($dog['backup_contact_phone'] ?? '')) !== '' ? (string) $dog['backup_contact_phone'] : (string) ($publicContact['backup_contact_phone'] ?? '');
$publicNotesValue = trim((string) ($dog['public_notes'] ?? '')) !== '' ? (string) $dog['public_notes'] : (string) ($publicContact['public_notes'] ?? '');
$handlerAddressSourceLabel = gpDogProfileSourceLabel((string) ($publicContact['handler_address_source'] ?? 'missing'));
$handlerEmailSourceLabel = gpDogProfileSourceLabel((string) ($publicContact['handler_email_source'] ?? 'missing'));
$handlerPhoneSourceLabel = gpDogProfileSourceLabel((string) ($publicContact['handler_phone_source'] ?? 'missing'));
$adminNotifyEmail = trim((string) gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app')));
$telegramEnabled = in_array(strtolower(trim((string) gpEnv('FOUND_DOG_NOTIFY_TELEGRAM_ENABLED', gpEnv('BETA_NOTIFY_TELEGRAM_ENABLED', 'false')))), ['1', 'true', 'yes', 'on'], true);
$approxAgeValue = !empty($dog['date_of_birth'])
    ? gpApproxAgeYearsFromBirthDate((string) $dog['date_of_birth'])
    : (($dog['approx_age_years'] ?? '') !== '' ? round((float) $dog['approx_age_years'], 1) : null);
$approxAgeReadonly = !empty($dog['date_of_birth']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.breed-card{border:1px solid #dfe3e8;border-radius:12px;background:#f8fafc;padding:12px;}.breed-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;}.breed-search-results{border:1px solid #dfe3e8;border-radius:12px;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.12);max-height:280px;overflow-y:auto;margin-top:6px;position:relative;z-index:40;}.breed-search-option{display:block;width:100%;text-align:left;border:0;background:#fff;padding:11px 12px;border-bottom:1px solid #eef2f7;}.breed-search-option:last-child{border-bottom:0;}.breed-search-option:hover,.breed-search-option:focus{background:#f8fafc;outline:0;}.breed-search-name{display:block;font-weight:600;}.breed-search-meta{display:block;color:#6c757d;font-size:.82rem;margin-top:2px;}.breed-search-empty{padding:11px 12px;color:#6c757d;}.profile-photo-preview{width:86px;height:86px;border-radius:18px;object-fit:cover;background:#eef2f7;border:1px solid #dbe3ef;}.qr-card{text-align:center;}.qr-card img{max-width:260px;width:100%;height:auto;border:1px solid #e5e7eb;padding:.5rem;background:#fff;border-radius:14px;}.crop-canvas{width:100%;max-width:260px;border-radius:16px;border:1px solid #dbe3ef;touch-action:none;background:#111827;}.crop-help{font-size:.82rem;color:#6b7280;}.section-heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap}.privacy-badge{border-radius:999px;padding:.35rem .65rem;font-size:.75rem;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.privacy-private{background:#eef2ff;color:#3730a3}.privacy-public{background:#dcfce7;color:#166534}.public-warning{border:1px solid #bbf7d0;background:#f0fdf4;border-radius:14px;padding:.85rem;color:#166534}.handler-defaults{border:1px dashed #bfdbfe;background:#eff6ff;border-radius:14px;padding:.85rem;}.contact-route{border:1px solid #bfdbfe;background:#eff6ff;border-radius:14px;padding:.95rem}.route-line{display:flex;justify-content:space-between;gap:1rem;border-top:1px solid #dbeafe;padding:.55rem 0}.route-line:first-child{border-top:0}.source-pill{display:inline-block;border-radius:999px;background:#e0f2fe;color:#075985;font-size:.72rem;font-weight:850;padding:.18rem .5rem;margin-left:.25rem}.source-pill.missing{background:#fef3c7;color:#92400e}.inherited-note{font-size:.82rem;color:#075985;margin-top:.25rem}.route-value{text-align:right;word-break:break-word;white-space:pre-line}.support-badge-card{border:1px solid rgba(59,130,246,.16);background:#eff6ff;border-radius:18px;padding:1rem;box-shadow:0 6px 16px rgba(15,23,42,.05);margin-bottom:1rem}.support-badge-card img{width:108px;height:108px;object-fit:contain;flex:0 0 auto}
</style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-0">🪪 <?= e($dog['name']) ?></h2><small class="text-muted">Owner: <?= e($dog['owner_username']) ?></small></div><div class="d-flex gap-2"><a href="dogs.php" class="btn btn-outline-secondary btn-sm">Dogs</a><a href="index.php?set_dog=<?= (int) $dog['id'] ?>" class="btn btn-outline-primary btn-sm">Make Active</a></div></div>
    <?php if (($_GET['status'] ?? '') === 'handler_defaults'): ?><div class="alert alert-success">Handler profile defaults applied to this dog’s public QR profile.</div><?php elseif (!empty($_GET['status'])): ?><div class="alert alert-success">Dog profile saved.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($supportBadge): ?>
        <section class="support-badge-card">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <img src="<?= e($supportBadge['image']) ?>" alt="<?= e($supportBadge['label']) ?>">
                <div class="flex-grow-1">
                    <div class="text-uppercase small fw-bold text-primary mb-1">Support badge</div>
                    <h3 class="h5 mb-1"><?= e($supportBadge['label']) ?></h3>
                    <div class="small text-muted">
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

    <div class="card shadow-sm mb-3 qr-card"><div class="card-body"><h3 class="h5">Public QR Profile</h3><p class="text-muted small mb-3">This unique QR code opens a public, no-login contact page for this dog only.</p><img src="<?= e($qrUrl) ?>" alt="Public QR profile for <?= e($dog['name']) ?>"><div class="d-grid gap-2 mt-3"><a class="btn btn-outline-primary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Preview Public Profile</a><a class="btn btn-outline-success" href="found_dog_notification_test.php?dog_id=<?= (int) $dog['id'] ?>">Test Found-Dog Alert</a><a class="btn btn-outline-dark" href="qr_tracking.php?dog_id=<?= (int) $dog['id'] ?>">QR Tracking</a><button type="button" class="btn btn-outline-secondary" id="copyPublicUrl">Copy Public Link</button></div><div class="small text-muted mt-2" id="copyStatus"></div></div></div>

    <div class="card shadow-sm"><div class="card-body"><?php if (!$canEdit): ?><div class="alert alert-info">You have read-only collaboration access for this dog profile.</div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_profile">
            <div class="col-12 section-heading"><div><h3 class="h5 mb-0">Private Dog Details</h3><div class="text-muted small">App-only information for managing this dog.</div></div><span class="privacy-badge privacy-private">Private</span></div>
            <div class="col-md-8"><label class="form-label">Dog Name</label><input type="text" name="name" class="form-control" value="<?= e($dog['name']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-md-4"><label class="form-label">Weight (lbs)</label><input type="number" step="0.1" name="weight_lbs" class="form-control" value="<?= e((string) $dog['weight_lbs']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-12"><label class="form-label">Breed</label><input type="text" name="breed" class="form-control breed-input" value="<?= e($dog['breed']) ?>" autocomplete="off" placeholder="Type 2+ letters or enter a custom breed/mix" <?= $canEdit ? '' : 'disabled' ?>><div class="form-text">Type at least 2 letters to search. You can still type any custom breed/mix.</div><div class="breed-search-results d-none" role="listbox" aria-label="Breed search results"></div></div>
            <details class="col-12 card shadow-sm mb-0">
                <summary class="card-body section-heading" style="cursor:pointer; list-style:none;">
                    <div>
                        <h3 class="h6 mb-0">Breed reference</h3>
                        <div class="text-muted small">Pick a breed to preview notes.</div>
                    </div>
                </summary>
                <div class="card-body pt-0">
                    <div class="breed-card breed-card-live">
                        <div class="breed-label mb-1">Breed reference</div>
                        <div class="fw-semibold breed-title">Pick a breed to preview notes</div>
                        <div class="small text-muted breed-group">Breed group will show here.</div>
                        <div class="mt-2"><span class="breed-label">Temperament</span><div class="breed-temperament">Common temperament notes will appear here.</div></div>
                        <div class="mt-2"><span class="breed-label">Traits</span><div class="breed-traits">Trainability, size, energy, and other typical traits.</div></div>
                        <div class="mt-2"><span class="breed-label">Notable notes</span><div class="breed-notes">Use these as a starting point, then rely on the individual dog in front of you.</div></div>
                    </div>
                </div>
            </details>
            <div class="col-md-6"><label class="form-label">Microchip #</label><input type="text" name="chip_number" class="form-control chip-input" value="<?= e($dog['chip_number']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-md-6"><label class="form-label">Chip Registry <span class="badge bg-success-subtle text-success">Public QR</span></label><input type="text" name="chip_registry" class="form-control" placeholder="AKC Reunite, HomeAgain, 24Petwatch..." value="<?= e($dog['chip_registry'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <details class="col-12 card shadow-sm mb-0">
                <summary class="card-body section-heading" style="cursor:pointer; list-style:none;">
                    <div>
                        <h3 class="h6 mb-0">Microchip quick links</h3>
                        <div class="text-muted small">Register or verify this chip with major registries and lookup tools.</div>
                    </div>
                </summary>
                <div class="card-body pt-0">
                    <div class="breed-card chip-links-card">
                        <div class="breed-label mb-1">Microchip quick links</div>
                        <div class="small text-muted chip-links-help mb-2">Register or verify this chip with major registries and lookup tools.</div>
                        <div class="d-flex flex-column gap-2 chip-links-list"><?php foreach ($chipLinks as $link): ?><a class="btn btn-outline-secondary btn-sm text-start chip-link" data-base-url="<?= e($link['url']) ?>" href="<?= e($link['url']) ?>" target="_blank" rel="noopener"><strong><?= e($link['label']) ?></strong><br><span class="small text-muted"><?= e($link['note']) ?></span></a><?php endforeach; ?></div>
                    </div>
                </div>
            </details>
            <div class="col-md-6"><label class="form-label" for="dogBirthday">Birthday</label><input type="date" name="date_of_birth" id="dogBirthday" class="form-control" value="<?= e((string) $dog['date_of_birth']) ?>" autocomplete="bday" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-md-6"><label class="form-label" for="dogApproxAge">Approx Age (years)</label><input type="number" step="0.1" name="approx_age_years" id="dogApproxAge" class="form-control" value="<?= e($approxAgeValue !== null ? (string) $approxAgeValue : '') ?>" <?= $canEdit ? '' : 'disabled' ?><?= $approxAgeReadonly && $canEdit ? ' readonly aria-readonly="true"' : '' ?> aria-describedby="dogAgeHelp"></div><div class="col-12"><div class="form-text" id="dogAgeHelp">If a birthday is set, GuidePaw fills the approximate age automatically. Leave the birthday blank to enter age manually.</div></div><div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="birth_is_approximate" id="birth_is_approximate" <?= !empty($dog['birth_is_approximate']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="birth_is_approximate">Birthday is approximate</label></div><div class="col-12"><label class="form-label">Private Notes</label><textarea name="notes" class="form-control" rows="3" <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['notes']) ?></textarea><div class="form-text">Private app notes. These do not show on the public QR profile.</div></div>
            <details class="col-12 card shadow-sm mb-0" id="public-qr-details">
                <summary class="card-body section-heading" style="cursor:pointer; list-style:none;">
                    <div>
                        <h3 class="h5 mb-0">Public QR Profile Details</h3>
                        <div class="text-muted small">Anything here may be visible to anyone who scans the QR code.</div>
                    </div>
                    <span class="privacy-badge privacy-public">Public</span>
                </summary>
                <div class="card-body pt-0">
                    <div class="public-warning mb-3">Use this section for return/contact information only. Avoid diagnosis details, private training notes, or full medical records. The handler address is stored as a private default for dog profiles and is not shown on the public QR page.</div>
                    <details class="col-12 card shadow-sm mb-3">
                        <summary class="card-body section-heading" style="cursor:pointer; list-style:none;">
                            <div>
                                <h4 class="h6 mb-0">Location reports and defaults</h4>
                                <div class="text-muted small">Handler defaults and contact routing.</div>
                            </div>
                        </summary>
                        <div class="card-body pt-0">
                            <div class="col-12 contact-route mb-3">
                                <h4 class="h6 mb-2">Location reports go to</h4>
                                <div class="small text-muted mb-2">GuidePaw uses dog-specific fields first, then Handler Profile defaults, then owner/admin fallback. The handler home state is used as the ADA card fallback when GPS is not available.</div>
                                <div class="route-line"><span>Handler address <span class="source-pill <?= ($publicContact['handler_address_source'] ?? 'missing') === 'missing' ? 'missing' : '' ?>"><?= e($handlerAddressSourceLabel) ?></span></span><span class="route-value"><?= $handlerAddressValue !== '' ? nl2br(e($handlerAddressValue)) : 'Missing' ?></span></div>
                                <div class="route-line"><span>Handler email <span class="source-pill <?= ($publicContact['handler_email_source'] ?? 'missing') === 'missing' ? 'missing' : '' ?>"><?= e($handlerEmailSourceLabel) ?></span></span><span class="route-value"><?= $handlerEmailValue !== '' ? e($handlerEmailValue) : 'Missing' ?></span></div>
                                <div class="route-line"><span>Handler phone <span class="source-pill <?= ($publicContact['handler_phone_source'] ?? 'missing') === 'missing' ? 'missing' : '' ?>"><?= e($handlerPhoneSourceLabel) ?></span></span><span class="route-value"><?= $handlerPhoneValue !== '' ? e($handlerPhoneValue) : 'Missing' ?></span></div>
                                <div class="route-line"><span>Handler home state <span class="source-pill <?= ($publicContact['home_state_source'] ?? 'missing') === 'missing' ? 'missing' : '' ?>"><?= e(($publicContact['home_state_source'] ?? 'missing') === 'missing' ? 'Missing' : ucwords(str_replace('_', ' ', (string) ($publicContact['home_state_source'] ?? '')))) ?></span></span><span class="route-value"><?= !empty($publicContact['home_state']) ? e($publicContact['home_state']) : 'Missing' ?></span></div>
                                <div class="route-line"><span>Admin fallback</span><span class="route-value"><?= $adminNotifyEmail !== '' ? e($adminNotifyEmail) : 'Missing' ?></span></div>
                                <div class="route-line"><span>Telegram fallback</span><span class="route-value"><?= $telegramEnabled ? 'Enabled if token/chat ID are set' : 'Disabled' ?></span></div>
                                <div class="d-grid gap-2 mt-3"><a class="btn btn-outline-success" href="found_dog_notification_test.php?dog_id=<?= (int) $dog['id'] ?>">Send Test Found-Dog Alert</a><a class="btn btn-outline-secondary" href="handler_profile.php">Update Handler Profile Defaults</a></div>
                            </div>
                            <?php if ($canEdit): ?><div class="col-12 handler-defaults mb-0"><div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center"><div><strong>Use your Handler Profile defaults</strong><div class="small text-muted">Copies your handler address parts, name, public phone/email, handler photo, backup contact, and public notes to this dog.</div></div><button type="submit" name="action" value="use_handler_defaults" class="btn btn-outline-primary">Use Handler Profile info</button></div></div><?php endif; ?>
                        </div>
                    </details>
                    <div class="row g-3">
                        <div class="col-md-6" data-crop-wrap><label class="form-label">Dog Profile Picture</label><div class="d-flex gap-3 align-items-center mb-2"><?php if (!empty($dog['profile_photo_url'])): ?><img id="dogPhotoPreview" src="<?= e($dog['profile_photo_url']) ?>" class="profile-photo-preview" alt="Dog photo"><?php else: ?><img id="dogPhotoPreview" class="profile-photo-preview" alt="Dog photo preview"><?php endif; ?><input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-crop-input data-crop-target="#profilePhotoCropped" data-crop-preview="#dogPhotoPreview" <?= $canEdit ? '' : 'disabled' ?>></div><input type="hidden" name="profile_photo_cropped" id="profilePhotoCropped"><canvas data-crop-canvas class="crop-canvas d-none" width="512" height="512"></canvas><div data-crop-controls class="d-none mt-2"><label class="form-label small mb-1">Zoom / drag to crop</label><input type="range" data-crop-zoom min="1" max="3" step="0.01" value="1" class="form-range"><button type="button" data-crop-clear class="btn btn-outline-secondary btn-sm">Clear crop</button></div><div class="crop-help">Square crop used on the public QR profile.</div></div>
                        <div class="col-md-6" data-crop-wrap><label class="form-label">Handler Picture</label><div class="d-flex gap-3 align-items-center mb-2"><?php if (!empty($dog['handler_photo_url'])): ?><img id="handlerPhotoPreview" src="<?= e($dog['handler_photo_url']) ?>" class="profile-photo-preview" alt="Handler photo"><?php elseif (!empty($publicContact['handler_photo_url'])): ?><img id="handlerPhotoPreview" src="<?= e($publicContact['handler_photo_url']) ?>" class="profile-photo-preview" alt="Handler photo preview"><?php else: ?><img id="handlerPhotoPreview" class="profile-photo-preview" alt="Handler photo preview"><?php endif; ?><input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-crop-input data-crop-target="#handlerPhotoCropped" data-crop-preview="#handlerPhotoPreview" <?= $canEdit ? '' : 'disabled' ?>></div><input type="hidden" name="handler_photo_cropped" id="handlerPhotoCropped"><canvas data-crop-canvas class="crop-canvas d-none" width="512" height="512"></canvas><div data-crop-controls class="d-none mt-2"><label class="form-label small mb-1">Zoom / drag to crop</label><input type="range" data-crop-zoom min="1" max="3" step="0.01" value="1" class="form-range"><button type="button" data-crop-clear class="btn btn-outline-secondary btn-sm">Clear crop</button></div><div class="crop-help">Square crop used on the public QR profile.<?php if (empty($dog['handler_photo_url']) && !empty($publicContact['handler_photo_url'])): ?> Using Handler Profile default.<?php endif; ?></div></div>
                        <div class="col-md-4"><label class="form-label">Handler Name</label><input type="text" name="handler_name" class="form-control" value="<?= e($handlerNameValue) ?>" <?= $canEdit ? '' : 'disabled' ?>><?php if (empty($dog['handler_name']) && $handlerNameValue !== ''): ?><div class="inherited-note">Using fallback/default value until saved here.</div><?php endif; ?></div>
                        <div class="col-md-6"><label class="form-label">Street</label><input type="text" name="handler_street" class="form-control" value="<?= e($handlerStreetValue) ?>" placeholder="Street address" <?= $canEdit ? '' : 'disabled' ?>><?php if (empty($dog['handler_street']) && $handlerStreetValue !== ''): ?><div class="inherited-note">Using <?= e($handlerAddressSourceLabel) ?> until saved here.</div><?php endif; ?></div>
                        <div class="col-md-3"><label class="form-label">Apt / Suite <span class="text-muted small">(optional)</span></label><input type="text" name="handler_apt" class="form-control" value="<?= e($handlerAptValue) ?>" placeholder="Apt, suite, unit, or #." <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-md-4"><label class="form-label">City</label><input type="text" name="handler_city" class="form-control" value="<?= e($handlerCityValue) ?>" placeholder="City" <?= $canEdit ? '' : 'disabled' ?>><?php if (empty($dog['handler_city']) && $handlerCityValue !== ''): ?><div class="inherited-note">Using <?= e($handlerAddressSourceLabel) ?> until saved here.</div><?php endif; ?></div>
                        <div class="col-md-4"><label class="form-label">State</label><select name="handler_state" class="form-select" <?= $canEdit ? '' : 'disabled' ?>><option value="">Select state</option><?php foreach ($states as $code => $name): ?><option value="<?= e($code) ?>" <?= strtoupper(trim((string) ($handlerStateValue ?? ''))) === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><?php if (empty($dog['handler_state']) && $handlerStateValue !== ''): ?><div class="inherited-note">Using <?= e($handlerAddressSourceLabel) ?> until saved here.</div><?php endif; ?></div>
                        <div class="col-md-4"><label class="form-label">ZIP</label><input type="text" name="handler_zip" class="form-control" value="<?= e($handlerZipValue) ?>" placeholder="ZIP" <?= $canEdit ? '' : 'disabled' ?>><?php if (empty($dog['handler_zip']) && $handlerZipValue !== ''): ?><div class="inherited-note">Using <?= e($handlerAddressSourceLabel) ?> until saved here.</div><?php endif; ?></div>
                        <div class="col-md-4"><label class="form-label">Handler Phone</label><input type="text" name="handler_phone" class="form-control" value="<?= e($handlerPhoneValue) ?>" <?= $canEdit ? '' : 'disabled' ?>><?php if (empty($dog['handler_phone']) && $handlerPhoneValue !== ''): ?><div class="inherited-note">Using <?= e($handlerPhoneSourceLabel) ?> until saved here.</div><?php endif; ?></div><div class="col-md-4"><label class="form-label">Handler Email</label><input type="email" name="handler_email" class="form-control" value="<?= e($handlerEmailValue) ?>" <?= $canEdit ? '' : 'disabled' ?>><?php if (empty($dog['handler_email']) && $handlerEmailValue !== ''): ?><div class="inherited-note">Using <?= e($handlerEmailSourceLabel) ?> until saved here.</div><?php endif; ?></div>
                        <div class="col-md-6"><label class="form-label">Backup Contact Name</label><input type="text" name="backup_contact_name" class="form-control" value="<?= e($backupNameValue) ?>" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-md-6"><label class="form-label">Backup Contact Phone</label><input type="text" name="backup_contact_phone" class="form-control" value="<?= e($backupPhoneValue) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">If Found / Emergency Instructions</label><textarea name="found_dog_instructions" class="form-control" rows="3" placeholder="Example: Please call handler first. If no answer, call backup contact or primary vet." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['found_dog_instructions'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Public Service Task Notes</label><textarea name="service_tasks" class="form-control" rows="3" placeholder="Keep this general. Do not disclose diagnosis details." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['service_tasks'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Critical Medical / Allergy Note</label><textarea name="critical_allergies" class="form-control" rows="3" placeholder="Only include urgent public safety information." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['critical_allergies'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Public Handler Notes</label><textarea name="public_notes" class="form-control" rows="3" placeholder="Optional public note for someone who scans the QR code." <?= $canEdit ? '' : 'disabled' ?>><?= e($publicNotesValue) ?></textarea><?php if (empty($dog['public_notes']) && $publicNotesValue !== ''): ?><div class="inherited-note">Using Handler Profile public note until saved here.</div><?php endif; ?></div>
                    </div>
                </div>
            </details>
            <?php if ($canEdit): ?><div class="col-12"><button class="btn btn-primary w-100">Save Dog Profile</button></div><?php endif; ?>
        </form>
    </div></div>
</div>
<script>
const breedCatalog = <?= json_encode($breedCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>; const publicUrl = <?= json_encode($publicUrl) ?>;
(function(){const b=document.getElementById('copyPublicUrl'),s=document.getElementById('copyStatus');if(b)b.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(publicUrl);if(s)s.textContent='Public link copied.';}catch(e){if(s)s.textContent=publicUrl;}});})();
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));} const breedNames=Object.keys(breedCatalog).sort((a,b)=>a.localeCompare(b)); function norm(v){return (v||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,' ').trim();} function rank(name,q){const n=norm(name);if(n===q)return 0;if(n.startsWith(q))return 1;if(n.split(' ').some(w=>w.startsWith(q)))return 2;if(n.includes(q))return 3;return 99;} function setupBreedSearch(){const input=document.querySelector('.breed-input'),results=document.querySelector('.breed-search-results');if(!input||!results)return;function hide(){results.classList.add('d-none');results.innerHTML='';}function choose(name){input.value=name;input.dispatchEvent(new Event('input',{bubbles:true}));hide();input.focus();}function render(){const q=norm(input.value.trim());if(q.length<2){hide();return;}const matches=breedNames.map(name=>({name,rank:rank(name,q)})).filter(i=>i.rank<99).sort((a,b)=>a.rank-b.rank||a.name.localeCompare(b.name)).slice(0,12);results.innerHTML='';if(!matches.length){results.innerHTML='<div class="breed-search-empty">No matching breed found. You can still save the breed exactly as typed.</div>';results.classList.remove('d-none');return;}matches.forEach(item=>{const info=breedCatalog[item.name]||{};const btn=document.createElement('button');btn.type='button';btn.className='breed-search-option';btn.innerHTML='<span class="breed-search-name">'+esc(item.name)+'</span><span class="breed-search-meta">'+esc(info.group||'Breed reference')+'</span>';btn.addEventListener('mousedown',e=>e.preventDefault());btn.addEventListener('click',()=>choose(item.name));results.appendChild(btn);});results.classList.remove('d-none');}input.addEventListener('input',render);input.addEventListener('focus',render);document.addEventListener('click',e=>{if(!results.contains(e.target)&&e.target!==input)hide();});}
function wireChipLinks(){const input=document.querySelector('.chip-input'),card=document.querySelector('.chip-links-card');if(!input||!card)return;const links=card.querySelectorAll('.chip-link'),help=card.querySelector('.chip-links-help');function render(){const chip=input.value.trim().replace(/\s+/g,'');if(chip){help.textContent='Quick jump to register or verify chip '+chip+'.';links.forEach(link=>{const base=link.getAttribute('data-base-url');link.href=base+(base.includes('?')?'&':'?')+'chip='+encodeURIComponent(chip);});}else{help.textContent='Enter a chip number to show quick registration and lookup links.';links.forEach(link=>link.href=link.getAttribute('data-base-url'));}}input.addEventListener('input',render);render();}
function setupBreedPreview(){const input=document.querySelector('.breed-input'),card=document.querySelector('.breed-card-live');if(!input||!card)return;const title=card.querySelector('.breed-title'),group=card.querySelector('.breed-group'),temp=card.querySelector('.breed-temperament'),traits=card.querySelector('.breed-traits'),notes=card.querySelector('.breed-notes');function render(){const v=input.value.trim(),info=breedCatalog[v];if(info){title.textContent=v;group.textContent='Group: '+(info.group||'Not listed');temp.textContent=info.temperament||'—';traits.textContent=info.traits||'—';notes.textContent=info.notes||'—';}else if(v){title.textContent=v;group.textContent='Custom breed entry';temp.textContent='No built-in reference for this exact name yet.';traits.textContent='You can still save this breed exactly as typed.';notes.textContent='Use private notes to capture individual observations.';}else{title.textContent='Pick a breed to preview notes';group.textContent='Breed group will show here.';temp.textContent='Common temperament notes will appear here.';traits.textContent='Trainability, size, energy, and other typical traits.';notes.textContent='Use these as a starting point, then rely on the individual dog in front of you.';}}input.addEventListener('input',render);render();}
setupBreedSearch();wireChipLinks();setupBreedPreview();
(function () {
    var birthday = document.getElementById('dogBirthday');
    var approxAge = document.getElementById('dogApproxAge');
    if (!birthday || !approxAge) return;
    function calcAge(value) {
        if (!value) return '';
        var birth = new Date(value + 'T00:00:00');
        if (isNaN(birth.getTime())) return '';
        var now = new Date();
        if (birth > now) return '';
        var years = (now - birth) / (365.2425 * 24 * 60 * 60 * 1000);
        return Math.max(0, Math.round(years * 10) / 10).toFixed(1);
    }
    function syncAge() {
        if (birthday.value.trim()) {
            approxAge.value = calcAge(birthday.value);
            approxAge.dataset.autoFilled = '1';
            approxAge.readOnly = true;
            approxAge.classList.add('bg-light');
            approxAge.setAttribute('aria-readonly', 'true');
        } else {
            if (approxAge.dataset.autoFilled === '1') {
                approxAge.value = '';
            }
            approxAge.dataset.autoFilled = '0';
            approxAge.readOnly = false;
            approxAge.classList.remove('bg-light');
            approxAge.removeAttribute('aria-readonly');
        }
    }
    birthday.addEventListener('input', syncAge);
    birthday.addEventListener('change', syncAge);
    syncAge();
})();
</script>
<?= gpProfileCropperScript() ?>
<?php guidepawFormUx(); ?>
</body>
</html>
