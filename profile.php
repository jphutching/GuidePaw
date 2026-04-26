<?php
require_once 'includes/db_connect.php';
checkLogin();
$userId = (int) $_SESSION['user_id'];
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : getActiveDogId($pdo, $userId);
if (!$dogId || !hasDogAccess($pdo, $userId, $dogId)) die('Invalid Profile');
$stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username FROM dogs d JOIN users u ON u.id=d.owner_user_id WHERE d.id = ?");
$stmt->execute([$dogId]);
$dog = $stmt->fetch();
?>
<!DOCTYPE html>
<html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet"></head>
<body class="bg-light p-4 text-center">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?><div class="card shadow-lg p-4 mx-auto" style="max-width:400px; border-top: 10px solid #0d6efd;"><h1 class="mb-0"><?= e($dog['name']) ?></h1><p class="badge bg-info text-dark"><?= e($dog['breed']) ?></p><p class="small text-muted mb-0">Owner: <?= e($dog['owner_username']) ?></p><hr><div class="bg-white p-3 rounded border mb-3"><small class="text-muted d-block">MICROCHIP ID</small><strong style="font-size: 1.2rem;"><?= e($dog['chip_number'] ?: 'Not Listed') ?></strong></div><?php if (!empty($dog['date_of_birth']) || !empty($dog['approx_age_years'])): ?><div class="bg-white p-3 rounded border mb-3"><small class="text-muted d-block">AGE</small><strong><?= !empty($dog['date_of_birth']) ? e($dog['date_of_birth']) : e((string) $dog['approx_age_years']) . ' years approx' ?></strong></div><?php endif; ?><div class="d-grid gap-2"><a href="dog_profile.php?dog_id=<?= (int) $dog['id'] ?>" class="btn btn-outline-primary btn-sm">Edit Dog Profile</a><a href="dogs.php" class="btn btn-outline-secondary btn-sm">All Dogs</a></div></div></body></html>
