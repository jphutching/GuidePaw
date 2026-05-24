<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/seo.php';

$title = 'GuidePaw Service Dog & ESA Legal Info | Public Guide';
$description = 'Public GuidePaw reference for service dogs, emotional support animals, housing, public access, and air travel basics.';
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
        'mainEntityOfPage' => guidepawSeoAbsoluteUrl('/service_dog_esa_legal_info.php'),
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'Is there an official national registry for service dogs or ESAs?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. There is no official national registry or mandatory certification for service dogs or emotional support animals in the United States.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Can an emotional support animal go everywhere a service dog can go?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. Emotional support animals do not have ADA public-access rights and are generally treated like pets outside housing rules that may apply under the Fair Housing Act.',
                ],
            ],
            [
                '@type' => 'Question',
                'name' => 'Do service dogs need to be professionally trained?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'No. Service dogs must be individually trained to do disability-related work or tasks, but the training can be done by a professional or by the handler.',
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
    'canonical' => guidepawSeoAbsoluteUrl('/service_dog_esa_legal_info.php'),
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
.source-list li + li { margin-top: .4rem; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:720px;">
                <div class="pill mb-3">Public legal info</div>
                <h1 class="display-6 fw-bold mb-3">Service Dog &amp; ESA Legal Info</h1>
                <p class="lead mb-0">A plain-language GuidePaw summary of service dogs, emotional support animals, housing, and air travel basics. This is a guide, not legal advice.</p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:360px;">
                <div class="small text-uppercase text-muted fw-semibold">Start here</div>
                <h2 class="h5 mb-2">Use official sources for final rules</h2>
                <p class="muted mb-3">GuidePaw is a reference and organizer. ADA, DOT, and HUD guidance still control the actual legal standards.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="service_dog_rights.php">Open detailed ADA notes</a>
                    <a class="btn btn-outline-primary fw-bold" href="air_travel_rights.php">Open air travel notes</a>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Service dogs</div>
                <h2 class="h4">Individually trained task dogs</h2>
                <ul class="mb-0">
                    <li>Service dogs are individually trained to do work or perform tasks related to a person’s disability.</li>
                    <li>Training may be done by a professional trainer or by the handler.</li>
                    <li>No vest, ID card, certificate, or registration is required for ADA public access rights.</li>
                    <li>Online registries and certificates do not create legal rights.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Emotional support animals</div>
                <h2 class="h4">Support, not task training</h2>
                <ul class="mb-0">
                    <li>ESAs provide comfort or emotional support but are not task-trained service dogs.</li>
                    <li>ESAs do not have ADA public-access rights.</li>
                    <li>Housing requests may involve Fair Housing Act rules and reliable disability-related information.</li>
                    <li>Online ESA certifications do not create public-access rights.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Access and behavior</div>
                <h2 class="h4">When access can be lost</h2>
                <p class="mb-2">Even a real service dog can lose access if it is out of control, disruptive, or not housebroken.</p>
                <ul class="mb-0">
                    <li>Excessive barking, lunging, or toileting accidents can justify removal.</li>
                    <li>Those problems can often be corrected with more training.</li>
                    <li>After the problem is fixed, access can be reliable again.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Air travel</div>
                <h2 class="h4">Service dogs and flights</h2>
                <p class="mb-2">Under DOT rules, service dogs are recognized for flights to, within, and from the United States.</p>
                <ul class="mb-0">
                    <li>Airlines may require DOT service animal forms.</li>
                    <li>Airlines may ask the two travel-specific questions.</li>
                    <li>ESAs are not service animals for air travel.</li>
                    <li>DOT forms do not apply as a replacement for airline-specific or destination rules.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">GuidePaw note</div>
                <h2 class="h4">What GuidePaw does and does not do</h2>
                <p class="mb-2">GuidePaw is a practical organizer for handlers — with a native Android companion app for training logs, goal intake, habit repair, and behavior tracking, plus web tools for public profiles, breed research, and support.</p>
                <ul class="mb-0">
                    <li>GuidePaw does not issue certifications or registrations.</li>
                    <li>GuidePaw does not replace ADA.gov, HUD guidance, DOT guidance, or airline policies.</li>
                    <li>Use GuidePaw to stay organized, then confirm final legal rules from official sources.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="section-card p-4 h-100">
                <div class="text-uppercase text-muted fw-semibold small mb-2">Official sources</div>
                <h2 class="h4">Links worth saving</h2>
                <ul class="source-list mb-0">
                    <li><a href="https://www.ada.gov/resources/service-animals-faqs/" target="_blank" rel="noopener noreferrer">ADA.gov Service Animals FAQ</a></li>
                    <li><a href="https://www.ada.gov/topics/service-animals/" target="_blank" rel="noopener noreferrer">ADA.gov Service Animals</a></li>
                    <li><a href="https://www.transportation.gov/resources/individuals/aviation-consumer-protection/service-animals" target="_blank" rel="noopener noreferrer">DOT Service Animals</a></li>
                    <li><a href="https://www.hud.gov/program_offices/fair_housing_equal_opp/assistance_animals" target="_blank" rel="noopener noreferrer">HUD Assistance Animals</a></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section-card p-4">
        <div class="text-uppercase text-muted fw-semibold small mb-2">Bottom line</div>
        <h2 class="h4">Use the public reference, then verify the rule that applies to your situation</h2>
        <p class="mb-0">This page covers the core federal distinctions between service dogs and ESAs, when access can be denied, and how air travel works under current DOT rules. Use the detailed ADA notes for scripts and handler guidance, and verify final rules from official sources before acting on any specific situation.</p>
    </section>
</main>
</body>
</html>
