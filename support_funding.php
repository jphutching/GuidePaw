<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/stripe_checkout.php';
checkLogin();

$supportUrl = trim((string) gpEnv('GUIDEPAW_SUPPORT_FUNDING_URL', ''));
$merchUrl = trim((string) gpEnv('GUIDEPAW_MERCH_STORE_URL', ''));
$discordUrl = trim((string) gpEnv('GUIDEPAW_DISCORD_INVITE_URL', ''));
$csrf = generateCsrfToken();
$checkoutState = strtolower(trim((string) ($_GET['checkout'] ?? '')));
$checkoutMessage = trim((string) ($_GET['msg'] ?? ''));
$checkoutError = trim((string) ($_GET['error'] ?? ''));
$supportOptions = [
    'one_time' => [
        'label' => 'One-time support',
        'summary' => 'A single contribution to help keep GuidePaw running.',
        'emoji' => '💙',
        'button' => 'Support once',
        'mode' => 'payment',
        'price_id' => gpStripeSupportPriceId('one_time'),
    ],
    'monthly' => [
        'label' => 'Monthly support',
        'summary' => 'A recurring contribution for ongoing development, hosting, and maintenance.',
        'emoji' => '🔁',
        'button' => 'Support monthly',
        'mode' => 'subscription',
        'price_id' => gpStripeSupportPriceId('monthly'),
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $supportType = strtolower(trim((string) ($_POST['support_type'] ?? '')));
    if (!isset($supportOptions[$supportType])) {
        header('Location: support_funding.php?error=' . rawurlencode('Unknown support option.'));
        exit;
    }

    $option = $supportOptions[$supportType];
    $priceId = trim((string) ($option['price_id'] ?? ''));
    if ($priceId === '') {
        if ($supportUrl !== '') {
            header('Location: ' . $supportUrl);
            exit;
        }
        header('Location: support_funding.php?error=' . rawurlencode('Funding checkout is not configured yet.'));
        exit;
    }

    $currentUser = getUserRecord($pdo, (int) ($_SESSION['user_id'] ?? 0)) ?: [];
    $email = trim((string) ($currentUser['email'] ?? ''));
    $urls = gpStripeSupportCheckoutUrls($supportType);
    $payload = [
        'mode' => (string) ($option['mode'] ?? 'payment'),
        'client_reference_id' => (string) ($_SESSION['user_id'] ?? ''),
        'success_url' => $urls['success_url'],
        'cancel_url' => $urls['cancel_url'],
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => '1',
        'metadata[support_type]' => $supportType,
        'metadata[app]' => appName(),
        'metadata[user_id]' => (string) ($_SESSION['user_id'] ?? ''),
    ];

    if ($email !== '') {
        $payload['customer_email'] = $email;
    }

    $checkout = gpStripeCreateCheckoutSession($payload);
    if (($checkout['ok'] ?? false) && !empty($checkout['url'])) {
        header('Location: ' . $checkout['url']);
        exit;
    }

    $fallback = trim((string) ($checkout['error'] ?? 'Unable to open checkout.'));
    header('Location: support_funding.php?error=' . rawurlencode($fallback));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Support Funding · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .support-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .support-link { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-top:1px solid rgba(15,23,42,.08); color:#1f2937; text-decoration:none; }
    .support-link:first-of-type { border-top:0; }
    .support-muted { color:#6b7280; font-size:.92rem; }
    .support-badge { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.25rem .7rem; background:#eef2ff; color:#4338ca; font-size:.8rem; font-weight:900; }
    .support-callout { border: 1px dashed rgba(13,110,253,.28); background:#f8fbff; border-radius: 16px; padding: 1rem; }
    .checkout-card { border:1px solid rgba(15,23,42,.08); border-radius:18px; background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.06); height:100%; }
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">💙 Support GuidePaw</h1>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (gpNavIsAdmin()): ?>
                <a href="admin_funding_settings.php" class="btn btn-outline-primary btn-sm">Funding Settings</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <?php if ($checkoutState === 'success'): ?>
        <div class="alert alert-success">Thanks for supporting GuidePaw. Your checkout session is complete.</div>
    <?php elseif ($checkoutState === 'cancel'): ?>
        <div class="alert alert-warning">Checkout was canceled before payment was completed.</div>
    <?php endif; ?>
    <?php if ($checkoutMessage !== ''): ?>
        <div class="alert alert-info"><?= e($checkoutMessage) ?></div>
    <?php endif; ?>
    <?php if ($checkoutError !== ''): ?>
        <div class="alert alert-danger"><?= e($checkoutError) ?></div>
    <?php endif; ?>

    <section class="card support-card mb-3">
        <div class="card-body">
            <div class="support-badge mb-2">One-time help or ongoing support</div>
            <h2 class="h5 mb-2">Help keep GuidePaw running</h2>
            <p class="support-muted mb-3">This page is where handlers and supporters can back the project without hunting through the app. The first handler account and first dog stay free.</p>

            <div class="row g-3">
                <?php foreach ($supportOptions as $supportType => $option): ?>
                    <div class="col-md-6">
                        <div class="checkout-card p-3 h-100">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <div class="support-badge mb-2"><?= e((string) ($option['emoji'] ?? '💙')) ?> <?= e($option['label']) ?></div>
                                    <div class="fw-bold fs-5 mb-1"><?= e($option['label']) ?></div>
                                </div>
                                <span class="badge bg-secondary align-self-start"><?= e(strtoupper((string) ($option['mode'] ?? 'payment'))) ?></span>
                            </div>
                            <p class="support-muted mb-3"><?= e($option['summary']) ?></p>
                            <form method="post" class="mb-0">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="support_type" value="<?= e($supportType) ?>">
                                <?php if (trim((string) ($option['price_id'] ?? '')) !== '' && gpStripeCheckoutConfigured()): ?>
                                    <button type="submit" class="btn btn-primary w-100"><?= e($option['button']) ?></button>
                                <?php else: ?>
                                    <div class="support-callout">
                                        <strong>Checkout not configured yet.</strong>
                                        <div class="support-muted mt-1">Set the Stripe secret key and the price id for this option in Render environment variables to turn this into a live one-tap checkout.</div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($supportUrl !== '' && (!gpStripeCheckoutConfigured() || trim((string) ($supportOptions['one_time']['price_id'] ?? '')) === '' || trim((string) ($supportOptions['monthly']['price_id'] ?? '')) === '')): ?>
                <a class="btn btn-outline-primary btn-lg w-100 mt-3" href="<?= e($supportUrl) ?>" target="_blank" rel="noopener noreferrer">Open funding page</a>
            <?php endif; ?>

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <a class="support-link" href="community.php">
                        <span><span class="me-2">🤝</span>Open Community</span>
                        <span>›</span>
                    </a>
                </div>
                <?php if ($merchUrl !== ''): ?>
                    <div class="col-md-4">
                        <a class="support-link" href="<?= e($merchUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <span><span class="me-2">🛍️</span>Browse merch</span>
                            <span>›</span>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($discordUrl !== ''): ?>
                    <div class="col-md-4">
                        <a class="support-link" href="<?= e($discordUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <span><span class="me-2">💬</span>Join Discord</span>
                            <span>›</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card support-card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">What support helps cover</h2>
            <p class="support-muted mb-3">Support funding is meant for the project itself, not for the free handler workflow. The first handler account and first dog stay free.</p>
            <ul class="mb-0">
                <li>Project hosting and maintenance</li>
                <li>New handler tools and accessibility work</li>
                <li>Optional add-ons like QR tracking, extra dogs, or premium surfaces</li>
                <li>Community features like the forum and support channels</li>
            </ul>
        </div>
    </section>

    <section class="card support-card">
        <div class="card-body">
            <h2 class="h5 mb-1">Suggested support paths</h2>
            <div class="support-muted mb-3">Pick the path that fits your setup. The app can route all of them from one page.</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="support-callout h-100">
                        <div class="fw-bold mb-1">One-time gift</div>
                        <div class="support-muted">A single contribution to help with ongoing development.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="support-callout h-100">
                        <div class="fw-bold mb-1">Monthly support</div>
                        <div class="support-muted">A recurring contribution for users who want to keep the project moving.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="support-callout h-100">
                        <div class="fw-bold mb-1">Merch support</div>
                        <div class="support-muted">Swag and community items can also help fund the app.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="app.js"></script>
</body>
</html>
