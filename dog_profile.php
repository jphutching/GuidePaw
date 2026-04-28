<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require 'includes/validation.php';
require 'includes/dog_breeds.php';
require_once 'includes/training_data.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : getActiveDogId($pdo, $userId);
if (!$dogId || !hasDogAccess($pdo, $userId, $dogId)) {
    die('Dog not found.');
}
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$errors = [];
$breedCatalog = getDogBreedsCatalog();
$chipLinks = getMicrochipResourceLinks();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $name = cleanText($_POST['name'] ?? '', 80);
    $breed = cleanText($_POST['breed'] ?? '', 120);
    $chip = cleanText($_POST['chip_number'] ?? '', 80);
    $weight = ($_POST['weight_lbs'] ?? '') !== '' ? round((float) $_POST['weight_lbs'], 2) : null;
    $dob = cleanDateValue($_POST['date_of_birth'] ?? '');
    $birthApprox = !empty($_POST['birth_is_approximate']) ? 1 : 0;
    $approxAge = ($_POST['approx_age_years'] ?? '') !== '' ? round((float) $_POST['approx_age_years'], 1) : null;
    $notes = cleanTextarea($_POST['notes'] ?? '', 2000);

    if ($name === '') {
        $errors[] = 'Dog name is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE dogs SET name=?, breed=?, chip_number=?, weight_lbs=?, date_of_birth=?, birth_is_approximate=?, approx_age_years=?, notes=? WHERE id=?');
        $stmt->execute([$name, $breed ?: null, $chip ?: null, $weight, $dob, $birthApprox, $approxAge, $notes ?: null, $dogId]);
        if ((int) getActiveDogId($pdo, $userId) !== $dogId) {
            setActiveDogId($pdo, $userId, $dogId);
        }
        header('Location: dog_profile.php?dog_id=' . $dogId . '&status=saved');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT d.*, u.username AS owner_username FROM dogs d JOIN users u ON u.id=d.owner_user_id WHERE d.id=?');
$stmt->execute([$dogId]);
$dog = $stmt->fetch();
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.breed-card{border:1px solid #dfe3e8;border-radius:12px;background:#f8fafc;padding:12px;}
.breed-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;}
</style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">🪪 <?= e($dog['name']) ?></h2>
            <small class="text-muted">Owner: <?= e($dog['owner_username']) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="dogs.php" class="btn btn-outline-secondary btn-sm">Dogs</a>
            <a href="index.php?set_dog=<?= (int) $dog['id'] ?>" class="btn btn-outline-primary btn-sm">Make Active</a>
        </div>
    </div>

    <?php if (!empty($_GET['status'])): ?><div class="alert alert-success">Dog profile saved.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$canEdit): ?><div class="alert alert-info">You have read-only collaboration access for this dog profile.</div><?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="col-12"><label class="form-label">Dog Name</label><input type="text" name="name" class="form-control" value="<?= e($dog['name']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="col-12">
                    <label class="form-label">Breed</label>
                    <input type="text" name="breed" class="form-control breed-input" value="<?= e($dog['breed']) ?>" list="breed-options" autocomplete="off" placeholder="Search breed or type your own" <?= $canEdit ? '' : 'disabled' ?>>
                    <div class="form-text">Search the list or type any custom breed/mix.</div>
                </div>
                <div class="col-12">
                    <div class="breed-card breed-card-live">
                        <div class="breed-label mb-1">Breed reference</div>
                        <div class="fw-semibold breed-title">Pick a breed to preview notes</div>
                        <div class="small text-muted breed-group">Breed group will show here.</div>
                        <div class="mt-2"><span class="breed-label">Temperament</span><div class="breed-temperament">Common temperament notes will appear here.</div></div>
                        <div class="mt-2"><span class="breed-label">Traits</span><div class="breed-traits">Trainability, size, energy, and other typical traits.</div></div>
                        <div class="row g-2 mt-1">
                            <div class="col-6"><span class="breed-label">Size</span><div class="breed-size">Typical size will appear here.</div></div>
                            <div class="col-6"><span class="breed-label">Weight</span><div class="breed-weight">Typical weight range will appear here.</div></div>
                            <div class="col-6"><span class="breed-label">Coat</span><div class="breed-coat">Coat type will appear here.</div></div>
                            <div class="col-6"><span class="breed-label">Shedding</span><div class="breed-shedding">Shedding level will appear here.</div></div>
                            <div class="col-12"><span class="breed-label">Exercise</span><div class="breed-exercise">Exercise needs will appear here.</div></div>
                        </div>
                        <div class="mt-2"><span class="breed-label">Notable notes</span><div class="breed-notes">Use these as a starting point, then rely on the individual dog in front of you.</div></div>
                    </div>
                </div>
                <div class="col-6"><label class="form-label">Microchip #</label><input type="text" name="chip_number" class="form-control chip-input" value="<?= e($dog['chip_number']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="col-6"><label class="form-label">Weight (lbs)</label><input type="number" step="0.1" name="weight_lbs" class="form-control" value="<?= e((string) $dog['weight_lbs']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="col-12">
                    <div class="breed-card chip-links-card">
                        <div class="breed-label mb-1">Microchip quick links</div>
                        <div class="small text-muted chip-links-help mb-2">Register or verify this chip with major registries and lookup tools.</div>
                        <div class="d-flex flex-column gap-2 chip-links-list">
                            <?php foreach ($chipLinks as $link): ?>
                                <a class="btn btn-outline-secondary btn-sm text-start chip-link" data-base-url="<?= e($link['url']) ?>" href="<?= e($link['url']) ?>" target="_blank" rel="noopener">
                                    <strong><?= e($link['label']) ?></strong><br><span class="small text-muted"><?= e($link['note']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-6"><label class="form-label">Birthday</label><input type="date" name="date_of_birth" class="form-control" value="<?= e((string) $dog['date_of_birth']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="col-6"><label class="form-label">Approx Age (years)</label><input type="number" step="0.1" name="approx_age_years" class="form-control" value="<?= e((string) $dog['approx_age_years']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="birth_is_approximate" id="birth_is_approximate" <?= !empty($dog['birth_is_approximate']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="birth_is_approximate">Birthday is approximate</label></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4" <?= $canEdit ? '' : 'disabled' ?>><?= e($dog['notes']) ?></textarea></div>
                <?php if ($canEdit): ?><div class="col-12"><button class="btn btn-primary w-100">Save Dog Profile</button></div><?php endif; ?>
            </form>
        </div>
    </div>
</div>
<datalist id="breed-options">
    <?php foreach (array_keys($breedCatalog) as $breedName): ?>
        <option value="<?= e($breedName) ?>"></option>
    <?php endforeach; ?>
</datalist>
<script>
const breedCatalog = <?= json_encode($breedCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function wireChipLinks(scope){
  const input = scope.querySelector('.chip-input');
  const card = scope.querySelector('.chip-links-card');
  if(!input || !card) return;
  const links = card.querySelectorAll('.chip-link');
  const help = card.querySelector('.chip-links-help');
  function renderChipLinks(){
    const chip = input.value.trim().replace(/\s+/g,'');
    if(chip){
      help.textContent = 'Quick jump to register or verify chip ' + chip + '.';
      links.forEach(link=>{
        const base = link.getAttribute('data-base-url');
        const glue = base.includes('?') ? '&' : '?';
        link.href = base + glue + 'chip=' + encodeURIComponent(chip);
      });
    } else {
      help.textContent = 'Enter a chip number to show quick registration and lookup links.';
      links.forEach(link=> link.href = link.getAttribute('data-base-url'));
    }
  }
  input.addEventListener('input', renderChipLinks);
  input.addEventListener('change', renderChipLinks);
  renderChipLinks();
}

(function(){
  const input = document.querySelector('.breed-input');
  const card = document.querySelector('.breed-card-live');
  if(!input || !card) return;
  const title = card.querySelector('.breed-title');
  const group = card.querySelector('.breed-group');
  const temperament = card.querySelector('.breed-temperament');
  const traits = card.querySelector('.breed-traits');
  const notes = card.querySelector('.breed-notes');
  const size = card.querySelector('.breed-size');
  const weight = card.querySelector('.breed-weight');
  const coat = card.querySelector('.breed-coat');
  const shedding = card.querySelector('.breed-shedding');
  const exercise = card.querySelector('.breed-exercise');
  function render(){
    const value = input.value.trim();
    const info = breedCatalog[value];
    if(info){
      title.textContent = value;
      group.textContent = 'Group: ' + (info.group || 'Not listed');
      temperament.textContent = info.temperament || '—';
      traits.textContent = info.traits || '—';
      notes.textContent = info.notes || '—';
      size.textContent = info.size || '—';
      weight.textContent = info.weight_range || '—';
      coat.textContent = info.coat_type || '—';
      shedding.textContent = info.shedding || '—';
      exercise.textContent = info.exercise_level || '—';
    } else if (value !== '') {
      title.textContent = value;
      group.textContent = 'Custom breed entry';
      temperament.textContent = 'No built-in reference for this exact name yet.';
      traits.textContent = 'You can still save this breed exactly as typed.';
      notes.textContent = 'Use the notes field to capture temperament, strengths, sensitivities, and working observations.';
      size.textContent = 'Custom';
      weight.textContent = 'Custom';
      coat.textContent = 'Custom';
      shedding.textContent = 'Custom';
      exercise.textContent = 'Custom';
    } else {
      title.textContent = 'Pick a breed to preview notes';
      group.textContent = 'Breed group will show here.';
      temperament.textContent = 'Common temperament notes will appear here.';
      traits.textContent = 'Trainability, size, energy, and other typical traits.';
      notes.textContent = 'Use these as a starting point, then rely on the individual dog in front of you.';
      size.textContent = 'Typical size will appear here.';
      weight.textContent = 'Typical weight range will appear here.';
      coat.textContent = 'Coat type will appear here.';
      shedding.textContent = 'Shedding level will appear here.';
      exercise.textContent = 'Exercise needs will appear here.';
    }
  }
  input.addEventListener('input', render);
  input.addEventListener('change', render);
  render();
  wireChipLinks(document);
})();
</script>
<?php guidepawFormUx(); ?>
</body>
</html>
