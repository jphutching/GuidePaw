<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/stripe_webhook.php';
require_once __DIR__ . '/includes/business_costs.php';
require_once __DIR__ . '/includes/cost_snapshots.php';
require_once __DIR__ . '/includes/audit_log.php';

checkLogin();
requireAdmin();

function abcEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function abcMoney(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

gpBusinessCostEnsureSchema($pdo);
$supportRevenue = gpStripeSupportRevenueSummary($pdo);
$costSummary = gpBusinessCostSummary($pdo);
$providerSnapshots = gpBusinessProviderSnapshots();
$allCostRows = gpBusinessCostRows($pdo);
$currentCostRows = array_values(array_filter($allCostRows, static fn(array $row): bool => !empty($row['is_active']) && ($row['category'] ?? '') === 'current'));
$futureCostRows = array_values(array_filter($allCostRows, static fn(array $row): bool => !empty($row['is_active']) && ($row['category'] ?? '') === 'future'));
$csrf = generateCsrfToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action !== 'save_item') {
            throw new RuntimeException('Unknown action.');
        }
        gpBusinessCostUpsert($pdo, [
            'slug' => (string) ($_POST['slug'] ?? ''),
            'category' => (string) ($_POST['category'] ?? 'current'),
            'label' => (string) ($_POST['label'] ?? ''),
            'summary' => (string) ($_POST['summary'] ?? ''),
            'billing_cycle' => (string) ($_POST['billing_cycle'] ?? 'monthly'),
            'unit_cost_cents' => (int) ($_POST['unit_cost_cents'] ?? 0),
            'quantity' => (float) ($_POST['quantity'] ?? 1),
            'currency' => (string) ($_POST['currency'] ?? 'USD'),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']),
            'notes' => (string) ($_POST['notes'] ?? ''),
        ]);
        writeAuditLog($pdo, 'business_cost_item_saved', 'business_cost_items', null, 'Admin saved business cost item ' . strtolower(trim((string) ($_POST['slug'] ?? ''))));
        header('Location: admin_business_costs.php?msg=updated');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (($_GET['msg'] ?? '') === 'updated') {
    $message = 'Business cost item saved.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Business Costs | GuidePaw Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; color:#1f2937; }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 1rem 1rem 5rem; }
        .panel { background:#fff; border:1px solid #dfe3ea; border-radius:18px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,.06); }
        .mini { color:#64748b; font-size:.92rem; }
        .kpi { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .kpi strong { font-size:1.4rem; }
        .table td, .table th { vertical-align: top; }
        .cost-edit summary { cursor:pointer; font-weight:800; color:#0d6efd; }
        .cost-edit[open] { background:#f8fafc; border:1px solid #dfe3ea; border-radius:14px; padding:10px; }
        .cost-edit .form-control-sm, .cost-edit .form-select-sm { margin-bottom:.45rem; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<main class="wrap">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h1 class="h3 mb-1">Business Costs</h1>
            <div class="mini">Track current operating costs, future expansion estimates, and support revenue side by side.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="admin.php">Back to Admin</a>
            <a class="btn btn-outline-primary" href="admin_funding_settings.php">Funding Settings</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= abcEsc($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= abcEsc($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="panel kpi"><div><div class="mini">Current monthly cost</div><strong><?= abcMoney((int) $costSummary['current_monthly_cents']) ?></strong></div><div class="badge text-bg-warning">Live</div></div></div>
        <div class="col-md-3"><div class="panel kpi"><div><div class="mini">Future monthly expansion</div><strong><?= abcMoney((int) $costSummary['future_monthly_cents']) ?></strong></div><div class="badge text-bg-info">Planned</div></div></div>
        <div class="col-md-3"><div class="panel kpi"><div><div class="mini">One-time future spend</div><strong><?= abcMoney((int) $costSummary['one_time_cents']) ?></strong></div><div class="badge text-bg-secondary">Upfront</div></div></div>
        <div class="col-md-3"><div class="panel kpi"><div><div class="mini">Support received</div><strong><?= abcMoney((int) $supportRevenue['total_cents']) ?></strong></div><div class="badge text-bg-success"><?= (int) $supportRevenue['payment_count'] ?> payments</div></div></div>
    </div>

    <div class="panel mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="mini">Support revenue last 30 days</div>
                <strong><?= abcMoney((int) $supportRevenue['last_30d_cents']) ?></strong>
            </div>
            <div class="col-md-4">
                <div class="mini">One-time support total</div>
                <strong><?= abcMoney((int) $supportRevenue['one_time_cents']) ?></strong>
            </div>
            <div class="col-md-4">
                <div class="mini">Monthly support total</div>
                <strong><?= abcMoney((int) $supportRevenue['monthly_cents']) ?></strong>
            </div>
        </div>
    </div>

    <div class="panel mb-3">
        <h2 class="h5 mb-2">Live provider snapshot</h2>
        <div class="mini mb-3">This section pulls live provider data where the API exposes it. The editable ledger below remains the place for costs that are not exposed cleanly by a provider API.</div>
        <div class="row g-3">
            <div class="col-md-4">
                <?php $twilio = (array) ($providerSnapshots['twilio'] ?? []); ?>
                <div class="border rounded-3 p-3 h-100">
                    <div class="d-flex justify-content-between gap-2 align-items-start">
                        <div>
                            <div class="mini">Twilio SMS</div>
                            <strong><?= abcEsc((string) ($twilio['label'] ?? 'Twilio SMS')) ?></strong>
                        </div>
                        <span class="badge <?= !empty($twilio['connected']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= abcEsc((string) ($twilio['status'] ?? 'missing')) ?></span>
                    </div>
                    <div class="mt-3">
                        <div class="mini">This month spend</div>
                        <strong><?= isset($twilio['monthly_cents']) ? abcMoney((int) $twilio['monthly_cents']) : '—' ?></strong>
                    </div>
                    <div class="mt-2">
                        <div class="mini">Messages</div>
                        <strong><?= isset($twilio['message_count']) ? number_format((float) $twilio['message_count'], 0) : '—' ?></strong>
                    </div>
                    <?php if (!empty($twilio['error'])): ?><div class="mini mt-2 text-danger"><?= abcEsc((string) $twilio['error']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <?php $renderSnapshot = (array) ($providerSnapshots['render'] ?? []); ?>
                <div class="border rounded-3 p-3 h-100">
                    <div class="d-flex justify-content-between gap-2 align-items-start">
                        <div>
                            <div class="mini">Render</div>
                            <strong><?= abcEsc((string) ($renderSnapshot['label'] ?? 'Render')) ?></strong>
                        </div>
                        <span class="badge <?= !empty($renderSnapshot['connected']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= abcEsc((string) ($renderSnapshot['status'] ?? 'missing')) ?></span>
                    </div>
                    <div class="mt-3">
                        <div class="mini">Services</div>
                        <strong><?= isset($renderSnapshot['service_count']) ? number_format((int) $renderSnapshot['service_count']) : '—' ?></strong>
                    </div>
                    <div class="mt-2">
                        <div class="mini">Databases</div>
                        <strong><?= isset($renderSnapshot['postgres_count']) ? number_format((int) $renderSnapshot['postgres_count']) : '—' ?></strong>
                    </div>
                    <details class="mt-2">
                        <summary class="mini">Show plans</summary>
                        <div class="mt-2 mini">
                            <?php foreach ((array) ($renderSnapshot['services'] ?? []) as $service): ?>
                                <div><?= abcEsc((string) ($service['name'] ?? 'service')) ?><?= !empty($service['plan']) ? ' · ' . abcEsc((string) $service['plan']) : '' ?></div>
                            <?php endforeach; ?>
                            <?php foreach ((array) ($renderSnapshot['postgres'] ?? []) as $db): ?>
                                <div><?= abcEsc((string) ($db['name'] ?? 'database')) ?><?= !empty($db['plan']) ? ' · ' . abcEsc((string) $db['plan']) : '' ?></div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php if (!empty($renderSnapshot['error'])): ?><div class="mini mt-2 text-danger"><?= abcEsc((string) $renderSnapshot['error']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <?php $zepto = (array) ($providerSnapshots['zeptomail'] ?? []); ?>
                <div class="border rounded-3 p-3 h-100">
                    <div class="d-flex justify-content-between gap-2 align-items-start">
                        <div>
                            <div class="mini">ZeptoMail</div>
                            <strong><?= abcEsc((string) ($zepto['label'] ?? 'ZeptoMail')) ?></strong>
                        </div>
                        <span class="badge <?= !empty($zepto['connected']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= abcEsc((string) ($zepto['status'] ?? 'missing')) ?></span>
                    </div>
                    <div class="mt-3">
                        <div class="mini">Emails this month</div>
                        <strong><?= isset($zepto['email_count']) ? number_format((int) $zepto['email_count']) : '—' ?></strong>
                    </div>
                    <div class="mini mt-2">ZeptoMail exposes usage logs, not a direct billing total. Keep the manual ledger row below for the actual monthly cost.</div>
                    <?php if (!empty($zepto['error'])): ?><div class="mini mt-2 text-danger"><?= abcEsc((string) $zepto['error']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="panel mb-3">
        <h2 class="h5 mb-2">Edit cost items</h2>
        <div class="mini mb-3">Enter the real monthly, annual, one-time, or usage-based costs. These numbers are editable in-app and update the tally immediately.</div>
        <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="csrf_token" value="<?= abcEsc($csrf) ?>">
            <input type="hidden" name="action" value="save_item">
            <div class="col-md-2"><label class="form-label small">Slug</label><input class="form-control" name="slug" placeholder="render_web_service"></div>
            <div class="col-md-2"><label class="form-label small">Label</label><input class="form-control" name="label" placeholder="Render web service"></div>
            <div class="col-md-2"><label class="form-label small">Category</label><select class="form-select" name="category"><option value="current">Current</option><option value="future">Future</option></select></div>
            <div class="col-md-2"><label class="form-label small">Cycle</label><select class="form-select" name="billing_cycle"><option value="monthly">Monthly</option><option value="annual">Annual</option><option value="one_time">One-time</option><option value="usage">Usage</option></select></div>
            <div class="col-md-2"><label class="form-label small">Unit cost cents</label><input class="form-control" type="number" min="0" step="1" name="unit_cost_cents" value="0"></div>
            <div class="col-md-1"><label class="form-label small">Qty</label><input class="form-control" type="number" min="0" step="0.01" name="quantity" value="1"></div>
            <div class="col-md-1"><label class="form-label small">Sort</label><input class="form-control" type="number" name="sort_order" value="0"></div>
            <div class="col-md-3"><label class="form-label small">Summary</label><input class="form-control" name="summary" placeholder="Short description"></div>
            <div class="col-md-2"><label class="form-label small">Currency</label><input class="form-control" name="currency" value="USD"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check-label"><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
            <div class="col-12"><label class="form-label small">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Save cost item</button></div>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel">
                <h2 class="h5 mb-3">Current operating costs</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Item</th><th>Cycle</th><th>Estimate</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($currentCostRows as $row): ?>
                                <tr>
                                    <td><strong><?= abcEsc($row['label']) ?></strong><div class="mini"><?= abcEsc($row['summary']) ?></div></td>
                                    <td><?= abcEsc((string) ($row['billing_cycle'] ?? 'monthly')) ?></td>
                                    <td><?= abcMoney((int) ($row['monthly_equivalent_cents'] ?? 0)) ?> / mo</td>
                                    <td>
                                        <details class="cost-edit">
                                            <summary>Edit</summary>
                                            <form method="post" class="d-grid gap-1 mt-2">
                                                <input type="hidden" name="csrf_token" value="<?= abcEsc($csrf) ?>">
                                                <input type="hidden" name="action" value="save_item">
                                                <input type="hidden" name="slug" value="<?= abcEsc($row['slug']) ?>">
                                                <label class="small">Label<input class="form-control form-control-sm" name="label" value="<?= abcEsc($row['label']) ?>"></label>
                                                <label class="small">Summary<input class="form-control form-control-sm" name="summary" value="<?= abcEsc($row['summary']) ?>"></label>
                                                <label class="small">Category<select class="form-select form-select-sm" name="category"><option value="current" <?= ($row['category'] ?? '') === 'current' ? 'selected' : '' ?>>Current</option><option value="future" <?= ($row['category'] ?? '') === 'future' ? 'selected' : '' ?>>Future</option></select></label>
                                                <label class="small">Cycle<select class="form-select form-select-sm" name="billing_cycle"><option value="monthly" <?= ($row['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option><option value="annual" <?= ($row['billing_cycle'] ?? '') === 'annual' ? 'selected' : '' ?>>Annual</option><option value="one_time" <?= ($row['billing_cycle'] ?? '') === 'one_time' ? 'selected' : '' ?>>One-time</option><option value="usage" <?= ($row['billing_cycle'] ?? '') === 'usage' ? 'selected' : '' ?>>Usage</option></select></label>
                                                <label class="small">Unit cents<input class="form-control form-control-sm" type="number" name="unit_cost_cents" value="<?= (int) ($row['unit_cost_cents'] ?? 0) ?>"></label>
                                                <label class="small">Quantity<input class="form-control form-control-sm" type="number" step="0.01" name="quantity" value="<?= abcEsc($row['quantity'] ?? 1) ?>"></label>
                                                <label class="small">Currency<input class="form-control form-control-sm" name="currency" value="<?= abcEsc($row['currency'] ?? 'USD') ?>"></label>
                                                <label class="small">Sort<input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>"></label>
                                                <label class="small">Notes<textarea class="form-control form-control-sm" name="notes"><?= abcEsc($row['notes'] ?? '') ?></textarea></label>
                                                <label class="small"><input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>> Active</label>
                                                <button class="btn btn-sm btn-outline-primary">Save row</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel">
                <h2 class="h5 mb-3">Future expansion estimates</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Item</th><th>Cycle</th><th>Estimate</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($futureCostRows as $row): ?>
                                <tr>
                                    <td><strong><?= abcEsc($row['label']) ?></strong><div class="mini"><?= abcEsc($row['summary']) ?></div></td>
                                    <td><?= abcEsc((string) ($row['billing_cycle'] ?? 'monthly')) ?></td>
                                    <td><?= abcEsc(($row['billing_cycle'] ?? '') === 'one_time' ? abcMoney((int) ($row['one_time_equivalent_cents'] ?? 0)) : abcMoney((int) ($row['monthly_equivalent_cents'] ?? 0)) . ' / mo') ?></td>
                                    <td><?= abcEsc($row['notes'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
