<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/public_breed_comparison_page.php';

renderGuidePawBreedComparisonPage([
    'slug' => '/breed_compare_standard_toy_poodle.php',
    'title' => 'Standard Poodle vs Toy Poodle | GuidePaw',
    'hero_title' => 'Standard Poodle vs Toy Poodle',
    'description' => 'Compare Standard Poodles and Toy Poodles by size, coat, training fit, and the difference between a compact companion and a larger working companion.',
    'hero_lead' => 'Poodles come in different sizes, and that changes the kind of home, handling, and work fit they offer. This page keeps the size difference explicit.',
    'next_note' => 'Use the questionnaire after you compare',
    'fit_reminder' => 'Standard and Toy Poodles can both be smart and trainable, but the size difference matters for mobility, public handling, and how much space the dog needs.',
    'naming_note' => 'People often say “Poodle” without the size. Standard and Toy are not the same lifestyle choice, even though they share the same breed family.',
    'left_label' => 'Standard Poodle',
    'right_label' => 'Toy Poodle',
    'quick_take' => [
        [
            'label' => 'Quick take',
            'title' => 'The short version',
            'body' => 'Standard Poodles are larger and often better for people who want a full-size companion. Toy Poodles are much smaller and easier to carry, but that smaller size can change how they work in public.',
        ],
        [
            'label' => 'Fit reminder',
            'title' => 'What matters most',
            'body' => 'Both sizes can be intelligent and sensitive. The right choice depends on adult size, grooming commitment, and whether you want a more portable dog.',
        ],
    ],
    'comparison_rows' => [
        ['topic' => 'Approx. size', 'left' => 'Usually around 40-70 lb and clearly medium-to-large in presence.', 'right' => 'Usually around 4-12 lb and easy to carry or travel with.'],
        ['topic' => 'Temperament', 'left' => 'Smart, athletic, and often highly trainable.', 'right' => 'Smart, lively, and often more delicate in handling.'],
        ['topic' => 'Grooming', 'left' => 'High grooming commitment with clipping and coat maintenance.', 'right' => 'Still high grooming commitment, but on a smaller body.'],
        ['topic' => 'Public access fit', 'left' => 'Can work well if the dog is steady and the handler wants a larger companion.', 'right' => 'Can work well in the right hands, but very small size may limit some homes or tasks.'],
        ['topic' => 'Watch for', 'left' => 'Activity needs, coat care, and not underestimating how much dog you are adding.', 'right' => 'Fragility, handling needs, and whether the smaller frame matches your day-to-day life.'],
    ],
    'faq_items' => [
        ['q' => 'Is a Toy Poodle just a small Standard Poodle?', 'a' => 'No. They share a breed family, but the size difference changes handling, travel, and lifestyle fit.'],
        ['q' => 'Which one is better for beginners?', 'a' => 'Neither is automatically better. A Toy Poodle may be easier to physically manage, while a Standard Poodle may be easier to handle in some environments because of its larger size.'],
        ['q' => 'Should I choose based on size alone?', 'a' => 'No. Choose by size plus grooming, temperament, and the kind of public and home life you actually live.'],
    ],
    'related_links' => [
        ['href' => 'breed_questionnaire.php', 'title' => 'Breed Questionnaire', 'body' => 'Filter by size and grooming commitment.'],
        ['href' => 'faq.php', 'title' => 'GuidePaw FAQ', 'body' => 'Public answers about GuidePaw and breed research.'],
    ],
]);
