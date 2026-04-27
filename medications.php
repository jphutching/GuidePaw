<?php
require 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
if (!featureEnabled($pdo, 'medications_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
require 'includes/validation.php';
checkLogin();
$userId=(int)$_SESSION['user_id']; $dog=requireActiveDog($pdo,$userId); $dogId=(int)$dog['id']; $canEdit=userCanEditDog($pdo,$userId,$dogId); $errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrfToken($_POST['csrf_token'] ?? '');
 if(!$canEdit){$errors[]='You have read-only access for this dog.';} else {
  $action=$_POST['action'] ?? '';
  if($action==='add_medication'){
   $name=cleanText($_POST['medication_name'] ?? '',150); $dosage=cleanText($_POST['dosage'] ?? '',120); $instructions=cleanTextarea($_POST['instructions'] ?? '',3000); $startDate=cleanDateValue($_POST['start_date'] ?? ''); $endDate=cleanDateValue($_POST['end_date'] ?? ''); $scheduleText=cleanText($_POST['schedule_text'] ?? '',150); $reminderTime=cleanDateTimeValue($_POST['reminder_time'] ?? ''); $refillDate=cleanDateValue($_POST['refill_date'] ?? ''); $provider=cleanText($_POST['prescribing_provider'] ?? '',150); $pharmacy=cleanText($_POST['pharmacy_name'] ?? '',150); $pharmacyPhone=cleanPhone($_POST['pharmacy_phone'] ?? '',40); $status=$_POST['status'] ?? 'active'; if(!in_array($status,['active','paused','completed'],true)) $status='active'; $notes=cleanTextarea($_POST['notes'] ?? '',3000);
   if($name==='') $errors[]='Medication name is required.';
   if(!$errors){$stmt=$pdo->prepare('INSERT INTO dog_medications (dog_id, created_by_user_id, medication_name, dosage, instructions, start_date, end_date, schedule_text, reminder_time, refill_date, prescribing_provider, pharmacy_name, pharmacy_phone, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'); $stmt->execute([$dogId,$userId,$name,$dosage ?: null,$instructions ?: null,$startDate,$endDate,$scheduleText ?: null,$reminderTime,$refillDate,$provider ?: null,$pharmacy ?: null,$pharmacyPhone ?: null,$status,$notes ?: null]); header('Location: medications.php?status=added'); exit;}
  } elseif($action==='set_status'){
   $medId=(int)($_POST['med_id'] ?? 0); $status=$_POST['status'] ?? 'active'; if(in_array($status,['active','paused','completed'],true)){$stmt=$pdo->prepare('UPDATE dog_medications SET status=? WHERE id=? AND dog_id=?'); $stmt->execute([$status,$medId,$dogId]);}
   header('Location: medications.php?status=updated'); exit;
  }
 }
}
$medications=getDogMedications($pdo,$dogId); $csrf=generateCsrfToken();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Medications</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet">
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

</head><body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="topbar p-4 shadow-sm"><div class="page-shell p-0"><div class="d-flex justify-content-between align-items-start gap-3"><div><div class="small opacity-75">Medication tracking</div><h2 class="mb-1">💊 <?= e($dog['name']) ?></h2><div class="small opacity-75">Track dosing, refill dates, and reminder times per dog.</div></div><a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a></div></div></div>
<div class="page-shell"><?php if(!empty($_GET['status'])): ?><div class="alert alert-success"><?= e(ucfirst($_GET['status'])) ?>.</div><?php endif; ?><?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3"><div class="col-lg-5"><div class="card shadow-sm h-100"><div class="card-body"><div class="section-title">Add Medication</div><?php if(!$canEdit): ?><div class="alert alert-info">You have read-only access for this dog.</div><?php endif; ?><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_medication"><div class="col-12"><label class="form-label">Medication name</label><input type="text" name="medication_name" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Dosage</label><input type="text" name="dosage" class="form-control" placeholder="e.g. 25 mg" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Status</label><select name="status" class="form-select" <?= $canEdit?'':'disabled' ?>><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></div><div class="col-12"><label class="form-label">Schedule</label><input type="text" name="schedule_text" class="form-control" placeholder="e.g. Twice daily with food" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">End date</label><input type="date" name="end_date" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Reminder time</label><input type="datetime-local" name="reminder_time" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Refill date</label><input type="date" name="refill_date" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-12"><label class="form-label">Prescribing provider</label><input type="text" name="prescribing_provider" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-8"><label class="form-label">Pharmacy</label><input type="text" name="pharmacy_name" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-4"><label class="form-label">Phone</label><input type="text" name="pharmacy_phone" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-12"><label class="form-label">Instructions</label><textarea name="instructions" rows="2" class="form-control" <?= $canEdit?'':'disabled' ?>></textarea></div><div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control" <?= $canEdit?'':'disabled' ?>></textarea></div><?php if($canEdit): ?><div class="col-12"><button class="btn btn-primary w-100">Save Medication</button></div><?php endif; ?></form></div></div></div>
<div class="col-lg-7"><div class="card shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-2"><div class="section-title mb-0">Current Medication List</div><span class="info-pill"><?= count($medications) ?> tracked</span></div><?php if(!$medications): ?><p class="text-muted mb-0">No medications added for this dog yet.</p><?php else: ?><div class="vstack gap-3"><?php foreach($medications as $med): ?><div class="kv-box"><div class="d-flex justify-content-between align-items-start gap-3 mb-2"><div><div class="fw-semibold"><?= e($med['medication_name']) ?></div><div class="small-muted"><?= e($med['dosage'] ?: 'Dosage not set') ?><?= !empty($med['schedule_text']) ? ' • ' . e($med['schedule_text']) : '' ?></div></div><span class="badge <?= $med['status']==='active'?'bg-success':($med['status']==='paused'?'bg-warning text-dark':'bg-secondary') ?>"><?= e(ucfirst($med['status'])) ?></span></div><div class="row g-2 small"><div class="col-md-6"><span class="data-label">Reminder</span><div><?= $med['reminder_time'] ? e(date('M j, Y g:i A', strtotime($med['reminder_time']))) : '—' ?></div></div><div class="col-md-6"><span class="data-label">Refill</span><div><?= $med['refill_date'] ? e(date('M j, Y', strtotime($med['refill_date']))) : '—' ?></div></div><div class="col-md-6"><span class="data-label">Provider</span><div><?= e($med['prescribing_provider'] ?: '—') ?></div></div><div class="col-md-6"><span class="data-label">Pharmacy</span><div><?= e($med['pharmacy_name'] ?: '—') ?><?= !empty($med['pharmacy_phone']) ? ' • ' . e($med['pharmacy_phone']) : '' ?></div></div><?php if(!empty($med['instructions'])): ?><div class="col-12"><span class="data-label">Instructions</span><div><?= nl2br(e($med['instructions'])) ?></div></div><?php endif; ?><?php if(!empty($med['notes'])): ?><div class="col-12"><span class="data-label">Notes</span><div><?= nl2br(e($med['notes'])) ?></div></div><?php endif; ?></div><?php if($canEdit): ?><form method="post" class="d-flex gap-2 mt-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="med_id" value="<?= (int)$med['id'] ?>"><select name="status" class="form-select form-select-sm" style="max-width:180px;"><option value="active" <?= $med['status']==='active'?'selected':'' ?>>Active</option><option value="paused" <?= $med['status']==='paused'?'selected':'' ?>>Paused</option><option value="completed" <?= $med['status']==='completed'?'selected':'' ?>>Completed</option></select><button class="btn btn-outline-primary btn-sm">Update</button></form><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></div></div></div></div></div></body></html>
