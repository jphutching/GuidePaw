<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/beta_qa_checklist_items.php';
$extraChecklistFile = __DIR__ . '/includes/beta_qa_checklist_extra_items.php';
if (is_file($extraChecklistFile)) {
    require_once $extraChecklistFile;
}
require_once 'includes/app_config.php';
checkLogin();

$currentRole = gpCurrentUserRole($pdo);
$isAdminQa = $currentRole === 'admin';
$adminOnlySections = [
    'render_deploy_environment',
    'admin_support',
    'security_privacy',
    'cleanup_data_safety',
    'sms_notifications',
    'project_history_upkeep',
];

$sections = gpBetaQaChecklistItems();
if (function_exists('gpBetaQaChecklistExtraItems')) {
    $sections = array_merge($sections, gpBetaQaChecklistExtraItems());
}
$sections = array_values(array_filter($sections, static function (array $section) use ($isAdminQa, $adminOnlySections): bool {
    $requiredRole = strtolower((string) ($section['required_role'] ?? ''));
    if ($requiredRole === 'admin') {
        return $isAdminQa;
    }
    if (in_array((string) ($section['id'] ?? ''), $adminOnlySections, true)) {
        return $isAdminQa;
    }
    return true;
}));
$totalItems = 0;
foreach ($sections as $section) {
    $totalItems += count($section['items'] ?? []);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beta QA Checklist · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.qa-hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.25rem 1rem 1.5rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}
.qa-shell{max-width:1040px;margin:0 auto;padding:1rem}.qa-card{border-radius:20px;border:1px solid rgba(15,23,42,.08);box-shadow:0 8px 20px rgba(15,23,42,.07);overflow:hidden}.qa-section{scroll-margin-top:1rem}.qa-section-header{background:#fff;padding:1rem;border-bottom:1px solid rgba(15,23,42,.08)}.qa-item{display:grid;grid-template-columns:auto 1fr;gap:.75rem;padding:1rem;border-bottom:1px solid rgba(15,23,42,.07);background:#fff}.qa-item:last-child{border-bottom:0}.qa-item.done{background:#f0fdf4}.qa-checkbox{width:1.35rem;height:1.35rem;margin-top:.15rem}.qa-item-title{font-weight:850;color:#0f172a}.qa-expected{font-size:.86rem;color:#64748b;margin-top:.25rem}.qa-pill{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.74rem;font-weight:900;background:#eef2ff;color:#4338ca}.qa-controls{position:sticky;top:0;z-index:20;background:rgba(248,250,252,.96);backdrop-filter:blur(12px);border:1px solid rgba(15,23,42,.08);border-radius:18px;padding:.75rem;box-shadow:0 8px 18px rgba(15,23,42,.08)}.qa-progress{height:.85rem;border-radius:999px;overflow:hidden;background:#e5e7eb}.qa-progress-bar{height:100%;width:0;background:#0d6efd;transition:width .2s ease}.qa-search{border-radius:14px}.qa-nav a{font-size:.85rem}.qa-print-note{display:none}.qa-notes{min-height:4.5rem;border-radius:14px}.qa-save-status{font-size:.82rem;font-weight:800;color:#64748b}.qa-save-status.saved{color:#047857}.qa-save-status.error{color:#b91c1c}
@media print{.gp-mobile-nav-shell,.gp-menu-backdrop,.gp-menu-panel,.qa-controls,.btn,.qa-search,.no-print{display:none!important}.qa-hero{box-shadow:none;border-radius:0;color:#000;background:#fff}.qa-print-note{display:block}.qa-card{box-shadow:none;border:1px solid #ccc}.qa-item{break-inside:avoid}.qa-shell{max-width:none;padding:0}.qa-section{page-break-inside:avoid}}
</style>
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<header class="qa-hero">
    <div class="container px-0" style="max-width:1040px;">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw beta testing · <?= e(ucfirst($currentRole)) ?> view</div>
                <h1 class="h2 mb-1">Beta QA Checklist</h1>
                <div class="small opacity-75"><?= $isAdminQa ? 'Admin/beta checks and regular user checks are visible.' : 'Regular user feature checks are visible. Admin-only beta checks are hidden.' ?></div>
            </div>
            <div class="d-flex gap-2 no-print"><button type="button" class="btn btn-light btn-sm" id="qaPrint">Print</button><a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a></div>
        </div>
    </div>
</header>
<main class="qa-shell">
    <?php if (!$isAdminQa): ?><div class="alert alert-info no-print">Admin-only deployment, system, security, cleanup, and administrator beta checks are hidden from regular users.</div><?php endif; ?>
    <section class="qa-controls mb-3 no-print"><div class="row g-2 align-items-center"><div class="col-12 col-md-4"><input type="search" class="form-control qa-search" id="qaSearch" placeholder="Search checklist…"></div><div class="col-12 col-md-4"><select class="form-select qa-search" id="qaFilter"><option value="all">Show all</option><option value="open">Open only</option><option value="done">Completed only</option></select></div><div class="col-12 col-md-4 d-flex gap-2"><button type="button" class="btn btn-outline-secondary w-100" id="qaExpand">Top</button><button type="button" class="btn btn-outline-danger w-100" id="qaReset">Reset checks</button></div><div class="col-12"><div class="d-flex justify-content-between small fw-bold mb-1"><span id="qaProgressText">0 of <?= (int) $totalItems ?> complete</span><span id="qaProgressPct">0%</span></div><div class="qa-progress"><div class="qa-progress-bar" id="qaProgressBar"></div></div><div class="qa-save-status mt-1" id="qaSaveStatus">Loading saved checklist…</div></div></div></section>
    <section class="card qa-card mb-3"><div class="card-body"><h2 class="h5">How to use this checklist</h2><p class="mb-2 text-muted">Use this after each Render redeploy or major feature batch. Progress saves to your GuidePaw account when online and falls back to this browser if needed. Regular users can test normal site features; admin-only beta checks are only shown to administrators.</p><div class="qa-print-note small">Printed copy. Checkbox state may not print in every browser; use this as a manual testing worksheet.</div><textarea class="form-control qa-notes no-print" id="qaNotes" placeholder="Testing notes, account names, dog IDs, bugs found, or follow-up work…"></textarea></div></section>
    <section class="card qa-card mb-3 no-print"><div class="card-body"><div class="fw-bold mb-2">Jump to section</div><div class="d-flex flex-wrap gap-2 qa-nav"><?php foreach ($sections as $section): ?><a class="btn btn-outline-primary btn-sm" href="#<?= e($section['id']) ?>"><?= e($section['title']) ?></a><?php endforeach; ?></div></div></section>
    <?php foreach ($sections as $section): ?><section class="card qa-card qa-section mb-3" id="<?= e($section['id']) ?>" data-section><div class="qa-section-header"><div class="d-flex justify-content-between gap-3 align-items-start flex-wrap"><div><h2 class="h4 mb-1"><?= e($section['title']) ?></h2><div class="text-muted small"><?= e($section['description'] ?? '') ?></div></div><span class="qa-pill" data-section-count>0 / <?= count($section['items'] ?? []) ?></span></div></div><div><?php foreach (($section['items'] ?? []) as $item): ?><label class="qa-item" data-qa-item data-item-id="<?= e($section['id'] . ':' . $item['id']) ?>"><input type="checkbox" class="qa-checkbox" data-qa-check value="1"><span><span class="qa-item-title"><?= e($item['text']) ?></span><span class="qa-expected d-block"><strong>Expected:</strong> <?= e($item['expected']) ?></span></span></label><?php endforeach; ?></div></section><?php endforeach; ?>
</main>
<script>
(function(){
const STORAGE_KEY='guidepaw_beta_qa_checklist_v1_<?= e($currentRole) ?>';
const NOTES_KEY='guidepaw_beta_qa_notes_v1_<?= e($currentRole) ?>';
const STATE_URL='beta_qa_checklist_state.php';
const checks=Array.from(document.querySelectorAll('[data-qa-check]'));
const sections=Array.from(document.querySelectorAll('[data-section]'));
const search=document.getElementById('qaSearch');
const filter=document.getElementById('qaFilter');
const notes=document.getElementById('qaNotes');
const progressText=document.getElementById('qaProgressText');
const progressPct=document.getElementById('qaProgressPct');
const progressBar=document.getElementById('qaProgressBar');
const saveStatus=document.getElementById('qaSaveStatus');
let state={};let saveTimer=null;
function setStatus(text,kind){if(!saveStatus)return;saveStatus.textContent=text;saveStatus.className='qa-save-status mt-1 '+(kind||'');}
function readLocal(){try{return JSON.parse(localStorage.getItem(STORAGE_KEY)||'{}')||{}}catch(e){return{}}}
function writeLocal(){localStorage.setItem(STORAGE_KEY,JSON.stringify(state));if(notes)localStorage.setItem(NOTES_KEY,notes.value||'');}
function applyState(){let done=0;checks.forEach(cb=>{const item=cb.closest('[data-qa-item]');const id=item.dataset.itemId;cb.checked=!!state[id];item.classList.toggle('done',cb.checked);if(cb.checked)done++;});const total=checks.length;const pct=total?Math.round(done/total*100):0;progressText.textContent=done+' of '+total+' complete';progressPct.textContent=pct+'%';progressBar.style.width=pct+'%';sections.forEach(section=>{const sectionChecks=Array.from(section.querySelectorAll('[data-qa-check]'));const sectionDone=sectionChecks.filter(cb=>cb.checked).length;const count=section.querySelector('[data-section-count]');if(count)count.textContent=sectionDone+' / '+sectionChecks.length;});applyFilter();}
function applyFilter(){const q=(search?search.value:'').trim().toLowerCase();const mode=filter?filter.value:'all';sections.forEach(section=>{let visibleCount=0;section.querySelectorAll('[data-qa-item]').forEach(item=>{const text=item.textContent.toLowerCase();const checked=item.querySelector('[data-qa-check]').checked;let show=true;if(q&&!text.includes(q))show=false;if(mode==='open'&&checked)show=false;if(mode==='done'&&!checked)show=false;item.style.display=show?'grid':'none';if(show)visibleCount++;});section.style.display=visibleCount?'block':'none';});}
async function loadRemote(){state=readLocal();if(notes)notes.value=localStorage.getItem(NOTES_KEY)||'';applyState();try{const res=await fetch(STATE_URL,{credentials:'same-origin'});const data=await res.json();if(data&&data.ok){state=data.checked_items||{};Object.keys(state).forEach(function(key){if(!document.querySelector('[data-item-id="'+CSS.escape(key)+'"]'))delete state[key];});if(notes)notes.value=data.notes||'';writeLocal();applyState();setStatus(data.updated_at?'Saved to GuidePaw account. Last update: '+data.updated_at:'Ready. Progress will save to your GuidePaw account.','saved');}else{setStatus('Using browser-only checklist storage.','error');}}catch(e){setStatus('Offline or save endpoint unavailable. Using browser-only checklist storage.','error');}}
async function saveRemote(){writeLocal();setStatus('Saving checklist…','');try{const res=await fetch(STATE_URL,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({checked_items:state,notes:notes?notes.value:''})});const data=await res.json();if(data&&data.ok){setStatus('Saved to GuidePaw account.','saved');}else{setStatus('Could not save to account. Browser copy saved.','error');}}catch(e){setStatus('Could not save to account. Browser copy saved.','error');}}
function queueSave(){writeLocal();window.clearTimeout(saveTimer);saveTimer=window.setTimeout(saveRemote,450);}
checks.forEach(cb=>cb.addEventListener('change',()=>{const item=cb.closest('[data-qa-item]');state[item.dataset.itemId]=cb.checked;if(!cb.checked)delete state[item.dataset.itemId];applyState();queueSave();}));
if(search)search.addEventListener('input',applyFilter);if(filter)filter.addEventListener('change',applyFilter);
const reset=document.getElementById('qaReset');if(reset)reset.addEventListener('click',()=>{if(confirm('Clear visible saved checklist checks for this account/browser?')){state={};localStorage.removeItem(STORAGE_KEY);applyState();queueSave();}});
const expand=document.getElementById('qaExpand');if(expand)expand.addEventListener('click',()=>{window.scrollTo({top:0,behavior:'smooth'});});
const printBtn=document.getElementById('qaPrint');if(printBtn)printBtn.addEventListener('click',()=>window.print());
if(notes)notes.addEventListener('input',queueSave);
loadRemote();
})();
</script>
</body>
</html>
