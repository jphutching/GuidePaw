<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/public_breed_comparison_page.php';

renderGuidePawBreedComparisonPage([
    'slug' => '/breed_compare_aussie_mini_american_shepherd.php',
    'title' => 'Australian Shepherd vs Miniature American Shepherd | GuidePaw',
    'hero_title' => 'Australian Shepherd vs Miniature American Shepherd',
    'description' => 'Compare Australian Shepherds and Miniature American Shepherds by size, herding drive, energy, and fit for homes that want a responsive working companion.',
    'hero_lead' => 'These breeds look related because they are. The difference is mainly size, handling, and how much dog you want in the house or on the go.',
    'next_note' => 'Use the questionnaire after you compare',
    'fit_reminder' => 'Both breeds are smart and active. If you want a calmer daily life, this is usually the wrong family unless you are committed to training and exercise.',
    'naming_note' => 'People often call both “Aussie,” but that nickname hides the size split. Miniature American Shepherds are the smaller line, while Australian Shepherds are the larger classic choice.',
    'left_label' => 'Australian Shepherd',
    'right_label' => 'Miniature American Shepherd',
    'quick_take' => [
        [
            'label' => 'Quick take',
            'title' => 'The short version',
            'body' => 'Australian Shepherds are usually the larger, more visible herding choice. Miniature American Shepherds give you a similar style in a smaller package.',
        ],
        [
            'label' => 'Fit reminder',
            'title' => 'What matters most',
            'body' => 'This family is about energy and engagement. Pick the size that fits your space, then be honest about whether you can handle the drive level.',
        ],
    ],
    'comparison_rows' => [
        ['topic' => 'Approx. size', 'left' => 'Usually around 40-65 lb with a medium working build.', 'right' => 'Usually around 20-40 lb with a smaller herding build.'],
        ['topic' => 'Temperament', 'left' => 'Alert, agile, and highly engaged.', 'right' => 'Alert, agile, and highly engaged with a smaller frame.'],
        ['topic' => 'Grooming', 'left' => 'Moderate coat care and regular shedding management.', 'right' => 'Moderate coat care and regular shedding management.'],
        ['topic' => 'Public access fit', 'left' => 'Can work well if the dog has the right focus and recovery.', 'right' => 'Can also work well, but the smaller frame changes physical handling.'],
        ['topic' => 'Watch for', 'left' => 'Herding intensity, voice, and under-exercise.', 'right' => 'Herding intensity, voice, and under-exercise in a smaller body.'],
    ],
    'faq_items' => [
        ['q' => 'Is the Miniature American Shepherd just a small Aussie?', 'a' => 'Not exactly. They are related-looking herding breeds, but they are separate choices with different size and registry history.'],
        ['q' => 'Which one is calmer?', 'a' => 'Neither is inherently calm. Both usually need a lot of engagement and structure.'],
        ['q' => 'Should I choose the smaller one for an apartment?', 'a' => 'Maybe, but only if the dog’s energy and your training plan fit the apartment life. Small size does not mean low drive.'],
    ],
    'related_links' => [
        ['href' => 'breed_questionnaire.php', 'title' => 'Breed Questionnaire', 'body' => 'Filter by size and public work needs.'],
        ['href' => 'faq.php', 'title' => 'GuidePaw FAQ', 'body' => 'Public answers about GuidePaw and breed research.'],
    ],
]);
