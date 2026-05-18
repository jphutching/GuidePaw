<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/dog_breeds.php';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function qv(array $source, string $key, string $default): string
{
    $value = strtolower(trim((string) ($source[$key] ?? $default)));
    return $value === '' ? $default : $value;
}

function containsAny(string $text, array $needles): bool
{
    $text = strtolower($text);
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($text, strtolower($needle))) {
            return true;
        }
    }
    return false;
}

function pickSizeRank(string $value): int
{
    return match ($value) {
        'toy' => 1,
        'small' => 2,
        'medium' => 3,
        'large' => 4,
        'giant' => 5,
        default => 3,
    };
}

function classifyBreedSize(string $value): int
{
    $value = strtolower($value);
    if (str_contains($value, 'toy')) {
        return 1;
    }
    if (str_contains($value, 'small')) {
        return 2;
    }
    if (str_contains($value, 'medium')) {
        return 3;
    }
    if (str_contains($value, 'large')) {
        return 4;
    }
    if (str_contains($value, 'giant')) {
        return 5;
    }
    return 3;
}

function classifyBreedEffort(string $value): int
{
    $value = strtolower($value);
    if (str_contains($value, 'very high')) {
        return 4;
    }
    if (str_contains($value, 'high')) {
        return 3;
    }
    if (str_contains($value, 'moderate')) {
        return 2;
    }
    if (str_contains($value, 'low')) {
        return 1;
    }
    return 2;
}

function classifyBreedShedding(string $value): int
{
    $value = strtolower($value);
    if (str_contains($value, 'low')) {
        return 1;
    }
    if (str_contains($value, 'moderate')) {
        return 2;
    }
    if (str_contains($value, 'high')) {
        return 3;
    }
    return 2;
}

function scoreDistance(int $actual, int $target): int
{
    $diff = abs($actual - $target);
    return match (true) {
        $diff === 0 => 3,
        $diff === 1 => 1,
        $diff === 2 => -1,
        default => -3,
    };
}

function scoreKeywordMatches(string $text, array $positive, array $negative = []): int
{
    $score = 0;
    foreach ($positive as $needle) {
        if ($needle !== '' && str_contains($text, strtolower($needle))) {
            $score += 2;
        }
    }
    foreach ($negative as $needle) {
        if ($needle !== '' && str_contains($text, strtolower($needle))) {
            $score -= 2;
        }
    }
    return $score;
}

function explainMatches(array $textChecks): string
{
    $textChecks = array_values(array_unique(array_filter($textChecks)));
    if (!$textChecks) {
        return 'General fit based on the answers you selected.';
    }
    return implode(' · ', $textChecks);
}

function normalizeBreedQuery(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function breedSuggestionBestFor(array $breed): string
{
    $blob = strtolower(implode(' ', [
        $breed['breed_family'] ?? '',
        $breed['group'] ?? '',
        $breed['temperament'] ?? '',
        $breed['traits'] ?? '',
        $breed['notes'] ?? '',
        $breed['size'] ?? '',
    ]));

    if (str_contains($blob, 'retrieval') || str_contains($blob, 'retrieving') || str_contains($blob, 'item delivery')) {
        return 'Best for retrieval and task work';
    }
    if (str_contains($blob, 'public access') || str_contains($blob, 'service')) {
        return 'Best for public access and service work';
    }
    if (str_contains($blob, 'alert') || str_contains($blob, 'response')) {
        return 'Best for alert and response tasks';
    }
    if (str_contains($blob, 'grounding') || str_contains($blob, 'affectionate') || str_contains($blob, 'people-oriented')) {
        return 'Best for grounding and companionship';
    }
    if (str_contains($blob, 'calm') || str_contains($blob, 'steady')) {
        return 'Best for calm, steady households';
    }
    if (str_contains($blob, 'athletic') || str_contains($blob, 'high drive')) {
        return 'Best for active, experienced handlers';
    }
    return 'Best for broader research';
}

function breedSuggestionFocus(array $breed): string
{
    $blob = strtolower(implode(' ', [
        $breed['breed_family'] ?? '',
        $breed['group'] ?? '',
        $breed['temperament'] ?? '',
        $breed['traits'] ?? '',
        $breed['notes'] ?? '',
        $breed['size'] ?? '',
    ]));

    if (str_contains($blob, 'retrieval') || str_contains($blob, 'retrieving') || str_contains($blob, 'item delivery')) {
        return 'task';
    }
    if (str_contains($blob, 'public access') || str_contains($blob, 'service')) {
        return 'public';
    }
    if (str_contains($blob, 'alert') || str_contains($blob, 'response')) {
        return 'task';
    }
    if (str_contains($blob, 'grounding') || str_contains($blob, 'affectionate') || str_contains($blob, 'people-oriented')) {
        return 'companion';
    }
    if (str_contains($blob, 'calm') || str_contains($blob, 'steady')) {
        return 'companion';
    }
    if (str_contains($blob, 'athletic') || str_contains($blob, 'high drive')) {
        return 'task';
    }
    return 'broader';
}

function familyBrowseKeywords(string $family): array
{
    $familyKey = normalizeBreedQuery($family);
    $keywords = [];
    foreach (preg_split('/\s+/', $familyKey) ?: [] as $token) {
        if ($token !== '' && $token !== 'family' && strlen($token) >= 3) {
            $keywords[] = $token;
        }
    }

    $familyExtras = [
        'retriever family' => [
            'retriever',
            'chesapeake bay retriever',
            'curly-coated retriever',
            'flat-coated retriever',
            'golden retriever',
            'labrador retriever',
            'nova scotia duck tolling retriever',
        ],
        'spaniel family' => [
            'spaniel',
            'american cocker spaniel',
            'american water spaniel',
            'boykin spaniel',
            'cocker spaniel',
            'english cocker spaniel',
            'english springer spaniel',
            'field spaniel',
            'irish water spaniel',
            'king charles spaniel',
            'sussex spaniel',
            'welsh springer spaniel',
        ],
        'pointer setter family' => [
            'pointer',
            'setter',
            'bracco',
            'braque',
            'spinone',
            'vizsla',
            'griffon',
            'german shorthaired pointer',
            'german wirehaired pointer',
            'english setter',
            'gordon setter',
            'irish setter',
        ],
        'herding shepherd family' => [
            'shepherd',
            'collie',
            'corgi',
            'heeler',
            'sheepdog',
            'herder',
            'bouvier',
            'picard',
            'puli',
            'pumi',
            'lapphund',
            'buhund',
            'beauceron',
            'bergamasco',
            'mudi',
            'canaan',
            'kelpie',
            'stumpy tail',
        ],
        'toy companion family' => [
            'toy',
            'affenpinscher',
            'biewer',
            'bolonese',
            'brussels griffon',
            'chinese crested',
            'japanese chin',
            'maltese',
            'miniature pinscher',
            'papillon',
            'pekingese',
            'pomeranian',
            'russian toy',
            'russian tsvetnaya bolonka',
            'silky terrier',
            'toy fox terrier',
            'cavalier king charles spaniel',
        ],
        'terrier family' => [
            'terrier',
            'schnauzer',
        ],
        'northern spitz family' => [
            'spitz',
            'akita',
            'samoyed',
            'shiba',
            'jindo',
            'laika',
            'keeshond',
            'elkhound',
            'kishu',
            'kai ken',
            'hokkaido',
            'eurasier',
        ],
        'scent hound family' => [
            'hound',
            'foxhound',
            'coonhound',
            'basset',
            'bloodhound',
            'harrier',
            'otterhound',
            'dachshund',
            'plott',
            'scent hound',
        ],
        'sighthound family' => [
            'afghan hound',
            'azawakh',
            'basenji',
            'borzoi',
            'cirneco',
            'ibizan hound',
            'irish wolfhound',
            'italian greyhound',
            'pharaoh hound',
            'saluki',
            'scottish deerhound',
            'sloughi',
            'greyhound',
            'whippet',
        ],
        'large giant working family' => [
            'mastiff',
            'boerboel',
            'corso',
            'dogo',
            'dogue',
            'giant schnauzer',
            'greater swiss',
            'leonberger',
            'neapolitan mastiff',
            'tibetan mastiff',
            'broholmer',
            'pyrenean mastiff',
            'great pyrenees',
            'kuvasz',
            'newfoundland',
        ],
        'bully power breed family' => [
            'staffordshire',
            'bull terrier',
            'bully',
            'presa canario',
            'american bully',
            'pit bull',
        ],
        'short nosed heat sensitive companion family' => [
            'bulldog',
            'french bulldog',
            'pug',
            'boston terrier',
            'lhasa apso',
            'tibetan spaniel',
            'shar pei',
            'chow chow',
        ],
        'large designer working cross family' => [
            'doodle',
            'poo',
            'shepsky',
            'bernedoodle',
            'labradoodle',
            'goldendoodle',
            'cavapoo',
            'cockapoo',
            'springerdoodle',
            'weimardoodle',
            'vizsladoodle',
            'newfypoo',
        ],
        'small companion designer cross family' => [
            'alier',
            'chon',
            'chiweenie',
            'chorkie',
            'morkie',
            'peekapoo',
            'pomapoo',
            'puggle',
            'shorkie',
            'yorkipoo',
        ],
    ];

    foreach ($familyExtras[$familyKey] ?? [] as $extraKeyword) {
        $extraKeyword = normalizeBreedQuery($extraKeyword);
        if ($extraKeyword !== '') {
            $keywords[] = $extraKeyword;
        }
    }

    return array_values(array_unique(array_filter($keywords)));
}

function familyRelatedBreeds(string $family): array
{
    $familyKey = normalizeBreedQuery($family);
    $familyRelatedMap = [
        'retriever family' => ['Golden Retriever', 'Labrador Retriever', 'Flat-Coated Retriever', 'Chesapeake Bay Retriever'],
        'spaniel family' => ['Brittany', 'Cocker Spaniel', 'English Toy Spaniel', 'Cavalier King Charles Spaniel'],
        'pointer setter family' => ['Brittany', 'Vizsla', 'German Shorthaired Pointer', 'English Setter'],
        'herding shepherd family' => ['Border Collie', 'Australian Cattle Dog', 'Pembroke Welsh Corgi', 'Bouvier des Flandres'],
        'toy companion family' => ['Papillon', 'Pekingese', 'Maltese', 'Cavalier King Charles Spaniel'],
        'terrier family' => ['Border Terrier', 'West Highland White Terrier', 'Miniature Schnauzer', 'Scottish Terrier'],
        'northern spitz family' => ['Shiba Inu', 'Samoyed', 'Keeshond', 'American Eskimo Dog'],
        'scent hound family' => ['Bloodhound', 'Basset Fauve de Bretagne', 'Harrier', 'Dachshund'],
        'sighthound family' => ['Whippet', 'Italian Greyhound', 'Borzoi', 'Saluki'],
        'large giant working family' => ['Great Pyrenees', 'Cane Corso', 'Giant Schnauzer', 'Leonberger'],
        'bully power breed family' => ['American Staffordshire Terrier', 'Staffordshire Bull Terrier', 'Bull Terrier', 'Perro de Presa Canario'],
        'short nosed heat sensitive companion family' => ['French Bulldog', 'Pug', 'Boston Terrier', 'Tibetan Spaniel'],
        'large designer working cross family' => ['Labradoodle', 'Goldendoodle', 'Bernedoodle', 'Springerdoodle'],
        'small companion designer cross family' => ['Cavachon', 'Morkie', 'Pomapoo', 'Shorkie'],
        'rare international breed family' => ['Carolina Dog', 'Catahoula Leopard Dog', 'Xoloitzcuintli', 'Kromfohrlander'],
        'mixed unknown breed family' => ['Mixed Breed', 'Unknown Breed', 'Other / Not Listed'],
    ];

    return $familyRelatedMap[$familyKey] ?? [];
}

function familyBrowseCandidateNames(array $allBreeds, string $family): array
{
    $familyKey = normalizeBreedQuery($family);
    $keywords = familyBrowseKeywords($family);
    $candidateNames = [];
    foreach ($allBreeds as $breedName => $breed) {
        $breedFamily = normalizeBreedQuery((string) ($breed['breed_family'] ?? $breed['group'] ?? ''));
        $breedNameNorm = normalizeBreedQuery((string) $breedName);
        $breedAliases = array_values(array_filter(array_map('normalizeBreedQuery', array_map('trim', (array) ($breed['aliases'] ?? [])))));
        if ($breedFamily !== '' && $breedFamily === $familyKey) {
            $candidateNames[] = $breedName;
            continue;
        }
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($breedNameNorm, $keyword)) {
                $candidateNames[] = $breedName;
                break;
            }
            foreach ($breedAliases as $alias) {
                if ($alias !== '' && (str_contains($alias, $keyword) || str_contains($keyword, $alias))) {
                    $candidateNames[] = $breedName;
                    break 2;
                }
            }
        }
    }

    $candidateNames = array_values(array_unique($candidateNames));
    sort($candidateNames, SORT_NATURAL | SORT_FLAG_CASE);
    return $candidateNames;
}

$defaults = [
    'goal' => 'service_access',
    'size' => 'medium',
    'energy' => 'moderate',
    'grooming' => 'moderate',
    'public' => 'busy',
    'experience' => 'some',
    'sensitivity' => 'balanced',
    'breed_query' => '',
    'breed_focus' => 'all',
];

$answers = $defaults;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($defaults as $key => $default) {
        $answers[$key] = qv($_POST, $key, $default);
    }
}

$goalLabel = [
    'service_access' => 'Public access / full service work',
    'psychiatric' => 'Psychiatric support / grounding',
    'medical_alert' => 'Medical alert / response',
    'hearing_alert' => 'Hearing alert / response',
    'retrieval' => 'Retrieval / task work',
    'companion' => 'Companion / family fit',
];
$sizeLabel = [
    'flexible' => 'Flexible',
    'toy' => 'Toy · about 4-12 lbs',
    'small' => 'Small · about 10-25 lbs',
    'medium' => 'Medium · about 20-55 lbs',
    'large' => 'Large · about 45-90 lbs',
    'giant' => 'Giant · about 85+ lbs',
];

$allBreeds = getDogBreedsCatalog();
$breedSuggestions = array_keys($allBreeds);
sort($breedSuggestions, SORT_NATURAL | SORT_FLAG_CASE);
$breedSuggestionData = [];
foreach ($breedSuggestions as $breedName) {
    $breed = $allBreeds[$breedName] ?? [];
    $breedSuggestionData[] = [
        'name' => $breedName,
        'aliases' => array_values(array_filter(array_map('trim', (array) ($breed['aliases'] ?? [])))),
        'group' => trim((string) ($breed['breed_family'] ?? $breed['group'] ?? '')),
        'notes' => trim((string) ($breed['notes'] ?? '')),
        'traits' => trim((string) ($breed['traits'] ?? '')),
        'size' => trim((string) ($breed['size'] ?? '')),
        'best_for' => breedSuggestionBestFor($breed),
        'focus' => breedSuggestionFocus($breed),
    ];
}
$breedAliasLookup = [];
foreach ($breedSuggestionData as $breedSuggestion) {
    $canonical = normalizeBreedQuery($breedSuggestion['name']);
    if ($canonical !== '') {
        $breedAliasLookup[$canonical] = $breedSuggestion['name'];
    }
    foreach ($breedSuggestion['aliases'] as $alias) {
        $normalizedAlias = normalizeBreedQuery((string) $alias);
        if ($normalizedAlias !== '') {
            $breedAliasLookup[$normalizedAlias] = $breedSuggestion['name'];
        }
    }
}
$commonBreedAliasSeeds = [
    'lab' => 'Labrador Retriever',
    'golden' => 'Golden Retriever',
    'gsd' => 'German Shepherd Dog',
    'german shepherd' => 'German Shepherd Dog',
    'mini schnauzer' => 'Miniature Schnauzer',
    'miniature schnauzer' => 'Miniature Schnauzer',
    'mini pin' => 'Miniature Pinscher',
    'min pin' => 'Miniature Pinscher',
    'westie' => 'West Highland White Terrier',
    'scottie' => 'Scottish Terrier',
    'staffie' => 'Staffordshire Bull Terrier',
    'poodle toy' => 'Toy Poodle',
    'toy poodle' => 'Toy Poodle',
    'poodle miniature' => 'Miniature Poodle',
    'mini poodle' => 'Miniature Poodle',
    'poodle standard' => 'Standard Poodle',
    'standard poodle' => 'Standard Poodle',
    'aussie' => 'Australian Shepherd',
    'sheltie' => 'Shetland Sheepdog',
    'dobie' => 'Doberman Pinscher',
    'dobermann' => 'Doberman Pinscher',
    'rottie' => 'Rottweiler',
    'mini aussie' => 'Miniature American Shepherd',
    'mini american shepherd' => 'Miniature American Shepherd',
    'mini aussie shepherd' => 'Miniature American Shepherd',
    'jack russell' => 'Parson Russell Terrier',
    'jrt' => 'Parson Russell Terrier',
    'american cocker' => 'American Cocker Spaniel',
    'cocker' => 'Cocker Spaniel',
    'brittany spaniel' => 'Brittany',
    'doxie' => 'Dachshund',
    'wiener dog' => 'Dachshund',
    'weiner dog' => 'Dachshund',
    'sausage dog' => 'Dachshund',
    'pembroke corgi' => 'Pembroke Welsh Corgi',
    'cardigan corgi' => 'Cardigan Welsh Corgi',
    'boston' => 'Boston Terrier',
    'crestie' => 'Chinese Crested',
    'shiba' => 'Shiba Inu',
    'toy manchester terrier' => 'Manchester Terrier (Toy)',
    'manchester toy' => 'Manchester Terrier (Toy)',
    'russian toy terrier' => 'Russian Toy',
    'biewer yorkie' => 'Biewer Terrier',
    'peke' => 'Pekingese',
    'bichon' => 'Bichon Frise',
    'yorkie' => 'Yorkshire Terrier',
    'pom' => 'Pomeranian',
    'chi' => 'Chihuahua',
    'basset' => 'Basset Hound',
    'greyhound' => 'Greyhound',
    'whip' => 'Whippet',
    'cavalier' => 'Cavalier King Charles Spaniel',
    'king charles' => 'English Toy Spaniel',
    'silky terrier' => 'Silky Terrier',
    'toy fox' => 'Toy Fox Terrier',
    'russkiy toy' => 'Russian Toy',
    'silky' => 'Silky Terrier',
];
foreach ($commonBreedAliasSeeds as $alias => $canonicalBreed) {
    $breedAliasLookup[normalizeBreedQuery($alias)] = $canonicalBreed;
}
$familyOptions = [];
foreach ($allBreeds as $breed) {
    $family = trim((string) ($breed['breed_family'] ?? $breed['group'] ?? ''));
    if ($family !== '') {
        $familyOptions[$family] = true;
    }
}
$familyOptions = array_keys($familyOptions);
sort($familyOptions, SORT_NATURAL | SORT_FLAG_CASE);
$drillFamily = qv($_POST, 'drill_family', 'any');
$drillSize = qv($_POST, 'drill_size', 'any');
$breedQuery = trim((string) ($_POST['breed_query'] ?? ''));
$breedQueryNorm = normalizeBreedQuery($breedQuery);
$breedQueryCanonical = $breedAliasLookup[$breedQueryNorm] ?? $breedQuery;
$breedQueryCanonicalNorm = normalizeBreedQuery($breedQueryCanonical);
$breedFocus = qv($_POST, 'breed_focus', 'all');
$matches = [];

foreach ($allBreeds as $breedName => $breed) {
    $groupText = strtolower((string) ($breed['breed_family'] ?? $breed['group'] ?? ''));
    $breedNameNorm = normalizeBreedQuery((string) $breedName);
    $breedAliases = array_values(array_filter(array_map('normalizeBreedQuery', array_map('trim', (array) ($breed['aliases'] ?? [])))));
    $blob = strtolower(implode(' ', [
        $breedName,
        $breed['group'] ?? '',
        $breed['breed_family'] ?? '',
        implode(' ', (array) ($breed['aliases'] ?? [])),
        $breed['temperament'] ?? '',
        $breed['traits'] ?? '',
        $breed['notes'] ?? '',
        $breed['size'] ?? '',
        $breed['exercise_level'] ?? '',
        $breed['shedding'] ?? '',
    ]));

    $breedFamily = trim((string) ($breed['breed_family'] ?? $breed['group'] ?? ''));
    $score = 0;
    $reasons = [];

    if ($breedQueryCanonicalNorm !== '') {
        if ($breedNameNorm === $breedQueryCanonicalNorm || in_array($breedQueryCanonicalNorm, $breedAliases, true)) {
            $score += 120;
            $reasons[] = 'Exact breed or alias match.';
        } elseif (str_contains($breedNameNorm, $breedQueryCanonicalNorm) || str_contains($blob, $breedQueryCanonicalNorm) || array_reduce($breedAliases, static function (bool $carry, string $alias) use ($breedQueryCanonicalNorm): bool {
            return $carry || str_contains($alias, $breedQueryCanonicalNorm) || str_contains($breedQueryCanonicalNorm, $alias);
        }, false)) {
            $score += 80;
            $reasons[] = 'Matches the breed name you typed.';
        } else {
            $queryTokens = preg_split('/\s+/', $breedQueryCanonicalNorm) ?: [];
            $nameTokens = preg_split('/\s+/', $breedNameNorm) ?: [];
            $hits = 0;
            foreach ($queryTokens as $token) {
                if (strlen($token) < 3) {
                    continue;
                }
                if (in_array($token, $nameTokens, true) || str_contains($blob, $token)) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $score += 12 * $hits;
                $reasons[] = 'Shares words with the breed name you typed.';
            }
        }
    }

    $goalBonuses = [
        'service_access' => [
            'positive' => ['retriever', 'poodle', 'spaniel', 'handler-focused', 'public access', 'trainable', 'steady', 'biddable'],
            'negative' => ['independent', 'guarding', 'vocal', 'reactive', 'intense'],
        ],
        'psychiatric' => [
            'positive' => ['calm', 'gentle', 'people-oriented', 'handler-focused', 'affectionate', 'steady', 'public access'],
            'negative' => ['guarding', 'independent', 'intense', 'reactive'],
        ],
        'medical_alert' => [
            'positive' => ['alert', 'response', 'attentive', 'devoted', 'bonded', 'handler-focused'],
            'negative' => ['independent', 'guarding', 'vocal'],
        ],
        'hearing_alert' => [
            'positive' => ['alert', 'attentive', 'portable', 'responsive', 'handler-focused'],
            'negative' => ['independent', 'guarding', 'scent-driven'],
        ],
        'retrieval' => [
            'positive' => ['retrieving', 'retrieval', 'item delivery', 'trainable', 'eager to please', 'handler-focused'],
            'negative' => ['fragile', 'independent'],
        ],
        'companion' => [
            'positive' => ['gentle', 'affectionate', 'calm', 'people-oriented', 'portable'],
            'negative' => ['intense', 'guarding', 'reactive', 'high drive'],
        ],
    ];

    $goalScore = scoreKeywordMatches($blob, $goalBonuses[$answers['goal']]['positive'], $goalBonuses[$answers['goal']]['negative']);
    if ($goalScore !== 0) {
        $score += $goalScore;
        $reasons[] = $goalScore > 0 ? 'Matches the work type you selected.' : 'Has traits that may be harder for this work type.';
    }

    if ($breedFocus !== 'all') {
        $focusTargets = [
            'public' => ['public access', 'service', 'handler-focused', 'trainable', 'steady', 'biddable'],
            'companion' => ['grounding', 'affectionate', 'people-oriented', 'calm', 'steady'],
            'task' => ['retrieval', 'retrieving', 'item delivery', 'alert', 'response', 'task', 'trainable', 'high drive', 'athletic'],
        ];
        $focusMatch = scoreKeywordMatches($blob, $focusTargets[$breedFocus] ?? []);
        if ($focusMatch !== 0) {
            $score += $focusMatch + 4;
            $reasons[] = 'Matches the focus you selected.';
        }
        if (breedSuggestionFocus($breed) === $breedFocus) {
            $score += 4;
            $reasons[] = 'This breed is a strong match for that focus.';
        }
    }

    $targetSize = pickSizeRank($answers['size']);
    $breedSize = classifyBreedSize((string) ($breed['size'] ?? ''));
    if ($answers['size'] !== 'flexible') {
        if (abs($breedSize - $targetSize) >= 2) {
            continue;
        }
        $score += scoreDistance($breedSize, $targetSize);
        if ($breedSize === $targetSize || abs($breedSize - $targetSize) === 1) {
            $reasons[] = 'Size fits the range you selected.';
        } elseif (abs($breedSize - $targetSize) >= 2) {
            $reasons[] = 'Size is farther from your preference.';
        }
    }

    if ($drillFamily !== 'any' && $drillFamily !== '' && strcasecmp($breedFamily, $drillFamily) !== 0) {
        continue;
    }

    if ($drillSize !== 'any' && $drillSize !== '') {
        $drillSizeRank = pickSizeRank($drillSize);
        if (abs($breedSize - $drillSizeRank) >= 2) {
            continue;
        }
    }

    $targetEnergy = match ($answers['energy']) {
        'low' => 1,
        'moderate' => 2,
        'high' => 3,
        'very_high' => 4,
        default => 2,
    };
    $breedEnergy = classifyBreedEffort((string) ($breed['exercise_level'] ?? ''));
    $energyDiff = abs($breedEnergy - $targetEnergy);
    $score += match (true) {
        $energyDiff === 0 => 3,
        $energyDiff === 1 => 1,
        $energyDiff === 2 => -1,
        default => -3,
    };
    if ($energyDiff <= 1) {
        $reasons[] = 'Exercise needs are close to your capacity.';
    } elseif ($energyDiff >= 2) {
        $reasons[] = 'Exercise needs may be harder to support.';
    }

    $groomingTolerance = match ($answers['grooming']) {
        'low' => 1,
        'moderate' => 2,
        'high' => 3,
        default => 2,
    };
    $breedShedding = classifyBreedShedding((string) ($breed['shedding'] ?? ''));
    $groomingDiff = abs($breedShedding - $groomingTolerance);
    $score += match (true) {
        $groomingDiff === 0 => 2,
        $groomingDiff === 1 => 1,
        $groomingDiff === 2 => -1,
        default => -2,
    };
    if ($groomingDiff <= 1) {
        $reasons[] = 'Coat maintenance is a workable match.';
    } elseif ($groomingDiff >= 2) {
        $reasons[] = 'Coat upkeep may be more than you want.';
    }

    $publicNeed = [
        'quiet' => ['calm', 'gentle', 'reserved', 'low-energy', 'steady'],
        'some' => ['trainable', 'steady', 'handler-focused', 'people-oriented'],
        'busy' => ['public access', 'neutrality', 'steady', 'trainable', 'handler-focused'],
        'always' => ['public access', 'neutrality', 'steady', 'handler-focused', 'calm'],
    ];
    $publicCautions = ['sensitive', 'independent', 'vocal', 'guarding', 'intense', 'reactive', 'environmentally aware'];
    $score += scoreKeywordMatches($blob, $publicNeed[$answers['public']] ?? [], $publicCautions);
    if (containsAny($blob, $publicNeed[$answers['public']] ?? [])) {
        $reasons[] = 'Public-access temperament lines up with your environment.';
    }

    $experiencePositive = [
        'new' => ['calm', 'gentle', 'biddable', 'handler-focused', 'steady', 'people-oriented'],
        'some' => ['trainable', 'responsive', 'steady', 'handler-focused'],
        'experienced' => ['intense', 'independent', 'high drive', 'confident', 'alert', 'task-oriented'],
    ];
    $experienceNegative = [
        'new' => ['intense', 'independent', 'guarding', 'reactive'],
        'some' => ['guarding', 'very high', 'high drive'],
        'experienced' => ['fragile', 'very soft'],
    ];
    $score += scoreKeywordMatches($blob, $experiencePositive[$answers['experience']] ?? [], $experienceNegative[$answers['experience']] ?? []);

    $sensitivityPositive = [
        'soft' => ['gentle', 'calm', 'sensitive', 'people-oriented', 'affectionate'],
        'balanced' => ['trainable', 'steady', 'responsive', 'handler-focused'],
        'drive_ok' => ['intense', 'high drive', 'alert', 'independent', 'athletic'],
    ];
    $sensitivityNegative = [
        'soft' => ['intense', 'guarding', 'reactive', 'vocal'],
        'balanced' => ['very high', 'extreme'],
        'drive_ok' => [],
    ];
    $score += scoreKeywordMatches($blob, $sensitivityPositive[$answers['sensitivity']] ?? [], $sensitivityNegative[$answers['sensitivity']] ?? []);

    if ($groupText !== '') {
        if ($answers['goal'] === 'service_access' && containsAny($groupText, ['retriever', 'spaniel', 'poodle', 'designer', 'hybrid'])) {
            $score += 2;
        }
        if ($answers['goal'] === 'psychiatric' && containsAny($groupText, ['retriever', 'poodle', 'toy', 'spaniel'])) {
            $score += 2;
        }
        if ($answers['goal'] === 'medical_alert' && containsAny($groupText, ['poodle', 'toy', 'retriever', 'spaniel', 'designer'])) {
            $score += 2;
        }
        if ($answers['goal'] === 'hearing_alert' && containsAny($groupText, ['spaniel', 'toy', 'retriever'])) {
            $score += 2;
        }
        if ($answers['goal'] === 'retrieval' && containsAny($groupText, ['retriever', 'sporting', 'poodle', 'working'])) {
            $score += 2;
        }
        if ($answers['goal'] === 'companion' && containsAny($groupText, ['toy', 'companion', 'retriever', 'spaniel'])) {
            $score += 1;
        }
    }

    if (str_contains($blob, 'can work calmly without distress') || str_contains($blob, 'very trainable')) {
        $score += 1;
        $reasons[] = 'The breed notes describe a workable temperament.';
    }
    if (str_contains($blob, 'public access') || str_contains($blob, 'neutrality')) {
        $score += 1;
    }
    if (str_contains($blob, 'high grooming') && $answers['grooming'] === 'low') {
        $score -= 2;
    }
    if (str_contains($blob, 'very high') && $answers['energy'] === 'low') {
        $score -= 2;
    }

    $matches[] = [
        'breed' => $breedName,
        'score' => $score,
        'group' => $breed['breed_family'] ?? $breed['group'] ?? 'Breed',
        'summary' => trim((string) ($breed['temperament'] ?? '')),
        'traits' => trim((string) ($breed['traits'] ?? '')),
        'notes' => trim((string) ($breed['notes'] ?? '')),
        'size' => trim((string) ($breed['size'] ?? '')),
        'exercise' => trim((string) ($breed['exercise_level'] ?? '')),
        'shedding' => trim((string) ($breed['shedding'] ?? '')),
        'reasons' => array_slice(array_unique($reasons), 0, 4),
    ];
}

usort($matches, static function (array $a, array $b): int {
    return ($b['score'] <=> $a['score']) ?: strcmp($a['breed'], $b['breed']);
});

$topBreeds = array_slice($matches, 0, 12);

$familyScores = [];
foreach ($matches as $match) {
    $family = $match['group'] ?: 'Breed';
    if (!isset($familyScores[$family])) {
        $familyScores[$family] = ['score' => 0, 'count' => 0, 'best' => $match];
    }
    $familyScores[$family]['score'] += $match['score'];
    $familyScores[$family]['count']++;
    if ($match['score'] > $familyScores[$family]['best']['score']) {
        $familyScores[$family]['best'] = $match;
    }
}

foreach ($familyScores as $family => &$row) {
    $row['average'] = $row['count'] ? round($row['score'] / $row['count'], 1) : 0;
}
unset($row);
uasort($familyScores, static function (array $a, array $b): int {
    return ($b['average'] <=> $a['average']) ?: strcmp($a['best']['breed'], $b['best']['breed']);
});
$topFamilies = array_slice($familyScores, 0, 6, true);
$familyBrowseBreeds = [];
$familyRelatedBreeds = [];
$matchIndex = [];
foreach ($matches as $match) {
    $matchIndex[$match['breed']] = $match;
}
if ($drillFamily !== 'any' && $drillFamily !== '') {
    $familyCandidateNames = familyBrowseCandidateNames($allBreeds, $drillFamily);
    foreach ($familyCandidateNames as $familyBreedName) {
        $breed = $allBreeds[$familyBreedName] ?? [];
        if ($breed === []) {
            continue;
        }
        $match = $matchIndex[$familyBreedName] ?? null;
        $familyBrowseBreeds[] = [
            'breed' => $familyBreedName,
            'score' => (int) ($match['score'] ?? 0),
            'group' => trim((string) ($breed['breed_family'] ?? $breed['group'] ?? 'Breed')),
            'size' => trim((string) ($breed['size'] ?? '')),
            'notes' => trim((string) ($breed['notes'] ?? '')),
            'summary' => trim((string) ($breed['temperament'] ?? '')),
            'reasons' => $match['reasons'] ?? ['Shown because it belongs to this family.'],
        ];
    }
    usort($familyBrowseBreeds, static function (array $a, array $b): int {
        return ($b['score'] <=> $a['score']) ?: strcmp($a['breed'], $b['breed']);
    });
    $familyRelatedSource = familyRelatedBreeds($drillFamily);
    foreach ($familyRelatedSource as $relatedBreed) {
        if (!isset($allBreeds[$relatedBreed])) {
            continue;
        }
        $breed = $allBreeds[$relatedBreed];
        $familyRelatedBreeds[] = [
            'breed' => $relatedBreed,
            'group' => trim((string) ($breed['breed_family'] ?? $breed['group'] ?? 'Breed')),
            'size' => trim((string) ($breed['size'] ?? '')),
            'notes' => trim((string) ($breed['notes'] ?? '')),
            'summary' => trim((string) ($breed['temperament'] ?? '')),
        ];
    }
}

$resultReady = $_SERVER['REQUEST_METHOD'] === 'POST';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Breed Questionnaire · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.question-shell{max-width:1080px;margin:0 auto;padding:1rem 1rem 4rem}.hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}.card-soft{border:1px solid rgba(15,23,42,.08);border-radius:18px;box-shadow:0 8px 18px rgba(15,23,42,.08)}.question-grid{display:grid;gap:1rem}@media(min-width:960px){.question-grid{grid-template-columns:1.1fr .9fr}}.form-select,.form-control{border-radius:14px}.result-grid{display:grid;gap:1rem}@media(min-width:900px){.result-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}.result-item{border:1px solid rgba(15,23,42,.08);border-radius:16px;padding:1rem;background:#fff}.rank{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:999px;background:#e0f2fe;color:#075985;font-weight:900;margin-right:.65rem;flex:0 0 auto}.subtle{color:#64748b}.pill{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.75rem;font-weight:900;background:#eef2ff;color:#4338ca}.family-card{border:1px solid rgba(15,23,42,.08);border-radius:16px;padding:1rem;background:#fff}.list-tight{margin-bottom:0;padding-left:1.1rem}.question-note{border-left:4px solid #0d6efd;background:#eff6ff;border-radius:14px;padding:.85rem}.badge-line{display:flex;flex-wrap:wrap;gap:.35rem}
.breed-live{display:grid;gap:.5rem}.breed-live-item{display:block;width:100%;text-align:left;border:1px solid rgba(15,23,42,.08);background:#fff;border-radius:14px;padding:.75rem .85rem;box-shadow:0 4px 10px rgba(15,23,42,.04)}.breed-live-item:hover,.breed-live-item:focus{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.12);outline:0}.breed-live-name{font-weight:800;color:#0f172a}.breed-live-meta{font-size:.8rem;color:#64748b;margin-top:.1rem}.breed-live-best{font-size:.8rem;color:#0f766e;font-weight:800;margin-top:.1rem}.breed-live-note{font-size:.85rem;color:#334155;margin-top:.2rem;line-height:1.25}.breed-focus-group{display:inline-flex;flex-wrap:wrap;gap:.35rem;padding:.35rem;background:#eef6ff;border:1px solid rgba(13,110,253,.16);border-radius:999px}.breed-focus-btn{border-radius:999px !important;min-width:0;padding:.4rem .75rem;line-height:1}.breed-focus-btn.active{background:#0d6efd !important;border-color:#0d6efd !important;color:#fff !important;box-shadow:0 4px 10px rgba(13,110,253,.16)}.breed-focus-btn:not(.active){background:#fff;color:#0d6efd;border-color:rgba(13,110,253,.2)}.breed-focus-count{font-size:.75rem;font-weight:700;color:#64748b;margin-left:.25rem}
.breed-advanced{border:1px solid rgba(15,23,42,.1);border-radius:16px;background:#f8fafc;padding:1rem}.breed-advanced summary{cursor:pointer;list-style:none}.breed-advanced summary::-webkit-details-marker{display:none}.breed-advanced summary::after{content:"";display:inline-block;width:.55rem;height:.55rem;margin-left:.5rem;border-right:2px solid #475569;border-bottom:2px solid #475569;transform:rotate(45deg) translateY(-1px);transition:transform .15s ease}.breed-advanced[open] summary::after{transform:rotate(-135deg) translateY(1px)}.breed-advanced-note{color:#64748b;font-size:.9rem;margin-top:.35rem}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<header class="hero">
    <div class="question-shell px-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw public tool</div>
                <h1 class="mb-2">Breed Questionnaire</h1>
                <p class="mb-0 opacity-75">Answer a few questions and get breed ideas to research, not a guarantee or a substitute for individual assessment.</p>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="pill">No account needed</span>
                    <span class="pill">Use before signing up</span>
                </div>
            </div>
            <a class="btn btn-light btn-sm" href="login.php">Handler login</a>
        </div>
    </div>
</header>

<main class="question-shell">
    <div class="question-grid">
        <section class="card card-soft">
            <div class="card-body">
                <h2 class="h5 mb-3">Your answers</h2>
                <form method="post" class="row g-3" id="breed-questionnaire-form">
                    <input type="hidden" name="breed_focus" value="<?= e($breedFocus) ?>">
                    <div class="col-12">
                        <label class="form-label fw-bold">What are you trying to do?</label>
                        <select class="form-select" name="goal">
                            <option value="service_access" <?= $answers['goal'] === 'service_access' ? 'selected' : '' ?>>Public access / full service work</option>
                            <option value="psychiatric" <?= $answers['goal'] === 'psychiatric' ? 'selected' : '' ?>>Psychiatric support / grounding</option>
                            <option value="medical_alert" <?= $answers['goal'] === 'medical_alert' ? 'selected' : '' ?>>Medical alert / response</option>
                            <option value="hearing_alert" <?= $answers['goal'] === 'hearing_alert' ? 'selected' : '' ?>>Hearing alert / response</option>
                            <option value="retrieval" <?= $answers['goal'] === 'retrieval' ? 'selected' : '' ?>>Retrieval / task work</option>
                            <option value="companion" <?= $answers['goal'] === 'companion' ? 'selected' : '' ?>>Companion / family fit</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Breed name to research</label>
                        <input class="form-control" name="breed_query" list="breed-query-options" value="<?= e($breedQuery) ?>" placeholder="Example: Cavalier King Charles Spaniel" autocomplete="off">
                        <datalist id="breed-query-options">
                            <?php foreach ($breedSuggestions as $breedName): ?>
                                <option value="<?= e($breedName) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div id="breed-query-hint" class="form-text">Optional. If you already have a breed in mind, type it here and the results will prioritize it.</div>
                        <div class="breed-focus-group mt-2" role="radiogroup" aria-label="Breed focus filter">
                            <button type="button" class="btn btn-sm breed-focus-btn<?= $breedFocus === 'all' ? ' active' : '' ?>" data-focus="all">Any</button>
                            <button type="button" class="btn btn-sm breed-focus-btn<?= $breedFocus === 'public' ? ' active' : '' ?>" data-focus="public">Public access</button>
                            <button type="button" class="btn btn-sm breed-focus-btn<?= $breedFocus === 'companion' ? ' active' : '' ?>" data-focus="companion">Companion</button>
                            <button type="button" class="btn btn-sm breed-focus-btn<?= $breedFocus === 'task' ? ' active' : '' ?>" data-focus="task">Task work</button>
                        </div>
                        <div id="breed-query-live" class="breed-live mt-2"></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold">Preferred size</label>
                        <select class="form-select" name="size">
                            <option value="flexible" <?= $answers['size'] === 'flexible' ? 'selected' : '' ?>><?= e($sizeLabel['flexible']) ?></option>
                            <option value="toy" <?= $answers['size'] === 'toy' ? 'selected' : '' ?>><?= e($sizeLabel['toy']) ?></option>
                            <option value="small" <?= $answers['size'] === 'small' ? 'selected' : '' ?>><?= e($sizeLabel['small']) ?></option>
                            <option value="medium" <?= $answers['size'] === 'medium' ? 'selected' : '' ?>><?= e($sizeLabel['medium']) ?></option>
                            <option value="large" <?= $answers['size'] === 'large' ? 'selected' : '' ?>><?= e($sizeLabel['large']) ?></option>
                            <option value="giant" <?= $answers['size'] === 'giant' ? 'selected' : '' ?>><?= e($sizeLabel['giant']) ?></option>
                        </select>
                        <div class="form-text">These are approximate adult weight ranges, not exact limits.</div>
                    </div>
                    <div class="col-12">
                        <details class="breed-advanced">
                            <summary class="fw-bold">Advanced</summary>
                            <div class="breed-advanced-note">Optional filters for handlers who already know what they want to narrow out.</div>
                            <div class="row g-3 mt-1">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Exercise you can support</label>
                                    <select class="form-select" name="energy">
                                        <option value="low" <?= $answers['energy'] === 'low' ? 'selected' : '' ?>>Low</option>
                                        <option value="moderate" <?= $answers['energy'] === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                                        <option value="high" <?= $answers['energy'] === 'high' ? 'selected' : '' ?>>High</option>
                                        <option value="very_high" <?= $answers['energy'] === 'very_high' ? 'selected' : '' ?>>Very high</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Grooming tolerance</label>
                                    <select class="form-select" name="grooming">
                                        <option value="low" <?= $answers['grooming'] === 'low' ? 'selected' : '' ?>>Low</option>
                                        <option value="moderate" <?= $answers['grooming'] === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                                        <option value="high" <?= $answers['grooming'] === 'high' ? 'selected' : '' ?>>High</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Public exposure</label>
                                    <select class="form-select" name="public">
                                        <option value="quiet" <?= $answers['public'] === 'quiet' ? 'selected' : '' ?>>Mostly quiet settings</option>
                                        <option value="some" <?= $answers['public'] === 'some' ? 'selected' : '' ?>>Some errands</option>
                                        <option value="busy" <?= $answers['public'] === 'busy' ? 'selected' : '' ?>>Busy daily public work</option>
                                        <option value="always" <?= $answers['public'] === 'always' ? 'selected' : '' ?>>Constant public access</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Your training experience</label>
                                    <select class="form-select" name="experience">
                                        <option value="new" <?= $answers['experience'] === 'new' ? 'selected' : '' ?>>New to dog training</option>
                                        <option value="some" <?= $answers['experience'] === 'some' ? 'selected' : '' ?>>Some experience</option>
                                        <option value="experienced" <?= $answers['experience'] === 'experienced' ? 'selected' : '' ?>>Experienced</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Sensitivity / drive tolerance</label>
                                    <select class="form-select" name="sensitivity">
                                        <option value="soft" <?= $answers['sensitivity'] === 'soft' ? 'selected' : '' ?>>Soft / sensitive</option>
                                        <option value="balanced" <?= $answers['sensitivity'] === 'balanced' ? 'selected' : '' ?>>Balanced</option>
                                        <option value="drive_ok" <?= $answers['sensitivity'] === 'drive_ok' ? 'selected' : '' ?>>Drive is okay</option>
                                    </select>
                                </div>
                                <div class="col-12 border rounded-4 p-3 bg-white">
                                    <div class="fw-bold mb-2">Drill-down mode</div>
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-bold">Breed family</label>
                                            <select class="form-select" name="drill_family">
                                                <option value="any" <?= $drillFamily === 'any' ? 'selected' : '' ?>>Any family</option>
                                                <?php foreach ($familyOptions as $family): ?>
                                                    <option value="<?= e($family) ?>" <?= $drillFamily === $family ? 'selected' : '' ?>><?= e($family) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-bold">Drill size</label>
                                            <select class="form-select" name="drill_size">
                                                <option value="any" <?= $drillSize === 'any' ? 'selected' : '' ?>>Any size</option>
                                                <option value="toy" <?= $drillSize === 'toy' ? 'selected' : '' ?>><?= e($sizeLabel['toy']) ?></option>
                                                <option value="small" <?= $drillSize === 'small' ? 'selected' : '' ?>><?= e($sizeLabel['small']) ?></option>
                                                <option value="medium" <?= $drillSize === 'medium' ? 'selected' : '' ?>><?= e($sizeLabel['medium']) ?></option>
                                                <option value="large" <?= $drillSize === 'large' ? 'selected' : '' ?>><?= e($sizeLabel['large']) ?></option>
                                                <option value="giant" <?= $drillSize === 'giant' ? 'selected' : '' ?>><?= e($sizeLabel['giant']) ?></option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text">Use this when you already have a broad fit and want to narrow the list to a family or working size.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100" type="submit">Show breed ideas</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card card-soft">
            <div class="card-body">
                <h2 class="h5 mb-3">How to use this</h2>
                <p class="text-muted mb-3">These are research suggestions only. Breed name alone does not decide suitability. Individual temperament, health, recovery, public neutrality, and task aptitude matter more than the label. You can use this tool with or without a GuidePaw account.</p>
                <div class="question-note mb-3">
                    <strong>Best use:</strong> narrow the list before you start looking at breeder, rescue, or program-specific dogs.
                </div>
                <div class="badge-line">
                    <span class="pill"><?= e($goalLabel[$answers['goal']] ?? 'Research mode') ?></span>
                    <?php if ($breedQuery !== ''): ?>
                        <span class="pill"><?= e($breedQuery) ?></span>
                    <?php endif; ?>
                    <?php if ($breedFocus !== 'all'): ?>
                        <span class="pill"><?= e(ucfirst($breedFocus)) ?> focus</span>
                    <?php endif; ?>
                    <span class="pill"><?= e($sizeLabel[$answers['size']] ?? ucfirst(str_replace('_', ' ', $answers['size']))) ?></span>
                    <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $answers['energy']))) ?> energy</span>
                    <span class="pill"><?= e(ucfirst($answers['grooming'])) ?> grooming</span>
                </div>
            </div>
        </section>
    </div>

    <?php if ($resultReady): ?>
        <section class="card card-soft mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Top breed ideas</h2>
                <div class="result-grid">
                    <?php foreach ($topBreeds as $index => $match): ?>
                        <article class="result-item">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <div class="rank"><?= (int) ($index + 1) ?></div>
                                <div>
                                    <div class="fw-bold"><?= e($match['breed']) ?></div>
                                    <div class="subtle small"><?= e($match['group']) ?> · Score <?= (int) $match['score'] ?></div>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">
                                <?= e($match['summary'] ?: 'Breed summary not available.') ?>
                            </div>
                            <div class="small mb-2">
                                <strong>Traits:</strong> <?= e($match['traits'] ?: 'Not listed') ?>
                            </div>
                            <div class="small mb-2">
                                <strong>Watch:</strong> <?= e($match['notes'] ?: 'No extra notes.') ?>
                            </div>
                            <?php if ($match['reasons']): ?>
                                <ul class="small list-tight">
                                    <?php foreach ($match['reasons'] as $reason): ?>
                                        <li><?= e($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card card-soft mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Families to research next</h2>
                <div class="result-grid">
                    <?php foreach ($topFamilies as $familyName => $family): ?>
                        <article class="family-card">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="fw-bold"><?= e($familyName) ?></div>
                                    <div class="subtle small">Average fit score <?= e((string) $family['average']) ?></div>
                                </div>
                                <span class="pill"><?= e($family['best']['breed']) ?></span>
                            </div>
                            <div class="small text-muted mb-2"><?= e(explainMatches($family['best']['reasons'])) ?></div>
                            <div class="small"><strong>Best candidate in this group:</strong> <?= e($family['best']['breed']) ?></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-browse-family="<?= e($familyName) ?>">Browse this family</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if ($drillFamily !== 'any' && $drillFamily !== ''): ?>
            <section class="card card-soft mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                        <div>
                            <h2 class="h5 mb-1">Breeds in <?= e($drillFamily) ?></h2>
                            <div class="text-muted small">Pick a breed from this family to keep narrowing the list.</div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-clear-family>Back to all families</button>
                    </div>
                    <?php if ($familyBrowseBreeds): ?>
                        <div class="result-grid">
                            <?php foreach ($familyBrowseBreeds as $match): ?>
                                <article class="family-card">
                                    <div class="fw-bold"><?= e($match['breed']) ?></div>
                                    <div class="subtle small mb-2"><?= e($match['group']) ?> · <?= e($match['size'] ?: 'Size not listed') ?> · Score <?= (int) $match['score'] ?></div>
                                    <div class="small text-muted mb-2"><?= e($match['notes'] ?: 'No notes available.') ?></div>
                                    <button type="button" class="btn btn-primary btn-sm" data-pick-breed="<?= e($match['breed']) ?>">Research this breed</button>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="question-note">No breeds matched that family with the current size and fit filters. Try <strong>Any size</strong> or a different advanced filter, or click <strong>Back to all families</strong>.</div>
                    <?php endif; ?>
                    <?php if ($familyRelatedBreeds): ?>
                        <div class="mt-4">
                            <h3 class="h6 mb-3">Related breeds to compare</h3>
                            <div class="result-grid">
                                <?php foreach ($familyRelatedBreeds as $match): ?>
                                    <article class="family-card">
                                        <div class="fw-bold"><?= e($match['breed']) ?></div>
                                        <div class="subtle small mb-2"><?= e($match['group']) ?> · <?= e($match['size'] ?: 'Size not listed') ?></div>
                                        <div class="small text-muted mb-2"><?= e($match['notes'] ?: $match['summary'] ?: 'No extra notes available.') ?></div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-pick-breed="<?= e($match['breed']) ?>">Research this breed</button>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script>
(() => {
    const input = document.querySelector('input[name="breed_query"]');
    const form = document.getElementById('breed-questionnaire-form');
    const drillFamilySelect = document.querySelector('select[name="drill_family"]');
    const focusInput = document.querySelector('input[name="breed_focus"]');
    const hint = document.getElementById('breed-query-hint');
    const live = document.getElementById('breed-query-live');
    const focusButtons = Array.from(document.querySelectorAll('[data-focus]'));
    const familyButtons = Array.from(document.querySelectorAll('[data-browse-family]'));
    const breedButtons = Array.from(document.querySelectorAll('[data-pick-breed]'));
    const clearFamilyButton = document.querySelector('[data-clear-family]');
    if (!input || !form || !drillFamilySelect || !focusInput || !hint || !live || !focusButtons.length) return;

    const breedItems = <?= json_encode($breedSuggestionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const aliasLookup = <?= json_encode($breedAliasLookup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let activeFocus = 'all';

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const setActiveFocus = (focus) => {
        activeFocus = focus;
        focusInput.value = focus;
        focusButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-focus') === focus;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        render();
    };

    focusButtons.forEach((btn) => {
        btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
        btn.addEventListener('click', () => setActiveFocus(btn.getAttribute('data-focus') || 'all'));
    });

    familyButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            drillFamilySelect.value = btn.getAttribute('data-browse-family') || 'any';
            form.requestSubmit();
        });
    });

    breedButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            input.value = btn.getAttribute('data-pick-breed') || '';
            form.requestSubmit();
        });
    });

    if (clearFamilyButton) {
        clearFamilyButton.addEventListener('click', () => {
            drillFamilySelect.value = 'any';
            form.requestSubmit();
        });
    }

    activeFocus = focusInput.value || 'all';

    const render = () => {
        const raw = input.value.trim();
        const query = normalize(raw);
        if (!query) {
            hint.textContent = 'Optional. If you already have a breed in mind, type it here and the results will prioritize it.';
            live.innerHTML = '';
            return;
        }

        const canonical = aliasLookup[query] || null;
        const ranked = breedItems
            .map((item) => ({ ...item, norm: normalize(item.name) }))
            .filter((entry) => entry.norm.includes(query) || (canonical && entry.name === canonical))
            .filter((entry) => activeFocus === 'all' || entry.focus === activeFocus)
            .sort((a, b) => {
                if (a.name === canonical) return -1;
                if (b.name === canonical) return 1;
                const aExact = a.norm === query ? 0 : 1;
                const bExact = b.norm === query ? 0 : 1;
                if (aExact !== bExact) return aExact - bExact;
                return a.name.localeCompare(b.name);
            })
            .slice(0, 4)
            .map((entry) => entry);

        if (!ranked.length && canonical) {
            hint.textContent = `Matched: ${canonical}`;
            live.innerHTML = '';
            return;
        }

        if (!ranked.length) {
            hint.textContent = activeFocus === 'all'
                ? 'No close breed matches yet. Try a longer breed name or the breed family.'
                : 'No close breed matches for that filter yet. Try Any or a different filter.';
            live.innerHTML = '';
            return;
        }

        if (ranked[0] && normalize(ranked[0].name) === query) {
            hint.textContent = `Matched: ${ranked[0].name}`;
        } else {
            hint.textContent = `Suggestions: ${ranked.map((entry) => entry.name).join(' · ')}`;
        }
        live.innerHTML = ranked.map((entry) => {
            const note = entry.notes || entry.traits || 'No notes available.';
            const meta = [entry.group, entry.size].filter(Boolean).join(' · ');
            return `
                <button type="button" class="breed-live-item" data-breed="${entry.name.replace(/"/g, '&quot;')}">
                    <div class="breed-live-name">${entry.name}</div>
                    <div class="breed-live-meta">${meta}</div>
                    <div class="breed-live-best">${entry.best_for || 'Best for broader research'}</div>
                    <div class="breed-live-note">${note}</div>
                </button>
            `;
        }).join('');
        live.querySelectorAll('[data-breed]').forEach((btn) => {
            btn.addEventListener('click', () => {
                input.value = btn.getAttribute('data-breed') || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            });
        });
    };

    input.addEventListener('input', render);
    input.addEventListener('change', render);
    render();
})();
</script>
</body>
</html>
