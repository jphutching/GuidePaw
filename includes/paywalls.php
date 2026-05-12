<?php
declare(strict_types=1);

function gpValidUserTiers(): array
{
    return ['free', 'plus', 'pro'];
}

function gpNormalizeUserTier(?string $tier): string
{
    $tier = strtolower(trim((string) $tier));
    $aliases = [
        'starter' => 'free',
        'basic' => 'free',
        'free' => 'free',
        'plus' => 'plus',
        'premium' => 'pro',
        'pro' => 'pro',
    ];
    if (isset($aliases[$tier])) {
        return $aliases[$tier];
    }
    return in_array($tier, gpValidUserTiers(), true) ? $tier : 'free';
}

function gpTierRank(string $tier): int
{
    return match (gpNormalizeUserTier($tier)) {
        'pro' => 30,
        'plus' => 20,
        default => 10,
    };
}

function gpTierDisplayLabel(?string $tier): string
{
    return match (gpNormalizeUserTier($tier)) {
        'pro' => 'Pro',
        'plus' => 'Plus',
        default => 'Free',
    };
}

function gpTierDefinitions(): array
{
    return [
        'free' => [
            'label' => 'Free',
            'summary' => 'Core handler tools for daily dog work.',
            'highlights' => [
                'Dashboard, dogs, logs, training, care, ADA tools, notifications, and community.',
            ],
            'locked' => [
                'Trainer Marketplace',
                'AI Training Assistant',
                'Tactical Training',
            ],
        ],
        'plus' => [
            'label' => 'Plus',
            'summary' => 'Adds the trainer directory and premium planning tools.',
            'highlights' => [
                'Trainer Marketplace',
                'Everything in Free',
            ],
            'locked' => [
                'AI Training Assistant',
            ],
        ],
        'pro' => [
            'label' => 'Pro',
            'summary' => 'Highest plan for handlers who want the premium training assistant.',
            'highlights' => [
                'Trainer Marketplace',
                'AI Training Assistant',
                'Everything in Plus',
            ],
            'locked' => [],
        ],
    ];
}

function gpUserTierOptions(): array
{
    return [
        'free' => 'Free',
        'plus' => 'Plus',
        'pro' => 'Pro',
    ];
}

function gpEnsureUserTierColumn(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_tier TEXT NOT NULL DEFAULT 'free'");
    $pdo->exec("UPDATE users SET user_tier = 'free' WHERE user_tier IS NULL OR trim(user_tier) = ''");
    $ensured = true;
}

function gpUserTier(array $user): string
{
    return gpNormalizeUserTier($user['user_tier'] ?? 'free');
}

function gpCurrentUserTier(PDO $pdo): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return 'free';
    }

    gpEnsureUserTierColumn($pdo);
    $stmt = $pdo->prepare('SELECT user_tier FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return gpNormalizeUserTier((string) $stmt->fetchColumn());
}

function gpCurrentUserHasTierAccess(PDO $pdo, string $requiredTier): bool
{
    if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()) {
        return true;
    }

    return gpTierRank(gpCurrentUserTier($pdo)) >= gpTierRank($requiredTier);
}

function gpRenderTierAccessNotice(PDO $pdo, string $requiredTier, string $featureName, string $featureSummary = '', array $highlights = [], string $backHref = 'paywalls.php'): void
{
    $requiredTier = gpNormalizeUserTier($requiredTier);
    $planLabel = gpTierDisplayLabel($requiredTier);
    $currentTier = gpTierDisplayLabel(gpCurrentUserTier($pdo));
    $featureSummary = trim($featureSummary);
    if ($featureSummary === '') {
        $featureSummary = 'This feature is locked on your current plan.';
    }

    if (!function_exists('guidepawBrandHeader')) {
        $brandFile = __DIR__ . '/brand_header.php';
        if (is_file($brandFile)) {
            require_once $brandFile;
        }
    }
    if (!function_exists('guidepawBrandHeader')) {
        throw new RuntimeException('GuidePaw brand header is unavailable.');
    }

    $highlightItems = array_values(array_filter(array_map('trim', $highlights), static fn(string $item): bool => $item !== ''));
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(appName()) ?> Plans</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="styles.css" rel="stylesheet">
        <style>
            body { background:#f4f7fb; }
            .paywall-wrap { max-width: 1080px; margin: 0 auto; padding: 1rem 1rem 4rem; }
            .paywall-hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color:#fff; border-radius: 0 0 28px 28px; padding: 1.2rem 1rem 1.4rem; box-shadow: 0 10px 24px rgba(15,23,42,.18); }
            .plan-card { border: 1px solid rgba(15,23,42,.08); border-radius: 18px; background: #fff; box-shadow: 0 8px 20px rgba(15,23,42,.06); height: 100%; }
            .plan-badge { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.25rem .65rem; background:#eef2ff; color:#4338ca; font-size:.78rem; font-weight:900; }
            .plan-list { padding-left: 1.2rem; margin-bottom: 0; }
            .note-box { border:1px dashed rgba(13,110,253,.32); background:#f8fbff; border-radius:16px; padding:1rem; }
        </style>
    </head>
    <body class="pb-5">
    <?php guidepawBrandHeader(); ?>
    <?php require_once __DIR__ . '/beta_banner.php'; ?>
    <?php require_once __DIR__ . '/mobile_nav.php'; ?>
    <header class="paywall-hero">
        <div class="paywall-wrap px-0">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="small opacity-75">GuidePaw plans and access</div>
                    <h1 class="mb-2"><?= e($featureName) ?> is locked</h1>
                    <p class="mb-0 opacity-75"><?= e($featureSummary) ?></p>
                </div>
                <a class="btn btn-light btn-sm" href="<?= e($backHref) ?>">View plans</a>
            </div>
        </div>
    </header>
    <main class="paywall-wrap">
        <div class="alert alert-info">
            Current plan: <strong><?= e($currentTier) ?></strong>. Required plan: <strong><?= e($planLabel) ?></strong>.
            <?php if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()): ?>
                Admin access overrides plan checks.
            <?php endif; ?>
        </div>
        <?php if ($highlightItems): ?>
            <div class="note-box mb-3">
                <div class="fw-bold mb-2">What this plan includes</div>
                <ul class="mb-0">
                    <?php foreach ($highlightItems as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="card plan-card mb-3">
            <div class="card-body">
                <h2 class="h5 mb-2">Why you are seeing this</h2>
                <p class="mb-0">This page is part of the plan gate. Free accounts keep the core GuidePaw workflow. Paid tiers unlock the premium training surfaces, and special access pages may require an application review instead of a simple upgrade.</p>
            </div>
        </div>
    </main>
    </body>
    </html>
    <?php
}
