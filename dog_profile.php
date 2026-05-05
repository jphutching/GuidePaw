<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require 'includes/validation.php';
require 'includes/dog_breeds.php';
require_once 'includes/training_data.php';
require_once 'includes/public_dog_profile_token.php';
require_once 'includes/profile_image_tools.php';
checkLogin();

function gpEnsureDogPublicProfileColumns(PDO $pdo): void
{
    $columns = [
        'chip_registry' => 'TEXT', 'profile_photo_url' => 'TEXT', 'handler_photo_url' => 'TEXT',
        'handler_name' => 'TEXT', 'handler_phone' => 'TEXT', 'handler_email' => 'TEXT',
        'backup_contact_name' => 'TEXT', 'backup_contact_phone' => 'TEXT', 'found_dog_instructions' => 'TEXT',
        'public_notes' => 'TEXT', 'service_tasks' => 'TEXT', 'critical_allergies' => 'TEXT',
    ];
    foreach ($columns as $column => $type) {
        $pdo->exec('ALTER TABLE dogs ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
    }
}

gpEnsureDogPublicProfileColumns($pdo);

$userId = (int) $_SESSION['user_id'];
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : getActiveDogId($pdo, $userId);
if (!$dogId || !hasDogAccess($pdo, $userId, $dogId)) {
    die('Dog not found.');
}
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$errors = [];
$breedCatalog = getDogBreedsCatalog();
$chipLinks = getMicrochipResourceLinks();

$stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username, u.email AS owner_email FROM dogs d JOIN users u ON u.id=d.owner_user_id WHERE d.id=?');
$stmt->execute([$dogId]);
$dog = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $name = cleanText($_POST['name'] ?? '', 80);
    $breed = cleanText($_POST['breed'] ?? '', 120);
    $chip = cleanText($_POST['chip_number'] ?? '', 80);
    $chipRegistry = cleanText($_POST['chip_registry'] ?? '', 160);
    $weight = ($_POST['weight_lbs'] ?? '') !== '' ? round((float) $_POST['weight_lbs'], 2) : null;
    $dob = cleanDateValue($_POST['date_of_birth'] ?? '');
    $birthApprox = !empty($_POST['birth_is_approximate']) ? 1 : 0;
    $approxAge = ($_POST['approx_age_years'] ?? '') !== '' ? round((float) $_POST['approx_age_years'], 1) : null;
    $notes = cleanTextarea($_POST['notes'] ?? '', 2000);
    $handlerName = cleanText($_POST['handler_name'] ?? '', 120);
    $handlerPhone = cleanText($_POST['handler_phone'] ?? '', 80);
    $handlerEmail = cleanText($_POST['handler_email'] ?? '', 160);
    $backupName = cleanText($_POST['backup_contact_name'] ?? '', 120);
    $backupPhone = cleanText($_POST['backup_contact_phone'] ?? '', 80);
    $foundInstructions = cleanTextarea($_POST['found_dog_instructions'] ?? '', 1200);
    $publicNotes = cleanTextarea($_POST['public_notes'] ?? '', 1200);
    $serviceTasks = cleanTextarea($_POST['service_tasks'] ?? '', 1200);
    $criticalAllergies = cleanTextarea($_POST['critical_allergies'] ?? '', 1200);

    $dogPhoto = gpSaveCroppedProfileImage('profile_photo_cropped', $dog['profile_photo_url'] ?? null, $errors);
    $handlerPhoto = gpSaveCroppedProfileImage('handler_photo_cropped', $dog['handler_photo_url'] ?? null, $errors);

    if ($name === '') {
        $errors[] = 'Dog name is required.';
    }
    if ($handlerEmail !== '' && !filter_var($handlerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Handler email must be valid.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE dogs SET name=?, breed=?, chip_number=?, chip_registry=?, weight_lbs=?, date_of_birth=?, birth_is_approximate=?, approx_age_years=?, notes=?, profile_photo_url=?, handler_photo_url=?, handler_name=?, handler_phone=?, handler_email=?, backup_contact_name=?, backup_contact_phone=?, found_dog_instructions=?, public_notes=?, service_tasks=?, critical_allergies=? WHERE id=?');
        $stmt->execute([
            $name, $breed ?: null, $chip ?: null, $chipRegistry ?: null, $weight, $dob, $birthApprox, $approxAge, $notes ?: null,
            $dogPhoto ?: null, $handlerPhoto ?: null, $handlerName ?: null, $handlerPhone ?: null, $handlerEmail ?: null,
            $backupName ?: null, $backupPhone ?: null, $foundInstructions ?: null, $publicNotes ?: null, $serviceTasks ?: null, $criticalAllergies ?: null, $dogId
        ]);
        if ((int) getActiveDogId($pdo, $userId) !== $dogId) {
            setActiveDogId($pdo, $userId, $dogId);
        }
        header('Location: dog_profile.php?dog_id=' . $dogId . '&status=saved');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username, u.email AS owner_email FROM dogs d JOIN users u ON u.id=d.owner_user_id WHERE d.id=?');
$stmt->execute([$dogId]);
$dog = $stmt->fetch();
$csrf = generateCsrfToken();
$publicUrl = publicDogProfileUrl((int) $dog['id']);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($publicUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.breed-card{border:1px solid #dfe3e8;border-radius:12px;background:#f8fafc;padding:12px;}.breed-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;}.breed-search-results{border:1px solid #dfe3e8;border-radius:12px;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.12);max-height:280px;overflow-y:auto;margin-top:6px;position:relative;z-index:40;}.breed-search-option{display:block;width:100%;text-align:left;border:0;background:#fff;padding:11px 12px;border-bottom:1px solid #eef2f7;}.breed-search-option:last-child{border-bottom:0;}.breed-search-option:hover,.breed-search-option:focus{background:#f8fafc;outline:0;}.breed-search-name{display:block;font-weight:600;}.breed-search-meta{display:block;color:#6c757d;font-size:.82rem;margin-top:2px;}.breed-search-empty{padding:11px 12px;color:#6c757d;}.profile-photo-preview{width:86px;height:86px;border-radius:18px;object-fit:cover;background:#eef2f7;border:1px solid #dbe3ef;}.qr-card{text-align:center;}.qr-card img{max-width:260px;width:100%;height:auto;border:1px solid #e5e7eb;padding:.5rem;background:#fff;border-radius:14px;}.crop-canvas{width:100%;max-width:260px;border-radius:16px;border:1px solid #dbe3ef;touch-action:none;background:#111827;}.crop-help{font-size:.82rem;color:#6b7280;}
</style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-0">🪪 <?= e($dog['name']) ?></h2><small class="text-muted">Owner: <?= e($dog['owner_username']) ?></small></div><div class="d-flex gap-2"><a href="dogs.php" class="btn btn-outline-secondary btn-sm">Dogs</a><a href="index.php?set_dog=<?= (int) $dog['id'] ?>" class="btn btn-outline-primary btn-sm">Make Active</a></div></div>
    <?php if (!empty($_GET['status'])): ?><div class="alert alert-success">Dog profile saved.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="card shadow-sm mb-3 qr-card"><div class="card-body"><h3 class="h5">Public QR Profile</h3><p class="text-muted small mb-3">This unique QR code opens a public, no-login contact page for this dog only.</p><img src="<?= e($qrUrl) ?>" alt="Public QR profile for <?= e($dog['name']) ?>"><div class="d-grid gap-2 mt-3"><a class="btn btn-outline-primary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Preview Public Profile</a><button type="button" class="btn btn-outline-secondary" id="copyPublicUrl">Copy Public Link</button></div><div class="small text-muted mt-2" id="copyStatus"></div></div></div>

    <div class="card shadow-sm"><div class="card-body"><?php if (!$canEdit): ?><div class="alert alert-info">You have read-only collaboration access for this dog profile.</div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <div class="col-12"><h3 class="h5 mb-0">Dog Details</h3><div class="text-muted small">Private dog details plus public QR identity basics.</div></div>
            <div class="col-md-8"><label class="form-label">Dog Name</label><input type="text" name="name" class="form-control" value="<?= e($dog['name']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-md-4"><label class="form-label">Weight (lbs)</label><input type="number" step="0.1" name="weight_lbs" class="form-control" value="<?= e((string) $dog['weight_lbs']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-12"><label class="form-label">Breed</label><input type="text" name="breed" class="form-control breed-input" value="<?= e($dog['breed']) ?>" autocomplete="off" placeholder="Type 2+ letters or enter a custom breed/mix" <?= $canEdit ? '' : 'disabled' ?>><div class="form-text">Type at least 2 letters to search. You can still type any custom breed/mix.</div><div class="breed-search-results d-none" role="listbox" aria-label="Breed search results"></div></div>
            <div class="col-12"><div class="breed-card breed-card-live"><div class="breed-label mb-1">Breed reference</div><div class="fw-semibold breed-title">Pick a breed to preview notes</div><div class="small text-muted breed-group">Breed group will show here.</div><div class="mt-2"><span class="breed-label">Temperament</span><div class="breed-temperament">Common temperament notes will appear here.</div></div><div class="mt-2"><span class="breed-label">Traits</span><div class="breed-traits">Trainability, size, energy, and other typical traits.</div></div><div class="mt-2"><span class="breed-label">Notable notes</span><div class="breed-notes">Use these as a starting point, then rely on the individual dog in front of you.</div></div></div></div>
            <div class="col-md-6"><label class="form-label">Microchip #</label><input type="text" name="chip_number" class="form-control chip-input" value="<?= e($dog['chip_number']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-md-6"><label class="form-label">Chip Registry</label><input type="text" name="chip_registry" class="form-control" placeholder="AKC Reunite, HomeAgain, 24Petwatch..." value="<?= e($dog['chip_registry'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-12"><div class="breed-card chip-links-card"><div class="breed-label mb-1">Microchip quick links</div><div class="small text-muted chip-links-help mb-2">Register or verify this chip with major registries and lookup tools.</div><div class="d-flex flex-column gap-2 chip-links-list"><?php foreach ($chipLinks as $link): ?><a class="btn btn-outline-secondary btn-sm text-start chip-link" data-base-url="<?= e($link['url']) ?>" href="<?= e($link['url']) ?>" target="_blank" rel="noopener"><strong><?= e($link['label']) ?></strong><br><span class="small text-muted"><?= e($link['note']) ?></span></a><?php endforeach; ?></div></div></div>
            <div class="col-md-6"><label class="form-label">Birthday</label><input type="date" name="date_of_birth" class="form-control" value="<?= e((string) $dog['date_of_birth']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-md-6"><label class="form-label">Approx Age (years)</label><input type="number" step="0.1" name="approx_age_years" class="form-control" value="<?= e((string) $dog['approx_age_years']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="birth_is_approximate" id="birth_is_approximate" <?= !empty($dog['birth_is_approximate']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="birth_is_approximate">Birthday is approximate</label></div><div class="col-12"><label class="form-label">Private Notes</label><textarea name="notes" class="form-control" rows="3" <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['notes']) ?></textarea><div class="form-text">Private app notes. These do not show on the public QR profile.</div></div>
            <div class="col-12"><hr><h3 class="h5 mb-0">Public QR Profile</h3><div class="text-muted small">Only fill in information you are comfortable showing to anyone who scans the QR code.</div></div>
            <div class="col-md-6" data-crop-wrap><label class="form-label">Dog Profile Picture</label><div class="d-flex gap-3 align-items-center mb-2"><?php if (!empty($dog['profile_photo_url'])): ?><img id="dogPhotoPreview" src="<?= e($dog['profile_photo_url']) ?>" class="profile-photo-preview" alt="Dog photo"><?php else: ?><img id="dogPhotoPreview" class="profile-photo-preview" alt="Dog photo preview"><?php endif; ?><input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-crop-input data-crop-target="#profilePhotoCropped" data-crop-preview="#dogPhotoPreview" <?= $canEdit ? '' : 'disabled' ?>></div><input type="hidden" name="profile_photo_cropped" id="profilePhotoCropped"><canvas data-crop-canvas class="crop-canvas d-none" width="512" height="512"></canvas><div data-crop-controls class="d-none mt-2"><label class="form-label small mb-1">Zoom / drag to crop</label><input type="range" data-crop-zoom min="1" max="3" step="0.01" value="1" class="form-range"><button type="button" data-crop-clear class="btn btn-outline-secondary btn-sm">Clear crop</button></div><div class="crop-help">Square crop used on the public QR profile.</div></div>
            <div class="col-md-6" data-crop-wrap><label class="form-label">Handler Picture</label><div class="d-flex gap-3 align-items-center mb-2"><?php if (!empty($dog['handler_photo_url'])): ?><img id="handlerPhotoPreview" src="<?= e($dog['handler_photo_url']) ?>" class="profile-photo-preview" alt="Handler photo"><?php else: ?><img id="handlerPhotoPreview" class="profile-photo-preview" alt="Handler photo preview"><?php endif; ?><input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-crop-input data-crop-target="#handlerPhotoCropped" data-crop-preview="#handlerPhotoPreview" <?= $canEdit ? '' : 'disabled' ?>></div><input type="hidden" name="handler_photo_cropped" id="handlerPhotoCropped"><canvas data-crop-canvas class="crop-canvas d-none" width="512" height="512"></canvas><div data-crop-controls class="d-none mt-2"><label class="form-label small mb-1">Zoom / drag to crop</label><input type="range" data-crop-zoom min="1" max="3" step="0.01" value="1" class="form-range"><button type="button" data-crop-clear class="btn btn-outline-secondary btn-sm">Clear crop</button></div><div class="crop-help">Square crop used on the public QR profile.</div></div>
            <div class="col-md-4"><label class="form-label">Handler Name</label><input type="text" name="handler_name" class="form-control" value="<?= e($dog['handler_name'] ?? ($dog['owner_username'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-md-4"><label class="form-label">Handler Phone</label><input type="text" name="handler_phone" class="form-control" value="<?= e($dog['handler_phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-md-4"><label class="form-label">Handler Email</label><input type="email" name="handler_email" class="form-control" value="<?= e($dog['handler_email'] ?? ($dog['owner_email'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-md-6"><label class="form-label">Backup Contact Name</label><input type="text" name="backup_contact_name" class="form-control" value="<?= e($dog['backup_contact_name'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></div><div class="col-md-6"><label class="form-label">Backup Contact Phone</label><input type="text" name="backup_contact_phone" class="form-control" value="<?= e($dog['backup_contact_phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="col-12"><label class="form-label">If Found / Emergency Instructions</label><textarea name="found_dog_instructions" class="form-control" rows="3" placeholder="Example: Please call handler first. If no answer, call backup contact or primary vet." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['found_dog_instructions'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Public Service Task Notes</label><textarea name="service_tasks" class="form-control" rows="3" placeholder="Keep this general. Do not disclose diagnosis details." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['service_tasks'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Critical Medical / Allergy Note</label><textarea name="critical_allergies" class="form-control" rows="3" placeholder="Only include urgent public safety information." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['critical_allergies'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Public Handler Notes</label><textarea name="public_notes" class="form-control" rows="3" placeholder="Optional public note for someone who scans the QR code." <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['public_notes'] ?? '') ?></textarea></div>
            <?php if ($canEdit): ?><div class="col-12"><button class="btn btn-primary w-100">Save Dog Profile</button></div><?php endif; ?>
        </form>
    </div></div>
</div>
<script>
const breedCatalog = <?= json_encode($breedCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>; const publicUrl = <?= json_encode($publicUrl) ?>;
(function(){const b=document.getElementById('copyPublicUrl'),s=document.getElementById('copyStatus');if(b)b.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(publicUrl);if(s)s.textContent='Public link copied.';}catch(e){if(s)s.textContent=publicUrl;}});})();
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));} const breedNames=Object.keys(breedCatalog).sort((a,b)=>a.localeCompare(b)); function norm(v){return (v||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,' ').trim();} function rank(name,q){const n=norm(name);if(n===q)return 0;if(n.startsWith(q))return 1;if(n.split(' ').some(w=>w.startsWith(q)))return 2;if(n.includes(q))return 3;return 99;} function setupBreedSearch(){const input=document.querySelector('.breed-input'),results=document.querySelector('.breed-search-results');if(!input||!results)return;function hide(){results.classList.add('d-none');results.innerHTML='';}function choose(name){input.value=name;input.dispatchEvent(new Event('input',{bubbles:true}));hide();input.focus();}function render(){const q=norm(input.value.trim());if(q.length<2){hide();return;}const matches=breedNames.map(name=>({name,rank:rank(name,q)})).filter(i=>i.rank<99).sort((a,b)=>a.rank-b.rank||a.name.localeCompare(b.name)).slice(0,12);results.innerHTML='';if(!matches.length){results.innerHTML='<div class="breed-search-empty">No matching breed found. You can still save the breed exactly as typed.</div>';results.classList.remove('d-none');return;}matches.forEach(item=>{const info=breedCatalog[item.name]||{};const btn=document.createElement('button');btn.type='button';btn.className='breed-search-option';btn.innerHTML='<span class="breed-search-name">'+esc(item.name)+'</span><span class="breed-search-meta">'+esc(info.group||'Breed reference')+'</span>';btn.addEventListener('mousedown',e=>e.preventDefault());btn.addEventListener('click',()=>choose(item.name));results.appendChild(btn);});results.classList.remove('d-none');}input.addEventListener('input',render);input.addEventListener('focus',render);document.addEventListener('click',e=>{if(!results.contains(e.target)&&e.target!==input)hide();});}
function wireChipLinks(){const input=document.querySelector('.chip-input'),card=document.querySelector('.chip-links-card');if(!input||!card)return;const links=card.querySelectorAll('.chip-link'),help=card.querySelector('.chip-links-help');function render(){const chip=input.value.trim().replace(/\s+/g,'');if(chip){help.textContent='Quick jump to register or verify chip '+chip+'.';links.forEach(link=>{const base=link.getAttribute('data-base-url');link.href=base+(base.includes('?')?'&':'?')+'chip='+encodeURIComponent(chip);});}else{help.textContent='Enter a chip number to show quick registration and lookup links.';links.forEach(link=>link.href=link.getAttribute('data-base-url'));}}input.addEventListener('input',render);render();}
function setupBreedPreview(){const input=document.querySelector('.breed-input'),card=document.querySelector('.breed-card-live');if(!input||!card)return;const title=card.querySelector('.breed-title'),group=card.querySelector('.breed-group'),temp=card.querySelector('.breed-temperament'),traits=card.querySelector('.breed-traits'),notes=card.querySelector('.breed-notes');function render(){const v=input.value.trim(),info=breedCatalog[v];if(info){title.textContent=v;group.textContent='Group: '+(info.group||'Not listed');temp.textContent=info.temperament||'—';traits.textContent=info.traits||'—';notes.textContent=info.notes||'—';}else if(v){title.textContent=v;group.textContent='Custom breed entry';temp.textContent='No built-in reference for this exact name yet.';traits.textContent='You can still save this breed exactly as typed.';notes.textContent='Use private notes to capture individual observations.';}else{title.textContent='Pick a breed to preview notes';group.textContent='Breed group will show here.';temp.textContent='Common temperament notes will appear here.';traits.textContent='Trainability, size, energy, and other typical traits.';notes.textContent='Use these as a starting point, then rely on the individual dog in front of you.';}}input.addEventListener('input',render);render();}
setupBreedSearch();wireChipLinks();setupBreedPreview();
</script>
<?= gpProfileCropperScript() ?>
<?php guidepawFormUx(); ?>
</body>
</html>
