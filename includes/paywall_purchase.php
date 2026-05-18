<?php
declare(strict_types=1);

require_once __DIR__ . '/paywall_catalog.php';
require_once __DIR__ . '/stripe_checkout.php';

if (!function_exists('gpPaywallPurchaseTableExists')) {
    function gpPaywallPurchaseTableExists(PDO $pdo): bool
    {
        return gpPaywallCatalogTableExists($pdo, 'paywall_purchase_events');
    }
}

if (!function_exists('gpPaywallPurchaseEnsureSchema')) {
    function gpPaywallPurchaseEnsureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS paywall_purchase_events (
                id BIGSERIAL PRIMARY KEY,
                stripe_event_id TEXT NOT NULL UNIQUE,
                stripe_event_type TEXT NOT NULL,
                stripe_checkout_session_id TEXT NOT NULL UNIQUE,
                purchase_type TEXT NOT NULL DEFAULT 'service',
                service_slug TEXT NOT NULL DEFAULT '',
                service_label TEXT NOT NULL DEFAULT '',
                scope TEXT NOT NULL DEFAULT 'user',
                user_id BIGINT NULL,
                dog_id BIGINT NULL,
                client_reference_id TEXT NOT NULL DEFAULT '',
                customer_email TEXT NOT NULL DEFAULT '',
                amount_total_cents INTEGER NOT NULL DEFAULT 0,
                amount_subtotal_cents INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'usd',
                payment_status TEXT NOT NULL DEFAULT '',
                payment_intent_id TEXT NOT NULL DEFAULT '',
                subscription_id TEXT NOT NULL DEFAULT '',
                price_id TEXT NOT NULL DEFAULT '',
                livemode BOOLEAN NOT NULL DEFAULT FALSE,
                raw_event JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS paywall_purchase_events_updated_at_idx ON paywall_purchase_events (updated_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS paywall_purchase_events_service_idx ON paywall_purchase_events (service_slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS paywall_purchase_events_user_idx ON paywall_purchase_events (user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS paywall_purchase_events_dog_idx ON paywall_purchase_events (dog_id)");
    }
}

if (!function_exists('gpPaywallCatalogStripePriceId')) {
    function gpPaywallCatalogStripePriceId(PDO $pdo, string $slug, string $fallback = ''): string
    {
        $row = gpPaywallCatalogRow($pdo, $slug);
        if ($row && !empty($row['stripe_price_id'])) {
            return trim((string) $row['stripe_price_id']);
        }
        return trim($fallback);
    }
}

if (!function_exists('gpPaywallServiceCheckoutPayload')) {
    function gpPaywallServiceCheckoutPayload(PDO $pdo, int $userId, string $serviceSlug, ?int $dogId = null): array
    {
        $user = getUserRecord($pdo, $userId) ?: [];
        $row = gpPaywallCatalogRow($pdo, $serviceSlug);
        if (!$row || ($row['item_type'] ?? '') !== 'service') {
            return ['ok' => false, 'error' => 'Unknown service.'];
        }

        $billingModel = strtolower(trim((string) ($row['billing_model'] ?? 'plan')));
        if (!in_array($billingModel, ['lifetime_dog', 'lifetime_user', 'recurring_user'], true)) {
            return ['ok' => false, 'error' => 'This service is not sold through checkout.'];
        }

        $priceId = trim((string) ($row['stripe_price_id'] ?? ''));
        if ($priceId === '') {
            return ['ok' => false, 'error' => 'This service is not configured with a Stripe price ID yet.'];
        }

        $scope = strtolower(trim((string) ($row['scope'] ?? 'user')));
        if ($scope === 'dog') {
            $dogId = $dogId !== null ? (int) $dogId : null;
            if ($dogId === null || $dogId <= 0) {
                return ['ok' => false, 'error' => 'Select a dog for this add-on.'];
            }
            if (!hasDogAccess($pdo, $userId, $dogId)) {
                return ['ok' => false, 'error' => 'You do not have access to that dog.'];
            }
            if (gpPaywallDogServiceActive($pdo, $dogId, $serviceSlug)) {
                return ['ok' => false, 'error' => 'That dog already has this add-on.'];
            }
        } else {
            $dogId = null;
            if (gpPaywallUserServiceActive($pdo, $userId, $serviceSlug)) {
                return ['ok' => false, 'error' => 'That add-on is already active for this account.'];
            }
        }

        $base = gpStripeSiteBaseUrl();
        $urls = [
            'success_url' => rtrim($base, '/') . '/purchase_service.php?service=' . rawurlencode($serviceSlug) . '&checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim($base, '/') . '/purchase_service.php?service=' . rawurlencode($serviceSlug) . '&checkout=cancel',
        ];

        $payload = [
            'mode' => 'payment',
            'client_reference_id' => (string) $userId,
            'success_url' => $urls['success_url'],
            'cancel_url' => $urls['cancel_url'],
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => '1',
            'metadata[purchase_type]' => 'service',
            'metadata[service_slug]' => $serviceSlug,
            'metadata[service_label]' => (string) ($row['label'] ?? $serviceSlug),
            'metadata[scope]' => $scope,
            'metadata[user_id]' => (string) $userId,
            'metadata[app]' => appName(),
        ];

        if ($dogId !== null) {
            $payload['metadata[dog_id]'] = (string) $dogId;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '') {
            $payload['customer_email'] = $email;
        }

        return [
            'ok' => true,
            'payload' => $payload,
            'row' => $row,
            'dog_id' => $dogId,
            'service_slug' => $serviceSlug,
        ];
    }
}

if (!function_exists('gpPaywallServiceRecordEvent')) {
    function gpPaywallServiceRecordEvent(PDO $pdo, array $event): array
    {
        gpPaywallPurchaseEnsureSchema($pdo);

        $object = gpStripeSupportNormalizeWebhookObject($event);
        $sessionId = trim((string) ($object['id'] ?? ''));
        if ($sessionId === '') {
            return ['ok' => false, 'error' => 'Missing Checkout Session id.'];
        }

        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $purchaseType = strtolower(trim((string) ($metadata['purchase_type'] ?? '')));
        if ($purchaseType !== 'service') {
            return ['ok' => true, 'ignored' => true, 'event_type' => (string) ($event['type'] ?? 'unknown')];
        }

        $serviceSlug = strtolower(trim((string) ($metadata['service_slug'] ?? '')));
        if ($serviceSlug === '') {
            return ['ok' => false, 'error' => 'Missing service slug.'];
        }

        $row = gpPaywallCatalogRow($pdo, $serviceSlug);
        if (!$row) {
            return ['ok' => false, 'error' => 'Unknown service.'];
        }

        $scope = strtolower(trim((string) ($metadata['scope'] ?? ($row['scope'] ?? 'user'))));
        $scope = in_array($scope, ['user', 'dog'], true) ? $scope : 'user';
        $serviceLabel = trim((string) ($metadata['service_label'] ?? ($row['label'] ?? $serviceSlug)));
        $clientReference = trim((string) ($object['client_reference_id'] ?? ''));
        $userId = null;
        $userIdCandidate = trim((string) ($metadata['user_id'] ?? $clientReference));
        if ($userIdCandidate !== '' && ctype_digit($userIdCandidate)) {
            $userId = (int) $userIdCandidate;
        }
        $dogId = null;
        $dogIdCandidate = trim((string) ($metadata['dog_id'] ?? ''));
        if ($dogIdCandidate !== '' && ctype_digit($dogIdCandidate)) {
            $dogId = (int) $dogIdCandidate;
        }

        $eventId = trim((string) ($event['id'] ?? ''));
        if ($eventId === '') {
            $eventId = $sessionId;
        }

        $payload = [
            'stripe_event_id' => $eventId,
            'stripe_event_type' => trim((string) ($event['type'] ?? 'checkout.session.completed')),
            'stripe_checkout_session_id' => $sessionId,
            'purchase_type' => 'service',
            'service_slug' => $serviceSlug,
            'service_label' => $serviceLabel,
            'scope' => $scope,
            'user_id' => $userId,
            'dog_id' => $dogId,
            'client_reference_id' => $clientReference,
            'customer_email' => trim((string) ($object['customer_email'] ?? '')),
            'amount_total_cents' => (int) ($object['amount_total'] ?? 0),
            'amount_subtotal_cents' => (int) ($object['amount_subtotal'] ?? 0),
            'currency' => strtolower(trim((string) ($object['currency'] ?? 'usd'))) ?: 'usd',
            'payment_status' => trim((string) ($object['payment_status'] ?? '')),
            'payment_intent_id' => trim((string) ($object['payment_intent'] ?? '')),
            'subscription_id' => trim((string) ($object['subscription'] ?? '')),
            'price_id' => trim((string) ($row['stripe_price_id'] ?? '')),
            'livemode' => !empty($event['livemode']) ? 1 : 0,
            'raw_event' => json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $stmt = $pdo->prepare("
            INSERT INTO paywall_purchase_events (
                stripe_event_id,
                stripe_event_type,
                stripe_checkout_session_id,
                purchase_type,
                service_slug,
                service_label,
                scope,
                user_id,
                dog_id,
                client_reference_id,
                customer_email,
                amount_total_cents,
                amount_subtotal_cents,
                currency,
                payment_status,
                payment_intent_id,
                subscription_id,
                price_id,
                livemode,
                raw_event,
                updated_at
            ) VALUES (
                :stripe_event_id,
                :stripe_event_type,
                :stripe_checkout_session_id,
                :purchase_type,
                :service_slug,
                :service_label,
                :scope,
                :user_id,
                :dog_id,
                :client_reference_id,
                :customer_email,
                :amount_total_cents,
                :amount_subtotal_cents,
                :currency,
                :payment_status,
                :payment_intent_id,
                :subscription_id,
                :price_id,
                :livemode,
                CAST(:raw_event AS jsonb),
                CURRENT_TIMESTAMP
            )
            ON CONFLICT (stripe_checkout_session_id) DO UPDATE SET
                stripe_event_id = EXCLUDED.stripe_event_id,
                stripe_event_type = EXCLUDED.stripe_event_type,
                purchase_type = EXCLUDED.purchase_type,
                service_slug = EXCLUDED.service_slug,
                service_label = EXCLUDED.service_label,
                scope = EXCLUDED.scope,
                user_id = EXCLUDED.user_id,
                dog_id = EXCLUDED.dog_id,
                client_reference_id = EXCLUDED.client_reference_id,
                customer_email = EXCLUDED.customer_email,
                amount_total_cents = EXCLUDED.amount_total_cents,
                amount_subtotal_cents = EXCLUDED.amount_subtotal_cents,
                currency = EXCLUDED.currency,
                payment_status = EXCLUDED.payment_status,
                payment_intent_id = EXCLUDED.payment_intent_id,
                subscription_id = EXCLUDED.subscription_id,
                price_id = EXCLUDED.price_id,
                livemode = EXCLUDED.livemode,
                raw_event = EXCLUDED.raw_event,
                updated_at = CURRENT_TIMESTAMP
            RETURNING *
        ");
        $stmt->execute($payload);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $eventType = trim((string) ($event['type'] ?? 'checkout.session.completed'));
        if (in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $source = 'stripe:' . $sessionId;
            $notes = 'Stripe ' . $serviceSlug . ' purchase';
            if ($scope === 'dog' && $dogId !== null) {
                gpGrantDogServiceEntitlement($pdo, $dogId, $serviceSlug, $source, $notes);
            } elseif ($userId !== null) {
                gpGrantUserServiceEntitlement($pdo, $userId, $serviceSlug, $source, $notes);
            }
        }

        return [
            'ok' => true,
            'row' => $row,
            'session_id' => $sessionId,
            'service_slug' => $serviceSlug,
            'event_type' => $payload['stripe_event_type'],
        ];
    }
}

if (!function_exists('gpPaywallPurchaseHandleWebhookEvent')) {
    function gpPaywallPurchaseHandleWebhookEvent(PDO $pdo, array $event): array
    {
        $eventType = trim((string) ($event['type'] ?? ''));
        $allowed = ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed'];
        if (!in_array($eventType, $allowed, true)) {
            return ['ok' => true, 'ignored' => true, 'event_type' => $eventType ?: 'unknown'];
        }

        $object = gpStripeSupportNormalizeWebhookObject($event);
        if (($object['object'] ?? '') !== 'checkout.session') {
            return ['ok' => false, 'error' => 'Unexpected Stripe object type.'];
        }

        return gpPaywallServiceRecordEvent($pdo, $event);
    }
}

if (!function_exists('gpPaywallRecentPurchaseEvents')) {
    function gpPaywallRecentPurchaseEvents(PDO $pdo, int $limit = 10): array
    {
        if (!gpPaywallPurchaseTableExists($pdo)) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("
            SELECT *
            FROM paywall_purchase_events
            ORDER BY updated_at DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
