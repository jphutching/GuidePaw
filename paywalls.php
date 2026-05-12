<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/paywalls.php';
require_once __DIR__ . '/includes/paywall_catalog.php';

checkLogin();
gpPaywallCatalogEnsureSchema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = getUserRecord($pdo, $userId) ?: [];
$currentTier = gpUserTier($user);
$tierDefinitions = gpPaywallPlanRows($pdo);
$serviceDefinitions = gpPaywallServiceRows($pdo);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plans & Access | <?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
        .wrap { max-width: 1160px; margin: 0 auto; padding: 1rem 1rem 4rem; }
        .hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color:#fff; border-radius: 0 0 28px 28px; padding: 1.2rem 1rem 1.45rem; box-shadow: 0 10px 24px rgba(15,23,42,.18); }
        .plan-card { border: 1px solid rgba(15,23,42,.08); border-radius: 18px; background:#fff; box-shadow: 0 8px 20px rgba(15,23,42,.06); height:100%; }
        .plan-badge { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.25rem .65rem; background:#eef2ff; color:#4338ca; font-size:.78rem; font-weight:900; }
        .plan-list { padding-left: 1.15rem; margin-bottom:0; }
        .current-box { border: 1px dashed rgba(13,110,253,.32); background:#f8fbff; border-radius:16px; padding:1rem; }
        .muted { color:#64748b; }
    </style>
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<header class="hero">
    <div class="wrap px-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw plans and access</div>
                <h1 class="mb-2">Plans & Access</h1>
                <p class="mb-0 opacity-75">Free keeps the core handler workflow and one dog. Plus and Pro unlock the premium training surfaces, and a la carte services can be added per dog or per account.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-light btn-sm" href="contact_us.php">Contact Us</a>
                <?php if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()): ?>
                    <a class="btn btn-outline-light btn-sm" href="admin_paywall_catalog.php">Manage catalog</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="wrap">
    <div class="current-box mb-3">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small text-uppercase text-muted fw-semibold">Your current plan</div>
                <div class="fw-bold fs-5"><?= e(gpTierDisplayLabel($currentTier)) ?></div>
                <div class="muted">Stored plan for this account: <?= e($currentTier) ?>.</div>
            </div>
            <?php if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()): ?>
                <span class="badge text-bg-primary align-self-start">Admin access overrides plan gates</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($tierDefinitions as $tier): ?>
            <div class="col-md-4">
                <div class="plan-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="plan-badge"><?= e($tier['label']) ?></div>
                            <h2 class="h5 mt-2 mb-1"><?= e($tier['label']) ?></h2>
                        </div>
                        <?php if ($currentTier === gpNormalizeUserTier((string) ($tier['required_tier'] ?? 'free'))): ?>
                            <span class="badge bg-success">Current</span>
                        <?php endif; ?>
                    </div>
                    <p class="muted mt-2 mb-3"><?= e($tier['summary']) ?></p>
                    <div class="fw-bold mb-2">Included</div>
                    <ul class="plan-list mb-3">
                        <?php foreach (gpPaywallCatalogBullets((string) ($tier['included_text'] ?? '')) as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (gpPaywallCatalogBullets((string) ($tier['locked_text'] ?? ''))): ?>
                        <div class="fw-bold mb-2">Still locked below this plan</div>
                        <ul class="plan-list mb-0">
                            <?php foreach (gpPaywallCatalogBullets((string) ($tier['locked_text'] ?? '')) as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h2 class="h5 mb-2">How the gate works</h2>
            <p class="mb-2">Free users can still use the core app and one dog. When they open a premium page or an add-on service, GuidePaw shows the plan notice and points them back here. Admin accounts can still review and manage everything from User Management.</p>
            <ul class="mb-0">
                <li><strong>Trainer Marketplace</strong> is on the Plus gate.</li>
                <li><strong>AI Training Assistant</strong> is on the Pro gate.</li>
                <li><strong>QR Tracking</strong> is sold per dog as a lifetime add-on.</li>
                <li><strong>Extra Dog Slot</strong> keeps the first dog free but allows another dog when purchased.</li>
            </ul>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h2 class="h5 mb-2">A la carte services</h2>
            <p class="mb-3">These are separate from monthly plans. They can be enabled one at a time for a user or for a dog.</p>
            <div class="row g-3">
                <?php foreach ($serviceDefinitions as $service): ?>
                    <div class="col-md-6">
                        <div class="plan-card p-3">
                            <div class="d-flex justify-content-between gap-2 flex-wrap">
                                <div>
                                    <div class="plan-badge"><?= e($service['label']) ?></div>
                                    <h3 class="h5 mt-2 mb-1"><?= e($service['label']) ?></h3>
                                </div>
                                <span class="badge bg-secondary"><?= e(strtoupper((string) ($service['billing_model'] ?? 'service'))) ?></span>
                            </div>
                            <p class="muted mt-2 mb-3"><?= e($service['summary']) ?></p>
                            <div class="small text-muted mb-2">Scope: <?= e((string) ($service['scope'] ?? 'user')) ?> · Price: $<?= e(number_format(((int) ($service['price_cents'] ?? 0)) / 100, 2)) ?></div>
                            <?php if (gpPaywallCatalogBullets((string) ($service['included_text'] ?? ''))): ?>
                                <div class="fw-bold mb-2">Includes</div>
                                <ul class="plan-list mb-0">
                                    <?php foreach (gpPaywallCatalogBullets((string) ($service['included_text'] ?? '')) as $item): ?>
                                        <li><?= e($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>
