<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/ada_state_laws.php';
require_once 'includes/ada_state_laws_extra.php';
checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$user = getUserRecord($pdo, $userId);

$dogName = $dog['name'] ?? ($_SESSION['dog_name'] ?? 'Your dog');
$handlerName = $user['username'] ?? 'Handler';
$homeState = strtoupper(trim((string) ($user['home_state'] ?? '')));
$stateNames = adaStateNames();
$stateProfiles = adaStateLawProfiles();
if (function_exists('adaExtraReviewedStateProfiles')) {
    $stateProfiles = array_replace($stateProfiles, adaExtraReviewedStateProfiles());
}
$stateSource = 'default';
$requestedStateSource = strtolower(trim((string) ($_GET['state_source'] ?? '')));
$allowedStateSources = ['query', 'manual', 'gps', 'saved'];
$stateCode = strtoupper(trim((string) ($_GET['state'] ?? '')));
if ($stateCode !== '' && !array_key_exists($stateCode, $stateNames)) {
    $stateCode = '';
}
if ($stateCode !== '') {
    $stateSource = in_array($requestedStateSource, $allowedStateSources, true) ? $requestedStateSource : 'query';
} else {
    $sessionState = strtoupper(trim((string) ($_SESSION['ada_access_state'] ?? '')));
    if ($sessionState !== '' && array_key_exists($sessionState, $stateNames)) {
        $stateCode = $sessionState;
        $stateSource = (string) ($_SESSION['ada_access_state_source'] ?? 'session');
    } elseif ($homeState !== '' && array_key_exists($homeState, $stateNames)) {
        $stateCode = $homeState;
        $stateSource = 'home';
    } else {
        $stateCode = 'UT';
    }
}
$_SESSION['ada_access_state'] = $stateCode;
$_SESSION['ada_access_state_source'] = $stateSource;
$stateLaw = $stateProfiles[$stateCode] ?? adaDefaultStateLawProfile($stateCode, $stateNames[$stateCode]);
$federalLaw = adaFederalLawProfile();
$calmScript = 'This is my service dog. You may ask whether the dog is required because of a disability and what work or task the dog is trained to perform.';
$adaFaqUrl = 'https://www.ada.gov/resources/service-animals-faqs/';
$dotServiceAnimalsUrl = 'https://www.transportation.gov/resources/individuals/aviation-consumer-protection/service-animals';
$hudAssistanceAnimalsUrl = 'https://www.hud.gov/helping-americans/assistance-animals';
$stateSourceLabel = match ($stateSource) {
    'query' => 'Manual selection',
    'manual' => 'Manual selection',
    'gps' => 'GPS ping',
    'saved' => 'Last ping restored',
    'session' => 'Last ping',
    'home' => 'Handler home state',
    default => 'Default state',
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ADA Access Card</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
:root{--bg:#07111f;--card:#0f172a;--text:#f8fafc;--muted:#cbd5e1;--border:rgba(255,255,255,.14);--accent:#38bdf8;--good:#22c55e}body{background:linear-gradient(180deg,#020617,var(--bg));color:var(--text);min-height:100vh}.access-shell{max-width:980px;margin:0 auto;padding:1rem 1rem calc(7rem + env(safe-area-inset-bottom))}.access-card{background:rgba(15,23,42,.94);border:1px solid var(--border);border-radius:24px;padding:1.1rem;box-shadow:0 16px 34px rgba(0,0,0,.28)}.access-hero{background:radial-gradient(circle at top right,rgba(56,189,248,.18),transparent 34%),radial-gradient(circle at top left,rgba(34,197,94,.16),transparent 30%),var(--card)}.kicker{color:var(--muted);font-weight:800;letter-spacing:.04em;text-transform:uppercase;font-size:.78rem}h1{font-size:clamp(2.1rem,7vw,3.3rem);font-weight:850;letter-spacing:-.04em;line-height:1}.muted{color:var(--muted)}.grid{display:grid;gap:1rem;margin-top:1rem}@media(min-width:760px){.grid.two{grid-template-columns:1fr 1fr}.utility{grid-template-columns:repeat(3,1fr)}}.script{font-size:clamp(1.35rem,4.8vw,2rem);line-height:1.35;font-weight:800}.qa{font-size:clamp(1.08rem,3.8vw,1.35rem);line-height:1.55;font-weight:700}li+li{margin-top:.45rem}.btn{border-radius:16px;font-weight:750;padding:.8rem 1rem}.btn-outline-light{color:var(--text);border-color:rgba(255,255,255,.22)}.pill{display:inline-flex;border:1px solid var(--border);border-radius:999px;padding:.4rem .75rem;margin:.2rem .25rem .2rem 0;color:var(--muted)}select.form-select{background:#0b1220;color:#fff;border-color:var(--border);border-radius:16px;padding:.8rem}select.form-select option{color:#111827}.law-title{font-size:clamp(1.35rem,4.5vw,2rem);font-weight:850;line-height:1.12}.status{border:1px solid var(--border);border-radius:999px;padding:.35rem .65rem;color:var(--muted);font-size:.82rem}a{color:var(--accent)}.note{border-left:4px solid var(--good);background:rgba(34,197,94,.08);border-radius:14px;padding:.85rem}.script-tools{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:1rem}.script-feedback{min-height:1.25rem;margin-top:.55rem;font-size:.9rem;color:var(--muted)}.access-mode-badge{display:none;border:2px solid #22c55e;color:#fff;background:rgba(34,197,94,.12);border-radius:999px;padding:.48rem .85rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase;margin-bottom:.85rem;text-align:center}.high-contrast{--bg:#000;--card:#000;--text:#fff;--muted:#fff;--border:#fff;--accent:#00e5ff;--good:#00ff66}.screenshot{--bg:#f8fafc;--card:#fff;--text:#0f172a;--muted:#475569;--border:rgba(15,23,42,.16);--accent:#0f766e;--good:#166534;background:#f8fafc}.screenshot .access-card{background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.08)}.screenshot select.form-select{background:#fff;color:#111827}body.lockscreen{background:#000!important;color:#fff!important;overflow:auto}body.lockscreen .beta-banner,body.lockscreen .gp-mobile-nav-shell,body.lockscreen .hide-lock,body.lockscreen .hide-lockscreen{display:none!important}body.lockscreen .access-mode-badge{display:block}body.lockscreen .access-shell{max-width:100%;min-height:100svh;padding:calc(.75rem + env(safe-area-inset-top)) .75rem calc(.75rem + env(safe-area-inset-bottom));display:flex;flex-direction:column;justify-content:center}body.lockscreen .access-card{background:#000!important;border:2px solid rgba(255,255,255,.42);border-radius:18px;box-shadow:none;padding:clamp(.9rem,3vw,1.35rem)}body.lockscreen h1{font-size:clamp(2.55rem,11vw,5rem)}body.lockscreen .script{font-size:clamp(1.75rem,7vw,3.4rem)}body.lockscreen .qa{font-size:clamp(1.35rem,5.5vw,2.4rem)}body.lockscreen .pill{color:#fff;border-color:rgba(255,255,255,.6)}body.lockscreen .grid{margin-top:.75rem;gap:.75rem}body.lockscreen .lockscreen-exit{display:block!important;position:fixed;right:.75rem;bottom:calc(.75rem + env(safe-area-inset-bottom));z-index:9999;opacity:.85}.lockscreen-exit{display:none}@media print{.hide-print,.beta-banner,.gp-mobile-nav-shell{display:none!important}body{background:#fff!important;color:#000!important}.access-shell{padding:0;max-width:100%}.access-card{box-shadow:none!important;border-color:#ddd!important;background:#fff!important;page-break-inside:avoid}}
</style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<button class="btn btn-light lockscreen-exit" type="button" id="floatingExitBtn">Exit</button>
<div class="access-shell">
<div class="access-mode-badge">Access Mode</div>
<section class="access-card access-hero">
<div class="kicker">Service dog public-access reference</div><h1 class="mb-2">ADA Access Card</h1>
<p class="muted mb-3 hide-lock">Fast access reminders for <?= e($dogName) ?> with federal ADA notes and selected state guidance.</p>
<div class="hide-print d-grid gap-2 d-md-flex mb-3 hide-lock"><button class="btn btn-success flex-fill" id="lockBtn" type="button">Enter lockscreen display</button><button class="btn btn-outline-light flex-fill" id="contrastBtn" type="button">High contrast</button><button class="btn btn-outline-light flex-fill" id="screenshotBtn" type="button">Screenshot-ready</button></div>
<div class="d-flex flex-wrap gap-2 mb-2 hide-lock">
    <a class="btn btn-outline-light btn-sm" href="<?= e($adaFaqUrl) ?>" target="_blank" rel="noopener">Official ADA.gov FAQ</a>
    <a class="btn btn-outline-light btn-sm" href="<?= e($hudAssistanceAnimalsUrl) ?>" target="_blank" rel="noopener">Official HUD housing guidance</a>
    <a class="btn btn-outline-light btn-sm" href="<?= e($dotServiceAnimalsUrl) ?>" target="_blank" rel="noopener">Official DOT airline rules</a>
</div>
<div class="d-flex flex-wrap gap-2 mb-3 hide-lock">
    <a class="btn btn-outline-light btn-sm" href="#faq">FAQ section</a>
    <a class="btn btn-outline-light btn-sm" href="#definitions">Service dog / SDIT / ESA</a>
    <a class="btn btn-outline-light btn-sm" href="#state-law">State notes</a>
    <a class="btn btn-outline-light btn-sm" href="#housing">Housing guidance</a>
    <a class="btn btn-outline-light btn-sm" href="#air-travel">Air travel section</a>
</div>
<div><span class="pill">Handler: <?= e($handlerName) ?></span><span class="pill">Dog: <?= e($dogName) ?></span><span class="pill hide-lock">State: <?= e($stateLaw['state_name']) ?></span><span class="pill hide-lock" id="stateSourcePill">Source: <?= e($stateSourceLabel) ?></span></div>
</section>
<section class="grid"><div class="access-card"><div class="kicker">Calm script</div><p class="script mb-0" id="calmScript">“<?= e($calmScript) ?>”</p><div class="script-tools hide-print hide-lock"><button class="btn btn-outline-light" type="button" id="copyScriptBtn">Copy script</button><button class="btn btn-outline-light" type="button" id="shareScriptBtn">Share script</button></div><div class="script-feedback hide-lock" id="scriptFeedback" aria-live="polite"></div></div></section>
<section class="grid two"><div class="access-card"><div class="kicker">Only two questions</div><ol class="qa mb-0"><li>Is the dog required because of a disability?</li><li>What work or task is it trained to perform?</li></ol></div><div class="access-card"><div class="kicker">Not required</div><ul class="mb-0 qa"><li>Certification or registry papers</li><li>Medical records or diagnosis details</li><li>A task demonstration on demand</li></ul></div></section>
<section class="grid two" id="definitions">
<div class="access-card">
    <div class="kicker">Service dog</div>
    <p class="qa mb-0">A service dog is individually trained to do work or perform tasks for a person with a disability. That is the ADA public-access category for businesses and other covered places.</p>
</div>
<div class="access-card">
    <div class="kicker">SDIT and ESA</div>
    <ul class="qa mb-0">
        <li><strong>SDIT:</strong> not a service animal under the ADA until the dog is trained; some state laws are different.</li>
        <li><strong>ESA:</strong> comfort-only animals are not service animals under the ADA; housing and airline rules can be separate.</li>
    </ul>
</div>
</section>
<section class="grid two">
<div class="access-card">
    <div class="kicker">Scam warning</div>
    <p class="qa mb-0">Online registrations, certificates, ID cards, and vests do not create ADA rights. The ADA does not require certification, registration, a vest, or a special harness.</p>
</div>
<div class="access-card">
    <div class="kicker">Official links</div>
    <p class="qa mb-2">Use the official federal pages when you want the source text.</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-light btn-sm" href="<?= e($adaFaqUrl) ?>" target="_blank" rel="noopener">Official ADA.gov FAQ</a>
        <a class="btn btn-outline-light btn-sm" href="<?= e($hudAssistanceAnimalsUrl) ?>" target="_blank" rel="noopener">Official HUD housing guidance</a>
        <a class="btn btn-outline-light btn-sm" href="<?= e($dotServiceAnimalsUrl) ?>" target="_blank" rel="noopener">Official DOT airline rules</a>
        <a class="btn btn-outline-light btn-sm" href="service_dog_rights.php">Detailed ADA notes</a>
    </div>
</div>
</section>
<section class="grid two hide-lockscreen">
<div class="access-card" id="faq"><div class="d-flex justify-content-between gap-2 flex-wrap align-items-start"><div><div class="kicker">Federal ADA baseline</div><div class="law-title"><?= e($federalLaw['title']) ?></div></div><span class="status">Reviewed <?= e($federalLaw['last_reviewed']) ?></span></div><p class="muted mt-3"><?= e($federalLaw['summary']) ?></p><ul><?php foreach ($federalLaw['bullets'] as $bullet): ?><li><?= e($bullet) ?></li><?php endforeach; ?></ul><p class="mb-0 hide-lock"><a href="<?= e($federalLaw['source_url']) ?>" target="_blank" rel="noopener"><?= e($federalLaw['source_label']) ?></a></p></div>
<div class="access-card" id="state-law"><div class="d-flex justify-content-between gap-2 flex-wrap align-items-start"><div><div class="kicker">State law notes</div><div class="law-title"><?= e($stateLaw['state_name']) ?></div></div><span class="status"><?= e($stateLaw['status'] === 'reviewed' ? 'Reviewed ' . $stateLaw['last_reviewed'] : 'Pending review') ?></span></div><div class="hide-lock hide-print mt-3"><label class="kicker" for="stateSelect">Choose state manually</label><select class="form-select" id="stateSelect"><?php foreach ($stateNames as $code => $name): ?><option value="<?= e($code) ?>" <?= $code === $stateCode ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><button type="button" class="btn btn-outline-light w-100 mt-2" id="gpsBtn">Use GPS to detect state</button><div class="muted small mt-2" id="gpsStatus"></div><div class="muted small mt-2">When location access is already granted, GuidePaw will try to auto-detect your state on load.</div><div class="muted small mt-2">Current source: <strong id="stateSourceText"><?= e($stateSourceLabel) ?></strong></div></div><p class="muted mt-3"><?= e($stateLaw['summary']) ?></p><ul><?php foreach ($stateLaw['bullets'] as $bullet): ?><li><?= e($bullet) ?></li><?php endforeach; ?></ul><div class="note mt-3"><strong>Training:</strong> <?= e($stateLaw['training_note']) ?></div><div class="note mt-3"><strong>Housing:</strong> <?= e($stateLaw['housing_note']) ?></div><p class="mb-0 mt-3 hide-lock"><?php if (!empty($stateLaw['source_url'])): ?><a href="<?= e($stateLaw['source_url']) ?>" target="_blank" rel="noopener"><?= e($stateLaw['source_label']) ?></a><?php else: ?><span class="muted"><?= e($stateLaw['source_label']) ?></span><?php endif; ?></p></div>
</section>
<section class="grid hide-lockscreen"><div class="access-card note">Access decisions should be based on the dog’s actual behavior and control, not on stereotypes, missing paperwork, or the fact that the dog is not wearing a vest.</div><div class="access-card" id="housing"><div class="kicker">Housing guidance</div><p class="qa mb-2">Housing is separate from ADA public access. HUD explains that assistance animals may include animals that work, assist, perform tasks, or provide emotional support that alleviates disability-related effects.</p><p class="muted mb-3">A housing provider may need to make a reasonable accommodation when Fair Housing Act requirements are met.</p><a class="btn btn-outline-light" href="<?= e($hudAssistanceAnimalsUrl) ?>" target="_blank" rel="noopener">Read official HUD guidance</a></div><div class="access-card"><div class="kicker">Need help now?</div><div style="font-size:clamp(1.6rem,5vw,2.35rem);font-weight:850;">800-514-0301</div><p class="muted mb-0">DOJ ADA Information Line · TTY 833-610-1264</p></div><div class="access-card muted small" id="air-travel">GuidePaw provides general service-animal information and official source links. This is not legal advice. Laws may vary by city, county, housing context, workplace, school, transportation setting, and facts of the situation. For airline-specific rules, use the DOT service animal page and the airline’s own policy before travel.</div></section>
<section class="grid utility hide-print hide-lock"><button class="btn btn-outline-light" type="button" onclick="window.print()">Print / Save PDF</button><a href="service_dog_rights.php" class="btn btn-outline-light">Detailed ADA notes</a><a href="air_travel_rights.php" class="btn btn-outline-light">Air Travel Rights</a><a href="<?= e($adaFaqUrl) ?>" class="btn btn-outline-light" target="_blank" rel="noopener">Official ADA.gov FAQ</a><a href="<?= e($hudAssistanceAnimalsUrl) ?>" class="btn btn-outline-light" target="_blank" rel="noopener">Official HUD Housing</a><a href="settings.php" class="btn btn-outline-light">Settings</a></section>
</div>
<script>
(function () {
    var profiles = <?= json_encode($stateProfiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var calmScript = <?= json_encode($calmScript, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var body = document.body;
    var lockBtn = document.getElementById('lockBtn');
    var floatingExitBtn = document.getElementById('floatingExitBtn');
    var contrastBtn = document.getElementById('contrastBtn');
    var screenshotBtn = document.getElementById('screenshotBtn');
    var stateSelect = document.getElementById('stateSelect');
    var gpsBtn = document.getElementById('gpsBtn');
    var gpsStatus = document.getElementById('gpsStatus');
    var stateSourceText = document.getElementById('stateSourceText');
    var copyScriptBtn = document.getElementById('copyScriptBtn');
    var shareScriptBtn = document.getElementById('shareScriptBtn');
    var scriptFeedback = document.getElementById('scriptFeedback');
    var wakeLock = null;
    var initialStateCode = <?= json_encode($stateCode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var initialStateSource = <?= json_encode($stateSource, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var initialStateSourceLabel = <?= json_encode($stateSourceLabel, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function status(message) {
        if (gpsStatus) {
            gpsStatus.textContent = message || '';
        }
    }

    function setStateSource(label) {
        if (stateSourceText) {
            stateSourceText.textContent = label || '';
        }
        var stateSourcePill = document.getElementById('stateSourcePill');
        if (stateSourcePill) {
            stateSourcePill.textContent = 'Source: ' + (label || '');
        }
    }

    function scriptStatus(message) {
        if (scriptFeedback) {
            scriptFeedback.textContent = message || '';
        }
    }

    function storeState(code, source) {
        try {
            localStorage.setItem('gpAdaAccessState', code);
            localStorage.setItem('gpAdaAccessStateSource', source || 'manual');
        } catch (e) {
        }
    }

    function restoreSavedStateIfAvailable() {
        try {
            var savedState = localStorage.getItem('gpAdaAccessState') || '';
            var savedSource = localStorage.getItem('gpAdaAccessStateSource') || 'saved';
            if (savedState && profiles[savedState] && savedState !== initialStateCode && (initialStateSource === 'default' || initialStateSource === 'home' || initialStateSource === 'session')) {
                status('Restoring last state: ' + profiles[savedState].state_name + '.');
                go(savedState, savedSource);
                return true;
            }
        } catch (e) {
        }
        return false;
    }

    function go(code, source) {
        if (!profiles[code]) {
            return;
        }
        storeState(code, source);
        var url = new URL(window.location.href);
        url.searchParams.set('state', code);
        url.searchParams.set('state_source', source || 'manual');
        window.location.href = url.toString();
    }

    function detectState(address) {
        var code = (((address['ISO3166-2-lvl4'] || address['ISO3166-2-lvl6'] || '') + '').split('-').pop() || '').toUpperCase();
        if (profiles[code]) {
            return code;
        }
        var name = ((address.state || '') + '').toLowerCase();
        for (var key in profiles) {
            if (profiles[key].state_name.toLowerCase() === name) {
                return key;
            }
        }
        return '';
    }

    async function reverseLookupState(lat, lon) {
        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon) + '&zoom=5&addressdetails=1';
        var response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        var data = await response.json();
        return detectState(data.address || {});
    }

    async function requestWakeLock() {
        try {
            if ('wakeLock' in navigator && navigator.wakeLock && navigator.wakeLock.request) {
                wakeLock = await navigator.wakeLock.request('screen');
            }
        } catch (e) {
            wakeLock = null;
        }
    }

    async function releaseWakeLock() {
        try {
            if (wakeLock && wakeLock.release) {
                await wakeLock.release();
            }
        } catch (e) {
        }
        wakeLock = null;
    }

    async function enterLockscreen() {
        body.classList.add('lockscreen');
        if (lockBtn) {
            lockBtn.textContent = 'Exit lockscreen display';
        }
        try {
            if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
                await document.documentElement.requestFullscreen();
            }
        } catch (e) {
        }
        await requestWakeLock();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function exitLockscreen() {
        body.classList.remove('lockscreen');
        if (lockBtn) {
            lockBtn.textContent = 'Enter lockscreen display';
        }
        await releaseWakeLock();
        try {
            if (document.fullscreenElement && document.exitFullscreen) {
                await document.exitFullscreen();
            }
        } catch (e) {
        }
    }

    async function copyScript() {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(calmScript);
                scriptStatus('Script copied.');
                return;
            }
            var textarea = document.createElement('textarea');
            textarea.value = calmScript;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            scriptStatus('Script copied.');
        } catch (e) {
            scriptStatus('Copy failed. You can still press and hold the script text to copy it.');
        }
    }

    async function shareScript() {
        try {
            if (navigator.share) {
                await navigator.share({ title: 'ADA Access Card calm script', text: calmScript });
                scriptStatus('Share sheet opened.');
            } else {
                await copyScript();
                scriptStatus('Sharing is not available here, so the script was copied instead.');
            }
        } catch (e) {
            if (e && e.name === 'AbortError') {
                return;
            }
            scriptStatus('Share failed. Try copy instead.');
        }
    }

    async function autoDetectStateOnLoad() {
        if (navigator.webdriver || window.__playwright__binding__ || window.__pwInitScripts) {
            return;
        }
        if (window.location.search.indexOf('state=') !== -1) {
            return;
        }

        try {
            if (sessionStorage.getItem('gpAdaAutoStateAttempted') === '1') {
                return;
            }
            sessionStorage.setItem('gpAdaAutoStateAttempted', '1');
        } catch (e) {
        }

        if (!navigator.geolocation || !navigator.permissions || !navigator.permissions.query) {
            restoreSavedStateIfAvailable();
            return;
        }

        try {
            var permission = await navigator.permissions.query({ name: 'geolocation' });
            if (!permission || permission.state !== 'granted') {
                restoreSavedStateIfAvailable();
                return;
            }
        } catch (e) {
            restoreSavedStateIfAvailable();
            return;
        }

        status('Auto-detecting state from your location...');
        navigator.geolocation.getCurrentPosition(
            async function (position) {
                try {
                    status('Detecting state from GPS...');
                    var stateCode = await reverseLookupState(position.coords.latitude, position.coords.longitude);
                    if (stateCode) {
                        status('Detected ' + profiles[stateCode].state_name + '. Loading...');
                        go(stateCode, 'gps');
                    } else {
                        if (!restoreSavedStateIfAvailable()) {
                        status('Could not detect a supported state. Choose your state manually.');
                        }
                    }
                } catch (e) {
                    if (!restoreSavedStateIfAvailable()) {
                        status('Could not reverse lookup GPS location. Choose your state manually.');
                    }
                }
            },
            function (error) {
                if (!restoreSavedStateIfAvailable()) {
                    status(error && error.message ? error.message : 'Location permission was not granted. Choose your state manually.');
                }
            },
            { enableHighAccuracy: false, timeout: 12000, maximumAge: 300000 }
        );
    }

    if (lockBtn) {
        lockBtn.addEventListener('click', function () {
            body.classList.contains('lockscreen') ? exitLockscreen() : enterLockscreen();
        });
    }

    if (floatingExitBtn) {
        floatingExitBtn.addEventListener('click', exitLockscreen);
    }

    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement && body.classList.contains('lockscreen')) {
            body.classList.remove('lockscreen');
            if (lockBtn) {
                lockBtn.textContent = 'Enter lockscreen display';
            }
            releaseWakeLock();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && body.classList.contains('lockscreen') && !wakeLock) {
            requestWakeLock();
        }
    });

    if (copyScriptBtn) {
        copyScriptBtn.addEventListener('click', copyScript);
    }

    if (shareScriptBtn) {
        shareScriptBtn.addEventListener('click', shareScript);
    }

    if (contrastBtn) {
        contrastBtn.addEventListener('click', function () {
            body.classList.toggle('high-contrast');
        });
    }

    if (screenshotBtn) {
        screenshotBtn.addEventListener('click', function () {
            body.classList.toggle('screenshot');
        });
    }

    if (stateSelect) {
        stateSelect.addEventListener('change', function () {
            go(stateSelect.value, 'manual');
        });
    }

    if (gpsBtn) {
        gpsBtn.addEventListener('click', function () {
            if (!navigator.geolocation) {
                status('GPS is not available. Choose your state manually.');
                return;
            }

            status('Requesting location permission...');
            navigator.geolocation.getCurrentPosition(
                async function (position) {
                    try {
                        status('Detecting state from GPS...');
                        var stateCode = await reverseLookupState(position.coords.latitude, position.coords.longitude);
                        if (stateCode) {
                            status('Detected ' + profiles[stateCode].state_name + '. Loading...');
                            go(stateCode, 'gps');
                        } else {
                            status('Could not detect a supported state. Choose your state manually.');
                        }
                    } catch (e) {
                        status('Could not reverse lookup GPS location. Choose your state manually.');
                    }
                },
                function (error) {
                    status(error && error.message ? error.message : 'Location permission was not granted. Choose your state manually.');
                },
                { enableHighAccuracy: false, timeout: 12000, maximumAge: 300000 }
            );
        });
    }

    autoDetectStateOnLoad();
    setStateSource(initialStateSourceLabel);
})();
</script>
</body>
</html>
