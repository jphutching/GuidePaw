<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/paywall_catalog.php';
require_once __DIR__ . '/includes/audit_log.php';

checkLogin();
requireAdmin();
gpPaywallCatalogEnsureSchema($pdo);

function apEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function apParseBullets(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    return preg_replace("/\r\n?/", "\n", $text);
}

$message = '';
$error = '';
$csrf = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_item') {
            $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
            $label = trim((string) ($_POST['label'] ?? ''));
            if ($slug === '' || $label === '') {
                throw new RuntimeException('Slug and label are required.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO paywall_catalog_items (
                    slug, item_type, label, summary, included_text, locked_text,
                    billing_model, required_tier, scope, price_cents, currency,
                    sort_order, is_active, notes, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
                )
                ON CONFLICT (slug) DO UPDATE SET
                    item_type = EXCLUDED.item_type,
                    label = EXCLUDED.label,
                    summary = EXCLUDED.summary,
                    included_text = EXCLUDED.included_text,
                    locked_text = EXCLUDED.locked_text,
                    billing_model = EXCLUDED.billing_model,
                    required_tier = EXCLUDED.required_tier,
                    scope = EXCLUDED.scope,
                    price_cents = EXCLUDED.price_cents,
                    currency = EXCLUDED.currency,
                    sort_order = EXCLUDED.sort_order,
                    is_active = EXCLUDED.is_active,
                    notes = EXCLUDED.notes,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $slug,
                in_array($_POST['item_type'] ?? 'feature', ['plan', 'feature', 'service'], true) ? $_POST['item_type'] : 'feature',
                $label,
                trim((string) ($_POST['summary'] ?? '')),
                apParseBullets((string) ($_POST['included_text'] ?? '')),
                apParseBullets((string) ($_POST['locked_text'] ?? '')),
                in_array($_POST['billing_model'] ?? 'plan', ['plan', 'lifetime_dog', 'lifetime_user', 'recurring_user'], true) ? $_POST['billing_model'] : 'plan',
                gpNormalizeUserTier((string) ($_POST['required_tier'] ?? 'free')),
                in_array($_POST['scope'] ?? 'user', ['user', 'dog'], true) ? $_POST['scope'] : 'user',
                (int) ($_POST['price_cents'] ?? 0),
                strtoupper(trim((string) ($_POST['currency'] ?? 'USD'))) ?: 'USD',
                (int) ($_POST['sort_order'] ?? 0),
                !empty($_POST['is_active']) ? 1 : 0,
                trim((string) ($_POST['notes'] ?? '')),
            ]);
            writeAuditLog($pdo, 'paywall_catalog_saved', 'paywall_catalog_items', null, 'Admin saved paywall catalog item ' . $slug . '.');
            $message = 'Catalog item saved.';
        } elseif ($action === 'grant_user_service') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $serviceSlug = strtolower(trim((string) ($_POST['user_service_slug'] ?? '')));
            if ($userId <= 0 || $serviceSlug === '') {
                throw new RuntimeException('User and service are required.');
            }
            gpGrantUserServiceEntitlement($pdo, $userId, $serviceSlug, 'admin', trim((string) ($_POST['notes'] ?? '')) ?: null);
            writeAuditLog($pdo, 'user_service_granted', 'users', $userId, 'Granted user service entitlement: ' . $serviceSlug);
            $message = 'User service granted.';
        } elseif ($action === 'revoke_user_service') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $serviceSlug = strtolower(trim((string) ($_POST['user_service_slug'] ?? '')));
            if ($userId <= 0 || $serviceSlug === '') {
                throw new RuntimeException('User and service are required.');
            }
            gpRevokeUserServiceEntitlement($pdo, $userId, $serviceSlug);
            writeAuditLog($pdo, 'user_service_revoked', 'users', $userId, 'Revoked user service entitlement: ' . $serviceSlug);
            $message = 'User service revoked.';
        } elseif ($action === 'grant_dog_service') {
            $dogId = (int) ($_POST['dog_id'] ?? 0);
            $serviceSlug = strtolower(trim((string) ($_POST['dog_service_slug'] ?? '')));
            if ($dogId <= 0 || $serviceSlug === '') {
                throw new RuntimeException('Dog and service are required.');
            }
            gpGrantDogServiceEntitlement($pdo, $dogId, $serviceSlug, 'admin', trim((string) ($_POST['notes'] ?? '')) ?: null);
            writeAuditLog($pdo, 'dog_service_granted', 'dogs', $dogId, 'Granted dog service entitlement: ' . $serviceSlug);
            $message = 'Dog service granted.';
        } elseif ($action === 'revoke_dog_service') {
            $dogId = (int) ($_POST['dog_id'] ?? 0);
            $serviceSlug = strtolower(trim((string) ($_POST['dog_service_slug'] ?? '')));
            if ($dogId <= 0 || $serviceSlug === '') {
                throw new RuntimeException('Dog and service are required.');
            }
            gpRevokeDogServiceEntitlement($pdo, $dogId, $serviceSlug);
            writeAuditLog($pdo, 'dog_service_revoked', 'dogs', $dogId, 'Revoked dog service entitlement: ' . $serviceSlug);
            $message = 'Dog service revoked.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$items = gpPaywallCatalogRows($pdo);
$planItems = array_values(array_filter($items, static fn(array $row): bool => ($row['item_type'] ?? '') === 'plan'));
$featureItems = array_values(array_filter($items, static fn(array $row): bool => ($row['item_type'] ?? '') === 'feature'));
$serviceItems = array_values(array_filter($items, static fn(array $row): bool => ($row['item_type'] ?? '') === 'service'));
$dogs = $pdo->query("SELECT d.id, d.name, d.owner_user_id, u.username AS owner_username FROM dogs d JOIN users u ON u.id = d.owner_user_id ORDER BY d.id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$dogServiceRows = $pdo->query("
    SELECT des.*, d.name AS dog_name, u.username AS owner_username
    FROM dog_service_entitlements des
    JOIN dogs d ON d.id = des.dog_id
    JOIN users u ON u.id = d.owner_user_id
    ORDER BY des.purchased_at DESC
    LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$userServiceRows = $pdo->query("
    SELECT ent.*, u.username, u.email
    FROM user_service_entitlements ent
    JOIN users u ON u.id = ent.user_id
    ORDER BY ent.purchased_at DESC
    LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Paywall Catalog | GuidePaw Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; color:#1f2937; }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 1rem 1rem 5rem; }
        .catalog-card { background:#fff; border:1px solid #dfe3ea; border-radius:18px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,.06); }
        .grid { display:grid; gap: 14px; }
        .plan-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .catalog-table { width:100%; border-collapse:collapse; }
        .catalog-table th,.catalog-table td { border-bottom:1px solid #e5e7eb; padding:12px 10px; vertical-align: top; }
        .catalog-table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
        textarea, input, select { width:100%; box-sizing:border-box; }
        textarea { min-height: 72px; }
        .mini { color:#64748b; font-size:.9rem; }
        .pill { display:inline-flex; align-items:center; border-radius:999px; padding:.2rem .55rem; background:#eef2ff; color:#4338ca; font-size:.78rem; font-weight:800; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .catalog-inline-edit summary { cursor:pointer; font-weight:800; color:#0d6efd; }
        .catalog-inline-edit[open] { background:#f8fafc; border:1px solid #dfe3ea; border-radius:14px; padding:10px; }
        .catalog-inline-edit .form-control-sm,.catalog-inline-edit .form-select-sm { margin-bottom:.45rem; }
        .catalog-inline-edit button { margin-top:.2rem; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h1 class="h3 mb-1">Paywall Catalog</h1>
            <div class="mini">Edit plans, features, and a la carte services from inside the app.</div>
        </div>
        <div class="actions">
            <a class="btn btn-outline-secondary" href="admin.php">Back to Admin</a>
            <a class="btn btn-outline-primary" href="paywalls.php">View Plans</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= apEsc($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= apEsc($error) ?></div><?php endif; ?>

    <div class="catalog-card mb-3">
        <h2 class="h5 mb-2">Add or update a catalog item</h2>
        <div class="mini mb-3">Use the item slug as the stable key. Existing rows update in place.</div>
        <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?= apEsc($csrf) ?>">
            <input type="hidden" name="action" value="save_item">
            <div class="col-md-3"><label class="form-label small">Slug</label><input name="slug" class="form-control" placeholder="qr_tracking"></div>
            <div class="col-md-3"><label class="form-label small">Label</label><input name="label" class="form-control" placeholder="QR Tracking"></div>
            <div class="col-md-2"><label class="form-label small">Type</label><select name="item_type" class="form-select"><option value="plan">Plan</option><option value="feature">Feature</option><option value="service">Service</option></select></div>
            <div class="col-md-2"><label class="form-label small">Billing</label><select name="billing_model" class="form-select"><option value="plan">Plan</option><option value="lifetime_dog">Lifetime / dog</option><option value="lifetime_user">Lifetime / user</option><option value="recurring_user">Recurring / user</option></select></div>
            <div class="col-md-2"><label class="form-label small">Required tier</label><select name="required_tier" class="form-select"><?php foreach (gpUserTierOptions() as $value => $label): ?><option value="<?= apEsc($value) ?>"><?= apEsc($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label small">Summary</label><input name="summary" class="form-control" placeholder="Short description"></div>
            <div class="col-md-3"><label class="form-label small">Scope</label><select name="scope" class="form-select"><option value="user">User</option><option value="dog">Dog</option></select></div>
            <div class="col-md-1"><label class="form-label small">Price</label><input name="price_cents" class="form-control" type="number" min="0" step="1" value="0"></div>
            <div class="col-md-2"><label class="form-label small">Currency</label><input name="currency" class="form-control" value="USD"></div>
            <div class="col-md-12"><label class="form-label small">Included text</label><textarea name="included_text" class="form-control" placeholder="One bullet per line"></textarea></div>
            <div class="col-md-12"><label class="form-label small">Locked text</label><textarea name="locked_text" class="form-control" placeholder="One bullet per line"></textarea></div>
            <div class="col-md-2"><label class="form-label small">Sort order</label><input name="sort_order" class="form-control" type="number" value="0"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check-label"><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
            <div class="col-md-12"><label class="form-label small">Notes</label><textarea name="notes" class="form-control" placeholder="Admin notes"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Save catalog item</button></div>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="catalog-card">
                <h2 class="h5 mb-3">Plans and features</h2>
                <div class="table-responsive">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th>Slug</th><th>Label</th><th>Type</th><th>Tier</th><th>Billing</th><th>Scope</th><th>Price</th><th>Content</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_merge($planItems, $featureItems) as $item): ?>
                                <tr>
                                    <td><span class="pill"><?= apEsc($item['slug']) ?></span></td>
                                    <td><strong><?= apEsc($item['label']) ?></strong><div class="mini"><?= apEsc($item['summary']) ?></div></td>
                                    <td><?= apEsc($item['item_type']) ?></td>
                                    <td><?= apEsc(gpTierDisplayLabel((string) ($item['required_tier'] ?? 'free'))) ?></td>
                                    <td><?= apEsc((string) ($item['billing_model'] ?? 'plan')) ?></td>
                                    <td><?= apEsc((string) ($item['scope'] ?? 'user')) ?></td>
                                    <td>$<?= apEsc(number_format(((int) ($item['price_cents'] ?? 0)) / 100, 2)) ?></td>
                                    <td>
                                        <details class="catalog-inline-edit">
                                            <summary>Edit row</summary>
                                        <form method="post" class="d-grid gap-2 mt-2">
                                            <input type="hidden" name="csrf_token" value="<?= apEsc($csrf) ?>">
                                            <input type="hidden" name="action" value="save_item">
                                            <input type="hidden" name="slug" value="<?= apEsc($item['slug']) ?>">
                                            <label class="small">Label<input name="label" class="form-control form-control-sm" value="<?= apEsc($item['label']) ?>"></label>
                                            <label class="small">Type<select name="item_type" class="form-select form-select-sm"><option value="plan" <?= ($item['item_type'] ?? '') === 'plan' ? 'selected' : '' ?>>Plan</option><option value="feature" <?= ($item['item_type'] ?? '') === 'feature' ? 'selected' : '' ?>>Feature</option><option value="service" <?= ($item['item_type'] ?? '') === 'service' ? 'selected' : '' ?>>Service</option></select></label>
                                            <label class="small">Billing<select name="billing_model" class="form-select form-select-sm"><option value="plan" <?= ($item['billing_model'] ?? '') === 'plan' ? 'selected' : '' ?>>Plan</option><option value="lifetime_dog" <?= ($item['billing_model'] ?? '') === 'lifetime_dog' ? 'selected' : '' ?>>Lifetime / dog</option><option value="lifetime_user" <?= ($item['billing_model'] ?? '') === 'lifetime_user' ? 'selected' : '' ?>>Lifetime / user</option><option value="recurring_user" <?= ($item['billing_model'] ?? '') === 'recurring_user' ? 'selected' : '' ?>>Recurring / user</option></select></label>
                                            <label class="small">Required tier<select name="required_tier" class="form-select form-select-sm"><?php foreach (gpUserTierOptions() as $value => $label): ?><option value="<?= apEsc($value) ?>" <?= gpNormalizeUserTier((string) ($item['required_tier'] ?? 'free')) === $value ? 'selected' : '' ?>><?= apEsc($label) ?></option><?php endforeach; ?></select></label>
                                            <label class="small">Scope<select name="scope" class="form-select form-select-sm"><option value="user" <?= ($item['scope'] ?? 'user') === 'user' ? 'selected' : '' ?>>user</option><option value="dog" <?= ($item['scope'] ?? '') === 'dog' ? 'selected' : '' ?>>dog</option></select></label>
                                            <label class="small">Price cents<input name="price_cents" class="form-control form-control-sm" type="number" min="0" step="1" value="<?= (int) ($item['price_cents'] ?? 0) ?>"></label>
                                            <label class="small">Currency<input name="currency" class="form-control form-control-sm" value="<?= apEsc($item['currency'] ?? 'USD') ?>"></label>
                                            <label class="small">Sort order<input name="sort_order" class="form-control form-control-sm" type="number" value="<?= (int) ($item['sort_order'] ?? 0) ?>"></label>
                                            <label class="small">Summary<input name="summary" class="form-control form-control-sm" value="<?= apEsc($item['summary']) ?>"></label>
                                            <label class="small">Included<textarea name="included_text" class="form-control form-control-sm"><?= apEsc($item['included_text']) ?></textarea></label>
                                            <label class="small">Locked<textarea name="locked_text" class="form-control form-control-sm"><?= apEsc($item['locked_text']) ?></textarea></label>
                                            <label class="small">Notes<textarea name="notes" class="form-control form-control-sm"><?= apEsc($item['notes'] ?? '') ?></textarea></label>
                                            <label class="small"><input type="checkbox" name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>> Active</label>
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
        <div class="col-lg-5">
            <div class="catalog-card mb-3">
                <h2 class="h5 mb-3">Grant user add-ons</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?= apEsc($csrf) ?>">
                    <input type="hidden" name="action" value="grant_user_service">
                    <div class="col-4">
                        <label class="form-label small">User</label>
                        <select class="form-select" name="user_id">
                            <option value="">Pick one</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['id'] ?>"><?= apEsc('#' . $user['id'] . ' ' . ($user['username'] ?? '') . ' ' . ($user['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Service</label>
                        <select class="form-select" name="user_service_slug">
                            <option value="extra_dog_slot">extra_dog_slot</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Notes</label>
                        <input class="form-control" name="notes" placeholder="Optional note">
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Grant user add-on</button></div>
                </form>
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>User</th><th>Service</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($userServiceRows as $row): ?>
                                <tr>
                                    <td><?= apEsc($row['username'] ?? '') ?></td>
                                    <td><?= apEsc($row['service_slug']) ?></td>
                                    <td><?= apEsc($row['status']) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= apEsc($csrf) ?>">
                                            <input type="hidden" name="action" value="revoke_user_service">
                                            <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                                            <input type="hidden" name="user_service_slug" value="<?= apEsc($row['service_slug']) ?>">
                                            <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="catalog-card mb-3">
                <h2 class="h5 mb-3">Grant dog add-ons</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?= apEsc($csrf) ?>">
                    <input type="hidden" name="action" value="grant_dog_service">
                    <div class="col-4">
                        <label class="form-label small">Dog</label>
                        <select class="form-select" name="dog_id">
                            <option value="">Pick one</option>
                            <?php foreach ($dogs as $dog): ?>
                                <option value="<?= (int) $dog['id'] ?>"><?= apEsc('#' . $dog['id'] . ' ' . ($dog['name'] ?? '') . ' · ' . ($dog['owner_username'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Service</label>
                        <select class="form-select" name="dog_service_slug">
                            <?php foreach ($serviceItems as $service): ?>
                                <option value="<?= apEsc($service['slug']) ?>"><?= apEsc($service['slug']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Notes</label>
                        <input class="form-control" name="notes" placeholder="Optional note">
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Grant dog add-on</button></div>
                </form>
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Dog</th><th>Service</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($dogServiceRows as $row): ?>
                                <tr>
                                    <td><?= apEsc($row['dog_name'] ?? '') ?></td>
                                    <td><?= apEsc($row['service_slug']) ?></td>
                                    <td><?= apEsc($row['status']) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= apEsc($csrf) ?>">
                                            <input type="hidden" name="action" value="revoke_dog_service">
                                            <input type="hidden" name="dog_id" value="<?= (int) $row['dog_id'] ?>">
                                            <input type="hidden" name="dog_service_slug" value="<?= apEsc($row['service_slug']) ?>">
                                            <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
