<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';
require_once __DIR__ . '/includes/feature_flags.php';
if (!featureEnabled($pdo, 'detailed_log_enabled')) {
    header('Location: index.php?msg=detailed_log_disabled');
    exit;
}
require 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$allowedSkills = ['Sit/Stay', 'Heel', 'Leave It', 'Under Tuck', 'DPT Task', 'PA Focus'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $postedDogId = (int) ($_POST['dog_id'] ?? 0);
    if ($postedDogId !== $dogId) {
        $errors[] = 'Active dog changed. Refresh and try again.';
    }

    $locationName = cleanText($_POST['location_name'] ?? '', 100);
    $cityState = cleanText($_POST['location_city_state'] ?? '', 100);
    $locationType = validateLocationType($_POST['location_type'] ?? 'Other', $allowedTypes);
    $focusLevel = validateFocusLevel($_POST['focus_level'] ?? 3);
    $handlerNotes = cleanTextarea($_POST['handler_notes'] ?? '', 5000);
    $skills = cleanSkills($_POST['skills'] ?? [], $allowedSkills);
    $latitude = validateLatitude($_POST['latitude'] ?? null);
    $longitude = validateLongitude($_POST['longitude'] ?? null);

    if ($locationName === '') {
        $errors[] = 'Location name is required.';
    }

    $media = null;
    $mediaType = null;
    $mimeType = null;
    if (!$errors) {
        try {
            [$media, $mediaType, $mimeType] = handleTrainingMediaUpload($_FILES['training_media'] ?? [], __DIR__);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, media_url, media_type, media_mime, latitude, longitude) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$userId, $dogId, $locationName, $cityState, $locationType, $focusLevel, json_encode($skills), $handlerNotes, $media, $mediaType, $mimeType, $latitude, $longitude]);
        header('Location: view_logs.php?status=created');
        exit;
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#0d6efd"><link rel="manifest" href="manifest.json"><title>New Training Log</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet"></head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-0">📝 Log Training</h3><small class="text-muted">Active dog: <?= e($dog['name']) ?></small></div><a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a></div>
    <?php if (!$canEdit): ?><div class="alert alert-warning">You only have view access for this dog. Ask the owner for edit collaboration access.</div><?php endif; ?>
    <div class="d-flex align-items-center gap-2 mb-3"><span data-network-status class="badge bg-secondary">Checking...</span><span class="badge bg-dark" data-queue-count style="display:none;">0</span><small class="text-muted">Queued offline logs on this device</small></div>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <form method="POST" action="log_entry.php" enctype="multipart/form-data" class="card p-4 shadow-sm" data-offline-log-form>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="dog_id" value="<?= (int) $dogId ?>">
        <input type="hidden" name="latitude" id="latitude" value="<?= e($_POST['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= e($_POST['longitude'] ?? '') ?>">
        <div class="mb-3"><label class="form-label fw-bold">Location Name</label><input type="text" name="location_name" class="form-control" value="<?= e($_POST['location_name'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label fw-bold">City, State</label><div class="input-group"><input type="text" name="location_city_state" id="city_state" class="form-control" value="<?= e($_POST['location_city_state'] ?? '') ?>"><button type="button" class="btn btn-primary" onclick="getLocation()">📍 GPS</button></div><small class="text-muted" id="gps-status">GPS can auto-fill coordinates and location details when available.</small></div>
        <div class="mb-3"><label class="form-label fw-bold">Environment</label><select name="location_type" class="form-select"><?php foreach ($allowedTypes as $type): ?><option value="<?= e($type) ?>" <?= (($_POST['location_type'] ?? '') === $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
        <hr>
        <div class="mb-3"><label class="form-label fw-bold">Focus Level (1-5)</label><input type="range" name="focus_level" class="form-range" min="1" max="5" step="1" value="<?= e($_POST['focus_level'] ?? '3') ?>"><div class="d-flex justify-content-between small px-2"><span>Distracted</span><span>Locked In</span></div></div>
        <div class="mb-3"><label class="form-label fw-bold d-block">Skills Practiced</label><div class="row g-2"><?php $selectedSkills = $_POST['skills'] ?? []; foreach ($allowedSkills as $skill): ?><div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="skills[]" value="<?= e($skill) ?>" id="skill_<?= md5($skill) ?>" <?= in_array($skill, $selectedSkills, true) ? 'checked' : '' ?>><label class="form-check-label" for="skill_<?= md5($skill) ?>"><?= e($skill) ?></label></div></div><?php endforeach; ?></div></div>
        <div class="mb-3"><label class="form-label fw-bold">Handler Notes</label><textarea name="handler_notes" class="form-control" rows="4"><?= e($_POST['handler_notes'] ?? '') ?></textarea></div>
        <div class="mb-4">
                    <label class="form-label fw-bold">Photo or Video</label>
                    <?php if (featureEnabled($pdo, 'media_upload_enabled')): ?>
                        <input type="file" name="training_media" class="form-control" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime">
                        <small class="text-muted d-block">Allowed: JPG, PNG, WEBP, MP4, WEBM, MOV. Images up to 8MB, videos up to 50MB.</small>
                        <small class="text-muted d-block" data-media-status>No media attached.</small>
                    <?php else: ?>
                        <input type="file" class="form-control" disabled>
                        <small class="text-muted d-block">Media uploads are temporarily disabled during beta.</small>
                    <?php endif; ?>
                </div>
        <div class="small text-muted mb-3">When offline, logs and attached media can be queued on this device and synced later. The selected dog travels with the queued log.</div>
        <button type="submit" class="btn btn-success btn-lg w-100" <?= $canEdit ? '' : 'disabled' ?>>Save Training Log</button>
    </form>
</div>
<script>
async function getLocation() {
    const status = document.getElementById('gps-status');
    const cityState = document.getElementById('city_state');
    const latitude = document.getElementById('latitude');
    const longitude = document.getElementById('longitude');
    if (!navigator.geolocation) { status.textContent = 'Geolocation is not supported on this device.'; return; }
    status.textContent = 'Getting GPS location...';
    navigator.geolocation.getCurrentPosition(async function(position) {
        latitude.value = position.coords.latitude.toFixed(7);
        longitude.value = position.coords.longitude.toFixed(7);
        status.textContent = `GPS captured: ${latitude.value}, ${longitude.value}`;
        try {
            const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude.value}&lon=${longitude.value}`;
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            const address = data.address || {};
            const city = address.city || address.town || address.village || address.municipality || address.hamlet || address.suburb || address.neighbourhood || address.county || '';
            const state = address.state || '';
            const formatted = [city, state].filter(Boolean).join(', ');
            if (formatted && !cityState.value) { cityState.value = formatted; }
            status.textContent = formatted ? `GPS captured: ${formatted}` : `GPS captured: ${latitude.value}, ${longitude.value}`;
        } catch (err) {}
    }, function(error) { status.textContent = 'GPS failed: ' + error.message; }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
}
</script>
<script src="app.js"></script>
</body></html>
