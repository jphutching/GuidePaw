<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/xml; charset=UTF-8');
$pages = [
    [
        'loc' => guidepawSeoAbsoluteUrl('/'),
        'changefreq' => 'weekly',
        'priority' => '1.0',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_questionnaire.php'),
        'changefreq' => 'weekly',
        'priority' => '0.9',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_comparison.php'),
        'changefreq' => 'monthly',
        'priority' => '0.8',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_compare_labrador_golden.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_compare_standard_toy_poodle.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_compare_aussie_mini_american_shepherd.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_compare_pembroke_cardigan_corgi.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/breed_compare_french_bulldog_boston_terrier.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/faq.php'),
        'changefreq' => 'monthly',
        'priority' => '0.8',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/support_funding.php'),
        'changefreq' => 'monthly',
        'priority' => '0.8',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/service_dog_rights.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/air_travel_rights.php'),
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'loc' => guidepawSeoAbsoluteUrl('/contact_us.php'),
        'changefreq' => 'monthly',
        'priority' => '0.5',
    ],
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
    <url>
        <loc><?= htmlspecialchars((string) $page['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></loc>
        <changefreq><?= htmlspecialchars((string) $page['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></changefreq>
        <priority><?= htmlspecialchars((string) $page['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
