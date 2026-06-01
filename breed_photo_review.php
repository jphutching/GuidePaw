<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

checkLogin();
if (!gpCurrentUserIsAdmin($pdo)) {
    header('Location: index.php');
    exit;
}

$flagged = $pdo->query(
    "SELECT breed_name, verification_score, verification_notes, image_url, source, photo_locked
     FROM breed_images
     WHERE verification_score < 55 AND verified_at IS NOT NULL
     ORDER BY verification_score, breed_name"
)->fetchAll(PDO::FETCH_ASSOC);

$title = 'Breed Photo Review | GuidePaw Admin';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<?php guidepawSeoHead(['title' => $title, 'robots' => 'noindex']); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8fafc; }
.review-card { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
.photo-grid { display: grid; grid-template-columns: 200px 1fr; gap: 1rem; align-items: start; }
.photo-box { position: relative; }
.photo-box img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; border: 2px solid #e2e8f0; background: #f0f4f8; display: block; }
.photo-box .photo-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-top: .3rem; text-align: center; }
.alts-row { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .75rem; }
.alt-box { position: relative; width: 150px; flex-shrink: 0; }
.alt-box img { width: 150px; height: 150px; object-fit: cover; border-radius: 10px; border: 2px solid #e2e8f0; cursor: pointer; background: #f0f4f8; }
.alt-box img:hover { border-color: #0d6efd; }
.alt-box .use-btn { display: block; margin-top: .3rem; width: 100%; font-size: .72rem; }
.alt-box .src-tag { font-size: .65rem; color: #94a3b8; text-align: center; }
.score-badge { font-size: .8rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; color: #fff; }
.score-badge.high   { background: #22c55e; }
.score-badge.mid    { background: #f59e0b; }
.score-badge.low    { background: #ef4444; }
.loading-alts { color: #94a3b8; font-size: .85rem; padding: .75rem 0; }
.kept-banner { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .4rem .75rem; font-size: .8rem; color: #166534; display: none; }
@media (max-width: 600px) {
    .photo-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php guidepawBrandHeader('Breed Photo Review', null, []); ?>

<main class="container" style="max-width:860px; padding: 1.5rem 1rem;">

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <div>
        <h1 class="h4 fw-bold mb-0">Breed Photo Review</h1>
        <div class="text-muted small"><?= count($flagged) ?> breeds flagged by AKC verification — review and pick replacements</div>
    </div>
    <a href="breed_gallery.php" class="btn btn-outline-secondary btn-sm ms-auto">← Gallery</a>
</div>

<?php if (empty($flagged)): ?>
    <div class="alert alert-success">No flagged breeds — all photos passed verification!</div>
<?php else: ?>
    <div class="alert alert-info small mb-4">
        Click any replacement photo to preview it full-size. Click <strong>Use this</strong> to save and lock it.
        Click <strong>Keep &amp; lock current</strong> if the existing photo is actually fine.
    </div>

    <?php foreach ($flagged as $row): ?>
    <?php
        $score     = (int) $row['verification_score'];
        $badgeClass = $score < 30 ? 'low' : ($score < 55 ? 'mid' : 'high');
        $breedEnc  = e($row['breed_name']);
        $breedJs   = json_encode($row['breed_name']);
        $currentUrl = e($row['image_url']);
        $skipJs    = json_encode($row['image_url']);
        $locked    = !empty($row['photo_locked']);
    ?>
    <div class="review-card" id="card-<?= e(preg_replace('/[^a-z0-9]/i', '-', $row['breed_name'])) ?>">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <span class="fw-bold"><?= $breedEnc ?></span>
            <span class="score-badge <?= $badgeClass ?>"><?= $score ?>/100</span>
            <?php if ($locked): ?><span class="badge bg-warning text-dark">🔒 locked</span><?php endif; ?>
            <span class="text-muted small flex-grow-1"><?= e((string)$row['verification_notes']) ?></span>
            <button class="btn btn-outline-success btn-sm keep-btn" data-breed=<?= $breedJs ?>>Keep &amp; lock current</button>
        </div>

        <div class="photo-grid">
            <div class="photo-box">
                <img src="<?= $currentUrl ?>" alt="<?= $breedEnc ?> current"
                     onerror="this.src=''; this.style.background='#fee2e2'; this.alt='Load failed'">
                <div class="photo-label">Current (flagged)</div>
            </div>
            <div>
                <div class="loading-alts" id="loading-<?= e(preg_replace('/[^a-z0-9]/i', '-', $row['breed_name'])) ?>">
                    Loading alternatives…
                </div>
                <div class="alts-row" id="alts-<?= e(preg_replace('/[^a-z0-9]/i', '-', $row['breed_name'])) ?>"></div>
            </div>
        </div>
        <div class="kept-banner mt-2" id="kept-<?= e(preg_replace('/[^a-z0-9]/i', '-', $row['breed_name'])) ?>">
            ✓ Photo kept and locked — won't be flagged again.
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const flagged = <?= json_encode(array_map(function($r) {
    return ['breed' => $r['breed_name'], 'skip' => $r['image_url']];
}, $flagged), JSON_HEX_TAG) ?>;

function cardId(breed) {
    return breed.replace(/[^a-z0-9]/gi, '-');
}

function sourceLabel(src) {
    return {'wikipedia':'Wikipedia','dog_ceo':'Dog CEO','unsplash':'Unsplash','pexels':'Pexels'}[src] || src;
}

async function loadAlts(breed, skipUrl) {
    const id      = cardId(breed);
    const loading = document.getElementById('loading-' + id);
    const row     = document.getElementById('alts-'    + id);
    if (!loading || !row) return;

    let data;
    try {
        const res = await fetch('/api/breed_photo_alternatives.php?breed=' + encodeURIComponent(breed) + '&skip=' + encodeURIComponent(skipUrl));
        data = await res.json();
    } catch (e) {
        loading.textContent = 'Error loading alternatives.';
        return;
    }
    loading.style.display = 'none';

    const alts = data.alternatives || [];
    if (alts.length === 0) {
        row.innerHTML = '<span class="text-muted small">No alternative photos found.</span>';
        return;
    }

    alts.forEach(function(alt) {
        const box = document.createElement('div');
        box.className = 'alt-box';
        box.innerHTML =
            '<img src="' + alt.url + '" alt="' + breed + ' alternative" ' +
                 'onerror="this.parentElement.style.display=\'none\'" ' +
                 'onclick="window.open(\'' + alt.url.replace(/'/g, "\\'") + '\',\'_blank\')" ' +
                 'title="Click to open full size">' +
            '<div class="src-tag">' + sourceLabel(alt.source) + '</div>' +
            '<button class="btn btn-primary use-btn btn-sm" ' +
                    'data-breed="' + breed.replace(/"/g, '&quot;') + '" ' +
                    'data-url="' + alt.url.replace(/"/g, '&quot;') + '">Use this</button>';
        row.appendChild(box);
    });
}

// Use this button
document.addEventListener('click', async function(e) {
    if (!e.target.classList.contains('use-btn')) return;
    const btn   = e.target;
    const breed = btn.dataset.breed;
    const url   = btn.dataset.url;
    btn.disabled = true;
    btn.textContent = 'Saving…';
    try {
        const res  = await fetch('/api/breed_photo_set.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ breed, url })
        });
        const data = await res.json();
        if (data.success) {
            const id   = cardId(breed);
            const card = document.getElementById('card-' + id);
            // Update the current photo
            const curImg = card.querySelector('.photo-grid .photo-box img');
            if (curImg) { curImg.src = url; }
            card.querySelector('.photo-label').textContent = 'Updated ✓ (locked)';
            // Show kept banner
            const banner = document.getElementById('kept-' + id);
            if (banner) { banner.style.display = ''; banner.textContent = '✓ New photo saved and locked.'; }
            btn.textContent = '✓ Saved';
        } else {
            btn.disabled = false;
            btn.textContent = 'Use this';
            alert('Error: ' + (data.message || 'unknown'));
        }
    } catch (err) {
        btn.disabled = false;
        btn.textContent = 'Use this';
        alert('Error: ' + err.message);
    }
});

// Keep & lock current
document.addEventListener('click', async function(e) {
    if (!e.target.classList.contains('keep-btn')) return;
    const btn   = e.target;
    const breed = btn.dataset.breed;
    btn.disabled = true;
    try {
        const res  = await fetch('/api/breed_photo_lock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ breed, locked: true })
        });
        const data = await res.json();
        if (data.success) {
            const id     = cardId(breed);
            const banner = document.getElementById('kept-' + id);
            if (banner) { banner.style.display = ''; }
            btn.textContent = '✓ Locked';
        } else {
            btn.disabled = false;
            alert('Error: ' + (data.message || 'unknown'));
        }
    } catch (err) {
        btn.disabled = false;
        alert('Error: ' + err.message);
    }
});

// Lazy-load alternatives as cards scroll into view
const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (!entry.isIntersecting) return;
        const card  = entry.target;
        const breed = card.dataset.breed;
        const skip  = card.dataset.skip;
        if (breed) loadAlts(breed, skip);
        observer.unobserve(card);
    });
}, { rootMargin: '300px' });

flagged.forEach(function(f) {
    const id   = cardId(f.breed);
    const card = document.getElementById('card-' + id);
    if (card) {
        card.dataset.breed = f.breed;
        card.dataset.skip  = f.skip;
        observer.observe(card);
    }
});
</script>
</body>
</html>
