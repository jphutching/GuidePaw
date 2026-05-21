<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/ada_state_laws.php';
require_once __DIR__ . '/../includes/sms_notifications.php';

function gpApiOptionalUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
}

function gpApiHandlerProfileResponse(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $homeAddress = trim((string) ($user['home_address'] ?? ''));
    if ($homeAddress === '') {
        $homeAddress = gpComposePostalAddress($user, 'home_');
    }

    return [
        'success' => true,
        'user' => [
            'id' => (int) ($user['id'] ?? $userId),
            'username' => (string) ($user['username'] ?? ''),
            'display_name' => (string) ($user['display_name'] ?? ''),
            'home_street' => (string) ($user['home_street'] ?? ''),
            'home_apt' => (string) ($user['home_apt'] ?? ''),
            'home_city' => (string) ($user['home_city'] ?? ''),
            'home_state' => (string) ($user['home_state'] ?? ''),
            'home_zip' => (string) ($user['home_zip'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'public_email' => (string) ($user['public_email'] ?? ''),
            'facebook_url' => (string) ($user['facebook_url'] ?? ''),
            'profile_photo_url' => (string) ($user['profile_photo_url'] ?? ''),
            'backup_contact_name' => (string) ($user['backup_contact_name'] ?? ''),
            'backup_contact_phone' => (string) ($user['backup_contact_phone'] ?? ''),
            'public_notes' => (string) ($user['public_notes'] ?? ''),
            'sms_phone' => (string) ($user['sms_phone'] ?? ''),
            'sms_notifications_enabled' => !empty($user['sms_notifications_enabled']),
            'home_address' => $homeAddress,
        ],
    ];
}

$user = requireApiUser($pdo);
gpEnsureRequiredHandlerProfileColumns($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    apiJson(gpApiHandlerProfileResponse($pdo, (int) $user['id']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$displayName = cleanText((string) ($input['display_name'] ?? ''), 120);
$homeStreet = cleanText((string) ($input['home_street'] ?? ''), 120);
$homeApt = cleanText((string) ($input['home_apt'] ?? ''), 120);
$homeCity = cleanText((string) ($input['home_city'] ?? ''), 120);
$homeState = strtoupper(trim((string) ($input['home_state'] ?? '')));
$homeZip = cleanText((string) ($input['home_zip'] ?? ''), 20);
$phone = cleanText((string) ($input['phone'] ?? ''), 80);
$publicEmail = cleanText((string) ($input['public_email'] ?? ''), 160);
$facebookUrl = gpApiOptionalUrl((string) ($input['facebook_url'] ?? ''));
$profilePhotoUrl = gpApiOptionalUrl((string) ($input['profile_photo_url'] ?? ''));
$backupName = cleanText((string) ($input['backup_contact_name'] ?? ''), 120);
$backupPhone = cleanText((string) ($input['backup_contact_phone'] ?? ''), 80);
$publicNotes = cleanTextarea((string) ($input['public_notes'] ?? ''), 1200);
$smsPhone = cleanText((string) ($input['sms_phone'] ?? ''), 80);
$smsEnabled = !empty($input['sms_notifications_enabled']) ? 1 : 0;
if ($smsPhone === '') {
    $smsPhone = $phone;
}
$smsPhoneNormalized = gpSmsNormalizePhone($smsPhone);

$errors = [];
if ($displayName === '') {
    $errors[] = 'Display name is required.';
}
if ($homeStreet === '') {
    $errors[] = 'Home street is required.';
}
if ($homeCity === '') {
    $errors[] = 'Home city is required.';
}
if ($homeState === '') {
    $errors[] = 'Home state is required.';
}
if ($homeZip === '') {
    $errors[] = 'Home ZIP is required.';
}
if ($phone === '') {
    $errors[] = 'Public phone is required.';
}
if ($publicEmail === '') {
    $errors[] = 'Public email is required.';
}
if ($publicEmail !== '' && !filter_var($publicEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Public email must be valid.';
}
if ($homeState !== '' && !array_key_exists($homeState, adaStateNames())) {
    $errors[] = 'Home state must be a valid US state code.';
}
if ($homeZip !== '' && !preg_match('/^\d{5}(?:-\d{4})?$/', $homeZip)) {
    $errors[] = 'Home ZIP must be valid.';
}
if ($smsEnabled && $smsPhoneNormalized === '') {
    $errors[] = 'SMS notifications require a valid mobile phone number.';
}

if ($errors) {
    apiJson(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 422);
}

$homeAddress = gpComposePostalAddress([
    'home_street' => $homeStreet,
    'home_apt' => $homeApt,
    'home_city' => $homeCity,
    'home_state' => $homeState,
    'home_zip' => $homeZip,
]);

$stmt = $pdo->prepare('UPDATE users SET display_name=?, home_street=?, home_apt=?, home_city=?, home_address=?, phone=?, public_email=?, facebook_url=?, profile_photo_url=?, home_state=?, home_zip=?, backup_contact_name=?, backup_contact_phone=?, public_notes=?, sms_phone=?, sms_notifications_enabled=? WHERE id=?');
$stmt->execute([
    $displayName,
    $homeStreet,
    $homeApt !== '' ? $homeApt : null,
    $homeCity,
    $homeAddress,
    $phone,
    $publicEmail,
    $facebookUrl !== '' ? $facebookUrl : null,
    $profilePhotoUrl !== '' ? $profilePhotoUrl : null,
    $homeState,
    $homeZip,
    $backupName !== '' ? $backupName : null,
    $backupPhone !== '' ? $backupPhone : null,
    $publicNotes !== '' ? $publicNotes : null,
    $smsPhoneNormalized !== '' ? $smsPhoneNormalized : null,
    $smsEnabled,
    (int) $user['id'],
]);

apiJson(array_merge(
    ['success' => true, 'message' => 'Handler profile saved.'],
    gpApiHandlerProfileResponse($pdo, (int) $user['id'])
));
