<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
if (!featureEnabled($pdo, 'quick_session_enabled')) {
    header('Location: index.php?msg=quick_session_disabled');
    exit;
}
require_once 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);

$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$skillOptions = ['Focus / Watch me', 'Loose leash', 'Settle', 'Recall', 'Task work', 'CGC prep'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $locationName = cleanText($_POST['location_name'] ?? '', 100);
    $locationType = validateLocationType($_POST['location_type'] ?? 'Other', $allowedTypes);
    $focus = validateFocusLevel($_POST['focus_level'] ?? 3);
    $skills = cleanSkills($_POST['skills'] ?? [], $skillOptions);
    $notes = cleanTextarea($_POST['handler_notes'] ?? '', 500);
    $locationCityState = cleanText($_POST['location_city_state'] ?? '', 100);

    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float) $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null;

    if ($locationName === '') {
        $errors[] = 'Location name is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, latitude, longitude, log_date) VALUES (?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $stmt->execute([
            $userId,
            $dogId,
            $locationName,
            $locationCityState,
            $locationType,
            $focus,
            json_encode($skills),
            $notes,
            $latitude,
            $longitude
        ]);

        header('Location: view_logs.php?status=quick_logged');
        exit;
    }
}

$csrf = generateCsrfToken();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Quick Session</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">

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

<div class="container py-4" style="max-width:720px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">⚡ Quick Session</h3>
            <small class="text-muted">Fast field entry for <?= e($dog['name']) ?></small>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" name="location_city_state" id="location_city_state">

        <div class="mb-3">
            <label class="form-label fw-bold">Where are you?</label>
            <input class="form-control form-control-lg" type="text" name="location_name" placeholder="Truck stop, lobby, parking lot..." required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Environment</label>
            <select class="form-select form-select-lg" name="location_type">
                <?php foreach ($allowedTypes as $type): ?>
                    <option value="<?= e($type) ?>"><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold d-block">GPS</label>
            <div class="d-grid gap-2">
                <?php if (featureEnabled($pdo, 'gps_enabled')): ?>
                    <button type="button" class="btn btn-outline-primary" id="gpsBtn">Use current GPS location</button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>GPS temporarily disabled during beta</button>
                <?php endif; ?>
                <div id="gpsStatus" class="small text-muted">No GPS location captured yet.</div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Detected city / state</label>
            <input class="form-control" type="text" id="gps_location_display" placeholder="Will fill after GPS capture" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Focus level</label>
            <input class="form-range" type="range" min="1" max="5" name="focus_level" value="3">
            <div class="d-flex justify-content-between small"><span>1</span><span>5</span></div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold d-block">What did you work on?</label>
            <div class="row g-2">
                <?php foreach ($skillOptions as $skill): ?>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="skills[]" value="<?= e($skill) ?>" id="<?= md5($skill) ?>">
                            <label class="form-check-label" for="<?= md5($skill) ?>"><?= e($skill) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Quick note</label>
            <textarea class="form-control" name="handler_notes" rows="3" placeholder="One fast note about the session..."></textarea>
        </div>

        <button class="btn btn-success btn-lg w-100" <?= $canEdit ? '' : 'disabled' ?>>Save quick log</button>
    </form>
</div>

<script>
(function () {
    var btn = document.getElementById('gpsBtn');
    var status = document.getElementById('gpsStatus');
    var lat = document.getElementById('latitude');
    var lng = document.getElementById('longitude');
    var cityState = document.getElementById('location_city_state');
    var display = document.getElementById('gps_location_display');

    if (!btn || !status || !lat || !lng || !cityState || !display) return;

    function setLocationText(text) {
        cityState.value = text || '';
        display.value = text || '';
    }

    btn.addEventListener('click', function () {
        if (!navigator.geolocation) {
            status.textContent = 'GPS is not supported on this device/browser.';
            return;
        }

        status.textContent = 'Getting GPS location...';
        setLocationText('');

        navigator.geolocation.getCurrentPosition(async function (position) {
            lat.value = position.coords.latitude;
            lng.value = position.coords.longitude;
            status.textContent = 'GPS captured. Looking up city/state...';

            try {
                var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='
                    + encodeURIComponent(position.coords.latitude)
                    + '&lon=' + encodeURIComponent(position.coords.longitude);

                var response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Reverse lookup failed');
                }

                var data = await response.json();
                var addr = data.address || {};
                var city = addr.city || addr.town || addr.village || addr.municipality || addr.hamlet || addr.suburb || addr.neighbourhood || addr.county || '';
                var state = addr.state || addr.region || '';
                var text = [city, state].filter(Boolean).join(', ');

                if (text) {
                    setLocationText(text);
                    status.textContent = 'GPS captured: ' + text;
                } else {
                    setLocationText('');
                    status.textContent = 'GPS captured, but city/state was not found.';
                }
            } catch (error) {
                setLocationText('');
                status.textContent = 'GPS captured. City/state lookup failed.';
            }
        }, function (error) {
            status.textContent = 'GPS failed: ' + (error && error.message ? error.message : 'Unable to get location.');
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    });
})();
</script>
<?php guidepawFormUx(); ?>
</body>
</html>
