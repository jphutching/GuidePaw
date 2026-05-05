<?php
declare(strict_types=1);

function adaStateNames(): array
{
    return [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
        'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
        'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
        'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
        'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];
}

function adaFederalLawProfile(): array
{
    return [
        'title' => 'Federal ADA baseline',
        'summary' => 'Under the ADA, a service animal is a dog individually trained to do work or perform tasks for a person with a disability. Staff may ask only the two ADA questions when the need is not obvious. Certification, registration papers, medical records, special ID, and task demonstrations are not required for public access.',
        'bullets' => [
            'Allowed questions: is the dog required because of a disability, and what work or task has the dog been trained to perform?',
            'Not allowed: diagnosis questions, medical records, certification demands, special ID requirements, or forced task demonstrations.',
            'A service animal may be excluded if it is out of control and not effectively corrected, or if it is not housebroken.',
            'The ADA does not recognize service animals in training for public access, but state law may provide access for training teams.',
        ],
        'source_label' => 'ADA.gov Service Animals FAQ',
        'source_url' => 'https://www.ada.gov/resources/service-animals-faqs/',
        'last_reviewed' => '2026-05-05',
    ];
}

function adaDefaultStateLawProfile(string $stateCode, string $stateName): array
{
    return [
        'state_code' => $stateCode,
        'state_name' => $stateName,
        'status' => 'pending',
        'summary' => 'GuidePaw has not yet added a reviewed state-specific summary for this state. Use the federal ADA baseline above and verify state or local rules through an official state source before relying on them.',
        'bullets' => [
            'Federal ADA public-access rules still apply in covered public places.',
            'State rules may differ for service animals in training, housing, penalties, schools, workplaces, transit, or misrepresentation.',
            'Use this as a reminder card, not legal advice.',
        ],
        'training_note' => 'State-specific service-dog-in-training rule not reviewed yet.',
        'housing_note' => 'Housing may involve separate federal and state reasonable-accommodation rules.',
        'source_label' => 'State law source pending review',
        'source_url' => '',
        'last_reviewed' => 'Pending',
    ];
}

function adaStateLawProfiles(): array
{
    $profiles = [];
    foreach (adaStateNames() as $code => $name) {
        $profiles[$code] = adaDefaultStateLawProfile($code, $name);
    }

    $profiles['UT'] = [
        'state_code' => 'UT',
        'state_name' => 'Utah',
        'status' => 'reviewed',
        'summary' => 'Utah law gives an individual with a disability the right to be accompanied by a service animal in listed public places without an additional charge, unless exclusion is permitted under federal law. Utah also gives access rights to an individual who is training an animal to become a service animal in those places, without an additional charge.',
        'bullets' => [
            'Utah recognizes public-access rights for a person with a disability accompanied by a service animal.',
            'Utah separately recognizes access for an individual training an animal to become a service animal.',
            'Utah allows recovery of reasonable repair costs for damage caused by the animal.',
            'Exclusion remains possible where permitted under federal law, including danger, nuisance, out-of-control behavior, or housebreaking issues.',
        ],
        'training_note' => 'Utah service-animal-in-training access is broader than the federal ADA baseline. Training access still depends on behavior, control, and the places covered by Utah law.',
        'housing_note' => 'Utah housing language bars extra fees or deposits for a service animal or support animal, while allowing recovery of reasonable repair costs for damage.',
        'source_label' => 'Utah Code § 26B-6-803',
        'source_url' => 'https://le.utah.gov/xcode/Title26B/Chapter6/C26B-6-S803_2023050320230503.pdf',
        'last_reviewed' => '2026-05-05',
    ];

    return $profiles;
}
