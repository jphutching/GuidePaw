<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/beta_access.php';
require_once __DIR__ . '/../includes/ada_state_laws.php';
require_once __DIR__ . '/../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'POST required.'], 405);
}

if (!featureEnabled($pdo, 'public_registration_enabled')) {
    apiJson(['success' => false, 'message' => 'Registration is not open at this time. Visit guidepaw.app to join the waitlist.'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$fullName   = trim((string) ($body['full_name']   ?? ''));
$homeStreet = cleanText((string) ($body['home_street'] ?? ''), 120);
$homeApt    = cleanText((string) ($body['home_apt']    ?? ''), 120);
$homeCity   = cleanText((string) ($body['home_city']   ?? ''), 120);
$homeState  = strtoupper(trim((string) ($body['home_state'] ?? '')));
$homeZip    = cleanText((string) ($body['home_zip']    ?? ''), 20);
$phone      = trim((string) ($body['phone']       ?? ''));
$email      = strtolower(trim((string) ($body['email']    ?? '')));
$password   = (string) ($body['password'] ?? '');

if ($fullName === '' || $homeStreet === '' || $homeCity === '' || $homeState === '' || $homeZip === '' || $phone === '' || $email === '' || $password === '') {
    apiJson(['success' => false, 'message' => 'Name, street, city, state, ZIP, phone, email, and password are required.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiJson(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
}
if (!array_key_exists($homeState, adaStateNames())) {
    apiJson(['success' => false, 'message' => 'Please choose a valid US state.'], 422);
}
if (!preg_match('/^\d{5}(?:-\d{4})?$/', $homeZip)) {
    apiJson(['success' => false, 'message' => 'Please enter a valid ZIP code (e.g. 12345).'], 422);
}
if (strlen($password) < 10) {
    apiJson(['success' => false, 'message' => 'Password must be at least 10 characters.'], 422);
}

$check = $pdo->prepare("SELECT id FROM users WHERE lower(username) = lower(?) OR lower(COALESCE(email,'')) = lower(?) LIMIT 1");
$check->execute([$email, $email]);
if ($check->fetchColumn()) {
    apiJson(['success' => false, 'message' => 'An account already exists for this email.'], 409);
}

try {
    $pdo->beginTransaction();
    $userId = betaInsertUserFlexible($pdo, [
        'email'       => $email,
        'full_name'   => $fullName,
        'home_street' => $homeStreet,
        'home_apt'    => $homeApt,
        'home_city'   => $homeCity,
        'home_state'  => $homeState,
        'home_zip'    => $homeZip,
        'phone'       => $phone,
        'password'    => $password,
    ]);
    $issued = issueApiToken($pdo, $userId, 'GuidePaw Companion (registration)');
    $pdo->commit();
    apiJson(['success' => true, 'message' => 'Account created.', 'token' => $issued['token']]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = appEnv('APP_ENV', 'production') === 'production'
        ? 'Account creation failed. Please contact admin@guidepaw.app.'
        : $e->getMessage();
    apiJson(['success' => false, 'message' => $msg], 500);
}
