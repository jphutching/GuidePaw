<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

if (!function_exists('gpStripeSecretKey')) {
    function gpStripeSecretKey(): string
    {
        return trim((string) (gpEnv('GUIDEPAW_STRIPE_SECRET_KEY', gpEnv('STRIPE_SECRET_KEY', '')) ?? ''));
    }
}

if (!function_exists('gpStripeApiVersion')) {
    function gpStripeApiVersion(): string
    {
        return trim((string) (gpEnv('GUIDEPAW_STRIPE_API_VERSION', '2026-02-25.clover') ?? '2026-02-25.clover'));
    }
}

if (!function_exists('gpStripeSiteBaseUrl')) {
    function gpStripeSiteBaseUrl(): string
    {
        $appUrl = trim((string) appUrl());
        if ($appUrl !== '') {
            return $appUrl;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            return $scheme . '://' . $host;
        }

        return 'https://guidepaw.app';
    }
}

if (!function_exists('gpStripeCheckoutConfigured')) {
    function gpStripeCheckoutConfigured(): bool
    {
        return gpStripeSecretKey() !== '';
    }
}

if (!function_exists('gpStripeSupportPriceId')) {
    function gpStripeSupportPriceId(string $supportType): string
    {
        $supportType = strtolower(trim($supportType));
        if ($supportType === 'monthly') {
            return trim((string) (gpEnv('GUIDEPAW_STRIPE_FUNDING_MONTHLY_PRICE_ID', '') ?? ''));
        }
        return trim((string) (gpEnv('GUIDEPAW_STRIPE_FUNDING_ONE_TIME_PRICE_ID', '') ?? ''));
    }
}

if (!function_exists('gpStripeSupportCheckoutUrls')) {
    function gpStripeSupportCheckoutUrls(string $supportType): array
    {
        $base = rtrim(gpStripeSiteBaseUrl(), '/');
        $supportType = strtolower(trim($supportType)) === 'monthly' ? 'monthly' : 'one_time';
        return [
            'success_url' => $base . '/support_funding.php?checkout=success&support_type=' . rawurlencode($supportType) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base . '/support_funding.php?checkout=cancel&support_type=' . rawurlencode($supportType),
        ];
    }
}

if (!function_exists('gpStripeCreateCheckoutSession')) {
    function gpStripeCreateCheckoutSession(array $payload): array
    {
        $secret = gpStripeSecretKey();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Stripe is not configured.'];
        }

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/x-www-form-urlencoded',
                'Stripe-Version: ' . gpStripeApiVersion(),
                'User-Agent: GuidePawStripeCheckout/1.0',
            ],
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $err !== '' ? $err : 'Unable to create Stripe checkout session.'];
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Stripe returned an unexpected response.'];
        }

        if ($status < 200 || $status >= 300 || empty($data['url'])) {
            $stripeError = (string) ($data['error']['message'] ?? 'Unable to create Stripe checkout session.');
            return ['ok' => false, 'error' => $stripeError];
        }

        return ['ok' => true, 'session' => $data, 'url' => (string) $data['url']];
    }
}
