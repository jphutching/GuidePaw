<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
checkLogin();

$uid = $_SESSION['user_id'];

// 1. Handle Stats Update (Weight, Breed, Chip)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_stats'])) {
    $breed = htmlspecialchars($_POST['breed']);
    $chip  = htmlspecialchars($_POST['chip_number']);
    $weight = !empty($_POST['weight_lbs']) ? (float)$_POST['weight_lbs'] : null;

    $stmt = $pdo->prepare("UPDATE users SET breed = ?, chip_number = ?, weight_lbs = ? WHERE id = ?");
    $stmt->execute([$breed, $chip, $weight, $uid]);
    $success_msg = "Stats updated successfully!";
}

// 2. Fetch Fresh Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

// 3. Calculate Mastery Progress (Goal: 100 Logs)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_logs WHERE user_id = ?");
$countStmt->execute([$uid]);
$logCount = $countStmt->fetchColumn();
$progressPercent = min(($logCount / 100) * 100, 100);

// 4. Generate QR Link
$baseUrl = appUrl() !== '' ? appUrl() : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$profile_url = rtrim($baseUrl, '/') . '/profile.php?dog_id=' . (int) getActiveDogId($pdo, $uid);
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($profile_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings & Mastery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">

<style>
.gp-brand-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(12px, 4vw, 28px);
    margin: clamp(12px, 3vw, 22px) auto;
    flex-wrap: wrap;
}
.gp-brand-logo {
    width: clamp(120px, 34vw, 175px);
    height: auto;
    border-radius: 16px;
    display: block;
}
.gp-brand-copy {
    text-align: center;
    color: #fff;
}
.gp-brand-tagline {
    font-family: 'Trebuchet MS', 'Arial Rounded MT Bold', system-ui, sans-serif;
    font-size: clamp(.86rem, 3.1vw, 1.08rem);
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #ffffff;
    text-shadow: 0 2px 8px rgba(0,0,0,.28);
}
</style>

</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>

<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">⚙️ Settings</h3>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg'] === '2fa_enabled'): ?><div class="alert alert-success alert-dismissible fade show" role="alert">Two-factor authentication is now enabled.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] === '2fa_disabled'): ?><div class="alert alert-warning alert-dismissible fade show" role="alert">Two-factor authentication was disabled.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Training Mastery</h5>
                <p class="small text-muted mb-2">Progress toward 100 verified sessions.</p>
                <div class="progress mb-2" style="height: 25px;">
                    <div class="progress-bar bg-success progress-bar-striped" role="progressbar" 
                         style="width: <?= $progressPercent ?>%;" 
                         aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100">
                         <?= floor($progressPercent) ?>%
                    </div>
                </div>
                <small class="fw-bold"><?= $logCount ?> / 100 Logs Completed</small>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Update Dog Profile</h5>
                <form method="POST">
                    <input type="hidden" name="update_stats" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Breed</label>
                        <input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($user['breed'] ?? '') ?>" placeholder="e.g. English Cocker Spaniel">
                    </div>
                    <div class="row">
                        <div class="col-7 mb-3">
                            <label class="form-label small fw-bold">Microchip ID</label>
                            <input type="text" name="chip_number" class="form-control" value="<?= htmlspecialchars($user['chip_number'] ?? '') ?>" placeholder="Searchable ID">
                        </div>
                        <div class="col-5 mb-3">
                            <label class="form-label small fw-bold">Weight (lbs)</label>
                            <input type="number" step="0.1" name="weight_lbs" class="form-control" value="<?= htmlspecialchars($user['weight_lbs'] ?? '') ?>" placeholder="0.0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Dog Stats</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body text-center">
                <h5 class="card-title mb-3">Service Dog Digital ID</h5>
                <img src="<?= $qr_api ?>" class="img-fluid border p-2 bg-white mb-3" alt="Profile QR Code">
                <p class="small text-muted mb-3">This QR code links to the public-facing profile for <?= htmlspecialchars($user['dog_name']) ?>.</p>
                
                <hr>
                
                <div class="d-grid gap-2">
                    <a href="setup_2fa.php" class="btn btn-outline-dark btn-sm">
                        <?= $user['is_2fa_enabled'] ? "Manage 2FA (Enabled)" : "Enable 2-Factor Auth" ?>
                    </a>
                    <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
                </div>
            </div>
        </div>

        <div class="text-center">
            <p class="text-muted small">Recovery Key: <span class="fw-bold"><?= $user['recovery_key'] ?></span></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
