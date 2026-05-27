<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/changelog.php';

$entries = gpChangelogEntries();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php guidepawSeoHead([
    'title'       => 'GuidePaw Companion — What\'s New',
    'description' => 'Full release history for the GuidePaw Companion Android app.',
    'robots'      => 'index,follow',
    'canonical'   => guidepawSeoAbsoluteUrl('/changelog.php'),
    'image'       => '/assets/brand/guidepaw-logo.png',
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f1f5f9; color:#0f172a; }
    .page-shell { max-width: 860px; margin: 0 auto; padding: 1rem 1rem 3rem; }
    .hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
    .panel { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:20px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
    .label { font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; font-weight:800; color:#64748b; }
    .muted { color:#64748b; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.4rem .75rem; background:rgba(255,255,255,.12); font-weight:700; }
    .version-badge { font-size:.75rem; font-weight:800; color:#0d6efd; background:#e8f1ff; border-radius:999px; padding:.2rem .65rem; }
    .entry + .entry { border-top: 1px solid #e2e8f0; margin-top: 1.25rem; padding-top: 1.25rem; }
    .entry-title { font-size:1rem; font-weight:700; margin-bottom:.35rem; }
    .entry-items { margin:0; padding-left:1.1rem; color:#334155; }
    .entry-items li { font-size:.93rem; margin-bottom:.2rem; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="pill mb-3">Release Notes</div>
        <h1 class="display-6 fw-bold mb-2">What's New</h1>
        <p class="lead mb-0">GuidePaw Companion — full release history.</p>
    </section>

    <section class="panel p-4">
        <?php foreach ($entries as $i => $entry): ?>
        <div class="entry <?= $i === 0 ? 'first' : '' ?>">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <span class="version-badge">v<?= htmlspecialchars($entry['version']) ?></span>
                <span class="muted" style="font-size:.83rem;"><?= htmlspecialchars($entry['date']) ?></span>
            </div>
            <div class="entry-title"><?= htmlspecialchars($entry['title']) ?></div>
            <ul class="entry-items">
                <?php foreach ($entry['items'] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </section>

    <div class="mt-4 text-center">
        <a href="faq.php" class="btn btn-outline-secondary btn-sm">← Back to FAQ</a>
        <a href="app.php" class="btn btn-primary btn-sm ms-2">Get the app</a>
    </div>
</main>
</body>
</html>
