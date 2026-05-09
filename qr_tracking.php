<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/beta_banner.php';
require_once 'includes/mobile_nav.php';
require_once 'includes/public_dog_profile_token.php';
require_once 'includes/public_contact_defaults.php';
require_once 'includes/qr_tracking.php';

checkLogin();
gpEnsureDogQrTrackingTable($pdo);

$userId = (int) $_SESSION['user_id'];
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : getActiveDogId($pdo, $userId);
if (!$dogId || !hasDogAccess($pdo, $userId, $dogId)) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<div class="container py-4" style="max-width: 820px;">
    <div class="alert alert-warning">QR tracking is not available for that dog.</div>
    <a href="dogs.php" class="btn btn-outline-secondary">Dogs</a>
</div>
</body>
</html>
<?php
    exit;
}

$stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username FROM dogs d JOIN users u ON u.id = d.owner_user_id WHERE d.id = ? LIMIT 1');
$stmt->execute([$dogId]);
$dog = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dog) {
    http_response_code(404);
    exit('Dog not found.');
}

$publicUrl = publicDogProfileUrl((int) $dog['id']);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($publicUrl);
$summary = gpDogQrTrackingSummary($pdo, (int) $dog['id']);
$publicContact = gpDogPublicContactDefaults($pdo, $dog);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>QR Tracking · <?= e(appName()) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f3f6fb; color:#1f2937; padding-bottom: 90px; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 18px; }
        .cardx { background:#fff; border:1px solid #dfe3ea; border-radius:18px; padding:18px; margin:14px 0; box-shadow:0 8px 24px rgba(15,23,42,.08); }
        .hero-qr { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .qr-img { width: 260px; max-width: 100%; height: auto; border: 1px solid #e5e7eb; padding: .5rem; background: #fff; border-radius: 16px; }
        .stat { border: 1px solid #dbeafe; background: #eff6ff; border-radius: 14px; padding: .85rem 1rem; }
        .stat strong { display:block; font-size: 1.5rem; line-height: 1.1; }
        .scan-row { border-top: 1px solid #e5e7eb; padding: .8rem 0; }
        .scan-row:first-child { border-top: 0; padding-top: 0; }
        .meta { color:#64748b; font-size:.9rem; word-break: break-word; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
        <div>
            <h1 class="h3 mb-0">QR Tracking</h1>
            <div class="text-muted small">Public QR opens, found-dog reports, and related scan activity.</div>
        </div>
        <a class="btn btn-outline-secondary" href="dog_profile.php?dog_id=<?= (int) $dog['id'] ?>">Back to Dog Profile</a>
    </div>

    <section class="cardx">
        <div class="hero-qr">
            <div>
                <div class="small text-muted">Tracking for</div>
                <h2 class="h4 mb-1"><?= e($dog['name']) ?></h2>
                <div class="text-muted"><?= e($dog['breed'] ?? 'Breed not listed') ?> · Owner: <?= e($dog['owner_username'] ?? 'unknown') ?></div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <a class="btn btn-primary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open Public Profile</a>
                    <a class="btn btn-outline-success" href="found_dog_notification_test.php?dog_id=<?= (int) $dog['id'] ?>">Test Found-Dog Alert</a>
                </div>
            </div>
            <div class="text-center">
                <img class="qr-img" src="<?= e($qrUrl) ?>" alt="QR code for <?= e($dog['name']) ?>">
                <div class="small text-muted mt-2">Public QR link</div>
            </div>
        </div>
    </section>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="stat">
                <span class="text-muted small">QR opens tracked</span>
                <strong><?= (int) $summary['total_views'] ?></strong>
                <div class="small text-muted">Valid public profile visits logged.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat">
                <span class="text-muted small">Last viewed</span>
                <strong><?= $summary['last_viewed_at'] ? e(date('M j, Y', strtotime((string) $summary['last_viewed_at']))) : 'None yet' ?></strong>
                <div class="small text-muted"><?= $summary['last_viewed_at'] ? e(date('g:i A', strtotime((string) $summary['last_viewed_at']))) : 'Scan activity has not started.' ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat">
                <span class="text-muted small">Public contact source</span>
                <strong><?= e((string) ($publicContact['handler_email_source'] ?? 'missing')) ?></strong>
                <div class="small text-muted"><?= e((string) ($publicContact['handler_phone_source'] ?? 'missing')) ?> phone source</div>
            </div>
        </div>
    </div>

    <section class="cardx">
        <h2 class="h5 mb-3">Recent QR Opens</h2>
        <?php if (!$summary['recent_views']): ?>
            <div class="text-muted">No QR opens have been recorded yet.</div>
        <?php else: ?>
            <?php foreach ($summary['recent_views'] as $view): ?>
                <div class="scan-row">
                    <div class="d-flex justify-content-between gap-2 flex-wrap">
                        <div><strong><?= e((string) $view['viewed_at']) ?></strong></div>
                        <div class="meta"><?= !empty($view['path']) ? e((string) $view['path']) : 'public profile' ?></div>
                    </div>
                    <div class="meta">
                        <?= !empty($view['user_agent']) ? e((string) $view['user_agent']) : 'Unknown browser' ?>
                        <?php if (!empty($view['referrer'])): ?> · Referrer: <?= e((string) $view['referrer']) ?><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
