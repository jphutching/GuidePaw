<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
if (!featureEnabled($pdo, 'health_docs_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
require 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$errors = [];
$status = '';
$canEdit = userCanEditDog($pdo, $userId, $dogId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_vet') {
        $clinic = cleanText($_POST['clinic_name'] ?? '', 120);
        $vetName = cleanText($_POST['vet_name'] ?? '', 120);
        $phone = cleanPhone($_POST['phone'] ?? '');
        $address = cleanTextarea($_POST['address'] ?? '', 500);
        $notes = cleanTextarea($_POST['notes'] ?? '', 2000);
        $isPrimary = !empty($_POST['is_primary']) ? 1 : 0;
        if ($clinic === '') {
            $errors[] = 'Clinic name is required.';
        }
        if (!$errors) {
            if ($isPrimary) {
                $pdo->prepare('UPDATE dog_vets SET is_primary = 0 WHERE dog_id = ?')->execute([$dogId]);
            }
            $pdo->prepare('INSERT INTO dog_vets (dog_id, clinic_name, vet_name, phone, address, is_primary, notes, created_by_user_id) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$dogId, $clinic, $vetName ?: null, $phone ?: null, $address ?: null, $isPrimary, $notes ?: null, $userId]);
            $status = 'Vet saved.';
        }
    }

    if ($action === 'upload_document') {
        $docType = $_POST['doc_type'] ?? 'vet_record';
        if (!in_array($docType, ['vet_record', 'esa_letter', 'service_dog_letter'], true)) {
            $docType = 'vet_record';
        }
        $title = cleanText($_POST['title'] ?? '', 150);
        $provider = cleanText($_POST['provider_name'] ?? '', 150);
        $notes = cleanTextarea($_POST['notes'] ?? '', 2000);
        if ($title === '') {
            $errors[] = 'Document title is required.';
        }
        if (!$errors) {
            try {
                [$path, $mime] = handleDogDocumentUpload($_FILES['document_file'] ?? [], __DIR__);
                $pdo->prepare('INSERT INTO dog_documents (dog_id, uploaded_by_user_id, doc_type, title, provider_name, notes, file_path, mime_type) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$dogId, $userId, $docType, $title, $provider ?: null, $notes ?: null, $path, $mime]);
                $status = 'Document uploaded.';
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$csrf = generateCsrfToken();
$vets = $pdo->prepare('SELECT * FROM dog_vets WHERE dog_id = ? ORDER BY is_primary DESC, clinic_name ASC');
$vets->execute([$dogId]);
$vets = $vets->fetchAll();
$savedVetRowsStmt = $pdo->prepare('
    SELECT v.*, d.name AS dog_name
    FROM dog_vets v
    JOIN dogs d ON d.id = v.dog_id
    WHERE d.owner_user_id = ?
    ORDER BY v.clinic_name ASC, d.name ASC
    LIMIT 60
');
$savedVetRowsStmt->execute([$userId]);
$savedVetRows = $savedVetRowsStmt->fetchAll();
$docs = $pdo->prepare('SELECT dd.*, u.username FROM dog_documents dd JOIN users u ON u.id = dd.uploaded_by_user_id WHERE dd.dog_id = ? ORDER BY dd.created_at DESC');
$docs->execute([$dogId]);
$docs = $docs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dog Health</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet"></head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-0">🩺 Health & Documents</h2><small class="text-muted"><?= e($dog['name']) ?> — vets, records, and provider letters</small></div><a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a></div>
    <?php if ($status): ?><div class="alert alert-success"><?= e($status) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$canEdit): ?><div class="alert alert-info">You have view-only access for this dog's health records.</div><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card shadow-sm"><div class="card-body">
                <h5 class="card-title">Vet Contacts</h5>
                <?php if ($canEdit): ?>
                    <div class="border rounded p-3 mb-3 bg-white">
                        <label class="form-label small fw-bold">Search saved vets</label>
                        <input type="search" id="vetSearch" class="form-control" placeholder="Type a clinic name or vet name">
                        <div id="vetSearchResults" class="mt-2 d-grid gap-2"></div>
                    </div>
                <?php endif; ?>
                <?php if ($vets): ?><div class="list-group list-group-flush mb-3"><?php foreach ($vets as $vet): ?><div class="list-group-item px-0"><div class="fw-semibold"><?= e($vet['clinic_name']) ?> <?php if (!empty($vet['is_primary'])): ?><span class="badge bg-primary">Primary</span><?php endif; ?></div><div class="small text-muted"><?= e($vet['vet_name']) ?><?= !empty($vet['phone']) ? ' • ' . e($vet['phone']) : '' ?></div><div class="small"><?= nl2br(e($vet['address'])) ?></div></div><?php endforeach; ?></div><?php else: ?><p class="text-muted">No vets saved yet.</p><?php endif; ?>
                <?php if ($canEdit): ?>
                <div class="mb-3 p-3 rounded bg-light border">
                    <div class="fw-bold small mb-2">🔍 Find nearby vets</div>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="findNearbyVetsBtn">Use my location</button>
                        <input type="text" class="form-control form-control-sm" id="vetCitySearch" placeholder="Or enter a city / zip">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vetCitySearchBtn">Search</button>
                    </div>
                    <div id="vetFindStatus" class="small text-muted"></div>
                    <div id="vetFindResults" class="mt-2 d-grid gap-2"></div>
                </div>
                <form method="post" class="row g-2 border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_vet">
                    <div class="col-12"><input type="text" name="clinic_name" id="addVetClinic" class="form-control" placeholder="Clinic or emergency vet" required></div>
                    <div class="col-12"><input type="text" name="vet_name" id="addVetName" class="form-control" placeholder="Vet doctor name"></div>
                    <div class="col-12"><input type="text" name="phone" id="addVetPhone" class="form-control" placeholder="Phone"></div>
                    <div class="col-12"><textarea name="address" id="addVetAddress" class="form-control" rows="2" placeholder="Address"></textarea></div>
                    <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Hours, after-hours notes, etc."></textarea></div>
                    <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_primary" id="is_primary"><label class="form-check-label" for="is_primary">Primary/home vet</label></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Save Vet</button></div>
                </form>
                <?php endif; ?>
            </div></div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="card-title mb-0">Documents</h5><a href="appointments.php" class="btn btn-outline-primary btn-sm">Appointments</a></div>
                <?php if ($docs): ?><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Type</th><th>Title</th><th>Provider</th><th>Added</th><th></th></tr></thead><tbody><?php foreach ($docs as $doc): ?><tr><td><?= e($doc['doc_type'] === 'vet_record' ? 'Vet Record' : ($doc['doc_type'] === 'esa_letter' ? 'ESA Letter' : 'Service Dog Letter')) ?></td><td><?= e($doc['title']) ?></td><td><?= e($doc['provider_name']) ?></td><td><?= e(date('M d, Y', strtotime($doc['created_at']))) ?></td><td><a href="<?= e($doc['file_path']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Open</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted">No documents uploaded yet.</p><?php endif; ?>
                <?php if ($canEdit): ?>
                <form method="post" enctype="multipart/form-data" class="row g-3 border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="upload_document">
                    <div class="col-md-5"><label class="form-label">Document Type</label><select name="doc_type" class="form-select"><option value="vet_record">Vet Record</option><option value="esa_letter">ESA Letter</option><option value="service_dog_letter">Service Dog Letter</option></select></div>
                    <div class="col-md-7"><label class="form-label">Title</label><input type="text" name="title" class="form-control" placeholder="Rabies certificate" required></div>
                    <div class="col-12"><label class="form-label">Provider / Clinic</label><input type="text" name="provider_name" class="form-control" placeholder="Clinic, vet, or health provider"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    <div class="col-12"><label class="form-label">Upload File</label><input type="file" name="document_file" class="form-control" accept="application/pdf,image/jpeg,image/png,image/webp" required><small class="text-muted">PDF, JPG, PNG, or WEBP up to 12MB.</small></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Upload Document</button></div>
                </form>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php guidepawFormUx(); ?>
<script>
(function () {
    var search = document.getElementById('vetSearch');
    var results = document.getElementById('vetSearchResults');
    if (!search || !results) return;

    var vets = <?= json_encode(array_map(static function (array $vet): array {
        return [
            'clinic_name' => (string) ($vet['clinic_name'] ?? ''),
            'vet_name' => (string) ($vet['vet_name'] ?? ''),
            'phone' => (string) ($vet['phone'] ?? ''),
            'address' => (string) ($vet['address'] ?? ''),
            'notes' => (string) ($vet['notes'] ?? ''),
            'dog_name' => (string) ($vet['dog_name'] ?? ''),
            'is_primary' => !empty($vet['is_primary'])
        ];
    }, $savedVetRows), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    var clinic = document.querySelector('input[name="clinic_name"]');
    var vetName = document.querySelector('input[name="vet_name"]');
    var phone = document.querySelector('input[name="phone"]');
    var address = document.querySelector('textarea[name="address"]');
    var notes = document.querySelector('textarea[name="notes"]');
    var primary = document.querySelector('input[name="is_primary"]');

    function render(list) {
        results.innerHTML = '';
        if (!list.length) {
            results.innerHTML = '<div class="small text-muted">No saved vet matches yet.</div>';
            return;
        }

        list.slice(0, 8).forEach(function (vet) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary text-start';
            button.innerHTML = '<strong>' + vet.clinic_name + '</strong><div class="small text-muted">' + (vet.vet_name || 'No vet name') + (vet.dog_name ? ' · ' + vet.dog_name : '') + '</div>';
            button.addEventListener('click', function () {
                if (clinic) clinic.value = vet.clinic_name || '';
                if (vetName) vetName.value = vet.vet_name || '';
                if (phone) phone.value = vet.phone || '';
                if (address) address.value = vet.address || '';
                if (notes) notes.value = vet.notes || '';
                if (primary) primary.checked = !!vet.is_primary;
            });
            results.appendChild(button);
        });
    }

    function filter() {
        var term = (search.value || '').trim().toLowerCase();
        if (!term) {
            render(vets);
            return;
        }
        render(vets.filter(function (vet) {
            return [vet.clinic_name, vet.vet_name, vet.phone, vet.address, vet.dog_name].join(' ').toLowerCase().includes(term);
        }));
    }

    search.addEventListener('input', filter);
    filter();
})();

// ── Nearby vet finder ──────────────────────────────────────────────────────
(function () {
    var findBtn      = document.getElementById('findNearbyVetsBtn');
    var cityInput    = document.getElementById('vetCitySearch');
    var citySearchBtn= document.getElementById('vetCitySearchBtn');
    var statusEl     = document.getElementById('vetFindStatus');
    var resultsEl    = document.getElementById('vetFindResults');
    if (!findBtn || !resultsEl) return;

    var clinic  = document.getElementById('addVetClinic');
    var phone   = document.getElementById('addVetPhone');
    var address = document.getElementById('addVetAddress');

    function setStatus(msg) { if (statusEl) statusEl.textContent = msg; }

    function renderPlaces(places) {
        resultsEl.innerHTML = '';
        if (!places.length) { setStatus('No nearby veterinary clinics found.'); return; }
        setStatus(places.length + ' results — click one to fill the form below.');
        places.slice(0, 10).forEach(function (p) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary text-start btn-sm w-100';
            var stars = p.rating ? ' <span class="text-warning">★</span> ' + p.rating.toFixed(1) : '';
            var open  = p.open_now ? ' <span class="badge bg-success">Open</span>' : '';
            btn.innerHTML = '<strong>' + escHtml(p.name) + '</strong>' + stars + open +
                '<div class="small text-muted">' + escHtml(p.address) + '</div>';
            btn.addEventListener('click', function () {
                if (clinic)  clinic.value  = p.name;
                if (phone)   phone.value   = '';
                if (address) address.value = p.address;
                setStatus('Filled from Places result. Add phone and doctor name if needed.');
                resultsEl.innerHTML = '';
            });
            resultsEl.appendChild(btn);
        });
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fetchNearby(lat, lng) {
        setStatus('Searching for vets near you…');
        fetch('api/places_search.php?type=veterinary_care&lat=' + lat + '&lng=' + lng + '&radius=25')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderPlaces(data.results);
                else setStatus('Search error: ' + (data.message || 'unknown'));
            })
            .catch(function () { setStatus('Could not reach search service.'); });
    }

    function fetchByText(q) {
        setStatus('Searching for "' + q + '"…');
        fetch('api/places_search.php?type=veterinary_care&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderPlaces(data.results);
                else setStatus('Search error: ' + (data.message || 'unknown'));
            })
            .catch(function () { setStatus('Could not reach search service.'); });
    }

    findBtn.addEventListener('click', function () {
        if (!navigator.geolocation) { setStatus('Geolocation not supported by this browser.'); return; }
        setStatus('Getting your location…');
        navigator.geolocation.getCurrentPosition(
            function (pos) { fetchNearby(pos.coords.latitude, pos.coords.longitude); },
            function ()    { setStatus('Location access denied. Try the city/zip search instead.'); }
        );
    });

    function doTextSearch() {
        var q = (cityInput ? cityInput.value : '').trim();
        if (!q) { setStatus('Enter a city or zip code.'); return; }
        fetchByText(q + ' veterinarian');
    }

    if (citySearchBtn) citySearchBtn.addEventListener('click', doTextSearch);
    if (cityInput) cityInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doTextSearch(); } });
})();
</script>
</body></html>
