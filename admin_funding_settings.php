<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/stripe_checkout.php';

checkLogin();
requireAdmin();

function afsMaskSecret(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Not configured';
    }
    $prefix = substr($value, 0, 7);
    $suffix = substr($value, -4);
    return $prefix . '…' . $suffix;
}

function afsStatus(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['label' => 'Not configured', 'class' => 'bg-danger-subtle text-danger-emphasis'];
    }
    return ['label' => 'Configured', 'class' => 'bg-success-subtle text-success-emphasis'];
}

$supportUrl = trim((string) gpEnv('GUIDEPAW_SUPPORT_FUNDING_URL', ''));
$merchUrl = trim((string) gpEnv('GUIDEPAW_MERCH_STORE_URL', ''));
$discordUrl = trim((string) gpEnv('GUIDEPAW_DISCORD_INVITE_URL', ''));
$stripeSecret = gpStripeSecretKey();
$oneTimePriceId = trim((string) gpStripeSupportPriceId('one_time'));
$monthlyPriceId = trim((string) gpStripeSupportPriceId('monthly'));
$apiVersion = gpStripeApiVersion();
$checkoutConfigured = gpStripeCheckoutConfigured();
$csrf = generateCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Funding Settings | GuidePaw Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; color:#1f2937; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 1rem 1rem 5rem; }
        .panel { background:#fff; border:1px solid #dfe3ea; border-radius:18px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,.06); }
        .mini { color:#64748b; font-size:.92rem; }
        .field { display:flex; justify-content:space-between; gap:1rem; padding:.85rem 0; border-top:1px solid #e5e7eb; }
        .field:first-child { border-top:0; padding-top:0; }
        .label { font-weight:800; }
        code { word-break: break-all; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<main class="wrap">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h1 class="h3 mb-1">Funding Settings</h1>
            <div class="mini">Read-only Stripe and support links for the live funding flow.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="admin.php">Back to Admin</a>
            <a class="btn btn-outline-primary" href="support_funding.php">Open Support Page</a>
        </div>
    </div>

    <section class="panel mb-3">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
            <div>
                <h2 class="h5 mb-1">Stripe Checkout</h2>
                <div class="mini">These values drive the one-time and monthly support buttons.</div>
            </div>
            <span class="badge <?= e(afsStatus($stripeSecret)['class']) ?>"><?= e($checkoutConfigured ? 'Checkout configured' : 'Checkout not configured') ?></span>
        </div>
        <div class="field">
            <div>
                <div class="label">Secret key</div>
                <div class="mini">Stored in Render environment variables only.</div>
            </div>
            <code><?= e(afsMaskSecret($stripeSecret)) ?></code>
        </div>
        <div class="field">
            <div>
                <div class="label">API version</div>
                <div class="mini">Sent with Stripe API requests.</div>
            </div>
            <code><?= e($apiVersion) ?></code>
        </div>
        <div class="field">
            <div>
                <div class="label">One-time support price</div>
                <div class="mini">Used by the one-time support button.</div>
            </div>
            <code><?= e($oneTimePriceId !== '' ? $oneTimePriceId : 'Not configured') ?></code>
        </div>
        <div class="field">
            <div>
                <div class="label">Monthly support price</div>
                <div class="mini">Used by the monthly support button.</div>
            </div>
            <code><?= e($monthlyPriceId !== '' ? $monthlyPriceId : 'Not configured') ?></code>
        </div>
    </section>

    <section class="panel mb-3">
        <h2 class="h5 mb-1">Support links</h2>
        <div class="mini mb-3">These links appear on the support hub and can backstop checkout if needed.</div>
        <div class="field">
            <div>
                <div class="label">Fallback funding URL</div>
                <div class="mini">Used when Stripe is not available yet.</div>
            </div>
            <code><?= e($supportUrl !== '' ? $supportUrl : 'Not configured') ?></code>
        </div>
        <div class="field">
            <div>
                <div class="label">Merch store URL</div>
                <div class="mini">Shown on the support and community pages.</div>
            </div>
            <code><?= e($merchUrl !== '' ? $merchUrl : 'Not configured') ?></code>
        </div>
        <div class="field">
            <div>
                <div class="label">Discord invite URL</div>
                <div class="mini">Shown on the support and community pages.</div>
            </div>
            <code><?= e($discordUrl !== '' ? $discordUrl : 'Not configured') ?></code>
        </div>
    </section>

    <section class="panel">
        <h2 class="h5 mb-1">Setup note</h2>
        <p class="mini mb-0">Change these values in Render environment variables. This page is intentionally read-only so the live payment config stays centralized.</p>
    </section>
</main>

</body>
</html>
