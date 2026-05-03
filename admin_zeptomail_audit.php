<?php
require 'includes/db_connect.php';

checkLogin();
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('Admin access required.');
}

header('Content-Type: text/plain; charset=utf-8');

function zaEnv(string $key, bool $secret = false): string {
    $value = getenv($key);
    if ($value === false || $value === '') return 'MISSING';
    return $secret ? 'SET / HIDDEN' : $value;
}

function zaLine(string $label, string $value): void {
    echo str_pad($label . ':', 30) . $value . PHP_EOL;
}

echo "GuidePaw ZeptoMail API Audit\n";
echo "============================\n\n";

zaLine('APP_ENV', zaEnv('APP_ENV'));
zaLine('ZEPTO_API_URL', zaEnv('ZEPTO_API_URL'));
zaLine('ZEPTO_SEND_MAIL_TOKEN', zaEnv('ZEPTO_SEND_MAIL_TOKEN', true));
zaLine('ZEPTO_FROM_ADDRESS', zaEnv('ZEPTO_FROM_ADDRESS'));
zaLine('ZEPTO_FROM_NAME', zaEnv('ZEPTO_FROM_NAME'));
zaLine('ZEPTO_BOUNCE_ADDRESS', zaEnv('ZEPTO_BOUNCE_ADDRESS'));
zaLine('MAIL_FROM_ADDRESS', zaEnv('MAIL_FROM_ADDRESS'));
zaLine('MAIL_FROM_NAME', zaEnv('MAIL_FROM_NAME'));

$url = getenv('ZEPTO_API_URL') ?: 'https://api.zeptomail.com/v1.1/email';
$host = parse_url($url, PHP_URL_HOST);

echo "\nDNS Check\n---------\n";
if (!$host) {
    echo "Could not parse ZeptoMail API host.\n";
    exit;
}
$records = gethostbynamel($host);
if (!$records) {
    echo "DNS lookup failed for {$host}\n";
} else {
    echo "DNS lookup OK for {$host}\n";
    foreach ($records as $ip) echo "- {$ip}\n";
}

echo "\nHTTPS Connection Check\n----------------------\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo "HTTPS connection FAILED\n";
    echo "Curl error: {$error}\n";
} else {
    echo "HTTPS connection OK\n";
    echo "HTTP code: {$httpCode}\n";
    echo "Note: 401/405/404 can be normal for a HEAD/no-auth audit request. We only need HTTPS to connect.\n";
}

echo "\nResult\n------\n";
echo "If ZEPTO_SEND_MAIL_TOKEN is SET / HIDDEN and HTTPS works, try approval email again.\n";
