<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
checkLogin();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Nearby Places · <?= e(appName()) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.np-hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.25rem 1rem 1.5rem;margin-bottom:1.25rem}
.np-shell{max-width:860px;margin:0 auto;padding:.75rem 1rem 6rem}
.place-card{background:#fff;border:1px solid rgba(15,23,42,.1);border-radius:14px;padding:.9rem 1rem;box-shadow:0 4px 12px rgba(15,23,42,.06);transition:box-shadow .15s}
.place-card:hover{box-shadow:0 8px 20px rgba(15,23,42,.12)}
.place-name{font-weight:800;font-size:.95rem;color:#0f172a}
.place-addr{font-size:.82rem;color:#64748b;margin-top:.15rem}
.place-meta{font-size:.78rem;color:#64748b;margin-top:.25rem}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<header class="np-hero">
    <div style="max-width:860px;margin:0 auto">
        <h1 class="h3 mb-1">Nearby Places</h1>
        <div class="small opacity-75">Dog parks, dog-friendly restaurants, and veterinary clinics near you.</div>
    </div>
</header>

<main class="np-shell">
    <div class="card mb-3 p-3">
        <div class="fw-bold mb-2 small">Search location</div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-primary btn-sm" id="npUseLocation">Use my current location</button>
            <input type="text" class="form-control form-control-sm" id="npCityInput" placeholder="Or enter a city / zip" style="max-width:220px">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="npCityBtn">Search</button>
        </div>
        <div class="small text-muted mt-2" id="npLocationStatus"></div>
    </div>

    <ul class="nav nav-tabs mb-3" id="npTabs">
        <li class="nav-item"><button class="nav-link active" data-type="park" data-label="Dog Parks">🐾 Dog Parks</button></li>
        <li class="nav-item"><button class="nav-link" data-type="restaurant" data-label="Dog-Friendly Eateries">🍽️ Eateries</button></li>
        <li class="nav-item"><button class="nav-link" data-type="veterinary_care" data-label="Veterinary Clinics">🩺 Vets</button></li>
    </ul>

    <div id="npResults" class="d-grid gap-2">
        <div class="text-muted text-center py-4">Choose a location above to see nearby places.</div>
    </div>
</main>

<script>
(function () {
    var statusEl  = document.getElementById('npLocationStatus');
    var resultsEl = document.getElementById('npResults');
    var tabs      = document.querySelectorAll('#npTabs .nav-link');
    var activeType = 'park';
    var currentLat = null, currentLng = null;

    function setStatus(msg) { if (statusEl) statusEl.textContent = msg; }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderResults(places, label) {
        resultsEl.innerHTML = '';
        if (!places.length) {
            resultsEl.innerHTML = '<div class="text-muted text-center py-4">No ' + escHtml(label) + ' found nearby.</div>';
            return;
        }
        setStatus(places.length + ' results');
        places.forEach(function (p) {
            var stars = p.rating ? '★ ' + p.rating.toFixed(1) + ' &nbsp;' : '';
            var open  = p.open_now ? '<span class="badge bg-success me-1">Open now</span>' : '';
            var div = document.createElement('div');
            div.className = 'place-card';
            div.innerHTML =
                '<div class="place-name">' + escHtml(p.name) + '</div>' +
                '<div class="place-addr">' + escHtml(p.address) + '</div>' +
                '<div class="place-meta">' + open + stars +
                (p.lat && currentLat ? gpDistanceMi(currentLat, currentLng, p.lat, p.lng).toFixed(1) + ' mi away' : '') +
                '</div>';
            resultsEl.appendChild(div);
        });
    }

    function gpDistanceMi(lat1, lng1, lat2, lng2) {
        var R = 3958.8, dLat = (lat2 - lat1) * Math.PI / 180, dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
        return R * 2 * Math.asin(Math.sqrt(a));
    }

    function search(lat, lng, type, label, q) {
        resultsEl.innerHTML = '<div class="text-muted text-center py-4">Searching…</div>';
        var url;
        if (q) {
            url = 'api/places_search.php?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q);
        } else {
            url = 'api/places_search.php?type=' + encodeURIComponent(type) + '&lat=' + lat + '&lng=' + lng + '&radius=25';
        }
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderResults(data.results, label);
                else resultsEl.innerHTML = '<div class="alert alert-warning">Search error: ' + escHtml(data.message || 'unknown') + '</div>';
            })
            .catch(function () {
                resultsEl.innerHTML = '<div class="alert alert-danger">Could not reach search service.</div>';
            });
    }

    function getActiveLabel() {
        var active = document.querySelector('#npTabs .nav-link.active');
        return active ? active.dataset.label : 'results';
    }

    function runSearch() {
        if (currentLat === null) { setStatus('Choose a location first.'); return; }
        search(currentLat, currentLng, activeType, getActiveLabel(), null);
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            activeType = tab.dataset.type;
            if (currentLat !== null) runSearch();
        });
    });

    document.getElementById('npUseLocation').addEventListener('click', function () {
        if (!navigator.geolocation) { setStatus('Geolocation not supported.'); return; }
        setStatus('Getting your location…');
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
                setStatus('Location found. Searching…');
                runSearch();
            },
            function () { setStatus('Location access denied. Try the city/zip search.'); }
        );
    });

    function doTextSearch() {
        var q = (document.getElementById('npCityInput').value || '').trim();
        if (!q) { setStatus('Enter a city or zip code.'); return; }
        resultsEl.innerHTML = '<div class="text-muted text-center py-4">Searching…</div>';
        var label = getActiveLabel();
        var query = q + ' ' + (activeType === 'park' ? 'dog park' : activeType === 'restaurant' ? 'dog friendly restaurant' : 'veterinarian');
        currentLat = null; currentLng = null;
        search(null, null, activeType, label, query);
    }

    document.getElementById('npCityBtn').addEventListener('click', doTextSearch);
    document.getElementById('npCityInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doTextSearch(); }
    });
})();
</script>
</body>
</html>
