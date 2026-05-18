<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/paywalls.php';
require_once __DIR__ . '/includes/paywall_catalog.php';
require_once __DIR__ . '/includes/paywall_purchase.php';
require_once __DIR__ . '/includes/stripe_checkout.php';

checkLogin();
gpPaywallCatalogEnsureSchema($pdo);
gpPaywallPurchaseEnsureSchema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = getUserRecord($pdo, $userId) ?: [];
$serviceSlug = strtolower(trim((string) ($_GET['service'] ?? $_POST['service_slug'] ?? '')));
$service = $serviceSlug !== '' ? gpPaywallCatalogRow($pdo, $serviceSlug) : null;
$serviceState = strtolower(trim((string) ($_GET['checkout'] ?? '')));
$serviceMsg = trim((string) ($_GET['msg'] ?? ''));
$serviceError = trim((string) ($_GET['error'] ?? ''));
$activeDogId = getActiveDogId($pdo, $userId);
$accessibleDogs = getAccessibleDogs($pdo, $userId);
$selectedDogId = (int) ($_POST['dog_id'] ?? $_GET['dog_id'] ?? $activeDogId ?? ($accessibleDogs[0]['id'] ?? 0));
$selectedDog = null;
foreach ($accessibleDogs as $dogRow) {
    if ((int) ($dogRow['id'] ?? 0) === $selectedDogId) {
        $selectedDog = $dogRow;
        break;
    }
}

if (!$service || ($service['item_type'] ?? '') !== 'service') {
    http_response_code(404);
    $service = null;
}

$billingModel = strtolower(trim((string) ($service['billing_model'] ?? '')));
$scope = strtolower(trim((string) ($service['scope'] ?? 'user')));
$priceId = trim((string) ($service['stripe_price_id'] ?? ''));
$isCheckoutService = in_array($billingModel, ['lifetime_dog', 'lifetime_user', 'recurring_user'], true);
$isApplicationOnly = $billingModel === 'application_only';
$serviceLabel = $service ? (string) ($service['label'] ?? $serviceSlug) : '';
$serviceSummary = $service ? (string) ($service['summary'] ?? '') : '';
$serviceIncludes = $service ? gpPaywallCatalogBullets((string) ($service['included_text'] ?? '')) : [];
$serviceLocked = $service ? gpPaywallCatalogBullets((string) ($service['locked_text'] ?? '')) : [];
$alreadyActive = false;
if ($service && !$isApplicationOnly) {
    if ($scope === 'dog' && $selectedDogId > 0) {
        $alreadyActive = gpPaywallDogServiceActive($pdo, $selectedDogId, $serviceSlug);
    } elseif ($scope !== 'dog') {
        $alreadyActive = gpPaywallUserServiceActive($pdo, $userId, $serviceSlug);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $service) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($isApplicationOnly) {
        header('Location: paywalls.php?msg=' . rawurlencode('This item needs admin review before checkout.'));
        exit;
    }

    $payloadResult = gpPaywallServiceCheckoutPayload($pdo, $userId, $serviceSlug, $scope === 'dog' ? (int) ($_POST['dog_id'] ?? 0) : null);
    if (empty($payloadResult['ok'])) {
        header('Location: purchase_service.php?service=' . rawurlencode($serviceSlug) . '&error=' . rawurlencode((string) ($payloadResult['error'] ?? 'Unable to start checkout.')));
        exit;
    }

    $checkout = gpStripeCreateCheckoutSession($payloadResult['payload']);
    if (($checkout['ok'] ?? false) && !empty($checkout['url'])) {
        header('Location: ' . $checkout['url']);
        exit;
    }

    header('Location: purchase_service.php?service=' . rawurlencode($serviceSlug) . '&error=' . rawurlencode((string) ($checkout['error'] ?? 'Unable to open checkout.')));
    exit;
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Service | <?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 1rem 1rem 4rem; }
        .hero { background: linear-gradient(135deg, #0d6efd, #0f766e); color:#fff; border-radius: 0 0 28px 28px; padding: 1.2rem 1rem 1.45rem; box-shadow: 0 10px 24px rgba(15,23,42,.18); }
        .panel { border: 1px solid rgba(15,23,42,.08); border-radius: 18px; background:#fff; box-shadow: 0 8px 20px rgba(15,23,42,.06); }
        .badge-pill { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.25rem .7rem; background:#eef2ff; color:#4338ca; font-size:.78rem; font-weight:900; }
        .muted { color:#64748b; }
        .callout { border: 1px dashed rgba(13,110,253,.28); background:#f8fbff; border-radius: 16px; padding: 1rem; }
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
                <div class="small opacity-75">GuidePaw a la carte service</div>
                <h1 class="mb-2"><?= $service ? e($serviceLabel) : 'Service purchase' ?></h1>
                <p class="mb-0 opacity-75"><?= $service ? e($serviceSummary) : 'Pick a service from Plans & Access.' ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-light btn-sm" href="paywalls.php">Back to plans</a>
                <?php if (gpNavIsAdmin()): ?>
                    <a class="btn btn-outline-light btn-sm" href="admin_paywall_catalog.php">Manage catalog</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="wrap">
    <?php if ($serviceState === 'success'): ?>
        <div class="alert alert-success">Checkout completed. The webhook will record the entitlement as soon as Stripe confirms payment.</div>
    <?php elseif ($serviceState === 'cancel'): ?>
        <div class="alert alert-warning">Checkout was canceled before payment completed.</div>
    <?php endif; ?>
    <?php if ($serviceMsg !== ''): ?>
        <div class="alert alert-info"><?= e($serviceMsg) ?></div>
    <?php endif; ?>
    <?php if ($serviceError !== ''): ?>
        <div class="alert alert-danger"><?= e($serviceError) ?></div>
    <?php endif; ?>

    <?php if (!$service): ?>
        <div class="panel p-3">
            <div class="alert alert-warning mb-0">Unknown service. Open a service from the Plans & Access page.</div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <div class="badge-pill mb-2"><?= e($serviceLabel) ?></div>
                            <div class="fw-bold fs-4 mb-1"><?= e($serviceLabel) ?></div>
                            <div class="muted">Scope: <?= e($scope) ?> · Billing: <?= e($billingModel) ?> · Price: $<?= e(number_format(((int) ($service['price_cents'] ?? 0)) / 100, 2)) ?></div>
                        </div>
                        <span class="badge bg-secondary align-self-start"><?= e(strtoupper($billingModel ?: 'service')) ?></span>
                    </div>

                    <?php if ($alreadyActive): ?>
                        <div class="alert alert-success mt-3 mb-0">This add-on is already active.</div>
                    <?php elseif ($isApplicationOnly): ?>
                        <div class="alert alert-light border mt-3 mb-0">This item is application-only. It needs admin review before checkout is available.</div>
                    <?php else: ?>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            <input type="hidden" name="service_slug" value="<?= e($serviceSlug) ?>">
                            <?php if ($scope === 'dog'): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Choose a dog</label>
                                    <select name="dog_id" class="form-select" required>
                                        <option value="">Select a dog</option>
                                        <?php foreach ($accessibleDogs as $dogRow): ?>
                                            <?php $dogId = (int) ($dogRow['id'] ?? 0); ?>
                                            <option value="<?= $dogId ?>" <?= $selectedDogId === $dogId ? 'selected' : '' ?>>
                                                <?= e(trim('#' . $dogId . ' ' . (string) ($dogRow['name'] ?? ''))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">The purchase will attach to the selected dog.</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($scope === 'dog' && !$selectedDogId): ?>
                                <div class="callout">
                                    <strong>Pick a dog first.</strong>
                                    <div class="muted mt-1">This add-on attaches to a specific dog, so choose one above before checkout.</div>
                                </div>
                            <?php elseif (!$isCheckoutService || $priceId === '' || !gpStripeCheckoutConfigured()): ?>
                                <div class="callout">
                                    <strong>Checkout is not configured yet.</strong>
                                    <div class="muted mt-1">Set the Stripe secret key and the service price ID in Render to turn this into a live purchase.</div>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-primary btn-lg">Continue to Stripe Checkout</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="panel p-3 mb-3">
                    <div class="fw-bold mb-2">What this includes</div>
                    <?php if ($serviceIncludes): ?>
                        <ul class="mb-0">
                            <?php foreach ($serviceIncludes as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="muted">No bullet list is configured for this service yet.</div>
                    <?php endif; ?>
                </div>
                <?php if ($serviceLocked): ?>
                    <div class="panel p-3">
                        <div class="fw-bold mb-2">Still locked elsewhere</div>
                        <ul class="mb-0">
                            <?php foreach ($serviceLocked as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($scope === 'dog' && !$selectedDogId): ?>
                    <div class="panel p-3 mt-3">
                        <div class="alert alert-info mb-0">Pick a dog to continue. The first dog stays free, and the add-on will attach to the chosen dog.</div>
                    </div>
                <?php elseif ($scope === 'dog' && $selectedDog): ?>
                    <div class="panel p-3 mt-3">
                        <div class="fw-bold">Selected dog</div>
                        <div class="muted">#<?= (int) ($selectedDog['id'] ?? 0) ?> <?= e((string) ($selectedDog['name'] ?? '')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
