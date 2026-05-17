<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

if (!function_exists('gpStripeWebhookSecret')) {
    function gpStripeWebhookSecret(): string
    {
        return trim((string) (gpEnv('GUIDEPAW_STRIPE_WEBHOOK_SECRET', '') ?? ''));
    }
}

if (!function_exists('gpStripeWebhookConfigured')) {
    function gpStripeWebhookConfigured(): bool
    {
        return gpStripeWebhookSecret() !== '';
    }
}

if (!function_exists('gpStripeWebhookEndpointUrl')) {
    function gpStripeWebhookEndpointUrl(): string
    {
        $base = trim((string) appUrl());
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $base = $host !== '' ? $scheme . '://' . $host : 'https://beta.guidepaw.app';
        }

        return rtrim($base, '/') . '/stripe_webhook.php';
    }
}

if (!function_exists('gpStripeSupportPaymentsTableExists')) {
    function gpStripeSupportPaymentsTableExists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                  AND table_name = ?
            )
        ");
        $stmt->execute(['support_funding_events']);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('gpStripeSupportEnsureSchema')) {
    function gpStripeSupportEnsureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS support_funding_events (
                id BIGSERIAL PRIMARY KEY,
                stripe_event_id TEXT NOT NULL UNIQUE,
                stripe_event_type TEXT NOT NULL,
                stripe_checkout_session_id TEXT NOT NULL UNIQUE,
                support_type TEXT NOT NULL DEFAULT 'one_time',
                support_mode TEXT NOT NULL DEFAULT 'payment',
                user_id BIGINT NULL,
                client_reference_id TEXT NOT NULL DEFAULT '',
                customer_email TEXT NOT NULL DEFAULT '',
                amount_total_cents INTEGER NOT NULL DEFAULT 0,
                amount_subtotal_cents INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'usd',
                payment_status TEXT NOT NULL DEFAULT '',
                payment_intent_id TEXT NOT NULL DEFAULT '',
                subscription_id TEXT NOT NULL DEFAULT '',
                livemode BOOLEAN NOT NULL DEFAULT FALSE,
                raw_event JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS support_funding_events_updated_at_idx ON support_funding_events (updated_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS support_funding_events_support_type_idx ON support_funding_events (support_type)");
    }
}

if (!function_exists('gpStripeVerifyWebhookSignature')) {
    function gpStripeVerifyWebhookSignature(string $payload, string $signatureHeader, string $endpointSecret, int $toleranceSeconds = 300): bool
    {
        $signatureHeader = trim($signatureHeader);
        $endpointSecret = trim($endpointSecret);
        if ($payload === '' || $signatureHeader === '' || $endpointSecret === '') {
            return false;
        }

        $timestamp = null;
        $v1Signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $segments = explode('=', trim($part), 2);
            if (count($segments) !== 2) {
                continue;
            }
            [$key, $value] = $segments;
            if ($key === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($key === 'v1') {
                $v1Signatures[] = trim($value);
            }
        }

        if ($timestamp === null || !$v1Signatures) {
            return false;
        }

        if ($toleranceSeconds > 0 && abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $endpointSecret);
        foreach ($v1Signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('gpStripeSupportNormalizeWebhookObject')) {
    function gpStripeSupportNormalizeWebhookObject(array $event): array
    {
        $object = $event['data']['object'] ?? [];
        return is_array($object) ? $object : [];
    }
}

if (!function_exists('gpStripeSupportRecordEvent')) {
    function gpStripeSupportRecordEvent(PDO $pdo, array $event): array
    {
        gpStripeSupportEnsureSchema($pdo);

        $object = gpStripeSupportNormalizeWebhookObject($event);
        $sessionId = trim((string) ($object['id'] ?? ''));
        if ($sessionId === '') {
            return ['ok' => false, 'error' => 'Missing Checkout Session id.'];
        }

        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $supportType = strtolower(trim((string) ($metadata['support_type'] ?? 'one_time')));
        $supportType = $supportType === 'monthly' ? 'monthly' : 'one_time';
        $supportMode = strtolower(trim((string) ($object['mode'] ?? 'payment')));
        $supportMode = in_array($supportMode, ['payment', 'subscription'], true) ? $supportMode : 'payment';

        $clientReference = trim((string) ($object['client_reference_id'] ?? ''));
        $userId = null;
        $userIdCandidate = trim((string) ($metadata['user_id'] ?? $clientReference));
        if ($userIdCandidate !== '' && ctype_digit($userIdCandidate)) {
            $userId = (int) $userIdCandidate;
        }

        $eventId = trim((string) ($event['id'] ?? ''));
        if ($eventId === '') {
            $eventId = $sessionId;
        }

        $payload = [
            'stripe_event_id' => $eventId,
            'stripe_event_type' => trim((string) ($event['type'] ?? 'checkout.session.completed')),
            'stripe_checkout_session_id' => $sessionId,
            'support_type' => $supportType,
            'support_mode' => $supportMode,
            'user_id' => $userId,
            'client_reference_id' => $clientReference,
            'customer_email' => trim((string) ($object['customer_email'] ?? '')),
            'amount_total_cents' => (int) ($object['amount_total'] ?? 0),
            'amount_subtotal_cents' => (int) ($object['amount_subtotal'] ?? 0),
            'currency' => strtolower(trim((string) ($object['currency'] ?? 'usd'))) ?: 'usd',
            'payment_status' => trim((string) ($object['payment_status'] ?? '')),
            'payment_intent_id' => trim((string) ($object['payment_intent'] ?? '')),
            'subscription_id' => trim((string) ($object['subscription'] ?? '')),
            'livemode' => !empty($event['livemode']) ? 1 : 0,
            'raw_event' => json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $stmt = $pdo->prepare("
            INSERT INTO support_funding_events (
                stripe_event_id,
                stripe_event_type,
                stripe_checkout_session_id,
                support_type,
                support_mode,
                user_id,
                client_reference_id,
                customer_email,
                amount_total_cents,
                amount_subtotal_cents,
                currency,
                payment_status,
                payment_intent_id,
                subscription_id,
                livemode,
                raw_event,
                updated_at
            ) VALUES (
                :stripe_event_id,
                :stripe_event_type,
                :stripe_checkout_session_id,
                :support_type,
                :support_mode,
                :user_id,
                :client_reference_id,
                :customer_email,
                :amount_total_cents,
                :amount_subtotal_cents,
                :currency,
                :payment_status,
                :payment_intent_id,
                :subscription_id,
                :livemode,
                CAST(:raw_event AS jsonb),
                CURRENT_TIMESTAMP
            )
            ON CONFLICT (stripe_checkout_session_id) DO UPDATE SET
                stripe_event_id = EXCLUDED.stripe_event_id,
                stripe_event_type = EXCLUDED.stripe_event_type,
                support_type = EXCLUDED.support_type,
                support_mode = EXCLUDED.support_mode,
                user_id = EXCLUDED.user_id,
                client_reference_id = EXCLUDED.client_reference_id,
                customer_email = EXCLUDED.customer_email,
                amount_total_cents = EXCLUDED.amount_total_cents,
                amount_subtotal_cents = EXCLUDED.amount_subtotal_cents,
                currency = EXCLUDED.currency,
                payment_status = EXCLUDED.payment_status,
                payment_intent_id = EXCLUDED.payment_intent_id,
                subscription_id = EXCLUDED.subscription_id,
                livemode = EXCLUDED.livemode,
                raw_event = EXCLUDED.raw_event,
                updated_at = CURRENT_TIMESTAMP
            RETURNING *
        ");
        $stmt->execute($payload);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'ok' => true,
            'row' => $row,
            'session_id' => $sessionId,
            'support_type' => $supportType,
            'event_type' => $payload['stripe_event_type'],
        ];
    }
}

if (!function_exists('gpStripeSupportHandleWebhookEvent')) {
    function gpStripeSupportHandleWebhookEvent(PDO $pdo, array $event): array
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

        return gpStripeSupportRecordEvent($pdo, $event);
    }
}

if (!function_exists('gpStripeSupportRecentEvents')) {
    function gpStripeSupportRecentEvents(PDO $pdo, int $limit = 10): array
    {
        if (!gpStripeSupportPaymentsTableExists($pdo)) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("
            SELECT *
            FROM support_funding_events
            ORDER BY updated_at DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
