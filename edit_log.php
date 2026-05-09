<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/validation.php';
checkLogin(); // Security Guard

$id = (int)($_GET['id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT dl.*, d.name AS dog_name FROM daily_logs dl JOIN dogs d ON d.id = dl.dog_id WHERE dl.id = ?");
$stmt->execute([$id]);
$log = $stmt->fetch();

if (!$log || !userCanEditDog($pdo, $user_id, (int) $log['dog_id'])) {
    http_response_code(404);
    die("Error: Log entry not found or you do not have permission to edit it.");
}

$skills = json_decode($log['skills_practiced'], true) ?: [];
$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$allowedSkills = ['Sit/Stay', 'Heel', 'Leave It', 'Under Tuck', 'DPT Task', 'PA Focus'];
$csrf = generateCsrfToken();
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
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell">
    <div class="card shadow-sm">
        <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
            <div>
                <h1 class="h3 mb-1">✏️ Edit Training Log</h1>
                <div class="small-muted"><?= e($log['dog_name']) ?></div>
            </div>
            <a href="view_logs.php" class="btn btn-outline-secondary btn-sm">Training History</a>
        </div>
        
        <form action="update_log.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
            <input type="hidden" name="dog_id" value="<?= (int) $log['dog_id'] ?>">

            <div class="mb-3">
                <label class="form-label fw-bold">Location Name</label>
                <input type="text" name="location_name" class="form-control" value="<?= e($log['location_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Date and time</label>
                <input type="datetime-local" name="log_date" class="form-control" value="<?= e(date('Y-m-d\TH:i', strtotime((string) $log['log_date']))) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">City, State</label>
                <input type="text" name="location_city_state" class="form-control" value="<?= e($log['location_city_state']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Environment</label>
                <select name="location_type" class="form-select">
                    <?php foreach ($allowedTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($log['location_type'] ?? 'Other') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Focus Level</label>
                <input type="range" name="focus_level" class="form-range" min="1" max="5" step="1" value="<?= (int) ($log['focus_level'] ?? 3) ?>">
                <div class="d-flex justify-content-between small px-2"><span>Distracted</span><span>Locked In</span></div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Latitude</label>
                    <input type="text" name="latitude" class="form-control" value="<?= e((string) ($log['latitude'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Longitude</label>
                    <input type="text" name="longitude" class="form-control" value="<?= e((string) ($log['longitude'] ?? '')) ?>">
                </div>
            </div>

            <div class="mb-4">
                    <label class="form-label fw-bold d-block">Skills Practiced</label>
                    <div class="row g-2">
                    <?php 
                    foreach ($allowedSkills as $opt):
                        $checked = in_array($opt, $skills) ? 'checked' : '';
                        $fieldId = 'skill_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $opt);
                    ?>
                        <div class="col-6">
                            <input type="checkbox" name="skills[]" value="<?= e($opt) ?>" class="btn-check" id="<?= e($fieldId) ?>" <?= $checked ?>>
                            <label class="btn btn-outline-primary w-100" for="<?= e($fieldId) ?>"><?= e($opt) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Handler Notes</label>
                <textarea name="handler_notes" class="form-control" rows="4"><?= e($log['handler_notes']) ?></textarea>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg fw-bold">Update Log Entry</button>
                <a href="view_logs.php" class="btn btn-link">Cancel and Return</a>
            </div>
        </form>
        </div>
    </div>
</main>
<?php guidepawFormUx(); ?>
</body>
</html>
