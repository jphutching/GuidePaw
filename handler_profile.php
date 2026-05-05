<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once 'includes/db_connect.php';
require_once 'includes/validation.php';
require_once 'includes/profile_image_tools.php';
require_once 'includes/app_config.php';
checkLogin();

function gpEnsureHandlerProfileColumns(PDO $pdo): void
{
    $columns = [
        'display_name' => 'TEXT',
        'phone' => 'TEXT',
        'public_email' => 'TEXT',
        'profile_photo_url' => 'TEXT',
        'backup_contact_name' => 'TEXT',
        'backup_contact_phone' => 'TEXT',
        'public_notes' => 'TEXT',
    ];
    foreach ($columns as $column => $type) {
        $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
    }
}

gpEnsureHandlerProfileColumns($pdo);
$userId = (int) $_SESSION['user_id'];
$errors = [];
$status = '';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $displayName = cleanText($_POST['display_name'] ?? '', 120);
    $phone = cleanText($_POST['phone'] ?? '', 80);
    $publicEmail = cleanText($_POST['public_email'] ?? '', 160);
    $backupName = cleanText($_POST['backup_contact_name'] ?? '', 120);
    $backupPhone = cleanText($_POST['backup_contact_phone'] ?? '', 80);
    $publicNotes = cleanTextarea($_POST['public_notes'] ?? '', 1200);
    $profilePhoto = gpSaveCroppedProfileImage('profile_photo_cropped', $user['profile_photo_url'] ?? null, $errors);

    if ($displayName === '') {
        $errors[] = 'Display name is required.';
    }
    if ($publicEmail !== '' && !filter_var($publicEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Public email must be valid.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET display_name=?, phone=?, public_email=?, profile_photo_url=?, backup_contact_name=?, backup_contact_phone=?, public_notes=? WHERE id=?');
        $stmt->execute([$displayName, $phone ?: null, $publicEmail ?: null, $profilePhoto ?: null, $backupName ?: null, $backupPhone ?: null, $publicNotes ?: null, $userId]);
        $_SESSION['username'] = $user['username'];
        header('Location: handler_profile.php?status=saved');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
$csrf = generateCsrfToken();
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
        <a href="settings.php" class="btn btn-outline-secondary btn-sm">Settings</a>
    </div>

    <?php if (!empty($_GET['status'])): ?><div class="alert alert-success">Handler profile saved.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <section class="card profile-card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
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

                <div class="col-md-6"><label class="form-label">Display Name</label><input type="text" name="display_name" class="form-control" value="<?= e($user['display_name'] ?? ($user['username'] ?? '')) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Username</label><input type="text" class="form-control" value="<?= e($user['username'] ?? '') ?>" disabled><div class="form-text">Username is used for login and is not changed here.</div></div>
                <div class="col-md-6"><label class="form-label">Public Phone</label><input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Public Email</label><input type="email" name="public_email" class="form-control" value="<?= e($user['public_email'] ?? ($user['email'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Backup Contact Name</label><input type="text" name="backup_contact_name" class="form-control" value="<?= e($user['backup_contact_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Backup Contact Phone</label><input type="text" name="backup_contact_phone" class="form-control" value="<?= e($user['backup_contact_phone'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label">Public Handler Notes</label><textarea name="public_notes" class="form-control" rows="4" placeholder="Optional public note, such as preferred contact method or return instructions."><?= e($user['public_notes'] ?? '') ?></textarea></div>
                <div class="col-12"><button class="btn btn-primary w-100">Save Handler Profile</button></div>
            </form>
        </div>
    </section>
</main>
<?= gpProfileCropperScript() ?>
<?php guidepawFormUx(); ?>
<script src="app.js"></script>
</body>
</html>
