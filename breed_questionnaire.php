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

$defaults = [
    'goal' => 'service_access',
    'size' => 'medium',
    'energy' => 'moderate',
    'grooming' => 'moderate',
    'public' => 'busy',
    'experience' => 'some',
    'sensitivity' => 'balanced',
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

$allBreeds = getDogBreedsCatalog();
$matches = [];

foreach ($allBreeds as $breedName => $breed) {
    $groupText = strtolower((string) ($breed['breed_family'] ?? $breed['group'] ?? ''));
    $blob = strtolower(implode(' ', [
        $breedName,
        $breed['group'] ?? '',
        $breed['breed_family'] ?? '',
        $breed['temperament'] ?? '',
        $breed['traits'] ?? '',
        $breed['notes'] ?? '',
        $breed['size'] ?? '',
        $breed['exercise_level'] ?? '',
        $breed['shedding'] ?? '',
    ]));

    $score = 0;
    $reasons = [];

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

    $targetSize = pickSizeRank($answers['size']);
    $breedSize = classifyBreedSize((string) ($breed['size'] ?? ''));
    if ($answers['size'] !== 'flexible') {
        $score += scoreDistance($breedSize, $targetSize);
        if ($breedSize === $targetSize || abs($breedSize - $targetSize) === 1) {
            $reasons[] = 'Size fits the range you selected.';
        } elseif (abs($breedSize - $targetSize) >= 2) {
            $reasons[] = 'Size is farther from your preference.';
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

$topBreeds = array_slice($matches, 0, 6);

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
$topFamilies = array_slice($familyScores, 0, 4, true);

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
                <form method="post" class="row g-3">
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
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold">Preferred size</label>
                        <select class="form-select" name="size">
                            <option value="flexible" <?= $answers['size'] === 'flexible' ? 'selected' : '' ?>>Flexible</option>
                            <option value="toy" <?= $answers['size'] === 'toy' ? 'selected' : '' ?>>Toy</option>
                            <option value="small" <?= $answers['size'] === 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= $answers['size'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= $answers['size'] === 'large' ? 'selected' : '' ?>>Large</option>
                            <option value="giant" <?= $answers['size'] === 'giant' ? 'selected' : '' ?>>Giant</option>
                        </select>
                    </div>
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
                    <span class="pill"><?= e(ucfirst(str_replace('_', ' ', $answers['size']))) ?> size</span>
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
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
