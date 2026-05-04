<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require 'includes/validation.php';
require 'includes/dog_breeds.php';
require_once 'includes/training_data.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$currentUser = getUserRecord($pdo, $userId);
if (!$currentUser) {
    logoutSessionState();
    header('Location: login.php?msg=session_expired');
    exit;
}
$errors = [];
$status = $_GET['status'] ?? '';
$breedCatalog = getDogBreedsCatalog();
$chipLinks = getMicrochipResourceLinks();

if (isset($_GET['set_dog'])) {
    setActiveDogId($pdo, $userId, (int) $_GET['set_dog']);
    header('Location: dogs.php?status=active_set');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_dog') {
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
            try {
                $newDogId = insertAndGetId($pdo, 'INSERT INTO dogs (owner_user_id, name, breed, chip_number, weight_lbs, date_of_birth, birth_is_approximate, approx_age_years, notes) VALUES (?,?,?,?,?,?,?,?,?)', [$userId, $name, $breed ?: null, $chip ?: null, $weight, $dob, $birthApprox, $approxAge, $notes ?: null]);
                setActiveDogId($pdo, $userId, $newDogId);
                header('Location: dogs.php?status=dog_added');
                exit;
            } catch (PDOException $e) {
                if ((int) $e->getCode() === 23000) {
                    logoutSessionState();
                    header('Location: login.php?msg=session_expired');
                    exit;
                }
                throw $e;
            }
        }
    }
}

$dogs = getAccessibleDogs($pdo, $userId);
$activeDog = getActiveDog($pdo, $userId);
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.breed-card{border:1px solid #dfe3e8;border-radius:12px;background:#f8fafc;padding:12px;}
.breed-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;}

.breed-suggestions{
    max-height:15rem;
    overflow-y:auto;
    border:1px solid #dfe3e8;
    border-radius:12px;
    background:#fff;
    box-shadow:0 8px 22px rgba(15,23,42,.12);
    -webkit-overflow-scrolling:touch;
}
.breed-suggestion{
    text-align:left;
    padding:.58rem .75rem;
    border-left:0;
    border-right:0;
}
.breed-suggestion:first-child{border-top:0;}
.breed-suggestion:last-child{border-bottom:0;}
.breed-suggestion.active,
.breed-suggestion:focus{
    background:#eef6ff;
}

.breed-search-results{border:1px solid #dfe3e8;border-radius:12px;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.12);max-height:280px;overflow-y:auto;margin-top:6px;position:relative;z-index:40;}
.breed-search-option{display:block;width:100%;text-align:left;border:0;background:#fff;padding:11px 12px;border-bottom:1px solid #eef2f7;}
.breed-search-option:last-child{border-bottom:0;}
.breed-search-option:hover,.breed-search-option:focus{background:#f8fafc;outline:0;}
.breed-search-name{display:block;font-weight:600;}
.breed-search-meta{display:block;color:#6c757d;font-size:.82rem;margin-top:2px;}
.breed-search-empty{padding:11px 12px;color:#6c757d;}

</style>

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

</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 980px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">🐕 Dog Profiles</h2>
            <small class="text-muted">One handler can manage multiple dogs, and shared dogs appear here too.</small>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>

    <?php if ($status): ?><div class="alert alert-info"><?= e(str_replace('_', ' ', $status)) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Your Accessible Dogs</h5>
                    <?php if (!$dogs): ?>
                        <p class="text-muted mb-0">No dogs yet.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($dogs as $dog): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold"><?= e($dog['name']) ?><?php if ($activeDog && (int) $activeDog['id'] === (int) $dog['id']): ?> <span class="badge bg-primary">Active</span><?php endif; ?></div>
                                        <div class="small text-muted">
                                            <?= e($dog['breed'] ?: 'Breed not set') ?> • <?= e(ucfirst($dog['access_role'])) ?>
                                            <?php if ((int) $dog['owner_user_id'] !== $userId): ?> • Owner: <?= e($dog['owner_username']) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="dogs.php?set_dog=<?= (int) $dog['id'] ?>" class="btn btn-outline-primary btn-sm">Use</a>
                                        <a href="dog_profile.php?dog_id=<?= (int) $dog['id'] ?>" class="btn btn-outline-secondary btn-sm">Profile</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Add Another Dog</h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="add_dog">
                        <div class="col-12"><label class="form-label">Dog Name</label><input type="text" name="name" class="form-control" required></div>
                        <div class="col-12">
                            <label class="form-label">Breed</label>
                            <input type="text" name="breed" class="form-control breed-input" autocomplete="off" placeholder="Type 2+ letters or enter a custom breed/mix">
                            <div class="form-text">Type at least 2 letters to search. You can still type any custom breed/mix.</div>
                    <div class="breed-search-results d-none" role="listbox" aria-label="Breed search results"></div>
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
                        <div class="col-6"><label class="form-label">Microchip #</label><input type="text" name="chip_number" class="form-control chip-input"></div>
                        <div class="col-6"><label class="form-label">Weight (lbs)</label><input type="number" step="0.1" name="weight_lbs" class="form-control"></div>
                        <div class="col-12">
                            <div class="breed-card chip-links-card">
                                <div class="breed-label mb-1">Microchip quick links</div>
                                <div class="small text-muted chip-links-help mb-2">Enter a chip number to show quick registration and lookup links.</div>
                                <div class="d-flex flex-column gap-2 chip-links-list">
                                    <?php foreach ($chipLinks as $link): ?>
                                        <a class="btn btn-outline-secondary btn-sm text-start chip-link" data-base-url="<?= e($link['url']) ?>" href="<?= e($link['url']) ?>" target="_blank" rel="noopener">
                                            <strong><?= e($link['label']) ?></strong><br><span class="small text-muted"><?= e($link['note']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6"><label class="form-label">Birthday</label><input type="date" name="date_of_birth" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Approx Age</label><input type="number" step="0.1" name="approx_age_years" class="form-control" placeholder="Years"></div>
                        <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="birth_is_approximate" id="birth_is_approximate"><label class="form-check-label" for="birth_is_approximate">Birthday is approximate</label></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
                        <div class="col-12"><button class="btn btn-primary w-100">Save Dog</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const breedCatalog = <?= json_encode($breedCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// GUIDEPAW_BREED_SEARCH_V1
const guidepawBreedNames = Object.keys(breedCatalog).sort((a, b) =>
  a.localeCompare(b, undefined, { sensitivity: 'base' })
);

function guidepawEscapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, function (char) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char];
  });
}

function guidepawBreedMatches(query) {
  const q = String(query ?? '').trim().toLowerCase();

  if (!q) {
    return guidepawBreedNames;
  }

  const starts = [];
  const wordStarts = [];
  const contains = [];

  for (const name of guidepawBreedNames) {
    const lower = name.toLowerCase();

    if (lower.startsWith(q)) {
      starts.push(name);
      continue;
    }

    // For 1 letter, do not show every breed containing that letter.
    if (q.length >= 2) {
      const parts = lower.split(/[\s\/\-()]+/).filter(Boolean);
      if (parts.some(part => part.startsWith(q))) {
        wordStarts.push(name);
        continue;
      }
    }

    // Only allow broad contains search once the user typed enough to be intentional.
    if (q.length >= 3 && lower.includes(q)) {
      contains.push(name);
    }
  }

  return [...starts, ...wordStarts, ...contains];
}

function initGuidepawBreedSearch(scope = document) {
  scope.querySelectorAll('.breed-input').forEach(input => {
    if (input.dataset.breedSearchReady === '1') {
      return;
    }
    input.dataset.breedSearchReady = '1';

    const container = input.closest('.mb-3') || input.parentElement || scope;
    let box = container.querySelector('.breed-suggestions');

    if (!box) {
      box = document.createElement('div');
      box.className = 'breed-suggestions list-group mt-1 d-none';
      box.setAttribute('role', 'listbox');
      box.setAttribute('aria-label', 'Breed search results');
      input.insertAdjacentElement('afterend', box);
    }

    let activeIndex = -1;

    function closeBox() {
      box.classList.add('d-none');
      activeIndex = -1;
    }

    function buttons() {
      return Array.from(box.querySelectorAll('.breed-suggestion'));
    }

    function setActive(index) {
      const items = buttons();
      if (!items.length) {
        activeIndex = -1;
        return;
      }

      activeIndex = Math.max(0, Math.min(index, items.length - 1));
      items.forEach((button, i) => {
        button.classList.toggle('active', i === activeIndex);
        button.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
      });

      items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function renderBox() {
      const matches = guidepawBreedMatches(input.value);
      box.innerHTML = '';

      if (!matches.length) {
        closeBox();
        return;
      }

      matches.forEach(name => {
        const info = breedCatalog[name] || {};
        const group = info.group || 'Breed reference';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'breed-suggestion list-group-item list-group-item-action';
        button.dataset.breedName = name;
        button.setAttribute('role', 'option');
        button.innerHTML =
          '<span class="fw-semibold">' + guidepawEscapeHtml(name) + '</span>' +
          '<span class="text-muted small ms-2">' + guidepawEscapeHtml(group) + '</span>';

        box.appendChild(button);
      });

      box.classList.remove('d-none');
      activeIndex = -1;
    }

    input.addEventListener('focus', renderBox);
    input.addEventListener('input', renderBox);

    input.addEventListener('keydown', event => {
      if (box.classList.contains('d-none')) {
        if (event.key === 'ArrowDown') {
          renderBox();
          setActive(0);
          event.preventDefault();
        }
        return;
      }

      const items = buttons();

      if (event.key === 'ArrowDown') {
        setActive(activeIndex + 1);
        event.preventDefault();
      } else if (event.key === 'ArrowUp') {
        setActive(activeIndex <= 0 ? items.length - 1 : activeIndex - 1);
        event.preventDefault();
      } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
        input.value = items[activeIndex].dataset.breedName;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        closeBox();
        event.preventDefault();
      } else if (event.key === 'Escape') {
        closeBox();
        event.preventDefault();
      }
    });

    box.addEventListener('mousedown', event => {
      const button = event.target.closest('.breed-suggestion');
      if (!button) {
        return;
      }

      event.preventDefault();
      input.value = button.dataset.breedName;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      closeBox();
    });

    input.addEventListener('blur', () => {
      window.setTimeout(closeBox, 150);
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initGuidepawBreedSearch(document));
} else {
  initGuidepawBreedSearch(document);
}


// GUIDEPAW_BREED_SEARCH_UI_V1
const GUIDEPAW_BREED_SEARCH_MIN_CHARS = 2;
const GUIDEPAW_BREED_SEARCH_LIMIT = 12;

function normalizeBreedSearch(value) {
  return (value || '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

function rankBreedName(name, query) {
  const normalized = normalizeBreedSearch(name);
  if (normalized === query) return 0;
  if (normalized.startsWith(query)) return 1;
  if (normalized.split(' ').some((word) => word.startsWith(query))) return 2;
  if (normalized.includes(query)) return 3;
  return 99;
}

function setupBreedSearch(scope) {
  const input = scope.querySelector('.breed-input');
  const results = scope.querySelector('.breed-search-results');

  if (!input || !results || input.dataset.breedSearchReady === '1') {
    return;
  }

  input.dataset.breedSearchReady = '1';
  const breedNames = Object.keys(breedCatalog).sort((a, b) => a.localeCompare(b));

  function hideResults() {
    results.classList.add('d-none');
    results.innerHTML = '';
  }

  function chooseBreed(name) {
    input.value = name;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    hideResults();
    input.focus();
  }

  function renderResults() {
    const raw = input.value.trim();
    const query = normalizeBreedSearch(raw);

    if (query.length < GUIDEPAW_BREED_SEARCH_MIN_CHARS) {
      hideResults();
      return;
    }

    const matches = breedNames
      .map((name) => ({ name, rank: rankBreedName(name, query) }))
      .filter((item) => item.rank < 99)
      .sort((a, b) => a.rank - b.rank || a.name.localeCompare(b.name));

    results.innerHTML = '';

    if (!matches.length) {
      const empty = document.createElement('div');
      empty.className = 'breed-search-empty';
      empty.textContent = 'No matching breed found. You can still save the breed exactly as typed.';
      results.appendChild(empty);
      results.classList.remove('d-none');
      return;
    }

    matches.slice(0, GUIDEPAW_BREED_SEARCH_LIMIT).forEach((item) => {
      const info = breedCatalog[item.name] || {};
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'breed-search-option';
      button.setAttribute('role', 'option');

      const nameSpan = document.createElement('span');
      nameSpan.className = 'breed-search-name';
      nameSpan.textContent = item.name;

      const metaSpan = document.createElement('span');
      metaSpan.className = 'breed-search-meta';
      metaSpan.textContent = info.group ? info.group : 'Breed reference';

      button.appendChild(nameSpan);
      button.appendChild(metaSpan);

      button.addEventListener('mousedown', (event) => event.preventDefault());
      button.addEventListener('click', () => chooseBreed(item.name));

      results.appendChild(button);
    });

    if (matches.length > GUIDEPAW_BREED_SEARCH_LIMIT) {
      const more = document.createElement('div');
      more.className = 'breed-search-empty';
      more.textContent = 'Keep typing to narrow the list.';
      results.appendChild(more);
    }

    results.classList.remove('d-none');
  }

  input.addEventListener('input', renderResults);
  input.addEventListener('focus', renderResults);
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      hideResults();
    }
  });

  document.addEventListener('click', (event) => {
    if (!results.contains(event.target) && event.target !== input) {
      hideResults();
    }
  });
}

document.querySelectorAll('.breed-input').forEach((input) => {
  setupBreedSearch(input.closest('form') || document);
});

function wireBreedCard(scope){
  const input = scope.querySelector('.breed-input');
  const card = scope.querySelector('.breed-card-live');
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
}
wireBreedCard(document);

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

wireChipLinks(document);
</script>
<?php guidepawFormUx(); ?>
</body>
</html>
