<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/dog_breeds_catalog.php';
require_once __DIR__ . '/includes/breed_photos.php';
require_once __DIR__ . '/includes/feature_flags.php';

$catalog = getDogBreedsCatalog();

$skipGallery = ['Unknown Breed', 'Unknown / Rescue Mix', 'Other / Custom', 'Other / Not Listed'];
$mappedBreeds = [];
foreach ($catalog as $name => $info) {
    if (!in_array($name, $skipGallery, true)) {
        $mappedBreeds[$name] = $info;
    }
}
ksort($mappedBreeds);

$groups = [];
foreach ($mappedBreeds as $info) {
    $g = trim((string) ($info['group'] ?? ''));
    if ($g !== '' && !in_array($g, $groups, true)) {
        $groups[] = $g;
    }
}
sort($groups);

$isAdmin       = !empty($_SESSION['is_admin']);
$photosEnabled = featureEnabled($pdo, 'breed_photos_enabled');

$breedData = [];
foreach ($mappedBreeds as $name => $info) {
    $breedData[$name] = [
        'group'       => (string) ($info['group']       ?? ''),
        'temperament' => (string) ($info['temperament'] ?? ''),
        'traits'      => (string) ($info['traits']      ?? ''),
        'notes'       => (string) ($info['notes']       ?? ''),
    ];
}

$title       = 'Breed Photo Gallery | GuidePaw';
$description = 'Browse photos and profiles for ' . count($mappedBreeds) . ' dog breeds commonly considered for service dog programs. Filter by group, search by name, and tap any breed for full details.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php guidepawSeoHead([
    'title'       => $title,
    'description' => $description,
    'robots'      => 'index,follow',
    'canonical'   => guidepawSeoAbsoluteUrl('/breed_gallery.php'),
    'image'       => '/assets/brand/guidepaw-logo.png',
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f1f5f9; color: #0f172a; }
.gallery-shell { max-width: 1200px; margin: 0 auto; padding: 1rem 1rem 4rem; }
.hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color: #fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); margin-bottom: 1.5rem; }
.filter-bar { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 18px; padding: 1rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(15,23,42,.06); }
.group-chip { border: 1.5px solid rgba(13,110,253,.25); border-radius: 999px; padding: .3rem .85rem; font-size: .8rem; font-weight: 700; cursor: pointer; background: #fff; color: #0d6efd; transition: all .15s; }
.group-chip:hover { background: #eff6ff; }
.group-chip.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
.breed-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
@media (min-width: 576px) { .breed-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 900px) { .breed-grid { grid-template-columns: repeat(4, 1fr); } }
@media (min-width: 1100px) { .breed-grid { grid-template-columns: repeat(5, 1fr); } }
.breed-card { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; overflow: hidden; cursor: pointer; box-shadow: 0 4px 12px rgba(15,23,42,.06); transition: transform .15s, box-shadow .15s; }
.breed-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(15,23,42,.12); }
.breed-card--hidden { display: none; }
.breed-photo-wrap { position: relative; width: 100%; padding-top: 72%; background: #e8f0fe; overflow: hidden; }
.breed-photo-wrap img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #f0f4f8; }
.breed-placeholder { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #94a3b8; }
.breed-card-body { padding: .65rem .75rem .75rem; }
.breed-card-name { font-size: .85rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: .2rem; }
.breed-card-group { font-size: .72rem; color: #64748b; font-weight: 600; }
/* Modal */
.modal-photo { width: 100%; max-height: 55vh; object-fit: contain; border-radius: 12px; margin-bottom: .25rem; display: block; background: #f1f5f9; cursor: zoom-in; }
.modal-photo-hint { font-size: .72rem; color: #64748b; text-align: center; margin-bottom: .75rem; }
.modal-photo-placeholder { width: 100%; height: 200px; background: #e8f0fe; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: #94a3b8; margin-bottom: 1rem; }
/* Lightbox */
.photo-lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.92); z-index: 2000; display: none; align-items: center; justify-content: center; cursor: zoom-out; }
.photo-lightbox.open { display: flex; }
.photo-lightbox img { max-width: 100vw; max-height: 100vh; object-fit: contain; border-radius: 4px; }
.photo-lightbox-close { position: absolute; top: 1rem; right: 1.25rem; color: #fff; font-size: 2.2rem; cursor: pointer; background: none; border: none; line-height: 1; opacity: .8; }
.info-row { margin-bottom: .6rem; }
.info-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; color: #64748b; }
.info-value { font-size: .9rem; color: #334155; margin-top: .1rem; }
/* Pre-fetch */
.prefetch-bar { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 14px; padding: .85rem 1rem; margin-bottom: 1.5rem; }
.prefetch-progress { height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: .5rem; }
.prefetch-fill { height: 100%; background: #0d6efd; border-radius: 999px; width: 0; transition: width .3s; }
.no-photos-note { background: #fef9c3; border: 1px solid #fde047; border-radius: 12px; padding: .75rem 1rem; font-size: .9rem; color: #713f12; margin-bottom: 1.5rem; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="gallery-shell">

    <section class="hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1">Breed Photo Gallery</h1>
                <p class="mb-0 opacity-90"><?= count($mappedBreeds) ?> breeds · tap any card for full profile</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="breed_questionnaire.php" class="btn btn-light btn-sm fw-bold">Breed Finder</a>
                <a href="breed_comparison_hub.php" class="btn btn-outline-light btn-sm fw-bold">Compare Breeds</a>
            </div>
        </div>
    </section>

    <?php if (!$photosEnabled): ?>
        <div class="no-photos-note">Photos are temporarily disabled. Breed profiles are still viewable — photos will return when the feature is re-enabled.</div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="prefetch-bar d-flex align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
                <div class="fw-bold small">Admin: Pre-fetch all breed photos</div>
                <div id="prefetch-status" class="text-muted" style="font-size:.8rem;">Warms the cache for all <?= count($mappedBreeds) ?> breeds so Android and web users get photos instantly.</div>
                <div class="prefetch-progress mt-2" id="prefetch-bar-wrap" style="display:none">
                    <div class="prefetch-fill" id="prefetch-fill"></div>
                </div>
            </div>
            <button id="prefetch-btn" class="btn btn-primary btn-sm fw-bold" <?= !$photosEnabled ? 'disabled' : '' ?>>Pre-fetch all photos</button>
            <button id="analyze-btn" class="btn btn-outline-secondary btn-sm fw-bold" <?= !$photosEnabled ? 'disabled' : '' ?> title="Uses AI to score each cached photo for full-dog visibility and swaps in a better image if score is low">🤖 Analyze photos</button>
            <span id="analyze-status" class="small text-muted ms-1"></span>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <input type="search" id="breed-search" class="form-control form-control-sm" placeholder="Search breed…" style="max-width:220px; border-radius:999px;">
            <button class="group-chip active" data-group="all">All</button>
            <?php foreach ($groups as $g): ?>
                <button class="group-chip" data-group="<?= e($g) ?>"><?= e($g) ?></button>
            <?php endforeach; ?>
        </div>
        <div id="gallery-count" class="text-muted" style="font-size:.8rem;"><?= count($mappedBreeds) ?> breeds shown</div>
    </div>

    <div class="breed-grid" id="breed-grid">
        <?php foreach ($mappedBreeds as $name => $info): ?>
            <div class="breed-card"
                 data-breed="<?= e($name) ?>"
                 data-group="<?= e((string)($info['group'] ?? '')) ?>"
                 data-photo-loaded="false"
                 tabindex="0"
                 role="button"
                 aria-label="<?= e($name) ?> breed profile">
                <div class="breed-photo-wrap">
                    <div class="breed-placeholder">🐕</div>
                </div>
                <div class="breed-card-body">
                    <div class="breed-card-name"><?= e($name) ?></div>
                    <div class="breed-card-group"><?= e((string)($info['group'] ?? '')) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p id="no-results" class="text-center text-muted mt-4" style="display:none;">No breeds match your filter.</p>

</main>

<!-- Breed profile modal -->
<div class="modal fade" id="breedModal" tabindex="-1" aria-labelledby="breedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px; overflow:hidden;">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5 fw-bold" id="breedModalLabel"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="modal-photo-placeholder" class="modal-photo-placeholder">🐕</div>
                <img id="modal-photo" class="modal-photo" src="" alt="" style="display:none;">
                <div id="modal-photo-hint" class="modal-photo-hint" style="display:none;">Tap photo to expand full size</div>
                <div class="info-row">
                    <div class="info-label">Group</div>
                    <div class="info-value" id="modal-group"></div>
                </div>
                <div class="info-row" id="modal-temperament-wrap">
                    <div class="info-label">Temperament</div>
                    <div class="info-value" id="modal-temperament"></div>
                </div>
                <div class="info-row" id="modal-traits-wrap">
                    <div class="info-label">Traits</div>
                    <div class="info-value" id="modal-traits"></div>
                </div>
                <div class="info-row" id="modal-notes-wrap">
                    <div class="info-label">Notes</div>
                    <div class="info-value" id="modal-notes"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-start">
                <a href="breed_questionnaire.php" class="btn btn-primary btn-sm fw-bold">Find with Breed Finder</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Full-size lightbox -->
<div class="photo-lightbox" id="photo-lightbox" role="dialog" aria-modal="true" aria-label="Full size breed photo">
    <button class="photo-lightbox-close" id="lightbox-close" aria-label="Close">&times;</button>
    <img id="lightbox-img" src="" alt="">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const photosEnabled = <?= $photosEnabled ? 'true' : 'false' ?>;
    const breedData     = <?= json_encode($breedData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const breedNames    = <?= json_encode(array_keys($mappedBreeds), JSON_HEX_TAG) ?>;

    const grid      = document.getElementById('breed-grid');
    const search    = document.getElementById('breed-search');
    const countEl   = document.getElementById('gallery-count');
    const noResults = document.getElementById('no-results');
    const modal     = new bootstrap.Modal(document.getElementById('breedModal'));
    const chips     = document.querySelectorAll('.group-chip');

    let activeGroup = 'all';

    // ── Filtering ────────────────────────────────────────────────────────────
    function applyFilter() {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        grid.querySelectorAll('.breed-card').forEach(function (card) {
            const name  = (card.dataset.breed  || '').toLowerCase();
            const group = (card.dataset.group  || '');
            const matchGroup  = activeGroup === 'all' || group === activeGroup;
            const matchSearch = q === '' || name.includes(q);
            const hide = !matchGroup || !matchSearch;
            card.classList.toggle('breed-card--hidden', hide);
            if (!hide) shown++;
        });
        countEl.textContent = shown + ' breed' + (shown !== 1 ? 's' : '') + ' shown';
        noResults.style.display = shown === 0 ? '' : 'none';
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            activeGroup = chip.dataset.group;
            applyFilter();
        });
    });
    search.addEventListener('input', applyFilter);

    // ── Lazy photo loading ────────────────────────────────────────────────────
    function loadPhoto(card) {
        if (card.dataset.photoLoaded !== 'false' || !photosEnabled) return;
        card.dataset.photoLoaded = 'pending';
        const breed = card.dataset.breed;
        fetch('/api/breed_photo.php?breed=' + encodeURIComponent(breed))
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                card.dataset.photoLoaded = 'done';
                card.dataset.photoUrl = (data && data.url) ? data.url : '';
                if (data && data.url) {
                    const wrap = card.querySelector('.breed-photo-wrap');
                    const placeholder = wrap.querySelector('.breed-placeholder');
                    const img = document.createElement('img');
                    img.src = data.url;
                    img.alt = breed;
                    img.loading = 'lazy';
                    img.onerror = function () { img.remove(); };
                    wrap.appendChild(img);
                    if (placeholder) placeholder.style.display = 'none';
                }
            })
            .catch(function () { card.dataset.photoLoaded = 'done'; });
    }

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                loadPhoto(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '200px' });

    grid.querySelectorAll('.breed-card').forEach(function (card) {
        observer.observe(card);
    });

    // ── Modal ─────────────────────────────────────────────────────────────────
    function openModal(card) {
        const breed = card.dataset.breed;
        const info  = breedData[breed] || {};
        const url   = card.dataset.photoUrl || '';

        document.getElementById('breedModalLabel').textContent = breed;
        document.getElementById('modal-group').textContent = info.group || '—';

        const tempEl = document.getElementById('modal-temperament');
        const tempWrap = document.getElementById('modal-temperament-wrap');
        tempEl.textContent = info.temperament || '';
        tempWrap.style.display = info.temperament ? '' : 'none';

        const traitsEl = document.getElementById('modal-traits');
        const traitsWrap = document.getElementById('modal-traits-wrap');
        traitsEl.textContent = info.traits || '';
        traitsWrap.style.display = info.traits ? '' : 'none';

        const notesEl = document.getElementById('modal-notes');
        const notesWrap = document.getElementById('modal-notes-wrap');
        notesEl.textContent = info.notes || '';
        notesWrap.style.display = info.notes ? '' : 'none';

        const modalPhoto = document.getElementById('modal-photo');
        const modalPlaceholder = document.getElementById('modal-photo-placeholder');

        if (url) {
            modalPhoto.src = url;
            modalPhoto.alt = breed;
            modalPhoto.style.display = '';
            modalPlaceholder.style.display = 'none';
        } else {
            modalPhoto.style.display = 'none';
            modalPlaceholder.style.display = '';
        }

        modal.show();
    }

    grid.addEventListener('click', function (e) {
        const card = e.target.closest('.breed-card');
        if (!card) return;
        if (card.dataset.photoLoaded === 'false' || card.dataset.photoLoaded === 'pending') {
            loadPhoto(card);
            // Small wait for photo to settle before opening modal
            setTimeout(function () { openModal(card); }, 200);
        } else {
            openModal(card);
        }
    });
    grid.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            const card = e.target.closest('.breed-card');
            if (card) { e.preventDefault(); openModal(card); }
        }
    });

    // ── Lightbox ──────────────────────────────────────────────────────────────
    const lightbox      = document.getElementById('photo-lightbox');
    const lightboxImg   = document.getElementById('lightbox-img');
    const lightboxClose = document.getElementById('lightbox-close');

    function openLightbox(src, alt) {
        lightboxImg.src = src;
        lightboxImg.alt = alt || '';
        lightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        lightbox.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('modal-photo').addEventListener('click', function () {
        if (this.src) openLightbox(this.src, this.alt);
    });
    lightbox.addEventListener('click', function (e) {
        if (e.target !== lightboxImg) closeLightbox();
    });
    lightboxClose.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLightbox();
    });

    // ── Lightbox pinch-to-zoom ────────────────────────────────────────────────
    (function () {
        var lbImg = document.getElementById('lightbox-img');
        if (!lbImg) return;
        var lbScale = 1, lbTX = 0, lbTY = 0, lbLastDist = null, lbLastX = null, lbLastY = null;

        function lbApply() {
            lbImg.style.transform = 'scale(' + lbScale + ') translate(' + lbTX / lbScale + 'px,' + lbTY / lbScale + 'px)';
        }
        function lbReset() { lbScale = 1; lbTX = 0; lbTY = 0; lbImg.style.transform = ''; }
        function lbDist(e) {
            var dx = e.touches[0].clientX - e.touches[1].clientX, dy = e.touches[0].clientY - e.touches[1].clientY;
            return Math.sqrt(dx * dx + dy * dy);
        }

        lbImg.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) { lbLastDist = lbDist(e); }
            else if (e.touches.length === 1) { lbLastX = e.touches[0].clientX; lbLastY = e.touches[0].clientY; }
        }, { passive: true });

        lbImg.addEventListener('touchmove', function (e) {
            e.preventDefault();
            if (e.touches.length === 2) {
                var d = lbDist(e);
                lbScale = Math.max(1, Math.min(6, lbScale * (d / lbLastDist)));
                lbLastDist = d;
                lbApply();
            } else if (e.touches.length === 1 && lbScale > 1) {
                lbTX += e.touches[0].clientX - lbLastX;
                lbTY += e.touches[0].clientY - lbLastY;
                lbLastX = e.touches[0].clientX;
                lbLastY = e.touches[0].clientY;
                lbApply();
            }
        }, { passive: false });

        lbImg.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) lbLastDist = null;
            if (e.touches.length === 0 && lbScale <= 1) lbReset();
        });

        // Reset zoom when lightbox closes
        var lb = document.getElementById('photo-lightbox');
        if (lb) {
            var observer = new MutationObserver(function () { if (!lb.classList.contains('open')) lbReset(); });
            observer.observe(lb, { attributes: true, attributeFilter: ['class'] });
        }

        // Mouse wheel zoom for desktop
        lbImg.addEventListener('wheel', function (e) {
            e.preventDefault();
            lbScale = Math.max(1, Math.min(6, lbScale * (e.deltaY < 0 ? 1.15 : 0.87)));
            if (lbScale <= 1) lbReset(); else lbApply();
        }, { passive: false });
    }());

    // ── Admin pre-fetch ───────────────────────────────────────────────────────
    const prefetchBtn = document.getElementById('prefetch-btn');
    if (prefetchBtn) {
        prefetchBtn.addEventListener('click', async function () {
            prefetchBtn.disabled = true;
            const status   = document.getElementById('prefetch-status');
            const barWrap  = document.getElementById('prefetch-bar-wrap');
            const fill     = document.getElementById('prefetch-fill');
            const total    = breedNames.length;
            barWrap.style.display = '';
            let done = 0;
            for (const breed of breedNames) {
                try {
                    await fetch('/api/breed_photo.php?breed=' + encodeURIComponent(breed));
                } catch (_) {}
                done++;
                fill.style.width = Math.round((done / total) * 100) + '%';
                status.textContent = 'Fetching ' + done + ' / ' + total + ' — ' + breed;
                // Re-render any visible card that now has a photo
                const card = grid.querySelector('[data-breed="' + CSS.escape(breed) + '"]');
                if (card && card.dataset.photoLoaded === 'false') loadPhoto(card);
            }
            status.textContent = '✓ All ' + total + ' breeds cached. Reload any card to see new photos.';
            prefetchBtn.textContent = 'Done';
        });
    }

    // ── AI photo analysis ─────────────────────────────────────────────────────
    const analyzeBtn    = document.getElementById('analyze-btn');
    const analyzeStatus = document.getElementById('analyze-status');
    if (analyzeBtn) {
        analyzeBtn.addEventListener('click', async function runBatch() {
            analyzeBtn.disabled = true;
            analyzeStatus.textContent = 'Analyzing…';
            let totalAnalyzed = 0, totalImproved = 0;

            while (true) {
                let data;
                try {
                    const res = await fetch('/api/breed_photo_analyze.php?limit=10');
                    data = await res.json();
                } catch (e) {
                    analyzeStatus.textContent = 'Error: ' + e.message;
                    break;
                }
                if (!data.success) { analyzeStatus.textContent = 'Error: ' + (data.message || 'unknown'); break; }
                totalAnalyzed += data.analyzed || 0;
                totalImproved += (data.results || []).filter(function (r) { return r.improved; }).length;
                analyzeStatus.textContent = totalAnalyzed + ' scored, ' + totalImproved + ' improved — ' + (data.remaining || 0) + ' remaining';
                if (!data.remaining || data.remaining <= 0 || data.analyzed === 0) break;
            }

            analyzeStatus.textContent = '✓ Done — ' + totalAnalyzed + ' scored, ' + totalImproved + ' photos improved. Reload to see updates.';
            analyzeBtn.textContent = 'Done';
        });
    }
}());
</script>
</body>
</html>
