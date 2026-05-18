<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/stripe_webhook.php';
require_once __DIR__ . '/includes/paywall_purchase.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

$secret = gpStripeWebhookSecret();
if ($secret === '') {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Stripe webhook secret is not configured.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = file_get_contents('php://input');
$payload = is_string($payload) ? $payload : '';
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if (!gpStripeVerifyWebhookSignature($payload, $signature, $secret)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid Stripe signature.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $result = gpStripeSupportHandleWebhookEvent($pdo, $event);
    if (!empty($result['ignored'])) {
        $result = gpPaywallPurchaseHandleWebhookEvent($pdo, $event);
    }
    if (!empty($result['ok']) && empty($result['ignored'])) {
        writeAuditLog(
            $pdo,
            ($result['service_slug'] ?? '') !== '' ? 'stripe_paywall_event_recorded' : 'stripe_support_event_recorded',
            ($result['service_slug'] ?? '') !== '' ? 'paywall_purchase_events' : 'support_funding_events',
            isset($result['row']['id']) ? (int) $result['row']['id'] : null,
            (($result['service_slug'] ?? '') !== '' ? 'Stripe service event ' : 'Stripe support event ') . ($result['event_type'] ?? 'unknown') . ' recorded for session ' . ($result['session_id'] ?? 'unknown') . '.'
        );
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => !empty($result['ok']),
        'ignored' => !empty($result['ignored']),
        'event_type' => (string) ($result['event_type'] ?? ''),
        'session_id' => (string) ($result['session_id'] ?? ''),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
