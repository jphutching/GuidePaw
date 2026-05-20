<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/public_breed_comparison_page.php';

renderGuidePawBreedComparisonPage([
    'slug' => '/breed_compare_french_bulldog_boston_terrier.php',
    'title' => 'French Bulldog vs Boston Terrier | GuidePaw',
    'hero_title' => 'French Bulldog vs Boston Terrier',
    'description' => 'Compare French Bulldogs and Boston Terriers by size, brachycephalic concerns, temperament, and which small companion fit is more workable.',
    'hero_lead' => 'These are both small companion breeds, but they are not interchangeable. Size, breathing concerns, and daily handling should drive the decision.',
    'next_note' => 'Use the questionnaire after you compare',
    'fit_reminder' => 'Because both breeds can have face-shape and heat-tolerance concerns, you should be especially careful about health screening and daily comfort.',
    'naming_note' => 'French Bulldogs and Boston Terriers get compared because they are both compact, social, and easy to recognize. The body type and health considerations are still different enough to matter.',
    'left_label' => 'French Bulldog',
    'right_label' => 'Boston Terrier',
    'quick_take' => [
        [
            'label' => 'Quick take',
            'title' => 'The short version',
            'body' => 'French Bulldogs are usually stockier and more brachycephalic. Boston Terriers are typically a bit lighter and more athletic in outline.',
        ],
        [
            'label' => 'Fit reminder',
            'title' => 'What matters most',
            'body' => 'These breeds can be charming companions, but heat tolerance, breathing, and day-to-day handling are not small details.',
        ],
    ],
    'comparison_rows' => [
        ['topic' => 'Approx. size', 'left' => 'Usually around 16-28 lb with a stocky, compact body.', 'right' => 'Usually around 12-25 lb with a lighter, more athletic body.'],
        ['topic' => 'Temperament', 'left' => 'Clownish, social, and often people-focused.', 'right' => 'Bright, social, and often a bit more athletic in movement.'],
        ['topic' => 'Grooming', 'left' => 'Low coat grooming, but skin and face care matter.', 'right' => 'Low coat grooming, but face, eye, and skin care still matter.'],
        ['topic' => 'Public access fit', 'left' => 'Can fit in some homes, but breathing and heat are important limits.', 'right' => 'Can also fit in some homes, with similar health caveats.'],
        ['topic' => 'Watch for', 'left' => 'Heat, breathing, soft tissue issues, and fatigue.', 'right' => 'Heat, breathing, eye issues, and fatigue.'],
    ],
    'faq_items' => [
        ['q' => 'Which one is healthier?', 'a' => 'Health varies by individual dog and breeder practices. Neither breed should be chosen without a serious health check.'],
        ['q' => 'Which one is better for hot weather?', 'a' => 'Neither is ideal for heat. You need to think carefully about climate, exercise, and cooling.'],
        ['q' => 'Should I pick one for public access?', 'a' => 'Only if the dog’s health and energy profile truly match the work you need. Small size alone is not enough.'],
    ],
    'related_links' => [
        ['href' => 'breed_questionnaire.php', 'title' => 'Breed Questionnaire', 'body' => 'Filter by size and public work needs.'],
        ['href' => 'faq.php', 'title' => 'GuidePaw FAQ', 'body' => 'Public answers about GuidePaw and breed research.'],
    ],
]);
