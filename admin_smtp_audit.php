<?php
require 'includes/db_connect.php';

checkLogin();

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('Admin access required.');
}

header('Content-Type: text/plain; charset=utf-8');

function auditEnv(string $key, bool $secret = false): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return 'MISSING';
    }
    if ($secret) {
        return 'SET / HIDDEN';
    }
    return $value;
}

function line(string $label, string $value): void {
    echo str_pad($label . ':', 28) . $value . PHP_EOL;
}

echo "GuidePaw SMTP Audit\n";
echo "===================\n\n";

$host = getenv('SMTP_HOST') ?: '';
$port = (int) (getenv('SMTP_PORT') ?: 587);
$secure = strtolower((string) (getenv('SMTP_SECURE') ?: getenv('SMTP_ENCRYPTION') ?: 'tls'));

line('APP_ENV', auditEnv('APP_ENV'));
line('SMTP_HOST', auditEnv('SMTP_HOST'));
line('SMTP_PORT', auditEnv('SMTP_PORT'));
line('SMTP_SECURE', auditEnv('SMTP_SECURE'));
line('SMTP_ENCRYPTION', auditEnv('SMTP_ENCRYPTION'));
line('SMTP_USERNAME', auditEnv('SMTP_USERNAME'));
line('SMTP_PASSWORD', auditEnv('SMTP_PASSWORD', true));
line('SMTP_FROM', auditEnv('SMTP_FROM'));
line('SMTP_FROM_NAME', auditEnv('SMTP_FROM_NAME'));
line('MAIL_FROM_ADDRESS', auditEnv('MAIL_FROM_ADDRESS'));
line('MAIL_FROM_NAME', auditEnv('MAIL_FROM_NAME'));

echo "\nDNS Check\n";
echo "---------\n";

if ($host === '') {
    echo "SMTP_HOST is missing. The Zoho SMTP env group is probably not linked to this web service.\n";
    exit;
}

$records = gethostbynamel($host);
if ($records === false || !$records) {
    echo "DNS lookup failed for {$host}\n";
} else {
    echo "DNS lookup OK for {$host}\n";
    foreach ($records as $ip) {
        echo "- {$ip}\n";
    }
}

echo "\nTCP Connection Check\n";
echo "--------------------\n";

$target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
echo "Target: {$target}\n";

$errno = 0;
$errstr = '';
$socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);

if (!$socket) {
    echo "TCP connection FAILED\n";
    echo "Error number: {$errno}\n";
    echo "Error text: {$errstr}\n";
    exit;
}

echo "TCP connection OK\n";
stream_set_timeout($socket, 20);

$banner = fgets($socket, 515);
echo "SMTP banner: " . trim((string) $banner) . "\n";

fwrite($socket, "EHLO guidepaw.app\r\n");
$response = '';
while (($line = fgets($socket, 515)) !== false) {
    $response .= $line;
    if (strlen($line) >= 4 && $line[3] === ' ') {
        break;
    }
}

echo "\nEHLO Response\n";
echo "-------------\n";
echo trim($response) . "\n";

if ($secure === 'tls') {
    echo "\nSTARTTLS Check\n";
    echo "--------------\n";

    fwrite($socket, "STARTTLS\r\n");
    $tlsResponse = fgets($socket, 515);
    echo "STARTTLS response: " . trim((string) $tlsResponse) . "\n";

    if (str_starts_with((string) $tlsResponse, '220')) {
        $tlsOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        echo $tlsOk ? "TLS negotiation OK\n" : "TLS negotiation FAILED\n";
    } else {
        echo "Server did not accept STARTTLS.\n";
    }
}

fwrite($socket, "QUIT\r\n");
fclose($socket);

echo "\nResult\n";
echo "------\n";
echo "Audit completed. If SMTP_PASSWORD says MISSING, the env group is not linked or password is blank.\n";
echo "If DNS works but TCP fails/times out, the host/port is wrong or outbound SMTP is blocked.\n";
echo "If TCP works but email still fails, the next issue is likely Zoho authentication/app password.\n";
