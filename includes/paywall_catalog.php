<?php
declare(strict_types=1);

require_once __DIR__ . '/paywalls.php';

function gpPaywallCatalogTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_name = ?
        )
    ");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function gpPaywallCatalogRows(PDO $pdo, ?string $itemType = null): array
{
    if (!gpPaywallCatalogTableExists($pdo, 'paywall_catalog_items')) {
        return gpPaywallCatalogDefaultRows($itemType);
    }

    $params = [];
    $where = '';
    if ($itemType !== null && $itemType !== '') {
        $where = 'WHERE item_type = ?';
        $params[] = $itemType;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM paywall_catalog_items
        {$where}
        ORDER BY sort_order ASC, label ASC, slug ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows) {
        return $rows;
    }

    return gpPaywallCatalogDefaultRows($itemType);
}

function gpPaywallCatalogRow(PDO $pdo, string $slug): ?array
{
    if (!gpPaywallCatalogTableExists($pdo, 'paywall_catalog_items')) {
        foreach (gpPaywallCatalogDefaultRows() as $row) {
            if (($row['slug'] ?? '') === $slug) {
                return $row;
            }
        }
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM paywall_catalog_items WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gpPaywallCatalogDefaultRows(?string $itemType = null): array
{
    $rows = [
        [
            'slug' => 'free',
            'item_type' => 'plan',
            'label' => 'Free',
            'summary' => 'Core handler tools for a single dog account.',
            'included_text' => "Dashboard\nDogs\nLogs\nTraining\nCare\nADA tools\nNotifications\nCommunity",
            'locked_text' => "Trainer Marketplace\nAI Training Assistant\nExtra dog slots\nQR Tracking add-ons",
            'billing_model' => 'plan',
            'required_tier' => 'free',
            'scope' => 'user',
            'price_cents' => 0,
            'currency' => 'USD',
            'sort_order' => 10,
            'is_active' => 1,
            'notes' => 'The first dog stays free with the handler account.',
        ],
        [
            'slug' => 'plus',
            'item_type' => 'plan',
            'label' => 'Plus',
            'summary' => 'Adds the trainer directory and other premium planning surfaces.',
            'included_text' => "Trainer Marketplace\nEverything in Free",
            'locked_text' => "AI Training Assistant",
            'billing_model' => 'plan',
            'required_tier' => 'plus',
            'scope' => 'user',
            'price_cents' => 0,
            'currency' => 'USD',
            'sort_order' => 20,
            'is_active' => 1,
            'notes' => 'Paid monthly plan tier.',
        ],
        [
            'slug' => 'pro',
            'item_type' => 'plan',
            'label' => 'Pro',
            'summary' => 'Premium tier for the AI training assistant and deeper planning tools.',
            'included_text' => "Trainer Marketplace\nAI Training Assistant\nEverything in Plus",
            'locked_text' => '',
            'billing_model' => 'plan',
            'required_tier' => 'pro',
            'scope' => 'user',
            'price_cents' => 0,
            'currency' => 'USD',
            'sort_order' => 30,
            'is_active' => 1,
            'notes' => 'Highest monthly plan tier.',
        ],
        [
            'slug' => 'trainer_marketplace',
            'item_type' => 'feature',
            'label' => 'Trainer Marketplace',
            'summary' => 'Trainer directory and saved trainer contacts.',
            'included_text' => "Browse saved trainer contacts\nCall, email, and website buttons\nSearch trainer profiles saved on the dog",
            'locked_text' => '',
            'billing_model' => 'plan',
            'required_tier' => 'plus',
            'scope' => 'user',
            'price_cents' => 0,
            'currency' => 'USD',
            'sort_order' => 40,
            'is_active' => 1,
            'notes' => 'Gate this at Plus unless admin overrides.',
        ],
        [
            'slug' => 'ai_training_assistant',
            'item_type' => 'feature',
            'label' => 'AI Training Assistant',
            'summary' => 'Bounded training help for current problems and next steps.',
            'included_text' => "Narrow next-step plan\nSafety-aware suggestions\nFollow-up questions",
            'locked_text' => '',
            'billing_model' => 'plan',
            'required_tier' => 'pro',
            'scope' => 'user',
            'price_cents' => 0,
            'currency' => 'USD',
            'sort_order' => 50,
            'is_active' => 1,
            'notes' => 'Gate this at Pro unless admin overrides.',
        ],
        [
            'slug' => 'qr_tracking',
            'item_type' => 'service',
            'label' => 'QR Tracking',
            'summary' => 'Public QR profile and scan history for a dog.',
            'included_text' => "Public profile opens\nScan logging\nFound-dog test alert\nLifetime access on one dog",
            'locked_text' => '',
            'billing_model' => 'lifetime_dog',
            'required_tier' => 'free',
            'scope' => 'dog',
            'price_cents' => 2500,
            'currency' => 'USD',
            'sort_order' => 60,
            'is_active' => 1,
            'notes' => 'First dog is free; extra dogs need a QR Tracking entitlement.',
        ],
        [
            'slug' => 'extra_dog_slot',
            'item_type' => 'service',
            'label' => 'Extra Dog Slot',
            'summary' => 'Add another dog beyond the first free dog.',
            'included_text' => "One additional dog slot\nTied to the handler account",
            'locked_text' => '',
            'billing_model' => 'lifetime_user',
            'required_tier' => 'free',
            'scope' => 'user',
            'price_cents' => 1500,
            'currency' => 'USD',
            'sort_order' => 70,
            'is_active' => 1,
            'notes' => 'Used to keep the first dog free while allowing add-on dogs.',
        ],
    ];

    if ($itemType !== null && $itemType !== '') {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['item_type'] ?? '') === $itemType));
    }

    return $rows;
}

function gpPaywallCatalogEnsureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS paywall_catalog_items (
            slug TEXT PRIMARY KEY,
            item_type TEXT NOT NULL DEFAULT 'feature',
            label TEXT NOT NULL,
            summary TEXT NOT NULL DEFAULT '',
            included_text TEXT NOT NULL DEFAULT '',
            locked_text TEXT NOT NULL DEFAULT '',
            billing_model TEXT NOT NULL DEFAULT 'plan',
            required_tier TEXT NOT NULL DEFAULT 'free',
            scope TEXT NOT NULL DEFAULT 'user',
            price_cents INTEGER NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT 'USD',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            notes TEXT,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_service_entitlements (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            service_slug TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            source TEXT NOT NULL DEFAULT 'admin',
            purchased_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP WITHOUT TIME ZONE NULL,
            notes TEXT
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dog_service_entitlements (
            id SERIAL PRIMARY KEY,
            dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
            service_slug TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            source TEXT NOT NULL DEFAULT 'admin',
            purchased_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP WITHOUT TIME ZONE NULL,
            notes TEXT
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_service_entitlements_user_service ON user_service_entitlements (user_id, service_slug, status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_dog_service_entitlements_dog_service ON dog_service_entitlements (dog_id, service_slug, status)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_user_service_entitlements_active ON user_service_entitlements (user_id, service_slug) WHERE status = 'active'");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_dog_service_entitlements_active ON dog_service_entitlements (dog_id, service_slug) WHERE status = 'active'");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM paywall_catalog_items')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $defaults = gpPaywallCatalogDefaultRows();
    $stmt = $pdo->prepare("
        INSERT INTO paywall_catalog_items (
            slug, item_type, label, summary, included_text, locked_text,
            billing_model, required_tier, scope, price_cents, currency,
            sort_order, is_active, notes, updated_at
        ) VALUES (
            :slug, :item_type, :label, :summary, :included_text, :locked_text,
            :billing_model, :required_tier, :scope, :price_cents, :currency,
            :sort_order, :is_active, :notes, CURRENT_TIMESTAMP
        )
    ");

    foreach ($defaults as $row) {
        $stmt->execute([
            ':slug' => $row['slug'],
            ':item_type' => $row['item_type'],
            ':label' => $row['label'],
            ':summary' => $row['summary'],
            ':included_text' => $row['included_text'],
            ':locked_text' => $row['locked_text'],
            ':billing_model' => $row['billing_model'],
            ':required_tier' => gpNormalizeUserTier((string) $row['required_tier']),
            ':scope' => $row['scope'],
            ':price_cents' => (int) $row['price_cents'],
            ':currency' => strtoupper((string) $row['currency']),
            ':sort_order' => (int) $row['sort_order'],
            ':is_active' => (int) !empty($row['is_active']),
            ':notes' => $row['notes'],
        ]);
    }
}

function gpPaywallCatalogBullets(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim((string) $text)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
}

function gpPaywallPlanRows(PDO $pdo): array
{
    return gpPaywallCatalogRows($pdo, 'plan');
}

function gpPaywallServiceRows(PDO $pdo): array
{
    return gpPaywallCatalogRows($pdo, 'service');
}

function gpPaywallCatalogItemLabel(PDO $pdo, string $slug, string $fallback = ''): string
{
    $row = gpPaywallCatalogRow($pdo, $slug);
    if ($row && !empty($row['label'])) {
        return (string) $row['label'];
    }
    return $fallback !== '' ? $fallback : $slug;
}

function gpPaywallCatalogItemTier(PDO $pdo, string $slug, string $fallbackTier = 'free'): string
{
    $row = gpPaywallCatalogRow($pdo, $slug);
    if ($row && !empty($row['required_tier'])) {
        return gpNormalizeUserTier((string) $row['required_tier']);
    }
    return gpNormalizeUserTier($fallbackTier);
}

function gpPaywallCatalogItemBillingModel(PDO $pdo, string $slug, string $fallback = 'plan'): string
{
    $row = gpPaywallCatalogRow($pdo, $slug);
    if ($row && !empty($row['billing_model'])) {
        return strtolower(trim((string) $row['billing_model']));
    }
    return $fallback;
}

function gpPaywallUserServiceActive(PDO $pdo, int $userId, string $serviceSlug): bool
{
    if ($userId <= 0 || !gpPaywallCatalogTableExists($pdo, 'user_service_entitlements')) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM user_service_entitlements
            WHERE user_id = ?
              AND service_slug = ?
              AND status = 'active'
              AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
        )
    ");
    $stmt->execute([$userId, $serviceSlug]);
    return (bool) $stmt->fetchColumn();
}

function gpPaywallDogServiceActive(PDO $pdo, int $dogId, string $serviceSlug): bool
{
    if ($dogId <= 0 || !gpPaywallCatalogTableExists($pdo, 'dog_service_entitlements')) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM dog_service_entitlements
            WHERE dog_id = ?
              AND service_slug = ?
              AND status = 'active'
              AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
        )
    ");
    $stmt->execute([$dogId, $serviceSlug]);
    return (bool) $stmt->fetchColumn();
}

function gpPaywallFirstOwnedDogId(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM dogs
        WHERE owner_user_id = ?
        ORDER BY created_at ASC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (int) $value : null;
}

function gpUserDogCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dogs WHERE owner_user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function gpUserCanCreateAnotherDog(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $user = getUserRecord($pdo, $userId);
    if (!$user) {
        return false;
    }

    if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()) {
        return true;
    }

    $tier = gpUserTier($user);
    $dogCount = gpUserDogCount($pdo, $userId);
    if ($dogCount < 1) {
        return true;
    }

    if ($tier !== 'free') {
        return true;
    }

    return gpPaywallUserServiceActive($pdo, $userId, 'extra_dog_slot');
}

function gpDogQrTrackingAvailable(PDO $pdo, int $userId, int $dogId): bool
{
    if ($dogId <= 0) {
        return false;
    }

    if (function_exists('currentUserIsAdmin') && currentUserIsAdmin()) {
        return true;
    }

    $firstDogId = gpPaywallFirstOwnedDogId($pdo, $userId);
    if ($firstDogId !== null && $firstDogId === $dogId) {
        return true;
    }

    return gpPaywallDogServiceActive($pdo, $dogId, 'qr_tracking');
}

function gpGrantUserServiceEntitlement(PDO $pdo, int $userId, string $serviceSlug, string $source = 'admin', ?string $notes = null): void
{
    if (!gpPaywallCatalogTableExists($pdo, 'user_service_entitlements')) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO user_service_entitlements (user_id, service_slug, status, source, purchased_at, notes)
        VALUES (?, ?, 'active', ?, CURRENT_TIMESTAMP, ?)
        ON CONFLICT DO NOTHING
    ");
    $stmt->execute([$userId, $serviceSlug, $source, $notes]);
}

function gpGrantDogServiceEntitlement(PDO $pdo, int $dogId, string $serviceSlug, string $source = 'admin', ?string $notes = null): void
{
    if (!gpPaywallCatalogTableExists($pdo, 'dog_service_entitlements')) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO dog_service_entitlements (dog_id, service_slug, status, source, purchased_at, notes)
        VALUES (?, ?, 'active', ?, CURRENT_TIMESTAMP, ?)
        ON CONFLICT DO NOTHING
    ");
    $stmt->execute([$dogId, $serviceSlug, $source, $notes]);
}

function gpRevokeUserServiceEntitlement(PDO $pdo, int $userId, string $serviceSlug): void
{
    if (!gpPaywallCatalogTableExists($pdo, 'user_service_entitlements')) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE user_service_entitlements
        SET status = 'revoked', expires_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND service_slug = ? AND status = 'active'
    ");
    $stmt->execute([$userId, $serviceSlug]);
}

function gpRevokeDogServiceEntitlement(PDO $pdo, int $dogId, string $serviceSlug): void
{
    if (!gpPaywallCatalogTableExists($pdo, 'dog_service_entitlements')) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE dog_service_entitlements
        SET status = 'revoked', expires_at = CURRENT_TIMESTAMP
        WHERE dog_id = ? AND service_slug = ? AND status = 'active'
    ");
    $stmt->execute([$dogId, $serviceSlug]);
}

function gpRenderDogServiceAccessNotice(PDO $pdo, array $dog, string $serviceSlug, string $serviceName, string $featureSummary = '', array $highlights = [], string $backHref = 'paywalls.php'): void
{
    $dogName = trim((string) ($dog['name'] ?? 'this dog'));
    $serviceLabel = gpPaywallCatalogItemLabel($pdo, $serviceSlug, $serviceName);
    $featureSummary = trim($featureSummary);
    if ($featureSummary === '') {
        $featureSummary = 'This add-on is not active for this dog yet.';
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
                    <div class="small opacity-75">GuidePaw add-on service</div>
                    <h1 class="mb-2"><?= e($serviceLabel) ?> is locked for <?= e($dogName) ?></h1>
                    <p class="mb-0 opacity-75"><?= e($featureSummary) ?></p>
                </div>
                <a class="btn btn-light btn-sm" href="<?= e($backHref) ?>">View plans</a>
            </div>
        </div>
    </header>
    <main class="paywall-wrap">
        <div class="alert alert-info">
            This add-on is sold per dog. The first dog can stay free, and extra dogs or extra services can be added from the plan page.
        </div>
        <?php if ($highlightItems): ?>
            <div class="note-box mb-3">
                <div class="fw-bold mb-2">What this add-on includes</div>
                <ul class="mb-0">
                    <?php foreach ($highlightItems as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </main>
    </body>
    </html>
    <?php
}
