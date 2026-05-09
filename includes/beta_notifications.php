<?php
declare(strict_types=1);

require_once __DIR__ . '/smtp_mailer.php';

function betaNotificationFlag(string $key, bool $fallback = false): bool
{
    $value = strtolower(trim((string) gpEnv($key, $fallback ? 'true' : 'false')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function betaAdminNotificationEmailEnabled(): bool
{
    return betaNotificationFlag('BETA_NOTIFY_EMAIL_ENABLED', true);
}

function betaAdminNotificationTelegramEnabled(): bool
{
    return betaNotificationFlag('BETA_NOTIFY_TELEGRAM_ENABLED', false);
}

function betaAdminNotificationBody(array $request): string
{
    $fullName = (string) ($request['full_name'] ?? 'Not provided');
    $email = (string) ($request['email'] ?? 'Not provided');
    $phone = (string) ($request['phone'] ?? 'Not provided');
    $reason = (string) ($request['reason'] ?? 'Not provided');
    $status = (string) ($request['status'] ?? 'pending');
    $createdAt = (string) ($request['created_at'] ?? date('Y-m-d H:i:s'));
    $adminUrl = rtrim((string) gpEnv('APP_URL', 'https://beta.guidepaw.app'), '/') . '/admin_beta_requests.php';

    return "A new GuidePaw beta access request was submitted.\n\n" .
        "Name: {$fullName}\n" .
        "Email: {$email}\n" .
        "Phone: {$phone}\n" .
        "Status: {$status}\n" .
        "Submitted: {$createdAt}\n\n" .
        "Reason / notes:\n{$reason}\n\n" .
        "Review the request here:\n{$adminUrl}\n\n" .
        "GuidePaw admin notification\n";
}

function betaAdminTelegramMessage(array $request): string
{
    $fullName = (string) ($request['full_name'] ?? 'Not provided');
    $email = (string) ($request['email'] ?? 'Not provided');
    $phone = (string) ($request['phone'] ?? 'Not provided');
    $reason = trim((string) ($request['reason'] ?? ''));
    $adminUrl = rtrim((string) gpEnv('APP_URL', 'https://beta.guidepaw.app'), '/') . '/admin_beta_requests.php';

    $message = "🐾 New GuidePaw beta request\n\n" .
        "Name: {$fullName}\n" .
        "Email: {$email}\n" .
        "Phone: {$phone}\n";

    if ($reason !== '') {
        $message .= "\nReason:\n{$reason}\n";
    }

    return $message . "\nReview: {$adminUrl}";
}

function betaFetchBetaRequest(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM beta_access_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    return $request ?: null;
}

function betaNotifyAdminEmail(array $request): bool
{
    if (!betaAdminNotificationEmailEnabled()) {
        return false;
    }

    $adminEmail = trim((string) gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app')));
    if ($adminEmail === '') {
        return false;
    }

    return gpSendMail($adminEmail, 'New GuidePaw beta access request', betaAdminNotificationBody($request));
}

function betaNotifyAdminTelegram(array $request): bool
{
    if (!betaAdminNotificationTelegramEnabled()) {
        return false;
    }

    $token = trim((string) gpEnv('TELEGRAM_BOT_TOKEN', ''));
    $chatId = trim((string) gpEnv('TELEGRAM_CHAT_ID', ''));

    if ($token === '' || $chatId === '') {
        throw new RuntimeException('Telegram notification is enabled, but TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is missing.');
    }

    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => betaAdminTelegramMessage($request),
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new RuntimeException('Could not encode Telegram payload.');
    }

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    if ($ch === false) {
        throw new RuntimeException('Could not initialize Telegram curl request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Telegram curl error: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException("Telegram API failed with HTTP {$httpCode}: " . mb_substr((string) $response, 0, 1000));
    }

    return true;
}

function betaNotifyAdminAlert(string $subject, string $body, string $telegramText = ''): bool
{
    $emailSent = false;
    $telegramSent = false;

    if (betaAdminNotificationEmailEnabled()) {
        $adminEmail = trim((string) gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app')));
        if ($adminEmail !== '') {
            try {
                $emailSent = gpSendMail($adminEmail, $subject, $body) || $emailSent;
            } catch (Throwable $e) {
                error_log('GuidePaw admin alert email failed: ' . $e->getMessage());
            }
        }
    }

    if (betaAdminNotificationTelegramEnabled()) {
        $token = trim((string) gpEnv('TELEGRAM_BOT_TOKEN', ''));
        $chatId = trim((string) gpEnv('TELEGRAM_CHAT_ID', ''));
        if ($token !== '' && $chatId !== '') {
            $message = trim($telegramText !== '' ? $telegramText : $body);
            try {
                $payload = json_encode([
                    'chat_id' => $chatId,
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($payload === false) {
                    throw new RuntimeException('Could not encode Telegram payload.');
                }
                $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
                if ($ch === false) {
                    throw new RuntimeException('Could not initialize Telegram curl request.');
                }
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => $payload,
                ]);
                $response = curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($response === false) {
                    throw new RuntimeException('Telegram curl error: ' . $curlError);
                }
                if ($httpCode < 200 || $httpCode >= 300) {
                    throw new RuntimeException("Telegram API failed with HTTP {$httpCode}: " . mb_substr((string) $response, 0, 1000));
                }
                $telegramSent = true;
            } catch (Throwable $e) {
                error_log('GuidePaw admin alert Telegram failed: ' . $e->getMessage());
            }
        }
    }

    return $emailSent || $telegramSent;
}

function betaNotifyAdminOfBetaRequest(PDO $pdo, int $requestId): bool
{
    try {
        $request = betaFetchBetaRequest($pdo, $requestId);
        if (!$request) {
            throw new RuntimeException('Beta request not found for admin notification.');
        }

        $emailSent = false;
        $telegramSent = false;

        try {
            $emailSent = betaNotifyAdminEmail($request);
        } catch (Throwable $e) {
            error_log('GuidePaw beta admin email notification failed: ' . $e->getMessage());
        }

        try {
            $telegramSent = betaNotifyAdminTelegram($request);
        } catch (Throwable $e) {
            error_log('GuidePaw beta admin Telegram notification failed: ' . $e->getMessage());
        }

        return $emailSent || $telegramSent;
    } catch (Throwable $e) {
        error_log('GuidePaw beta admin notification failed: ' . $e->getMessage());
        return false;
    }
}
