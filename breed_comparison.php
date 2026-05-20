<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'Cavalier King Charles Spaniel vs English Toy Spaniel | GuidePaw';
$description = 'Compare the Cavalier King Charles Spaniel and English Toy Spaniel by size, temperament, grooming, and fit before you choose a companion breed.';
$schema = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $title,
        'description' => $description,
        'author' => [
            '@type' => 'Organization',
            'name' => appShortName(),
        ],
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
                'name' => 'Are Cavalier King Charles Spaniels and English Toy Spaniels the same breed?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. They are separate breeds with similar names and related toy-group history.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Is King Charles Spaniel the same as Cavalier King Charles Spaniel?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. The name King Charles Spaniel usually points people toward English Toy Spaniel, while Cavalier King Charles Spaniel is a different breed.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Which one is easier for public access work?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Neither is automatically better. Temperament, adult size, health, and training matter more than breed name alone.',
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
    'canonical' => guidepawSeoAbsoluteUrl('/breed_comparison.php'),
    'image' => '/assets/brand/guidepaw-logo.png',
    'json_ld' => $schema,
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f1f5f9; color:#0f172a; }
    .page-shell { max-width: 1120px; margin: 0 auto; padding: 1rem 1rem 3rem; }
    .hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
    .panel { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:20px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
    .label { font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; font-weight:800; color:#64748b; }
    .value { font-weight:700; }
    .muted { color:#64748b; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.4rem .75rem; background:rgba(255,255,255,.12); font-weight:700; }
    .callout { border-left:4px solid #0d6efd; background:#eff6ff; border-radius:16px; padding:1rem; }
    .table-responsive { border:1px solid rgba(15,23,42,.08); border-radius:18px; overflow:hidden; background:#fff; }
    table { margin-bottom:0; }
    th { background:#eef2ff; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:720px;">
                <div class="pill mb-3">Public breed comparison</div>
                <h1 class="display-6 fw-bold mb-3">Cavalier King Charles Spaniel vs English Toy Spaniel</h1>
                <p class="lead mb-0">These names are easy to mix up. This page separates the breeds so people can compare size, coat, temperament, and companion fit before they choose a dog.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:360px;">
                <div class="label">Best next step</div>
                <h2 class="h5 mb-2">Use the questionnaire after you compare</h2>
                <p class="muted mb-3">A breed comparison is a starting point. The public questionnaire helps narrow by size, family, grooming, and work fit.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Open Breed Questionnaire</a>
                    <a class="btn btn-outline-primary fw-bold" href="faq.php">Read GuidePaw FAQ</a>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Quick take</div>
                <h2 class="h4">The short version</h2>
                <ul class="mb-0">
                    <li><strong>Cavalier King Charles Spaniel:</strong> slightly larger toy companion breed, usually more common in modern family settings, with an easygoing and affectionate profile.</li>
                    <li><strong>English Toy Spaniel:</strong> smaller and more reserved, often a quieter indoor companion with a flatter face and a more traditional toy-spaniel look.</li>
                    <li><strong>King Charles Spaniel:</strong> in common search behavior, this often points people toward English Toy Spaniel rather than Cavalier.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Fit reminder</div>
                <h2 class="h4">What matters most</h2>
                <p class="mb-0">Neither breed is automatically a service prospect. Temperament, adult size, health testing, socialization, and the work you actually need matter more than the name on the registration paper.</p>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="label mb-2">Side by side</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:22%">Topic</th>
                        <th>Cavalier King Charles Spaniel</th>
                        <th>English Toy Spaniel</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="value">Approx. size</td>
                        <td>Usually around 13-18 lb, with a slightly more athletic outline for a toy companion.</td>
                        <td>Usually around 8-14 lb, with a smaller and more compact build.</td>
                    </tr>
                    <tr>
                        <td class="value">Temperament</td>
                        <td>Affectionate, adaptable, and people-oriented.</td>
                        <td>Gentle, calm, and often a little more reserved.</td>
                    </tr>
                    <tr>
                        <td class="value">Grooming</td>
                        <td>Regular brushing and coat care, especially around ears and feathering.</td>
                        <td>Regular brushing with extra attention to the face, ears, and coat matting.</td>
                    </tr>
                    <tr>
                        <td class="value">Public access fit</td>
                        <td>Can suit some homes and companion roles if health, size, and temperament line up.</td>
                        <td>Can suit calm companion work, but the smaller frame and flatter face deserve careful evaluation.</td>
                    </tr>
                    <tr>
                        <td class="value">Watch for</td>
                        <td>Health testing, heat tolerance, and the individual puppy’s temperament.</td>
                        <td>Brachycephalic concerns, health screening, and very small-dog handling needs.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Why the names confuse people</div>
                <h2 class="h4">King Charles naming history</h2>
                <p class="mb-0">Searchers often use <em>King Charles Spaniel</em> as a shorthand for the smaller English Toy Spaniel. Cavalier King Charles Spaniel is a different, separate breed. That distinction matters when you are comparing size and companion fit.</p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Decision helper</div>
                <h2 class="h4">Choose by fit, not just name</h2>
                <p class="mb-3">If you are still early in the decision, compare breed family, size, public-work tolerance, and grooming first. Then move into the questionnaire to filter by your actual household and work needs.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary fw-bold" href="breed_questionnaire.php">Research a breed</a>
                    <a class="btn btn-outline-secondary fw-bold" href="faq.php">FAQ</a>
                </div>
            </div>
        </div>
    </section>

    <section class="panel p-4">
        <div class="label mb-2">Common questions</div>
        <div class="accordion accordion-flush" id="comparisonFaq">
            <div class="accordion-item">
                <h2 class="accordion-header" id="cmpQ1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cmpA1" aria-expanded="false" aria-controls="cmpA1">
                        Which breed is easier for beginners?
                    </button>
                </h2>
                <div id="cmpA1" class="accordion-collapse collapse" aria-labelledby="cmpQ1" data-bs-parent="#comparisonFaq">
                    <div class="accordion-body">Neither breed is guaranteed to be easy. The best beginner choice is the puppy or adult that matches your schedule, training style, and grooming tolerance.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="cmpQ2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cmpA2" aria-expanded="false" aria-controls="cmpA2">
                        Are these good family dogs?
                    </button>
                </h2>
                <div id="cmpA2" class="accordion-collapse collapse" aria-labelledby="cmpQ2" data-bs-parent="#comparisonFaq">
                    <div class="accordion-body">They can be, if the dog is healthy, socialized, and matched to the family’s noise, handling, and daily routine. Temperament varies by individual dog.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="cmpQ3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cmpA3" aria-expanded="false" aria-controls="cmpA3">
                        What should I do next?
                    </button>
                </h2>
                <div id="cmpA3" class="accordion-collapse collapse" aria-labelledby="cmpQ3" data-bs-parent="#comparisonFaq">
                    <div class="accordion-body">Open the public questionnaire, set your size and public-access needs, then use the results to narrow down family and breed names that fit.</div>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
