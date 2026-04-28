<?php
require_once __DIR__ . '/includes/form_ux.php'; 
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php'; 
checkLogin(); // Security Guard

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Stress-Tested Query: Checks both ID and Ownership
$stmt = $pdo->prepare("SELECT * FROM daily_logs WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$log = $stmt->fetch();

if (!$log) { 
    die("Error: Log entry not found or you do not have permission to edit it."); 
}

$skills = json_decode($log['skills_practiced'], true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Training Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="container mt-4 bg-light">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
    <div class="card shadow p-4 mx-auto" style="max-width: 600px;">
        <h2 class="mb-4">✏️ Edit Session</h2>
        
        <form action="update_log.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $log['id'] ?>">

            <div class="mb-3">
                <label class="form-label fw-bold">Location Name</label>
                <input type="text" name="location_name" class="form-control" value="<?= htmlspecialchars($log['location_name']) ?>" required>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold d-block">Skills Practiced</label>
                <div class="row g-2">
                    <?php 
                    $options = ['Sit/Stay', 'Heel', 'Leave It', 'Under Tuck', 'DPT Task', 'PA Focus'];
                    foreach ($options as $opt): 
                        $checked = in_array($opt, $skills) ? 'checked' : '';
                    ?>
                        <div class="col-6">
                            <input type="checkbox" name="skills[]" value="<?= $opt ?>" class="btn-check" id="btn_<?= $opt ?>" <?= $checked ?>>
                            <label class="btn btn-outline-primary w-100" for="btn_<?= $opt ?>"><?= $opt ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Handler Notes</label>
                <textarea name="handler_notes" class="form-control" rows="4"><?= htmlspecialchars($log['handler_notes']) ?></textarea>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-warning btn-lg fw-bold">UPDATE LOG ENTRY</button>
                <a href="view_logs.php" class="btn btn-link">Cancel and Return</a>
            </div>
        </form>
    </div>
<?php guidepawFormUx(); ?>
</body>
</html>
