<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

function gpSmsFlag(string $key, bool $fallback = false): bool
{
    $value = strtolower(trim((string) gpEnv($key, $fallback ? 'true' : 'false')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function gpSmsGlobalEnabled(): bool
{
    return gpSmsFlag('SMS_NOTIFY_ENABLED', false);
}

function gpSmsProvider(): string
{
    return strtolower(trim((string) gpEnv('SMS_PROVIDER', 'twilio')));
}

function gpSmsNormalizePhone(?string $phone): string
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '';
    }

    if (str_starts_with($phone, '+')) {
        return preg_replace('/[^+0-9]/', '', $phone) ?: '';
    }

    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        return '+' . $digits;
    }
    return $digits !== '' ? '+' . $digits : '';
}

function gpSmsUserEnabled(array $user): bool
{
    return !empty($user['sms_notifications_enabled']);
}

function gpSmsUserPhone(array $user): string
{
    $phone = trim((string) ($user['sms_phone'] ?? ''));
    if ($phone === '') {
        $phone = trim((string) ($user['phone'] ?? ''));
    }
    return gpSmsNormalizePhone($phone);
}

function gpSmsTrimBody(string $body): string
{
    $body = trim(preg_replace('/\s+/', ' ', $body) ?: $body);
    if (strlen($body) > 480) {
        $body = substr($body, 0, 477) . '...';
    }
    return $body;
}

function gpSmsSendRaw(string $toPhone, string $body): bool
{
    if (!gpSmsGlobalEnabled()) {
        return false;
    }

    $toPhone = gpSmsNormalizePhone($toPhone);
    $body = gpSmsTrimBody($body);
    if ($toPhone === '' || $body === '') {
        return false;
    }

    if (gpSmsProvider() !== 'twilio') {
        error_log('GuidePaw SMS provider is not supported: ' . gpSmsProvider());
        return false;
    }

    $sid = trim((string) gpEnv('TWILIO_ACCOUNT_SID', ''));
    $token = trim((string) gpEnv('TWILIO_AUTH_TOKEN', ''));
    $from = gpSmsNormalizePhone(gpEnv('TWILIO_FROM_NUMBER', ''));
    if ($sid === '' || $token === '' || $from === '') {
        error_log('GuidePaw SMS enabled but Twilio settings are missing.');
        return false;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_POSTFIELDS => http_build_query([
            'From' => $from,
            'To' => $toPhone,
            'Body' => $body,
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log('GuidePaw SMS notification failed: ' . ($curlError ?: ('HTTP ' . $httpCode . ' ' . substr((string) $response, 0, 300))));
        return false;
    }

    return true;
}

function gpSmsNotifyUser(array $user, string $body, string $featureFlag = 'SMS_NOTIFY_ENABLED'): bool
{
    if (!gpSmsFlag($featureFlag, true)) {
        return false;
    }
    if (!gpSmsUserEnabled($user)) {
        return false;
    }
    return gpSmsSendRaw(gpSmsUserPhone($user), $body);
}
