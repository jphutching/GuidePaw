<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/public_breed_comparison_page.php';

renderGuidePawBreedComparisonPage([
    'slug' => '/breed_compare_pembroke_cardigan_corgi.php',
    'title' => 'Pembroke Welsh Corgi vs Cardigan Welsh Corgi | GuidePaw',
    'hero_title' => 'Pembroke Welsh Corgi vs Cardigan Welsh Corgi',
    'description' => 'Compare Pembroke Welsh Corgis and Cardigan Welsh Corgis by body type, temperament, grooming, and which corgi family fits your life better.',
    'hero_lead' => 'People say corgi and mean one of two different breeds. This page keeps the Pembroke and Cardigan split clear so you can compare them on purpose.',
    'next_note' => 'Use the questionnaire after you compare',
    'fit_reminder' => 'Both breeds can be clever and very people-aware, but both also bring herding habits, low-slung bodies, and a need for real training.',
    'naming_note' => 'Corgi is the family name, but Pembroke and Cardigan are different breeds. That matters when you care about body type, tail, and overall look.',
    'left_label' => 'Pembroke Welsh Corgi',
    'right_label' => 'Cardigan Welsh Corgi',
    'quick_take' => [
        [
            'label' => 'Quick take',
            'title' => 'The short version',
            'body' => 'Pembrokes are usually a bit more compact and are often the corgi people picture first. Cardigans are a separate corgi breed with a slightly different outline and tail style.',
        ],
        [
            'label' => 'Fit reminder',
            'title' => 'What matters most',
            'body' => 'The corgi choice is less about which one is “better” and more about whether you can manage a low body, herding instinct, and daily training.',
        ],
    ],
    'comparison_rows' => [
        ['topic' => 'Approx. size', 'left' => 'Usually around 22-31 lb with a compact build.', 'right' => 'Usually around 25-38 lb with a slightly longer body and heavier frame.'],
        ['topic' => 'Temperament', 'left' => 'Clever, outgoing, and herding-minded.', 'right' => 'Clever, steady, and herding-minded with a somewhat different feel.'],
        ['topic' => 'Grooming', 'left' => 'Moderate coat care with regular shedding.', 'right' => 'Moderate coat care with regular shedding.'],
        ['topic' => 'Public access fit', 'left' => 'Can fit well if barking and herding habits are trained early.', 'right' => 'Can fit well if the dog’s steadiness and recovery are good.'],
        ['topic' => 'Watch for', 'left' => 'Back strain, barking, and pushing at heels.', 'right' => 'Back strain, barking, and the same herding instincts in a different body type.'],
    ],
    'faq_items' => [
        ['q' => 'Which corgi is smaller?', 'a' => 'Pembrokes are usually the more compact choice, though the individual dog still matters.'],
        ['q' => 'Do both corgis shed?', 'a' => 'Yes. Both need regular brushing and consistent coat care.'],
        ['q' => 'Is the tail the main difference?', 'a' => 'No. Tail style is one difference, but overall build and breed history matter too.'],
    ],
    'related_links' => [
        ['href' => 'breed_questionnaire.php', 'title' => 'Breed Questionnaire', 'body' => 'Filter by size and public work needs.'],
        ['href' => 'faq.php', 'title' => 'GuidePaw FAQ', 'body' => 'Public answers about GuidePaw and breed research.'],
    ],
]);
