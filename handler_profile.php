<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once 'includes/db_connect.php';
require_once 'includes/validation.php';
require_once 'includes/profile_image_tools.php';
require_once 'includes/app_config.php';
require_once 'includes/ada_state_laws.php';
require_once 'includes/support_badges.php';
require_once __DIR__ . '/includes/sms_notifications.php';
checkLogin();

function gpEnsureHandlerProfileColumns(PDO $pdo): void
{
    $columns = [
        'display_name' => 'TEXT',
        'home_street' => 'TEXT',
        'home_apt' => 'TEXT',
        'home_city' => 'TEXT',
        'home_address' => 'TEXT',
        'phone' => 'TEXT',
        'public_email' => 'TEXT',
        'home_state' => 'TEXT',
        'home_zip' => 'TEXT',
        'profile_photo_url' => 'TEXT',
        'backup_contact_name' => 'TEXT',
        'backup_contact_phone' => 'TEXT',
        'public_notes' => 'TEXT',
        'sms_phone' => 'TEXT',
        'sms_notifications_enabled' => 'BOOLEAN NOT NULL DEFAULT FALSE',
    ];
    foreach ($columns as $column => $type) {
        $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
    }
}

function gpSafeProfileReturnTo(string $returnTo): string
{
    $returnTo = trim($returnTo);
    if ($returnTo === '' || str_starts_with($returnTo, 'http://') || str_starts_with($returnTo, 'https://') || str_starts_with($returnTo, '//')) {
        return 'index.php';
    }
    if (str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
        return 'index.php';
    }
    return $returnTo;
}

function gpProfileMissingLabelsVisible(array $labels): array
{
    return array_values(array_filter($labels, static function ($label): bool {
        return !in_array((string) $label, ['Backup contact name', 'Backup contact phone'], true);
    }));
}

function gpOptionalBackupDisplay(?string $value): string
{
    $value = trim((string) $value);
    return $value === 'Not applicable' ? '' : $value;
}

function gpNormalizeOptionalUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
}

gpEnsureHandlerProfileColumns($pdo);
$userId = (int) $_SESSION['user_id'];
$errors = [];
$status = '';
$returnTo = gpSafeProfileReturnTo((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? 'index.php'));
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $displayName = cleanText($_POST['display_name'] ?? '', 120);
    $homeStreet = cleanText($_POST['home_street'] ?? '', 120);
    $homeApt = cleanText($_POST['home_apt'] ?? '', 120);
    $homeCity = cleanText($_POST['home_city'] ?? '', 120);
    $homeState = strtoupper(trim((string) ($_POST['home_state'] ?? '')));
    $homeZip = cleanText($_POST['home_zip'] ?? '', 20);
    $phone = cleanText($_POST['phone'] ?? '', 80);
    $publicEmail = cleanText($_POST['public_email'] ?? '', 160);
    $facebookUrl = gpNormalizeOptionalUrl((string) ($_POST['facebook_url'] ?? ''));
    $backupName = cleanText($_POST['backup_contact_name'] ?? '', 120);
    $backupPhone = cleanText($_POST['backup_contact_phone'] ?? '', 80);
    $publicNotes = cleanTextarea($_POST['public_notes'] ?? '', 1200);
    $smsEnabled = !empty($_POST['sms_notifications_enabled']) ? 1 : 0;
    $smsPhone = cleanText($_POST['sms_phone'] ?? '', 80);
    if ($smsPhone === '') {
        $smsPhone = $phone;
    }
    $smsPhoneNormalized = gpSmsNormalizePhone($smsPhone);
    $profilePhoto = gpSaveCroppedProfileImage('profile_photo_cropped', $user['profile_photo_url'] ?? null, $errors);

    if ($displayName === '') {
        $errors[] = 'Display name is required.';
    }
    if ($homeStreet === '') {
        $errors[] = 'Home street is required.';
    }
    if ($homeCity === '') {
        $errors[] = 'Home city is required.';
    }
    if ($homeState === '') {
        $errors[] = 'Home state is required.';
    }
    if ($homeZip === '') {
        $errors[] = 'Home ZIP is required.';
    }
    if ($phone === '') {
        $errors[] = 'Public phone is required.';
    }
    if ($publicEmail === '') {
        $errors[] = 'Public email is required.';
    }
    if ($publicEmail !== '' && !filter_var($publicEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Public email must be valid.';
    }
    if ($homeState !== '' && !array_key_exists($homeState, adaStateNames())) {
        $errors[] = 'Home state must be a valid US state code.';
    }
    if ($homeZip !== '' && !preg_match('/^\d{5}(?:-\d{4})?$/', $homeZip)) {
        $errors[] = 'Home ZIP must be valid.';
    }
    if ($smsEnabled && $smsPhoneNormalized === '') {
        $errors[] = 'SMS notifications require a valid mobile phone number.';
    }

    if (!$errors) {
        $homeAddress = gpComposePostalAddress([
            'home_street' => $homeStreet,
            'home_apt' => $homeApt,
            'home_city' => $homeCity,
            'home_state' => $homeState,
            'home_zip' => $homeZip,
        ]);
        $stmt = $pdo->prepare('UPDATE users SET display_name=?, home_street=?, home_apt=?, home_city=?, home_address=?, phone=?, public_email=?, facebook_url=?, home_state=?, home_zip=?, profile_photo_url=?, backup_contact_name=?, backup_contact_phone=?, public_notes=?, sms_phone=?, sms_notifications_enabled=? WHERE id=?');
        $stmt->execute([$displayName, $homeStreet, $homeApt !== '' ? $homeApt : null, $homeCity, $homeAddress, $phone, $publicEmail, $facebookUrl ?: null, $homeState, $homeZip, $profilePhoto ?: null, $backupName !== '' ? $backupName : 'Not applicable', $backupPhone !== '' ? $backupPhone : 'Not applicable', $publicNotes ?: null, $smsPhoneNormalized ?: null, $smsEnabled, $userId]);
        $_SESSION['username'] = $user['username'];
        unset($_SESSION['handler_profile_required_missing']);
        if (($_POST['completion_required'] ?? '') === '1') {
            header('Location: ' . $returnTo);
        } else {
            header('Location: handler_profile.php?status=saved');
        }
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
$csrf = generateCsrfToken();
$completionRequired = (($_GET['required'] ?? '') === '1') || !empty($_SESSION['handler_profile_required_missing']);
$stateNames = adaStateNames();
$supportBadge = gpSupportBadgeForUser($pdo, $user ?: []);
$legacyAddress = gpParseLegacyPostalAddress((string) ($user['home_address'] ?? ''));
$homeStreetValue = trim((string) ($user['home_street'] ?? '')) !== '' ? (string) $user['home_street'] : $legacyAddress['street'];
$homeAptValue = trim((string) ($user['home_apt'] ?? '')) !== '' ? (string) $user['home_apt'] : $legacyAddress['apt'];
$homeCityValue = trim((string) ($user['home_city'] ?? '')) !== '' ? (string) $user['home_city'] : $legacyAddress['city'];
$homeStateValue = strtoupper(trim((string) ($user['home_state'] ?? ''))) !== '' ? strtoupper(trim((string) $user['home_state'])) : strtoupper(trim((string) $legacyAddress['state']));
$homeZipValue = trim((string) ($user['home_zip'] ?? '')) !== '' ? (string) $user['home_zip'] : $legacyAddress['zip'];
$missingLabels = [];
if (function_exists('gpMissingRequiredHandlerProfileFields')) {
    $missingLabels = gpProfileMissingLabelsVisible(array_values(gpMissingRequiredHandlerProfileFields($user ?: [])));
}
if (!$missingLabels && !empty($_SESSION['handler_profile_required_missing'])) {
    $missingLabels = gpProfileMissingLabelsVisible(array_values((array) $_SESSION['handler_profile_required_missing']));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Handler Profile · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.profile-card{border:1px solid rgba(15,23,42,.08);border-radius:20px;box-shadow:0 8px 20px rgba(15,23,42,.07)}
.profile-photo-preview{width:96px;height:96px;border-radius:22px;object-fit:cover;background:#eef2f7;border:1px solid #dbe3ef;}
.crop-canvas{width:100%;max-width:280px;border-radius:16px;border:1px solid #dbe3ef;touch-action:none;background:#111827;}
.crop-help{font-size:.82rem;color:#6b7280;}
.required-note{border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:16px;padding:1rem;}
.required-note ul{margin-bottom:0;}
.req{color:#dc2626;font-weight:900;}
.opt{color:#64748b;font-size:.82rem;font-weight:700;}
.sms-box{border:1px solid #bfdbfe;background:#eff6ff;border-radius:16px;padding:1rem;}
.support-badge-card{border:1px solid rgba(59,130,246,.16);background:#eff6ff;border-radius:18px;padding:1rem;box-shadow:0 6px 16px rgba(15,23,42,.05);}
.support-badge-card img{width:108px;height:108px;object-fit:contain;flex:0 0 auto;}
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">👤 Handler Profile</h1>
            <div class="text-muted small">Default public handler/contact info you can reuse on dog QR profiles.</div>
        </div>
        <?php if (!$completionRequired): ?><a href="settings.php" class="btn btn-outline-secondary btn-sm">Settings</a><?php endif; ?>
    </div>

    <?php if ($completionRequired): ?>
        <div class="required-note mb-3">
            <strong>Finish your Handler Profile before continuing.</strong>
            <div class="small mt-1">GuidePaw uses this information for public QR profiles, found-dog alerts, and reusable handler defaults so you do not have to retype it for every dog.</div>
            <?php if ($missingLabels): ?>
                <div class="mt-2 fw-semibold">Missing:</div>
                <ul>
                    <?php foreach ($missingLabels as $label): ?><li><?= e($label) ?></li><?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="small mt-2">Backup contacts are optional. Save the profile to continue.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['status'])): ?><div class="alert alert-success">Handler profile saved.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($supportBadge): ?>
        <section class="support-badge-card mb-3">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <img src="<?= e($supportBadge['image']) ?>" alt="<?= e($supportBadge['label']) ?>">
                <div class="flex-grow-1">
                    <div class="text-uppercase small fw-bold text-primary mb-1">Support badge</div>
                    <h2 class="h4 mb-1"><?= e($supportBadge['label']) ?></h2>
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
                <span class="badge text-bg-primary"><?= e(strtoupper((string) ($supportBadge['tier'] ?? 'support'))) ?></span>
            </div>
        </section>
    <?php endif; ?>

    <section class="card profile-card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <input type="hidden" name="completion_required" value="<?= $completionRequired ? '1' : '0' ?>">
                <div class="col-12" data-crop-wrap>
                    <label class="form-label">Handler Profile Picture</label>
                    <div class="d-flex gap-3 align-items-center mb-2">
                        <?php if (!empty($user['profile_photo_url'])): ?>
                            <img id="handlerProfilePreview" src="<?= e($user['profile_photo_url']) ?>" class="profile-photo-preview" alt="Handler photo">
                        <?php else: ?>
                            <img id="handlerProfilePreview" class="profile-photo-preview" alt="Handler photo preview">
                        <?php endif; ?>
                        <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-crop-input data-crop-target="#handlerProfilePhotoCropped" data-crop-preview="#handlerProfilePreview">
                    </div>
                    <input type="hidden" name="profile_photo_cropped" id="handlerProfilePhotoCropped">
                    <canvas data-crop-canvas class="crop-canvas d-none" width="512" height="512"></canvas>
                    <div data-crop-controls class="d-none mt-2">
                        <label class="form-label small mb-1">Zoom / drag to crop</label>
                        <input type="range" data-crop-zoom min="1" max="3" step="0.01" value="1" class="form-range">
                        <button type="button" data-crop-clear class="btn btn-outline-secondary btn-sm">Clear crop</button>
                    </div>
                    <div class="crop-help">Square crop used wherever the handler photo appears publicly.</div>
                </div>

                <div class="col-md-6"><label class="form-label">Display Name <span class="req">*</span></label><input type="text" name="display_name" class="form-control" value="<?= e($user['display_name'] ?? ($user['username'] ?? '')) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Username</label><input type="text" class="form-control" value="<?= e($user['username'] ?? '') ?>" disabled><div class="form-text">Username is used for login and is not changed here.</div></div>
                <div class="col-12">
                    <label class="form-label">Home Address</label>
                    <div class="form-text mb-2">Enter the address as separate fields so GuidePaw can reuse it cleanly across QR profiles and contact defaults.</div>
                </div>
                <div class="col-md-6"><label class="form-label">Street <span class="req">*</span></label><input type="text" name="home_street" class="form-control" value="<?= e($homeStreetValue) ?>" placeholder="Street address" required></div>
                <div class="col-md-6"><label class="form-label">Apt / Suite <span class="opt">optional</span></label><input type="text" name="home_apt" class="form-control" value="<?= e($homeAptValue) ?>" placeholder="Apt, suite, unit, or #"></div>
                <div class="col-md-6"><label class="form-label">City <span class="req">*</span></label><input type="text" name="home_city" class="form-control" value="<?= e($homeCityValue) ?>" placeholder="City" required></div>
                <div class="col-md-3">
                    <label class="form-label">State <span class="req">*</span></label>
                    <select name="home_state" class="form-select" required>
                        <option value="">Choose a state</option>
                        <?php foreach ($stateNames as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $homeStateValue === $code ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Used as the ADA card fallback and for lost-dog contact context when GPS is unavailable.</div>
                </div>
                <div class="col-md-3"><label class="form-label">ZIP <span class="req">*</span></label><input type="text" name="home_zip" class="form-control" value="<?= e($homeZipValue) ?>" placeholder="ZIP" required></div>
                <div class="col-md-6"><label class="form-label">Public Phone <span class="req">*</span></label><input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" required></div>
                <div class="col-md-6"><label class="form-label">Public Email <span class="req">*</span></label><input type="email" name="public_email" class="form-control" value="<?= e($user['public_email'] ?? ($user['email'] ?? '')) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Facebook Link <span class="opt">optional</span></label><input type="url" name="facebook_url" class="form-control" value="<?= e($user['facebook_url'] ?? '') ?>" placeholder="https://www.facebook.com/your.profile"><div class="form-text">Saved on your handler profile for quick sharing.</div></div>
                <div class="col-md-6"><label class="form-label">Backup Contact Name <span class="opt">optional</span></label><input type="text" name="backup_contact_name" class="form-control" value="<?= e(gpOptionalBackupDisplay($user['backup_contact_name'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Backup Contact Phone <span class="opt">optional</span></label><input type="text" name="backup_contact_phone" class="form-control" value="<?= e(gpOptionalBackupDisplay($user['backup_contact_phone'] ?? '')) ?>"></div>
                <div class="col-12 sms-box">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="sms_notifications_enabled" name="sms_notifications_enabled" value="1" <?= !empty($user['sms_notifications_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="sms_notifications_enabled">Send important GuidePaw alerts by SMS text message</label>
                    </div>
                    <label class="form-label">SMS Mobile Phone <span class="opt">optional unless SMS is enabled</span></label>
                    <input type="text" name="sms_phone" class="form-control" value="<?= e($user['sms_phone'] ?? ($user['phone'] ?? '')) ?>" placeholder="Example: +15551234567">
                    <div class="form-text">SMS is opt-in and may be used for found-dog alerts, transfer requests, and urgent access changes. Message/data rates may apply. Telegram remains admin-only.</div>
                </div>
                <div class="col-12"><label class="form-label">Public Handler Notes</label><textarea name="public_notes" class="form-control" rows="4" placeholder="Optional public note, such as preferred contact method or return instructions."><?= e($user['public_notes'] ?? '') ?></textarea></div>
                <div class="col-12"><button class="btn btn-primary w-100"><?= $completionRequired ? 'Save and Continue' : 'Save Handler Profile' ?></button></div>
            </form>
        </div>
    </section>
</main>
<?= gpProfileCropperScript() ?>
<?php guidepawFormUx(); ?>
<script src="app.js"></script>
</body>
</html>
