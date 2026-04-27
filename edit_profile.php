<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
checkLogin();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("UPDATE users SET breed = ?, chip_number = ?, weight_lbs = ? WHERE id = ?");
    $stmt->execute([$_POST['breed'], $_POST['chip_number'], $_POST['weight_lbs'], $uid]);
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$u = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet"></head>
<body class="container p-4 bg-light">
<?php guidepawBrandHeader(); ?>

<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
    <form method="POST" class="card p-4 shadow-sm mx-auto" style="max-width:400px;">
        <h4>Edit Dog Profile</h4>
        <div class="mb-3"><label>Breed</label><input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($u['breed']) ?>"></div>
        <div class="mb-3"><label>Microchip #</label><input type="text" name="chip_number" class="form-control" value="<?= htmlspecialchars($u['chip_number']) ?>"></div>
        <div class="mb-3"><label>Weight (lbs)</label><input type="number" step="0.1" name="weight_lbs" class="form-control" value="<?= $u['weight_lbs'] ?>"></div>
        <button class="btn btn-primary w-100">Update Stats</button>
        <a href="index.php" class="btn btn-link w-100 mt-2">Cancel</a>
    </form>
</body>
</html>
