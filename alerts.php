<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
require_once 'includes/training_suggestion_links.php';
if (!featureEnabled($pdo, 'alerts_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
checkLogin();
$userId=(int)$_SESSION['user_id']; $dog=requireActiveDog($pdo,$userId); $alerts=getDogAlertItems($pdo,$userId,(int)$dog['id']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Smart Alerts</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet">
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
<div class="page-shell">
<div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap"><div><div class="small-muted">Smart alerts</div><h1 class="h3 mb-1">🧠 <?= e($dog['name']) ?></h1><div class="small-muted">Training, health, medication, and certification warnings in one place.</div></div><a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a></div>
<div class="d-flex flex-wrap gap-2 mb-3"><a href="log_entry.php" class="btn btn-outline-primary btn-sm">New log</a><a href="appointments.php" class="btn btn-outline-primary btn-sm">Appointments</a><a href="medications.php" class="btn btn-outline-primary btn-sm">Medications</a><a href="certification.php" class="btn btn-outline-primary btn-sm">Certification</a></div><?php if(!$alerts): ?><div class="alert alert-success">No active alerts for this dog right now.</div><?php else: ?><div class="vstack gap-3"><?php foreach($alerts as $alert): ?><div class="card shadow-sm alert-card <?= e($alert['level']) ?>"><div class="card-body"><div class="d-flex justify-content-between gap-3 align-items-start"><div><div class="fw-semibold"><?= e($alert['title']) ?></div><div class="small-muted mt-1"><?= e($alert['detail']) ?></div><?php $moduleLink = gpTrainingSuggestionLink(($alert['title'] ?? '') . ' ' . ($alert['detail'] ?? '')); if ($moduleLink): ?><a class="btn btn-outline-primary btn-sm mt-3 me-2" href="<?= e($moduleLink['url']) ?>"><?= e($moduleLink['label']) ?></a><?php endif; ?><?php if (!empty($alert['action_url'])): ?><a class="btn btn-primary btn-sm mt-3" href="<?= e($alert['action_url']) ?>"><?= e($alert['action_label'] ?? 'Open') ?></a><?php endif; ?></div><span class="badge <?= $alert['level']==='danger'?'bg-danger':($alert['level']==='warning'?'bg-warning text-dark':'bg-info text-dark') ?>"><?= e(ucfirst($alert['level'])) ?></span></div></div></div><?php endforeach; ?></div><?php endif; ?></div></body></html>
