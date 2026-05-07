<?php
declare(strict_types=1);

if (!function_exists('gpEnv')) {
    function gpEnv(string $key, ?string $fallback = null): ?string
    {
        if (function_exists('appEnv')) {
            return appEnv($key, $fallback);
        }
        $value = getenv($key);
        return ($value === false || $value === '') ? $fallback : $value;
    }
}

function gpMailHeaders(string $fromEmail, string $fromName, string $toEmail, string $subject): string
{
    $fromName = str_replace(["\r", "\n"], '', $fromName);
    $fromEmail = str_replace(["\r", "\n"], '', $fromEmail);
    $subject = str_replace(["\r", "\n"], '', $subject);
    return "From: {$fromName} <{$fromEmail}>\r\n" .
        "Reply-To: {$fromEmail}\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";
}

function gpSendViaZeptoMail(string $toEmail, string $subject, string $body): bool
{
    $token = gpEnv('ZEPTO_SEND_MAIL_TOKEN', '');
    $url = gpEnv('ZEPTO_API_URL', 'https://api.zeptomail.com/v1.1/email');

    if ($token === '') {
        throw new RuntimeException('ZEPTO_SEND_MAIL_TOKEN is missing.');
    }

    $fromEmail = gpEnv('ZEPTO_FROM_ADDRESS', gpEnv('SMTP_FROM', gpEnv('MAIL_FROM_ADDRESS', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app'))));
    $fromName = gpEnv('ZEPTO_FROM_NAME', gpEnv('SMTP_FROM_NAME', gpEnv('MAIL_FROM_NAME', 'GuidePaw')));

    $payload = [
        'from' => ['address' => $fromEmail, 'name' => $fromName],
        'to' => [['email_address' => ['address' => $toEmail]]],
        'subject' => $subject,
        'textbody' => $body,
    ];

    $bounceAddress = gpEnv('ZEPTO_BOUNCE_ADDRESS', '');
    if ($bounceAddress !== '') {
        $payload['bounce_address'] = $bounceAddress;
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode ZeptoMail payload.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize curl.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Zoho-enczapikey ' . $token,
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('ZeptoMail curl error: ' . $curlError);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException("ZeptoMail API failed with HTTP {$httpCode}: " . mb_substr((string)$response, 0, 1000));
    }

    return true;
}

function gpSmtpRead($socket): string
{
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return $data;
}

function gpSmtpExpect($socket, array $codes): string
{
    $response = gpSmtpRead($socket);
    $code = substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }
    return $response;
}

function gpSmtpSendCommand($socket, string $command, array $expect): string
{
    fwrite($socket, $command . "\r\n");
    return gpSmtpExpect($socket, $expect);
}

function gpSendViaSmtp(string $toEmail, string $subject, string $body): bool
{
    $fromEmail = gpEnv('SMTP_FROM', gpEnv('MAIL_FROM_ADDRESS', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app')));
    $fromName = gpEnv('SMTP_FROM_NAME', gpEnv('MAIL_FROM_NAME', 'GuidePaw'));
    $host = gpEnv('SMTP_HOST', '');
    $port = (int) gpEnv('SMTP_PORT', '587');
    $username = gpEnv('SMTP_USERNAME', '');
    $password = gpEnv('SMTP_PASSWORD', '');
    $secure = strtolower((string) gpEnv('SMTP_SECURE', gpEnv('SMTP_ENCRYPTION', 'tls')));

    if ($host === '') {
        return mail($toEmail, $subject, $body, gpMailHeaders($fromEmail, $fromName, $toEmail, $subject));
    }

    $transportHost = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException("Could not connect to SMTP server: {$errstr}");
    }

    stream_set_timeout($socket, 20);
    gpSmtpExpect($socket, ['220']);
    gpSmtpSendCommand($socket, 'EHLO guidepaw.app', ['250']);

    if ($secure === 'tls') {
        gpSmtpSendCommand($socket, 'STARTTLS', ['220']);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not start SMTP TLS encryption.');
        }
        gpSmtpSendCommand($socket, 'EHLO guidepaw.app', ['250']);
    }

    if ($username !== '' && $password !== '') {
        gpSmtpSendCommand($socket, 'AUTH LOGIN', ['334']);
        gpSmtpSendCommand($socket, base64_encode($username), ['334']);
        gpSmtpSendCommand($socket, base64_encode($password), ['235']);
    }

    gpSmtpSendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', ['250']);
    gpSmtpSendCommand($socket, 'RCPT TO:<' . $toEmail . '>', ['250', '251']);
    gpSmtpSendCommand($socket, 'DATA', ['354']);
    $headers = gpMailHeaders($fromEmail, $fromName, $toEmail, $subject);
    $message = "Subject: {$subject}\r\n" . $headers . "\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
    fwrite($socket, $message . "\r\n");
    gpSmtpExpect($socket, ['250']);
    gpSmtpSendCommand($socket, 'QUIT', ['221']);
    fclose($socket);
    return true;
}

function gpSendMail(string $toEmail, string $subject, string $body): bool
{
    if (gpEnv('ZEPTO_SEND_MAIL_TOKEN', '') !== '') {
        return gpSendViaZeptoMail($toEmail, $subject, $body);
    }
    return gpSendViaSmtp($toEmail, $subject, $body);
}
