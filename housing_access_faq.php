<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'GuidePaw Housing & Access FAQ | Public Guide';
$description = 'Public GuidePaw FAQ for housing questions, service-animal access disputes, and what to do when a policy conflicts with official guidance.';
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
        'mainEntityOfPage' => guidepawSeoAbsoluteUrl('/housing_access_faq.php'),
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What should I do if a business asks for certification or registration?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'For ADA public access, certification or registration is not required. A business may ask only the two ADA questions and should not require proof of training as a condition of entry.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'What should I do if a housing provider asks for animal paperwork?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Housing requests are handled differently from public access. Under the Fair Housing Act, a housing provider may request reliable disability-related information when needed, but the exact information should be guided by HUD rules and the specific situation.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'When can a service animal be removed from a public place?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'If the dog is out of control and the handler does not correct it, or if the dog is not housebroken, the animal may be removed from the premises.',
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
    'canonical' => guidepawSeoAbsoluteUrl('/housing_access_faq.php'),
    'image' => '/assets/brand/guidepaw-logo.png',
    'json_ld' => $schema,
]); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f1f5f9; color:#0f172a; }
.page-shell { max-width: 1040px; margin: 0 auto; padding: 1rem 1rem 3rem; }
.hero { background: linear-gradient(135deg,#0d6efd,#0f766e); color:#fff; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,.18); }
.panel { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:20px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
.muted { color:#64748b; }
.pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.4rem .75rem; background:rgba(255,255,255,.12); font-weight:700; }
.section-card { border:1px solid rgba(15,23,42,.08); border-radius:18px; background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.07); height:100%; }
.faq-q { font-weight:800; font-size:1.05rem; }
.faq-a { color:#334155; }
.source-list li + li { margin-top: .4rem; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:720px;">
                <div class="pill mb-3">Public FAQ</div>
                <h1 class="display-6 fw-bold mb-3">Housing &amp; Access FAQ</h1>
                <p class="lead mb-0">A plain-language GuidePaw guide for housing questions, public-access disputes, and what to do when someone asks for paperwork that the rules do not require.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:360px;">
                <div class="small text-uppercase text-muted fw-semibold">Start here</div>
                <h2 class="h5 mb-2">Use the official rule set</h2>
                <p class="muted mb-3">ADA.gov governs public access. HUD guidance governs housing requests. GuidePaw helps you organize the basics.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="service_dog_esa_legal_info.php">Open legal info</a>
                    <a class="btn btn-outline-primary fw-bold" href="service_dog_rights.php">Open detailed ADA notes</a>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Public access</div>
                <h2 class="h4">Businesses can ask only limited questions</h2>
                <ul class="mb-0">
                    <li>For public access, businesses may ask whether the dog is required because of a disability.</li>
                    <li>They may also ask what work or task the dog has been trained to perform.</li>
                    <li>They should not require certification, registration, or proof of training as a condition of entry.</li>
                    <li>If the dog is out of control or not housebroken, the business may remove the dog.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Housing</div>
                <h2 class="h4">Housing questions follow a different rule set</h2>
                <ul class="mb-0">
                    <li>Housing providers are not asking the same questions as a store or restaurant.</li>
                    <li>Fair Housing Act requests may involve reliable disability-related information when a need is not obvious.</li>
                    <li>HUD guidance is the right place to check when a housing provider wants documentation.</li>
                    <li>ESAs can matter in housing even though they do not have ADA public-access rights.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section-card p-4 mb-4">
        <div class="text-uppercase text-muted fw-semibold small mb-2">Common disputes</div>
        <div class="faq-q mb-1">A business wants a vest, card, or certificate.</div>
        <div class="faq-a mb-3">That is not required by the ADA. The business should be guided by the two permitted questions and the dog’s actual behavior.</div>

        <div class="faq-q mb-1">A landlord wants something different from a store.</div>
        <div class="faq-a mb-3">Housing requests use Fair Housing Act rules. The right response depends on whether the animal is a service dog or an assistance animal in housing.</div>

        <div class="faq-q mb-1">A public place says the dog is too disruptive.</div>
        <div class="faq-a">If the dog is out of control and the handler does not correct it, or if the dog is not housebroken, removal can be allowed under ADA rules.</div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">What GuidePaw suggests</div>
                <h2 class="h4">Keep the category straight</h2>
                <ul class="mb-0">
                    <li>Public access follows ADA service-animal rules.</li>
                    <li>Housing follows Fair Housing Act assistance-animal rules.</li>
                    <li>Air travel follows DOT / ACAA service-animal rules.</li>
                    <li>ESAs are not service dogs, so do not treat them as the same thing in public access or flights.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Official sources</div>
                <h2 class="h4">References worth keeping</h2>
                <ul class="source-list mb-0">
                    <li><a href="https://www.ada.gov/resources/service-animals-faqs/" target="_blank" rel="noopener noreferrer">ADA Service Animals FAQ</a></li>
                    <li><a href="https://www.ada.gov/topics/service-animals/" target="_blank" rel="noopener noreferrer">ADA Service Animals</a></li>
                    <li><a href="https://www.hud.gov/program_offices/fair_housing_equal_opp/assistance_animals" target="_blank" rel="noopener noreferrer">HUD Assistance Animals</a></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section-card p-4">
        <div class="text-uppercase text-muted fw-semibold small mb-2">Bottom line</div>
        <h2 class="h4">Use the right rule set for the situation</h2>
        <p class="mb-0">The public page is meant to give searchers a clean answer without making GuidePaw pretend to be the legal source itself. That keeps the guidance useful, indexable, and less likely to mislead people who are dealing with a real dispute.</p>
    </section>
</main>
</body>
</html>
