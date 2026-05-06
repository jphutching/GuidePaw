<?php
require_once 'includes/db_connect.php';
require_once 'includes/public_dog_profile_token.php';
require_once 'includes/found_dog_reports.php';
require_once 'includes/app_config.php';
require_once 'includes/validation.php';

gpEnsureFoundDogReportsTable($pdo);

$dogId = isset($_GET['dog']) ? (int) $_GET['dog'] : (int) ($_POST['dog_id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
if ($dogId <= 0 || $token === '' || !publicDogProfileTokenValid($dogId, $token)) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Report not available</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="card p-4 shadow-sm"><h1 class="h4">Report link not available</h1><p class="text-muted mb-0">This dog location report link is invalid.</p></div></main></body></html>';
    exit;
}

$dog = gpFoundDogFetchPublicDog($pdo, $dogId);
if (!$dog) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dog not found</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="card p-4 shadow-sm"><h1 class="h4">Dog not found</h1><p class="text-muted mb-0">This public profile does not exist.</p></div></main></body></html>';
    exit;
}

$errors = [];
$submitted = false;
$dogName = (string) ($dog['name'] ?? 'Service Dog');
$dogPhoto = (string) ($dog['profile_photo_url'] ?? ($dog['photo_url'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    $location = cleanText($_POST['finder_location'] ?? '', 240);
    $finderName = cleanText($_POST['finder_name'] ?? '', 120);
    $finderPhone = cleanText($_POST['finder_phone'] ?? '', 80);
    $message = cleanTextarea($_POST['finder_message'] ?? '', 1000);
    $lat = trim((string) ($_POST['finder_latitude'] ?? ''));
    $lng = trim((string) ($_POST['finder_longitude'] ?? ''));
    $accuracy = trim((string) ($_POST['finder_accuracy_m'] ?? ''));

    if ($honeypot !== '') {
        $submitted = true;
    } else {
        if ($location === '' && ($lat === '' || $lng === '')) {
            $errors[] = 'Please enter a location or share your current location once.';
        }
        if ($finderPhone === '') {
            $errors[] = 'Please enter a phone number so the handler can follow up.';
        }
        if ($lat !== '' && !is_numeric($lat)) {
            $errors[] = 'Latitude was invalid.';
        }
        if ($lng !== '' && !is_numeric($lng)) {
            $errors[] = 'Longitude was invalid.';
        }
        if ($accuracy !== '' && !ctype_digit($accuracy)) {
            $accuracy = '';
        }

        if (!$errors) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ipHash = $ip !== '' ? hash('sha256', $ip . '|guidepaw-found-dog') : null;
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $stmt = $pdo->prepare('INSERT INTO found_dog_reports (dog_id, finder_location, finder_latitude, finder_longitude, finder_accuracy_m, finder_name, finder_phone, finder_message, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id');
            $stmt->execute([$dogId, $location ?: null, $lat !== '' ? $lat : null, $lng !== '' ? $lng : null, $accuracy !== '' ? (int) $accuracy : null, $finderName ?: null, $finderPhone ?: null, $message ?: null, $ipHash, $userAgent ?: null]);
            $reportId = (int) $stmt->fetchColumn();
            gpNotifyFoundDogReport($pdo, $reportId);
            $submitted = true;
        }
    }
}

$profileUrl = 'public_dog_profile.php?dog=' . $dogId . '&token=' . rawurlencode($token);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report <?= e($dogName) ?> Location</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.shell{max-width:720px;margin:0 auto;padding:1rem}.topbar{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem}.back{font-size:2rem;text-decoration:none;color:#334155}.cardx{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:22px;padding:1.1rem;box-shadow:0 8px 22px rgba(15,23,42,.08)}.dog-photo{width:72px;height:72px;border-radius:18px;object-fit:cover;background:#e2e8f0}.btn-main{border-radius:18px;padding:.9rem 1rem;font-weight:850}.small-muted{color:#64748b;font-size:.92rem}.hidden-field{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}.gps-status{font-size:.9rem;color:#475569}</style>
</head>
<body>
<main class="shell">
    <div class="topbar"><a class="back" href="<?= e($profileUrl) ?>" aria-label="Back">‹</a><div><h1 class="h4 mb-0">Report Dog Location</h1><div class="small-muted">One-time report for <?= e($dogName) ?></div></div></div>
    <?php if ($submitted): ?>
        <section class="cardx text-center"><div class="display-5 mb-2">✅</div><h2 class="h4">Location report sent</h2><p class="text-muted">Thank you. The handler/admin notification has been queued. Your location is only sent with this report and is not continuously tracked.</p><a class="btn btn-primary btn-main w-100" href="<?= e($profileUrl) ?>">Back to Public Profile</a></section>
    <?php else: ?>
        <section class="cardx mb-3"><div class="d-flex gap-3 align-items-center"><?php if ($dogPhoto): ?><img src="<?= e($dogPhoto) ?>" class="dog-photo" alt="<?= e($dogName) ?>"><?php else: ?><div class="dog-photo d-flex align-items-center justify-content-center fs-3">🐕</div><?php endif; ?><div><h2 class="h5 mb-1">Found or saw <?= e($dogName) ?>?</h2><p class="small-muted mb-0">Share a location or cross street with the handler. You may optionally share your current GPS location one time.</p></div></div></section>
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" class="cardx">
            <input type="hidden" name="dog_id" value="<?= (int) $dogId ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="finder_latitude" id="finderLatitude"><input type="hidden" name="finder_longitude" id="finderLongitude"><input type="hidden" name="finder_accuracy_m" id="finderAccuracy"><div class="hidden-field"><label>Website <input type="text" name="website" autocomplete="off"></label></div>
            <div class="mb-3"><label class="form-label fw-semibold">Location or cross street *</label><input type="text" name="finder_location" id="finderLocation" class="form-control form-control-lg" placeholder="Example: Main St & 4th Ave, near the park"><div class="form-text">You can type a location, use GPS below, or both.</div></div>
            <button type="button" id="shareGps" class="btn btn-outline-primary btn-main w-100 mb-2">Share my current location once</button><div class="gps-status mb-3" id="gpsStatus">GPS is optional. GuidePaw will not track you continuously.</div>
            <div class="mb-3"><label class="form-label fw-semibold">Your name</label><input type="text" name="finder_name" class="form-control form-control-lg" placeholder="Optional"></div><div class="mb-3"><label class="form-label fw-semibold">Your phone *</label><input type="tel" name="finder_phone" class="form-control form-control-lg" placeholder="Phone number"></div><div class="mb-3"><label class="form-label fw-semibold">Optional message</label><textarea name="finder_message" class="form-control" rows="4" placeholder="Example: Dog is safe with me near the entrance."></textarea></div>
            <div class="alert alert-info small">Your location is sent only with this report. It is not live tracking and will not be shown publicly.</div><div class="d-grid gap-2"><button class="btn btn-success btn-main">Send Location Report</button><a class="btn btn-outline-secondary btn-main" href="<?= e($profileUrl) ?>">Cancel</a></div>
        </form>
    <?php endif; ?>
</main>
<script>(function(){var btn=document.getElementById('shareGps'),status=document.getElementById('gpsStatus'),lat=document.getElementById('finderLatitude'),lng=document.getElementById('finderLongitude'),acc=document.getElementById('finderAccuracy'),loc=document.getElementById('finderLocation');if(!btn)return;btn.addEventListener('click',function(){if(!navigator.geolocation){status.textContent='GPS is not available on this device/browser.';return;}status.textContent='Requesting one-time location permission...';navigator.geolocation.getCurrentPosition(function(pos){lat.value=pos.coords.latitude.toFixed(7);lng.value=pos.coords.longitude.toFixed(7);acc.value=Math.round(pos.coords.accuracy||0);if(!loc.value)loc.value='GPS location shared: '+lat.value+', '+lng.value;status.textContent='GPS location added to this one-time report.';},function(){status.textContent='Location permission was not granted. You can type a cross street instead.';},{enableHighAccuracy:true,timeout:12000,maximumAge:0});});})();</script>
</body>
</html>
