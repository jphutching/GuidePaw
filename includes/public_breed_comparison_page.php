<?php
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/brand_header.php';

if (!function_exists('renderGuidePawBreedComparisonPage')) {
    function renderGuidePawBreedComparisonPage(array $config): void
    {
        $title = trim((string) ($config['title'] ?? 'Breed Comparison | GuidePaw'));
        $description = trim((string) ($config['description'] ?? 'Compare breeds before you choose a dog.'));
        $slug = trim((string) ($config['slug'] ?? '/breed_comparison.php'));
        $heroTitle = trim((string) ($config['hero_title'] ?? $title));
        $heroLead = trim((string) ($config['hero_lead'] ?? $description));
        $quickTake = is_array($config['quick_take'] ?? null) ? $config['quick_take'] : [];
        $comparisonRows = is_array($config['comparison_rows'] ?? null) ? $config['comparison_rows'] : [];
        $fitReminder = trim((string) ($config['fit_reminder'] ?? 'Temperament, size, health, and training matter more than the breed name alone.'));
        $namingNote = trim((string) ($config['naming_note'] ?? ''));
        $nextNote = trim((string) ($config['next_note'] ?? 'Use the questionnaire after you compare.'));
        $faqItems = is_array($config['faq_items'] ?? null) ? $config['faq_items'] : [];
        $related = is_array($config['related_links'] ?? null) ? $config['related_links'] : [];

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
        ];
        if ($faqItems !== []) {
            $schema[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static function (array $item): array {
                    return [
                        '@type' => 'Question',
                        'name' => (string) ($item['q'] ?? ''),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => (string) ($item['a'] ?? ''),
                        ],
                    ];
                }, $faqItems),
            ];
        }

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
    'canonical' => guidepawSeoAbsoluteUrl($slug),
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
    .table-responsive { border:1px solid rgba(15,23,42,.08); border-radius:18px; overflow:hidden; background:#fff; }
    table { margin-bottom:0; }
    th { background:#eef2ff; }
    .callout { border-left:4px solid #0d6efd; background:#eff6ff; border-radius:16px; padding:1rem; }
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<main class="page-shell">
    <section class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:260px; max-width:720px;">
                <div class="pill mb-3">Public breed comparison</div>
                <h1 class="display-6 fw-bold mb-3"><?= e($heroTitle) ?></h1>
                <p class="lead mb-0"><?= e($heroLead) ?></p>
            </div>
            <div class="panel p-3 text-dark" style="min-width:280px; max-width:360px;">
                <div class="label">Next step</div>
                <h2 class="h5 mb-2"><?= e($nextNote) ?></h2>
                <p class="muted mb-3">A comparison page helps you sort the obvious choices. The public questionnaire helps narrow by size, family, grooming, and work fit.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="breed_questionnaire.php">Open Breed Questionnaire</a>
                    <a class="btn btn-outline-primary fw-bold" href="faq.php">Read GuidePaw FAQ</a>
                </div>
            </div>
        </div>
    </section>

    <?php if ($quickTake !== []): ?>
    <section class="row g-3 mb-4">
        <?php foreach ($quickTake as $take): ?>
            <div class="col-lg-6">
                <div class="panel p-4 h-100">
                    <div class="label mb-2"><?= e((string) ($take['label'] ?? 'Quick take')) ?></div>
                    <h2 class="h4"><?= e((string) ($take['title'] ?? 'Overview')) ?></h2>
                    <p class="mb-0"><?= e((string) ($take['body'] ?? '')) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($comparisonRows !== []): ?>
    <section class="mb-4">
        <div class="label mb-2">Side by side</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:22%">Topic</th>
                        <th><?= e((string) ($config['left_label'] ?? 'Left breed')) ?></th>
                        <th><?= e((string) ($config['right_label'] ?? 'Right breed')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comparisonRows as $row): ?>
                    <tr>
                        <td class="value"><?= e((string) ($row['topic'] ?? '')) ?></td>
                        <td><?= e((string) ($row['left'] ?? '')) ?></td>
                        <td><?= e((string) ($row['right'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Why these names get mixed up</div>
                <h2 class="h4">Naming note</h2>
                <p class="mb-0"><?= e($namingNote) ?></p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel p-4 h-100">
                <div class="label mb-2">Fit reminder</div>
                <h2 class="h4">What matters most</h2>
                <p class="mb-0"><?= e($fitReminder) ?></p>
            </div>
        </div>
    </section>

    <?php if ($faqItems !== []): ?>
    <section class="panel p-4 mb-4">
        <div class="label mb-2">Common questions</div>
        <div class="accordion accordion-flush" id="comparisonFaq">
            <?php foreach ($faqItems as $i => $item): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="cmpQ<?= (int) $i ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cmpA<?= (int) $i ?>" aria-expanded="false" aria-controls="cmpA<?= (int) $i ?>">
                            <?= e((string) ($item['q'] ?? 'Question')) ?>
                        </button>
                    </h2>
                    <div id="cmpA<?= (int) $i ?>" class="accordion-collapse collapse" aria-labelledby="cmpQ<?= (int) $i ?>" data-bs-parent="#comparisonFaq">
                        <div class="accordion-body"><?= e((string) ($item['a'] ?? '')) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($related !== []): ?>
    <section class="panel p-4">
        <div class="label mb-2">Related comparisons</div>
        <div class="row g-3">
            <?php foreach ($related as $link): ?>
                <div class="col-md-6 col-lg-4">
                    <a class="text-decoration-none text-dark d-block panel p-3 h-100" href="<?= e((string) ($link['href'] ?? '#')) ?>">
                        <div class="fw-bold mb-1"><?= e((string) ($link['title'] ?? 'Comparison')) ?></div>
                        <div class="muted small"><?= e((string) ($link['body'] ?? '')) ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}
