<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/stripe_checkout.php';
require_once __DIR__ . '/includes/stripe_webhook.php';

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

function afsMoney(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

function afsPaymentLabel(?array $payment): string
{
    if (!$payment) {
        return 'No support payments recorded yet.';
    }

    $parts = [];
    $supportType = trim((string) ($payment['support_type'] ?? ''));
    $parts[] = $supportType === 'monthly' ? 'Monthly support' : 'One-time support';

    $amount = (int) ($payment['amount_total_cents'] ?? 0);
    if ($amount > 0) {
        $parts[] = afsMoney($amount);
    }

    $when = trim((string) ($payment['updated_at'] ?? $payment['created_at'] ?? ''));
    if ($when !== '') {
        $parts[] = $when;
    }

    $status = trim((string) ($payment['payment_status'] ?? ''));
    if ($status !== '') {
        $parts[] = $status;
    }

    $sessionId = trim((string) ($payment['stripe_checkout_session_id'] ?? ''));
    if ($sessionId !== '') {
        $parts[] = $sessionId;
    }

    return implode(' · ', $parts);
}

$supportUrl = trim((string) gpEnv('GUIDEPAW_SUPPORT_FUNDING_URL', ''));
$merchUrl = trim((string) gpEnv('GUIDEPAW_MERCH_STORE_URL', ''));
$discordUrl = trim((string) gpEnv('GUIDEPAW_DISCORD_INVITE_URL', ''));
$stripeSecret = gpStripeSecretKey();
$oneTimePriceId = trim((string) gpStripeSupportPriceId('one_time'));
$monthlyPriceId = trim((string) gpStripeSupportPriceId('monthly'));
$apiVersion = gpStripeApiVersion();
$checkoutConfigured = gpStripeCheckoutConfigured();
$webhookSecret = gpStripeWebhookSecret();
$webhookConfigured = gpStripeWebhookConfigured();
$webhookUrl = gpStripeWebhookEndpointUrl();
$timeline = gpStripeSupportTimelineSummary($pdo);
$recentPayments = gpStripeSupportRecentEvents($pdo, 10);
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
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
            <div>
                <h2 class="h5 mb-1">Stripe Webhook</h2>
                <div class="mini">Stripe calls this endpoint when checkout completes or fails asynchronously.</div>
            </div>
            <span class="badge <?= e(afsStatus($webhookSecret)['class']) ?>"><?= e($webhookConfigured ? 'Webhook configured' : 'Webhook not configured') ?></span>
        </div>
        <div class="field">
            <div>
                <div class="label">Webhook endpoint</div>
                <div class="mini">Add this URL in the Stripe dashboard.</div>
            </div>
            <code><?= e($webhookUrl) ?></code>
        </div>
        <div class="field">
            <div>
                <div class="label">Webhook secret</div>
                <div class="mini">Stored in Render environment variables only.</div>
            </div>
            <code><?= e(afsMaskSecret($webhookSecret)) ?></code>
        </div>
    </section>

    <section class="panel mb-3">
        <h2 class="h5 mb-1">Support timeline</h2>
        <div class="mini mb-3">This is the plain-English summary of the first payment, latest payment, and total support received.</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="field h-100 d-block">
                    <div class="label mb-1">First support payment</div>
                    <div class="mini"><?= e(afsPaymentLabel($timeline['first'] ?? null)) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field h-100 d-block">
                    <div class="label mb-1">Latest support payment</div>
                    <div class="mini"><?= e(afsPaymentLabel($timeline['latest'] ?? null)) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field h-100 d-block">
                    <div class="label mb-1">Total support received</div>
                    <div class="mini"><?= e(afsMoney((int) ($timeline['total_cents'] ?? 0))) ?> across <?= e((string) ((int) ($timeline['payment_count'] ?? 0))) ?> payments</div>
                </div>
            </div>
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

    <section class="panel mb-3">
        <h2 class="h5 mb-1">Recent support payments</h2>
        <div class="mini mb-3">Latest recorded Stripe Checkout sessions and webhook updates.</div>
        <?php if (!$recentPayments): ?>
            <div class="mini">No support payments recorded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Type</th>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>User</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <?php
                                $paymentStatusLabel = trim((string) ($payment['payment_status'] ?? ''));
                                if ($paymentStatusLabel === '') {
                                    $paymentStatusLabel = (string) ($payment['stripe_event_type'] ?? '');
                                }
                            ?>
                            <tr>
                                <td><?= e((string) ($payment['updated_at'] ?? '')) ?></td>
                                <td><?= e((string) ($payment['support_type'] ?? '')) ?></td>
                                <td><code><?= e((string) ($payment['stripe_checkout_session_id'] ?? '')) ?></code></td>
                                <td><?= e($paymentStatusLabel) ?></td>
                                <td><?= e(number_format(((int) ($payment['amount_total_cents'] ?? 0)) / 100, 2)) ?> <?= e(strtoupper((string) ($payment['currency'] ?? 'USD'))) ?></td>
                                <td><?= e((string) ($payment['user_id'] ?? '')) ?></td>
                                <td><?= e((string) ($payment['customer_email'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="h5 mb-1">Setup note</h2>
        <p class="mini mb-0">Change these values in Render environment variables. This page is intentionally read-only so the live payment config stays centralized.</p>
    </section>
</main>

</body>
</html>
