<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/paywall_catalog.php';
require_once __DIR__ . '/../includes/paywall_purchase.php';
require_once __DIR__ . '/../includes/stripe_checkout.php';
require_once __DIR__ . '/../includes/support_badges.php';

$user = requireApiUser($pdo);
$userRecord = getUserRecord($pdo, (int) $user['id']) ?: [];
$currentTier = gpUserTier($userRecord);
$activeDogId = apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0)) ?? 0;

function gpBillingNormalizeSupportOption(string $supportType): array
{
    $supportType = strtolower(trim($supportType)) === 'monthly' ? 'monthly' : 'one_time';
    if ($supportType === 'monthly') {
        return [
            'support_type' => 'monthly',
            'label' => 'Monthly support',
            'summary' => 'A recurring contribution for ongoing development, hosting, and maintenance.',
            'emoji' => '🔁',
            'mode' => 'subscription',
            'price_id_configured' => gpStripeSupportPriceId('monthly') !== '',
            'checkout_available' => gpStripeCheckoutConfigured() && gpStripeSupportPriceId('monthly') !== '',
        ];
    }

    return [
        'support_type' => 'one_time',
        'label' => 'One-time support',
        'summary' => 'A single contribution to help keep GuidePaw running.',
        'emoji' => '💙',
        'mode' => 'payment',
        'price_id_configured' => gpStripeSupportPriceId('one_time') !== '',
        'checkout_available' => gpStripeCheckoutConfigured() && gpStripeSupportPriceId('one_time') !== '',
    ];
}

function gpBillingNormalizePlanRow(array $row, string $currentTier): array
{
    return [
        'slug' => (string) ($row['slug'] ?? ''),
        'label' => (string) ($row['label'] ?? ''),
        'summary' => (string) ($row['summary'] ?? ''),
        'included_text' => gpPaywallCatalogBullets((string) ($row['included_text'] ?? '')),
        'locked_text' => gpPaywallCatalogBullets((string) ($row['locked_text'] ?? '')),
        'required_tier' => gpNormalizeUserTier((string) ($row['required_tier'] ?? 'free')),
        'is_current' => gpNormalizeUserTier((string) ($row['required_tier'] ?? 'free')) === gpNormalizeUserTier($currentTier),
    ];
}

function gpBillingNormalizeServiceRow(PDO $pdo, array $row, int $userId, ?int $activeDogId): array
{
    $serviceSlug = (string) ($row['slug'] ?? '');
    $billingModel = strtolower(trim((string) ($row['billing_model'] ?? 'plan')));
    $scope = strtolower(trim((string) ($row['scope'] ?? 'user')));
    $stripePriceId = trim((string) ($row['stripe_price_id'] ?? ''));
    $includedText = gpPaywallCatalogBullets((string) ($row['included_text'] ?? ''));
    $lockedText = gpPaywallCatalogBullets((string) ($row['locked_text'] ?? ''));

    $active = false;
    $checkoutAvailable = false;
    $actionLabel = '';
    $requiresActiveDog = false;

    if (in_array($billingModel, ['lifetime_dog', 'lifetime_user', 'recurring_user'], true)) {
        if ($scope === 'dog') {
            $active = false;
            if ($activeDogId !== null && $activeDogId > 0) {
                if ($serviceSlug === 'qr_tracking') {
                    $active = gpDogQrTrackingAvailable($pdo, $userId, (int) $activeDogId);
                } else {
                    $active = gpPaywallDogServiceActive($pdo, (int) $activeDogId, $serviceSlug);
                }
            }
            $checkoutAvailable = $stripePriceId !== '' && gpStripeCheckoutConfigured();
            $requiresActiveDog = true;
            $actionLabel = 'Buy for this dog';
            if ($active) {
                $actionLabel = 'Already active';
            } elseif (!$activeDogId) {
                $actionLabel = 'Choose a dog first';
            }
        } else {
            $active = gpPaywallUserServiceActive($pdo, $userId, $serviceSlug);
            $checkoutAvailable = $stripePriceId !== '' && gpStripeCheckoutConfigured();
            $actionLabel = 'Buy now';
            if ($active) {
                $actionLabel = 'Already active';
            }
        }
    }

    return [
        'slug' => $serviceSlug,
        'label' => (string) ($row['label'] ?? ''),
        'summary' => (string) ($row['summary'] ?? ''),
        'included_text' => $includedText,
        'locked_text' => $lockedText,
        'billing_model' => $billingModel,
        'required_tier' => gpNormalizeUserTier((string) ($row['required_tier'] ?? 'free')),
        'scope' => $scope,
        'price_cents' => (int) ($row['price_cents'] ?? 0),
        'currency' => strtolower(trim((string) ($row['currency'] ?? 'usd'))) ?: 'usd',
        'stripe_price_id' => $stripePriceId,
        'notes' => trim((string) ($row['notes'] ?? '')),
        'active' => $active,
        'checkout_available' => $checkoutAvailable,
        'requires_active_dog' => $requiresActiveDog,
        'action_label' => $actionLabel,
    ];
}

function gpBillingNormalizeSupportEvent(array $row): array
{
    return [
        'source' => 'support',
        'title' => trim((string) ($row['support_type'] ?? 'one_time')) === 'monthly' ? 'Monthly support' : 'One-time support',
        'amount_cents' => (int) ($row['amount_total_cents'] ?? 0),
        'currency' => strtolower(trim((string) ($row['currency'] ?? 'usd'))) ?: 'usd',
        'status' => trim((string) ($row['payment_status'] ?? '')),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'details' => trim((string) ($row['stripe_event_type'] ?? 'Stripe checkout complete')),
    ];
}

function gpBillingNormalizePurchaseEvent(array $row): array
{
    $serviceLabel = trim((string) ($row['service_label'] ?? 'Service purchase'));
    if ($serviceLabel === '') {
        $serviceLabel = 'Service purchase';
    }
    return [
        'source' => 'purchase',
        'title' => $serviceLabel,
        'amount_cents' => (int) ($row['amount_total_cents'] ?? 0),
        'currency' => strtolower(trim((string) ($row['currency'] ?? 'usd'))) ?: 'usd',
        'status' => trim((string) ($row['payment_status'] ?? '')),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'details' => trim((string) ($row['service_slug'] ?? '')),
    ];
}

function gpBillingRecentSupportEvents(PDO $pdo, int $userId, int $limit = 10): array
{
    if (!function_exists('gpStripeSupportPaymentsTableExists') || !gpStripeSupportPaymentsTableExists($pdo)) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $stmt = $pdo->prepare("
        SELECT stripe_event_type, support_type, amount_total_cents, currency, payment_status, created_at
        FROM support_funding_events
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$userId]);
    return array_map('gpBillingNormalizeSupportEvent', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function gpBillingRecentPurchaseEvents(PDO $pdo, int $userId, int $limit = 10): array
{
    if (!gpPaywallPurchaseTableExists($pdo)) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $stmt = $pdo->prepare("
        SELECT service_slug, service_label, amount_total_cents, currency, payment_status, created_at
        FROM paywall_purchase_events
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$userId]);
    return array_map('gpBillingNormalizePurchaseEvent', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function gpBillingCreateSupportCheckout(PDO $pdo, array $userRecord, string $supportType): array
{
    $supportType = strtolower(trim($supportType)) === 'monthly' ? 'monthly' : 'one_time';
    $priceId = gpStripeSupportPriceId($supportType);
    if ($priceId === '' || !gpStripeCheckoutConfigured()) {
        return ['ok' => false, 'error' => 'Support checkout is not configured yet.'];
    }

    $urls = gpStripeSupportCheckoutUrls($supportType);
    $payload = [
        'mode' => $supportType === 'monthly' ? 'subscription' : 'payment',
        'client_reference_id' => (string) ($userRecord['id'] ?? ''),
        'success_url' => $urls['success_url'],
        'cancel_url' => $urls['cancel_url'],
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => '1',
        'metadata[support_type]' => $supportType,
        'metadata[purchase_type]' => 'support',
        'metadata[app]' => appName(),
        'metadata[user_id]' => (string) ($userRecord['id'] ?? ''),
    ];

    $email = trim((string) ($userRecord['email'] ?? ''));
    if ($email !== '') {
        $payload['customer_email'] = $email;
    }

    $checkout = gpStripeCreateCheckoutSession($payload);
    if (!($checkout['ok'] ?? false) || empty($checkout['url'])) {
        return ['ok' => false, 'error' => (string) ($checkout['error'] ?? 'Unable to open support checkout.')];
    }

    return [
        'ok' => true,
        'checkout_url' => (string) $checkout['url'],
        'message' => $supportType === 'monthly' ? 'Opening monthly support checkout.' : 'Opening one-time support checkout.',
        'support_type' => $supportType,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    if ($action !== 'start_checkout') {
        apiJson(['success' => false, 'message' => 'Unsupported action.'], 422);
    }

    $kind = strtolower(trim((string) ($input['kind'] ?? '')));
    if ($kind === 'support') {
        $supportType = strtolower(trim((string) ($input['support_type'] ?? '')));
        $result = gpBillingCreateSupportCheckout($pdo, $userRecord, $supportType);
        if (!($result['ok'] ?? false)) {
            apiJson(['success' => false, 'message' => (string) ($result['error'] ?? 'Unable to start support checkout.')], 422);
        }
        apiJson([
            'success' => true,
            'kind' => 'support',
            'support_type' => (string) ($result['support_type'] ?? 'one_time'),
            'checkout_url' => (string) ($result['checkout_url'] ?? ''),
            'message' => (string) ($result['message'] ?? 'Opening support checkout.'),
        ]);
    }

    if ($kind === 'service') {
        $serviceSlug = strtolower(trim((string) ($input['service_slug'] ?? '')));
        $requestedDogId = (int) ($input['dog_id'] ?? 0);
        $dogId = $requestedDogId > 0 ? $requestedDogId : $activeDogId;
        $payloadResult = gpPaywallServiceCheckoutPayload($pdo, (int) $user['id'], $serviceSlug, $dogId > 0 ? $dogId : null);
        if (!($payloadResult['ok'] ?? false)) {
            apiJson(['success' => false, 'message' => (string) ($payloadResult['error'] ?? 'Unable to start service checkout.')], 422);
        }

        $checkout = gpStripeCreateCheckoutSession((array) ($payloadResult['payload'] ?? []));
        if (!($checkout['ok'] ?? false) || empty($checkout['url'])) {
            apiJson(['success' => false, 'message' => (string) ($checkout['error'] ?? 'Unable to open checkout.')], 422);
        }

        apiJson([
            'success' => true,
            'kind' => 'service',
            'service_slug' => $serviceSlug,
            'dog_id' => (int) ($payloadResult['dog_id'] ?? 0),
            'checkout_url' => (string) ($checkout['url'] ?? ''),
            'message' => 'Opening service checkout.',
        ]);
    }

    apiJson(['success' => false, 'message' => 'Missing billing kind.'], 422);
}

$requestedDogId = (int) ($_GET['dog_id'] ?? 0);
$resolvedDogId = $requestedDogId > 0 ? $requestedDogId : $activeDogId;
if ($resolvedDogId > 0 && !hasDogAccess($pdo, (int) $user['id'], $resolvedDogId)) {
    apiJson(['success' => false, 'message' => 'No dog access.'], 403);
}

$plans = array_map(
    static fn(array $row): array => gpBillingNormalizePlanRow($row, $currentTier),
    gpPaywallPlanRows($pdo)
);
$services = array_map(
    static fn(array $row): array => gpBillingNormalizeServiceRow($pdo, $row, (int) $user['id'], $resolvedDogId > 0 ? $resolvedDogId : null),
    gpPaywallServiceRows($pdo)
);

apiJson([
    'success' => true,
    'user_id' => (int) $user['id'],
    'username' => (string) ($user['username'] ?? ''),
    'active_dog_id' => $activeDogId,
    'requested_dog_id' => $resolvedDogId,
    'current_tier' => $currentTier,
    'current_tier_label' => gpTierDisplayLabel($currentTier),
    'dog_count' => gpUserDogCount($pdo, (int) $user['id']),
    'can_create_another_dog' => gpUserCanCreateAnotherDog($pdo, (int) $user['id']),
    'support_badge' => gpSupportBadgeForUser($pdo, $userRecord),
    'plan_rows' => $plans,
    'support_options' => array_map(
        static fn(string $supportType): array => gpBillingNormalizeSupportOption($supportType),
        ['one_time', 'monthly']
    ),
    'service_rows' => $services,
    'recent_support_events' => gpBillingRecentSupportEvents($pdo, (int) $user['id']),
    'recent_purchase_events' => gpBillingRecentPurchaseEvents($pdo, (int) $user['id']),
]);
