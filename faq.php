<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'GuidePaw FAQ | Breed Research, Support, and Public Access';
$description = 'Answers to common GuidePaw questions about breed research, public profiles, support options, and how the app fits into handler work.';
$faqSchema = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What is GuidePaw?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'GuidePaw is a handler-focused dog training and profile tool for people who want to keep logs, public contact details, and support tools in one place.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Do I need an account to use the public breed tools?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. The breed questionnaire, comparison content, and other public pages are readable without signing in.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'How do I start choosing a breed?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Start with the public breed questionnaire, compare a few related breeds, and then narrow by size, public-access needs, grooming, and the kind of work you need.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'How is support different from the free core?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'The free core stays available for the first handler and first dog. Support and add-ons are optional extras that help fund GuidePaw and unlock separate services.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'What do public dog profiles show?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Public dog profiles show only the contact and public notes you choose to share, plus the found-dog reporting flow. They do not expose private logs or account history.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Does GuidePaw replace ADA.gov or airline rules?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. GuidePaw is a practical organizer and reference tool, but you should still rely on official ADA, HUD, DOT, and airline guidance for final rules.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Where do housing and access disputes fit?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Housing requests are different from public access. Use the public housing and access FAQ for a plain-language summary, then verify the details with HUD and ADA guidance.',
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
    'canonical' => guidepawSeoAbsoluteUrl('/faq.php'),
    'image' => '/assets/brand/guidepaw-logo.png',
    'json_ld' => $faqSchema,
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f1f5f9; color:#0f172a; }
    .page-shell { max-width: 980px; margin: 0 auto; padding: 1rem 1rem 3rem; }
    .hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
    .panel { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:20px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
    .label { font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; font-weight:800; color:#64748b; }
    .muted { color:#64748b; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.4rem .75rem; background:rgba(255,255,255,.12); font-weight:700; }
    .faq-item + .faq-item { margin-top: .75rem; }
    .faq-q { font-size: 1.05rem; font-weight: 800; margin-bottom: .35rem; }
    .faq-a { color:#334155; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:700px;">
                <div class="pill mb-3">Public FAQ</div>
                <h1 class="display-6 fw-bold mb-3">GuidePaw FAQ</h1>
                <p class="lead mb-0">Straight answers about breed research, public profiles, support, and what the app does for handlers.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:320px;">
                <div class="label">Start here</div>
                <h2 class="h5 mb-2">Use the public tools first</h2>
                <p class="muted mb-3">Research a breed, compare a few related options, then create an account when you are ready.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Open Breed Questionnaire</a>
                    <a class="btn btn-outline-primary fw-bold" href="breed_comparison.php">Compare breeds</a>
                </div>
            </div>
        </div>
    </section>

    <section class="panel p-4 mb-4">
        <div class="label mb-2">Questions people actually ask</div>
        <div class="faq-item">
            <div class="faq-q">What is GuidePaw?</div>
            <div class="faq-a">GuidePaw is a handler-focused dog training and profile tool for people who want to keep logs, public contact details, and support tools in one place.</div>
        </div>
        <div class="faq-item">
            <div class="faq-q">Do I need an account to use the public breed tools?</div>
            <div class="faq-a">No. The breed questionnaire, comparison content, and other public pages are readable without signing in.</div>
        </div>
        <div class="faq-item">
            <div class="faq-q">How do I start choosing a breed?</div>
            <div class="faq-a">Start with the public breed questionnaire, compare a few related breeds, and then narrow by size, public-access needs, grooming, and the kind of work you need.</div>
        </div>
        <div class="faq-item">
            <div class="faq-q">How is support different from the free core?</div>
            <div class="faq-a">The free core stays available for the first handler and first dog. Support and add-ons are optional extras that help fund GuidePaw and unlock separate services.</div>
        </div>
        <div class="faq-item">
            <div class="faq-q">What do public dog profiles show?</div>
            <div class="faq-a">Public dog profiles show only the contact and public notes you choose to share, plus the found-dog reporting flow. They do not expose private logs or account history.</div>
        </div>
        <div class="faq-item">
            <div class="faq-q">Does GuidePaw replace ADA.gov or airline rules?</div>
            <div class="faq-a">No. GuidePaw is a practical organizer and reference tool, but you should still rely on official ADA, HUD, DOT, and airline guidance for final rules.</div>
        </div>
        <div class="faq-item">
            <div class="faq-q">Where do housing and access disputes fit?</div>
            <div class="faq-a">Housing requests are different from public access. Use the public housing and access FAQ for a plain-language summary, then verify the details with HUD and ADA guidance.</div>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-md-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Public pages</div>
                <h2 class="h4">Useful starting points</h2>
                <ul class="mb-0">
                    <li><a href="breed_questionnaire.php">Breed Questionnaire</a></li>
                    <li><a href="breed_comparison.php">Breed Comparison</a></li>
                    <li><a href="service_dog_esa_legal_info.php">Service Dog &amp; ESA Legal Info</a></li>
                    <li><a href="housing_access_faq.php">Housing &amp; Access FAQ</a></li>
                    <li><a href="support_funding.php">Support GuidePaw</a></li>
                    <li><a href="service_dog_rights.php">Service Dog Rights</a></li>
                    <li><a href="air_travel_rights.php">Air Travel Rights</a></li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Need more detail?</div>
                <h2 class="h4">Use the product pages</h2>
                <p class="mb-0">The public FAQ is for search and quick orientation. Once you know what you need, the dashboard and profile tools handle the actual work.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
