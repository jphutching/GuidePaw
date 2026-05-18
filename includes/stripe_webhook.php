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
        $purchaseType = strtolower(trim((string) ($metadata['purchase_type'] ?? 'support')));
        if ($purchaseType !== 'support' && $purchaseType !== '') {
            return ['ok' => true, 'ignored' => true, 'event_type' => trim((string) ($event['type'] ?? '')) ?: 'unknown'];
        }
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

if (!function_exists('gpStripeSupportRevenueSummary')) {
    function gpStripeSupportRevenueSummary(PDO $pdo): array
    {
        if (!gpStripeSupportPaymentsTableExists($pdo)) {
            return [
                'payment_count' => 0,
                'total_cents' => 0,
                'one_time_cents' => 0,
                'monthly_cents' => 0,
                'last_30d_cents' => 0,
            ];
        }

        $row = $pdo->query("
            SELECT
                COUNT(*) AS payment_count,
                COALESCE(SUM(amount_total_cents), 0) AS total_cents,
                COALESCE(SUM(CASE WHEN support_type = 'one_time' THEN amount_total_cents ELSE 0 END), 0) AS one_time_cents,
                COALESCE(SUM(CASE WHEN support_type = 'monthly' THEN amount_total_cents ELSE 0 END), 0) AS monthly_cents,
                COALESCE(SUM(CASE WHEN updated_at >= (CURRENT_TIMESTAMP - INTERVAL '30 days') THEN amount_total_cents ELSE 0 END), 0) AS last_30d_cents
            FROM support_funding_events
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'payment_count' => (int) ($row['payment_count'] ?? 0),
            'total_cents' => (int) ($row['total_cents'] ?? 0),
            'one_time_cents' => (int) ($row['one_time_cents'] ?? 0),
            'monthly_cents' => (int) ($row['monthly_cents'] ?? 0),
            'last_30d_cents' => (int) ($row['last_30d_cents'] ?? 0),
        ];
    }
}

if (!function_exists('gpStripeSupportMonthlySeries')) {
    function gpStripeSupportMonthlySeries(PDO $pdo, int $months = 6): array
    {
        if (!gpStripeSupportPaymentsTableExists($pdo)) {
            return [];
        }

        $months = max(1, min(24, $months));
        $lookbackMonths = max(0, $months - 1);
        $stmt = $pdo->prepare("
            SELECT
                TO_CHAR(date_trunc('month', created_at), 'YYYY-MM') AS month_key,
                COALESCE(SUM(amount_total_cents), 0) AS support_cents,
                COALESCE(SUM(CASE WHEN support_type = 'one_time' THEN amount_total_cents ELSE 0 END), 0) AS one_time_cents,
                COALESCE(SUM(CASE WHEN support_type = 'monthly' THEN amount_total_cents ELSE 0 END), 0) AS monthly_cents,
                COUNT(*) AS payment_count
            FROM support_funding_events
            WHERE created_at >= (date_trunc('month', CURRENT_TIMESTAMP) - INTERVAL '{$lookbackMonths} months')
            GROUP BY 1
            ORDER BY 1 ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $series = [];
        foreach ($rows as $row) {
            $monthKey = trim((string) ($row['month_key'] ?? ''));
            if ($monthKey === '') {
                continue;
            }

            $series[$monthKey] = [
                'month_key' => $monthKey,
                'support_cents' => (int) ($row['support_cents'] ?? 0),
                'one_time_cents' => (int) ($row['one_time_cents'] ?? 0),
                'monthly_cents' => (int) ($row['monthly_cents'] ?? 0),
                'payment_count' => (int) ($row['payment_count'] ?? 0),
            ];
        }

        return $series;
    }
}

if (!function_exists('gpStripeSupportTimelineSummary')) {
    function gpStripeSupportTimelineSummary(PDO $pdo): array
    {
        if (!gpStripeSupportPaymentsTableExists($pdo)) {
            return [
                'first' => null,
                'latest' => null,
                'payment_count' => 0,
                'total_cents' => 0,
            ];
        }

        $first = $pdo->query("
            SELECT
                stripe_event_id,
                stripe_event_type,
                stripe_checkout_session_id,
                support_type,
                support_mode,
                user_id,
                customer_email,
                amount_total_cents,
                amount_subtotal_cents,
                currency,
                payment_status,
                payment_intent_id,
                subscription_id,
                livemode,
                created_at,
                updated_at
            FROM support_funding_events
            ORDER BY created_at ASC, id ASC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC) ?: null;

        $latest = $pdo->query("
            SELECT
                stripe_event_id,
                stripe_event_type,
                stripe_checkout_session_id,
                support_type,
                support_mode,
                user_id,
                customer_email,
                amount_total_cents,
                amount_subtotal_cents,
                currency,
                payment_status,
                payment_intent_id,
                subscription_id,
                livemode,
                created_at,
                updated_at
            FROM support_funding_events
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC) ?: null;

        $summary = gpStripeSupportRevenueSummary($pdo);

        return [
            'first' => is_array($first) ? $first : null,
            'latest' => is_array($latest) ? $latest : null,
            'payment_count' => (int) ($summary['payment_count'] ?? 0),
            'total_cents' => (int) ($summary['total_cents'] ?? 0),
        ];
    }
}

if (!function_exists('gpStripeSupportUserSummaries')) {
    function gpStripeSupportUserSummaries(PDO $pdo, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if (!$userIds || !gpStripeSupportPaymentsTableExists($pdo)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("
            SELECT
                user_id,
                COUNT(*) AS payment_count,
                COALESCE(SUM(amount_total_cents), 0) AS total_cents,
                COALESCE(SUM(CASE WHEN support_type = 'one_time' THEN amount_total_cents ELSE 0 END), 0) AS one_time_cents,
                COALESCE(SUM(CASE WHEN support_type = 'monthly' THEN amount_total_cents ELSE 0 END), 0) AS monthly_cents,
                MAX(updated_at) AS last_support_at
            FROM support_funding_events
            WHERE user_id IN ({$placeholders})
            GROUP BY user_id
        ");
        $stmt->execute($userIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summaries = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $monthlyCents = (int) ($row['monthly_cents'] ?? 0);
            $oneTimeCents = (int) ($row['one_time_cents'] ?? 0);
            $label = $monthlyCents > 0 ? 'Monthly supporter' : ($oneTimeCents > 0 ? 'One-time supporter' : 'Supporter');
            $detailBits = [];
            if ((int) ($row['payment_count'] ?? 0) > 1) {
                $detailBits[] = (string) ((int) $row['payment_count']) . ' payments';
            } elseif ((int) ($row['payment_count'] ?? 0) === 1) {
                $detailBits[] = '1 payment';
            }
            $totalCents = (int) ($row['total_cents'] ?? 0);
            if ($totalCents > 0) {
                $detailBits[] = '$' . number_format($totalCents / 100, 2);
            }
            if (!empty($row['last_support_at'])) {
                $detailBits[] = 'Last: ' . (string) $row['last_support_at'];
            }

            $summaries[$userId] = [
                'label' => $label,
                'payment_count' => (int) ($row['payment_count'] ?? 0),
                'total_cents' => $totalCents,
                'one_time_cents' => $oneTimeCents,
                'monthly_cents' => $monthlyCents,
                'last_support_at' => (string) ($row['last_support_at'] ?? ''),
                'detail' => implode(' | ', $detailBits),
            ];
        }

        return $summaries;
    }
}
