<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/public_breed_comparison_page.php';

renderGuidePawBreedComparisonPage([
    'slug' => '/breed_compare_labrador_golden.php',
    'title' => 'Labrador Retriever vs Golden Retriever | GuidePaw',
    'hero_title' => 'Labrador Retriever vs Golden Retriever',
    'description' => 'Compare Labrador Retrievers and Golden Retrievers by size, coat, temperament, and public-work fit before you choose a family or working dog.',
    'hero_lead' => 'These are two of the most commonly searched retriever breeds. This page separates the similarities so you can compare the differences that matter in real life.',
    'next_note' => 'Use the questionnaire after you compare',
    'fit_reminder' => 'Both breeds can be excellent family companions, but either one still needs the right energy level, training foundation, and health screening for the job you need.',
    'naming_note' => 'People often compare these breeds because both are friendly retrievers. The better comparison is coat care, adult size, and the dog’s steadiness in the environments you expect to work in.',
    'left_label' => 'Labrador Retriever',
    'right_label' => 'Golden Retriever',
    'quick_take' => [
        [
            'label' => 'Quick take',
            'title' => 'The short version',
            'body' => 'Labs are usually a little more straightforward in build and coat maintenance. Goldens often need more coat care and feathering management, but both can be highly social and handler-oriented.',
        ],
        [
            'label' => 'Fit reminder',
            'title' => 'What matters most',
            'body' => 'Choose the individual dog, not just the breed name. Calmness, biddability, and good public manners matter more than which retriever is theoretically “better.”',
        ],
    ],
    'comparison_rows' => [
        ['topic' => 'Approx. size', 'left' => 'Usually around 55-80 lb with a sturdy working outline.', 'right' => 'Usually around 55-75 lb with a slightly softer outline and longer coat.'],
        ['topic' => 'Temperament', 'left' => 'Friendly, eager, and often very work-oriented.', 'right' => 'Friendly, people-oriented, and often softer in presentation.'],
        ['topic' => 'Grooming', 'left' => 'Shorter coat, lighter grooming burden, frequent shedding.', 'right' => 'Longer coat, more brushing and coat management, frequent shedding.'],
        ['topic' => 'Public access fit', 'left' => 'Can be a strong fit when training and steadiness are solid.', 'right' => 'Can also fit well, but coat care and matting need more attention.'],
        ['topic' => 'Watch for', 'left' => 'Mouthiness, youthful energy, and boredom.', 'right' => 'Grooming time, weight control, and coat maintenance.'],
    ],
    'faq_items' => [
        ['q' => 'Which one is easier to groom?', 'a' => 'Labradors are usually easier to maintain because the coat is shorter, though both still shed and need regular care.'],
        ['q' => 'Which one is better for public access?', 'a' => 'Neither breed is automatically better. The dog’s temperament, steadiness, and training quality matter most.'],
        ['q' => 'Should I choose by coat color?', 'a' => 'No. Color is a weak predictor of fit. Focus on health, temperament, and how the dog lives day to day.'],
    ],
    'related_links' => [
        ['href' => 'breed_questionnaire.php', 'title' => 'Breed Questionnaire', 'body' => 'Narrow by size, public access, and work fit.'],
        ['href' => 'faq.php', 'title' => 'GuidePaw FAQ', 'body' => 'Public answers about GuidePaw and breed research.'],
    ],
]);
