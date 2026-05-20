<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'GuidePaw Breed Comparison Hub | Guide Paw Breed Research';
$description = 'Browse real breed comparisons for GuidePaw, including retriever, poodle, corgi, herding, and compact companion comparisons.';
$comparisons = [
    ['href' => 'breed_comparison.php', 'title' => 'Cavalier King Charles Spaniel vs English Toy Spaniel', 'body' => 'Clear up the King Charles naming confusion and compare the toy spaniel pair.'],
    ['href' => 'breed_compare_labrador_golden.php', 'title' => 'Labrador Retriever vs Golden Retriever', 'body' => 'Compare the two most searched retriever choices.'],
    ['href' => 'breed_compare_standard_toy_poodle.php', 'title' => 'Standard Poodle vs Toy Poodle', 'body' => 'Compare the size split inside the Poodle family.'],
    ['href' => 'breed_compare_aussie_mini_american_shepherd.php', 'title' => 'Australian Shepherd vs Miniature American Shepherd', 'body' => 'Compare the larger and smaller herding options.'],
    ['href' => 'breed_compare_pembroke_cardigan_corgi.php', 'title' => 'Pembroke Welsh Corgi vs Cardigan Welsh Corgi', 'body' => 'Compare the two corgi breeds without mixing them up.'],
    ['href' => 'breed_compare_french_bulldog_boston_terrier.php', 'title' => 'French Bulldog vs Boston Terrier', 'body' => 'Compare two compact companion breeds with health caveats.'],
];

$schema = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $title,
        'description' => $description,
        'url' => guidepawSeoAbsoluteUrl('/breed_comparison_hub.php'),
        'publisher' => [
            '@type' => 'Organization',
            'name' => appShortName(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => guidepawSeoAbsoluteUrl('/assets/brand/guidepaw-logo.png'),
            ],
        ],
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($comparisons),
            'itemListElement' => array_map(static function (array $item, int $index): array {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => guidepawSeoAbsoluteUrl('/' . ltrim((string) $item['href'], '/')),
                    'name' => (string) $item['title'],
                ];
            }, $comparisons, array_keys($comparisons)),
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
                    'text' => 'This page collects the public breed comparison articles in one place so searchers can browse by breed family.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Why does GuidePaw show comparison pages?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'People often start with breed research before they create an account. Real comparison pages are more useful than a blank keyword page.',
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
    'canonical' => guidepawSeoAbsoluteUrl('/breed_comparison_hub.php'),
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
.comp-card { border:1px solid rgba(15,23,42,.08); border-radius:18px; background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.07); height:100%; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:720px;">
                <div class="pill mb-3">Breed comparison hub</div>
                <h1 class="display-6 fw-bold mb-3">GuidePaw Breed Comparison Hub</h1>
                <p class="lead mb-0">Browse real comparisons before you choose a dog. This hub keeps the public pages in one place and gives search engines a clear path into the content.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:360px;">
                <div class="small text-uppercase text-muted fw-semibold">Best next step</div>
                <h2 class="h5 mb-2">Open the questionnaire after you compare</h2>
                <p class="muted mb-3">Comparison pages help you narrow the field. The questionnaire helps you filter by size, grooming, public access, and work fit.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Open Breed Questionnaire</a>
                    <a class="btn btn-outline-primary fw-bold" href="faq.php">Read GuidePaw FAQ</a>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="row g-3">
            <?php foreach ($comparisons as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <a class="comp-card text-decoration-none text-dark d-block p-3 h-100" href="<?= e((string) $item['href']) ?>">
                        <div class="fw-bold mb-1"><?= e((string) $item['title']) ?></div>
                        <div class="muted small"><?= e((string) $item['body']) ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="small text-uppercase text-muted fw-semibold mb-2">Why this exists</div>
                <h2 class="h4">Use real comparisons instead of a blank SEO page</h2>
                <p class="mb-0">Each article answers a specific breed question that people actually type into search. That makes the content useful to readers and useful to search engines at the same time.</p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="small text-uppercase text-muted fw-semibold mb-2">Brand note</div>
                <h2 class="h4">Guide Paw searchers should land here too</h2>
                <p class="mb-0">We are making the brand signals explicit across the public site so “Guide Paw” and “GuidePaw” both point toward the same homepage, hub pages, and tools.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
