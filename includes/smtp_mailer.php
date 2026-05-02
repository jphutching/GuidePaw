<?php
declare(strict_types=1);

function gpEnv(string $key, ?string $fallback = null): ?string
{
    if (function_exists('appEnv')) {
        return appEnv($key, $fallback);
    }
    $value = getenv($key);
    return ($value === false || $value === '') ? $fallback : $value;
}

function gpMailHeaders(string $fromEmail, string $fromName, string $toEmail, string $subject): string
{
    $fromName = str_replace(["\r", "\n"], '', $fromName);
    $fromEmail = str_replace(["\r", "\n"], '', $fromEmail);
    $toEmail = str_replace(["\r", "\n"], '', $toEmail);
    $subject = str_replace(["\r", "\n"], '', $subject);

    return "From: {$fromName} <{$fromEmail}>\r\n" .
        "Reply-To: {$fromEmail}\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";
}

function gpSmtpRead($socket): string
{
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
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

/**
 * Sends email through SMTP when SMTP_HOST is configured.
 * Falls back to PHP mail() only when SMTP is not configured.
 */
function gpSendMail(string $toEmail, string $subject, string $body): bool
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
    $socket = @stream_socket_client(
        $transportHost . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

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
