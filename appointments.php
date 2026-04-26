<?php
require 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
if (!featureEnabled($pdo, 'vet_appointments_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
require 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$errors = [];
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_appointment') {
        $title = cleanText($_POST['title'] ?? '', 150);
        $appointmentAt = cleanDateTimeValue($_POST['appointment_at'] ?? '');
        $reminderAt = cleanDateTimeValue($_POST['reminder_at'] ?? '');
        $location = cleanText($_POST['location_text'] ?? '', 255);
        $notes = cleanTextarea($_POST['notes'] ?? '', 2000);
        $vetId = (int) ($_POST['dog_vet_id'] ?? 0) ?: null;
        if ($title === '' || !$appointmentAt) {
            $errors[] = 'Title and appointment time are required.';
        }
        if (!$errors) {
            $pdo->prepare('INSERT INTO dog_vet_appointments (dog_id, dog_vet_id, created_by_user_id, title, appointment_at, reminder_at, location_text, notes) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$dogId, $vetId, $userId, $title, $appointmentAt, $reminderAt, $location ?: null, $notes ?: null]);
            $status = 'Appointment saved.';
        }
    }

    if ($action === 'mark_status') {
        $apptId = (int) ($_POST['appointment_id'] ?? 0);
        $newStatus = in_array($_POST['new_status'] ?? '', ['scheduled','completed','cancelled'], true) ? $_POST['new_status'] : 'scheduled';
        $pdo->prepare('UPDATE dog_vet_appointments SET status = ? WHERE id = ? AND dog_id = ?')->execute([$newStatus, $apptId, $dogId]);
        $status = 'Appointment updated.';
    }
}

$csrf = generateCsrfToken();
$vetsStmt = $pdo->prepare('SELECT * FROM dog_vets WHERE dog_id = ? ORDER BY is_primary DESC, clinic_name ASC');
$vetsStmt->execute([$dogId]);
$vets = $vetsStmt->fetchAll();
$apptStmt = $pdo->prepare('SELECT a.*, v.clinic_name, v.phone FROM dog_vet_appointments a LEFT JOIN dog_vets v ON v.id = a.dog_vet_id WHERE a.dog_id = ? ORDER BY a.appointment_at ASC');
$apptStmt->execute([$dogId]);
$appointments = $apptStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Vet Appointments</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet"></head>
<body class="pb-5">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-0">📅 Vet Appointments</h2><small class="text-muted"><?= e($dog['name']) ?> reminder board</small></div><a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a></div>
    <div class="alert alert-info py-2 small">Turn on notifications from the dashboard to get browser/PWA reminders for upcoming vet appointments.</div>
    <?php if ($status): ?><div class="alert alert-success"><?= e($status) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm"><div class="card-body">
                <h5 class="card-title">Schedule Appointment</h5>
                <?php if (!$canEdit): ?><div class="alert alert-info">You have read-only access for this dog's appointments.</div><?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_appointment">
                    <div class="col-12"><label class="form-label">Title</label><input type="text" name="title" class="form-control" placeholder="Annual wellness exam" required></div>
                    <div class="col-12"><label class="form-label">Vet / Clinic</label><select name="dog_vet_id" class="form-select"><option value="">Choose saved vet</option><?php foreach ($vets as $vet): ?><option value="<?= (int) $vet['id'] ?>"><?= e($vet['clinic_name']) ?><?= !empty($vet['vet_name']) ? ' — ' . e($vet['vet_name']) : '' ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Appointment Time</label><input type="datetime-local" name="appointment_at" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Reminder Time</label><input type="datetime-local" name="reminder_at" class="form-control"><small class="text-muted">This powers dashboard reminders and browser/PWA notifications when enabled.</small></div>
                    <div class="col-12"><label class="form-label">Location / Notes</label><input type="text" name="location_text" class="form-control" placeholder="Clinic address or away-from-home emergency vet"></div>
                    <div class="col-12"><textarea name="notes" class="form-control" rows="3" placeholder="Shots due, paperwork to bring, etc."></textarea></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Save Appointment</button></div>
                </form>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm"><div class="card-body">
                <h5 class="card-title">Upcoming & Past Appointments</h5>
                <?php if (!$appointments): ?><p class="text-muted">No appointments yet.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($appointments as $appt): ?><div class="list-group-item px-0"><div class="d-flex justify-content-between gap-3"><div><div class="fw-semibold"><?= e($appt['title']) ?> <span class="badge <?= $appt['status'] === 'scheduled' ? 'bg-warning text-dark' : ($appt['status'] === 'completed' ? 'bg-success' : 'bg-secondary') ?>"><?= e($appt['status']) ?></span></div><div class="small text-muted"><?= e(date('M d, Y g:i A', strtotime($appt['appointment_at']))) ?><?= !empty($appt['clinic_name']) ? ' • ' . e($appt['clinic_name']) : '' ?></div><?php if (!empty($appt['location_text'])): ?><div class="small"><?= e($appt['location_text']) ?></div><?php endif; ?><?php if (!empty($appt['reminder_at'])): ?><div class="small text-muted">Reminder: <?= e(date('M d, Y g:i A', strtotime($appt['reminder_at']))) ?></div><?php endif; ?></div><?php if ($canEdit): ?><div class="d-flex flex-column gap-2"><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="mark_status"><input type="hidden" name="appointment_id" value="<?= (int) $appt['id'] ?>"><input type="hidden" name="new_status" value="completed"><button class="btn btn-outline-success btn-sm">Complete</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="mark_status"><input type="hidden" name="appointment_id" value="<?= (int) $appt['id'] ?>"><input type="hidden" name="new_status" value="cancelled"><button class="btn btn-outline-secondary btn-sm">Cancel</button></form></div><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
    <script src="app.js"></script>
</body></html>
