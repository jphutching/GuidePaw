<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'GuidePaw Breed Family Guide | Compare Spaniel, Retriever, Poodle, Corgi, and Herding Breeds';
$description = 'Browse GuidePaw breed families with plain-language notes about spaniels, retrievers, poodles, corgis, herding breeds, and compact companion breeds.';
$families = [
    [
        'name' => 'Spaniel family',
        'summary' => 'Good for handlers who want a smaller, affectionate dog with close attention to family and routine. Watch barking, confidence, grooming, and handling tolerance.',
        'links' => [
            ['href' => 'breed_comparison.php', 'label' => 'Cavalier vs English Toy Spaniel'],
            ['href' => 'breed_comparison_hub.php', 'label' => 'Browse more comparisons'],
        ],
    ],
    [
        'name' => 'Retriever family',
        'summary' => 'Often chosen for trainability, steadiness, and public-neutral temperaments. Watch size, shedding, exercise needs, and heat tolerance.',
        'links' => [
            ['href' => 'breed_compare_labrador_golden.php', 'label' => 'Labrador vs Golden Retriever'],
            ['href' => 'breed_comparison_hub.php', 'label' => 'Browse more comparisons'],
        ],
    ],
    [
        'name' => 'Poodle family',
        'summary' => 'Useful when intelligence and trainability matter, but grooming and coat care are part of the commitment. Compare sizes by fit, not just personality.',
        'links' => [
            ['href' => 'breed_compare_standard_toy_poodle.php', 'label' => 'Standard vs Toy Poodle'],
            ['href' => 'breed_questionnaire.php', 'label' => 'Use the questionnaire'],
        ],
    ],
    [
        'name' => 'Corgi family',
        'summary' => 'Low-to-the-ground herding dogs that can be bright, sturdy, and fun, but may be vocal or stubborn if not handled consistently.',
        'links' => [
            ['href' => 'breed_compare_pembroke_cardigan_corgi.php', 'label' => 'Pembroke vs Cardigan Corgi'],
            ['href' => 'breed_questionnaire.php', 'label' => 'Use the questionnaire'],
        ],
    ],
    [
        'name' => 'Herding family',
        'summary' => 'Good for active teams that want engagement and problem-solving, but they often need structured training and off-switch work.',
        'links' => [
            ['href' => 'breed_compare_aussie_mini_american_shepherd.php', 'label' => 'Australian Shepherd vs Miniature American Shepherd'],
            ['href' => 'breed_questionnaire.php', 'label' => 'Use the questionnaire'],
        ],
    ],
    [
        'name' => 'Compact companion family',
        'summary' => 'A smaller option can work well for travel and close handling, but physical durability and public confidence still matter.',
        'links' => [
            ['href' => 'breed_compare_french_bulldog_boston_terrier.php', 'label' => 'French Bulldog vs Boston Terrier'],
            ['href' => 'breed_questionnaire.php', 'label' => 'Use the questionnaire'],
        ],
    ],
];

$schema = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $title,
        'description' => $description,
        'url' => guidepawSeoAbsoluteUrl('/breed_family_guide.php'),
        'publisher' => [
            '@type' => 'Organization',
            'name' => appShortName(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => guidepawSeoAbsoluteUrl('/assets/brand/guidepaw-logo.png'),
            ],
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What is this page for?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'This page gives a family-level overview before you narrow into a specific comparison or the questionnaire.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Should I use the hub or the questionnaire first?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Use the family guide when you want a broad overview, then use the hub or questionnaire when you want to compare details.',
                ],
            ],
        ],
    ],
];

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php guidepawSeoHead([
    'title' => $title,
    'description' => $description,
    'robots' => 'index,follow',
    'canonical' => guidepawSeoAbsoluteUrl('/breed_family_guide.php'),
    'image' => '/assets/brand/guidepaw-logo.png',
    'json_ld' => $schema,
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f1f5f9; color:#0f172a; }
.page-shell { max-width: 1120px; margin: 0 auto; padding: 1rem 1rem 3rem; }
.hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
.panel { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:20px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
.muted { color:#64748b; }
.pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.4rem .75rem; background:rgba(255,255,255,.12); font-weight:700; }
.family-card { border:1px solid rgba(15,23,42,.08); border-radius:18px; background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.07); height:100%; }
.family-card ul { margin-bottom:0; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:720px;">
                <div class="pill mb-3">Public family guide</div>
                <h1 class="display-6 fw-bold mb-3">GuidePaw Breed Family Guide</h1>
                <p class="lead mb-0">A broader way to compare breed families before you go deeper. Start here if you know the kind of dog you want, but not the exact breed.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:360px;">
                <div class="small text-uppercase text-muted fw-semibold">Next step</div>
                <h2 class="h5 mb-2">Move from family to exact breed</h2>
                <p class="muted mb-3">Use the family guide for the overview, then open the questionnaire or comparison hub to narrow the fit.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Open Breed Questionnaire</a>
                    <a class="btn btn-outline-primary fw-bold" href="breed_comparison_hub.php">Open Comparison Hub</a>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <?php foreach ($families as $family): ?>
            <div class="col-md-6 col-lg-4">
                <div class="family-card p-4 h-100">
                    <h2 class="h5 mb-2"><?= e($family['name']) ?></h2>
                    <p class="muted mb-3"><?= e($family['summary']) ?></p>
                    <div class="d-grid gap-2">
                        <?php foreach ($family['links'] as $link): ?>
                            <a class="btn btn-outline-primary btn-sm" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="row g-3">
        <div class="col-lg-7">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">How to use it</div>
                <h2 class="h4">Compare family traits first</h2>
                <ul>
                    <li>Start with size, grooming, energy, and handling tolerance.</li>
                    <li>Then compare the breeds that sit inside the family you like best.</li>
                    <li>Use the questionnaire when you want the site to rank likely fits for you.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Brand note</div>
                <h2 class="h4">Guide Paw searchers should land here too</h2>
                <p class="mb-0">We are making the brand signals explicit so “Guide Paw” and “GuidePaw” both point toward the same public content and tools.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
