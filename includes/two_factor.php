<?php
require_once __DIR__ . '/GoogleAuthenticator.php';

function getGoogleAuthenticator(): PHPGangsta_GoogleAuthenticator {
    static $ga = null;
    if ($ga === null) {
        $ga = new PHPGangsta_GoogleAuthenticator();
    }
    return $ga;
}

function generateTotpSecret(): string {
    return getGoogleAuthenticator()->createSecret(16);
}

function normalizeTotpCode(?string $code): string {
    return preg_replace('/\D+/', '', (string) $code) ?? '';
}

function buildTotpOtpAuthUrl(string $label, string $secret, ?string $issuer = null): string {
    $issuer = trim((string) ($issuer ?: appShortName()));
    $label = trim($label);
    $params = [
        'secret' => $secret,
    ];
    if ($issuer !== '') {
        $params['issuer'] = $issuer;
    }
    return 'otpauth://totp/' . rawurlencode($label) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function verifyTotpCode(string $secret, ?string $code, int $discrepancy = 1): bool {
    $clean = normalizeTotpCode($code);
    if ($clean === '' || strlen($clean) !== 6) {
        return false;
    }
    return getGoogleAuthenticator()->verifyCode($secret, $clean, $discrepancy);
}

function canUseRecoveryKey(array $user, ?string $recoveryKey): bool {
    $provided = strtoupper(trim((string) $recoveryKey));
    $stored = strtoupper(trim((string) ($user['recovery_key'] ?? '')));
    return $provided !== '' && $stored !== '' && hash_equals($stored, $provided);
}
