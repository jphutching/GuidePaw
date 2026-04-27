<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
if (!featureEnabled($pdo, 'certification_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
require 'includes/validation.php';
checkLogin();
$userId=(int)$_SESSION['user_id'];
$dog=requireActiveDog($pdo,$userId);
$dogId=(int)$dog['id'];
$canEdit=userCanEditDog($pdo,$userId,$dogId);
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrfToken($_POST['csrf_token'] ?? '');
 $action=$_POST['action'] ?? '';
 if(!$canEdit){$errors[]='You have read-only access for this dog.';}
 elseif($action==='seed_template'){seedCertificationChecklist($pdo,$dogId); header('Location: certification.php?status=template_loaded'); exit;}
 elseif($action==='update_item'){
  $itemId=(int)($_POST['item_id'] ?? 0); $status=$_POST['status'] ?? 'not_started'; $notes=cleanTextarea($_POST['notes'] ?? '',2000);
  if(!in_array($status,['not_started','in_training','proficient'],true)) $status='not_started';
  $stmt=$pdo->prepare('UPDATE dog_certification_items SET status=?, notes=?, last_assessed_at=CURRENT_TIMESTAMP WHERE id=? AND dog_id=?');
  $stmt->execute([$status,$notes ?: null,$itemId,$dogId]); header('Location: certification.php?status=item_updated'); exit;
 }
 elseif($action==='add_assessment'){
  $assessmentDate=cleanDateValue($_POST['assessment_date'] ?? '') ?: date('Y-m-d');
  $public=max(0,min(100,(int)($_POST['public_access_score'] ?? 0)));
  $task=max(0,min(100,(int)($_POST['task_reliability_score'] ?? 0)));
  $obedience=max(0,min(100,(int)($_POST['obedience_score'] ?? 0)));
  $env=max(0,min(100,(int)($_POST['environmental_score'] ?? 0)));
  $notes=cleanTextarea($_POST['notes'] ?? '',2000);
  $stmt=$pdo->prepare('INSERT INTO dog_certification_assessments (dog_id, assessed_by_user_id, assessment_date, public_access_score, task_reliability_score, obedience_score, environmental_score, notes) VALUES (?,?,?,?,?,?,?,?)');
  $stmt->execute([$dogId,$userId,$assessmentDate,$public ?: null,$task ?: null,$obedience ?: null,$env ?: null,$notes ?: null]);
  header('Location: certification.php?status=assessment_saved'); exit;
 }
}
$items=getDogCertificationItems($pdo,$dogId); $assessment=getLatestCertificationAssessment($pdo,$dogId); $csrf=generateCsrfToken(); $byCategory=[]; foreach($items as $item){$byCategory[$item['category']][]=$item;} $total=count($items); $proficient=count(array_filter($items, fn($i)=>($i['status']??'')==='proficient')); $inTraining=count(array_filter($items, fn($i)=>($i['status']??'')==='in_training')); $readyPct=$total?round(($proficient/$total)*100):0;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Certification Tracking</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet"></head><body>
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="topbar p-4 shadow-sm"><div class="page-shell p-0 d-flex justify-content-between align-items-start gap-3"><div><div class="small opacity-75">Certification and readiness</div><h2 class="mb-1">✅ <?= e($dog['name']) ?></h2><div class="small opacity-75">Track public access, task reliability, manners, and road-readiness.</div></div><a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a></div></div>
<div class="page-shell">
<?php if(!empty($_GET['status'])): ?><div class="alert alert-success"><?= e(str_replace('_',' ',$_GET['status'])) ?>.</div><?php endif; ?>
<?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3 mb-3"><div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Checklist items</div><div class="fs-4 fw-bold"><?= $total ?></div></div></div><div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Proficient</div><div class="fs-4 fw-bold text-success"><?= $proficient ?></div></div></div><div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">In training</div><div class="fs-4 fw-bold text-warning"><?= $inTraining ?></div></div></div><div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Readiness</div><div class="fs-4 fw-bold"><?= $readyPct ?>%</div></div></div></div>
<div class="row g-3"><div class="col-lg-7"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div class="section-title mb-0">Checklist</div><?php if($canEdit && !$items): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="seed_template"><button class="btn btn-primary btn-sm">Load starter checklist</button></form><?php endif; ?></div><?php if(!$items): ?><div class="alert alert-info mb-0">No checklist loaded yet. Start with the built-in public access and task reliability template.</div><?php else: ?><div class="accordion" id="certAccordion"><?php $acc=0; foreach($byCategory as $category=>$rows): $acc++; ?><div class="accordion-item"><h2 class="accordion-header" id="heading<?= $acc ?>"><button class="accordion-button <?= $acc>1?'collapsed':'' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $acc ?>"><?= e($category) ?> <span class="ms-2 badge bg-light text-dark"><?= count($rows) ?></span></button></h2><div id="collapse<?= $acc ?>" class="accordion-collapse collapse <?= $acc===1?'show':'' ?>" data-bs-parent="#certAccordion"><div class="accordion-body"><div class="vstack gap-3"><?php foreach($rows as $item): ?><form method="post" class="kv-box"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="update_item"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><div class="fw-semibold"><?= e($item['item_name']) ?></div><?php if(!empty($item['description'])): ?><div class="small-muted mb-2"><?= e($item['description']) ?></div><?php endif; ?><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm" <?= $canEdit?'':'disabled' ?>><option value="not_started" <?= $item['status']==='not_started'?'selected':'' ?>>Not started</option><option value="in_training" <?= $item['status']==='in_training'?'selected':'' ?>>In training</option><option value="proficient" <?= $item['status']==='proficient'?'selected':'' ?>>Proficient</option></select></div><div class="col-md-6"><label class="form-label small">Notes</label><input type="text" name="notes" class="form-control form-control-sm" value="<?= e($item['notes']) ?>" <?= $canEdit?'':'disabled' ?>></div><div class="col-md-2 d-grid"><?php if($canEdit): ?><button class="btn btn-outline-primary btn-sm">Save</button><?php endif; ?></div></div></form><?php endforeach; ?></div></div></div></div><?php endforeach; ?></div><?php endif; ?></div></div></div>
<div class="col-lg-5"><div class="card shadow-sm mb-3"><div class="card-body"><div class="section-title">Latest assessment snapshot</div><?php if($assessment): ?><div class="small-muted mb-2">Assessed <?= e(date('M j, Y', strtotime($assessment['assessment_date']))) ?></div><div class="row g-2"><div class="col-6"><div class="kv-box"><div class="data-label">Public access</div><div class="fs-5 fw-bold"><?= e((string)$assessment['public_access_score']) ?>%</div></div></div><div class="col-6"><div class="kv-box"><div class="data-label">Task reliability</div><div class="fs-5 fw-bold"><?= e((string)$assessment['task_reliability_score']) ?>%</div></div></div><div class="col-6"><div class="kv-box"><div class="data-label">Obedience</div><div class="fs-5 fw-bold"><?= e((string)$assessment['obedience_score']) ?>%</div></div></div><div class="col-6"><div class="kv-box"><div class="data-label">Environmental</div><div class="fs-5 fw-bold"><?= e((string)$assessment['environmental_score']) ?>%</div></div></div></div><?php if(!empty($assessment['notes'])): ?><div class="mt-3"><div class="data-label">Notes</div><div><?= nl2br(e($assessment['notes'])) ?></div></div><?php endif; ?><?php else: ?><p class="text-muted mb-0">No assessment saved yet.</p><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body"><div class="section-title">Add assessment</div><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_assessment"><div class="col-12"><label class="form-label">Assessment date</label><input type="date" name="assessment_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Public access %</label><input type="number" min="0" max="100" name="public_access_score" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Task reliability %</label><input type="number" min="0" max="100" name="task_reliability_score" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Obedience %</label><input type="number" min="0" max="100" name="obedience_score" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-6"><label class="form-label">Environmental %</label><input type="number" min="0" max="100" name="environmental_score" class="form-control" <?= $canEdit?'':'disabled' ?>></div><div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control" <?= $canEdit?'':'disabled' ?>></textarea></div><?php if($canEdit): ?><div class="col-12"><button class="btn btn-primary w-100">Save assessment</button></div><?php endif; ?></form></div></div></div></div>
</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
