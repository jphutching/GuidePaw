<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/public_dog_profile_token.php';
require_once __DIR__ . '/../includes/found_dog_reports.php';
require_once __DIR__ . '/../includes/validation.php';

gpEnsureFoundDogReportsTable($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$dogId = (int) ($input['dog_id'] ?? 0);
$token = trim((string) ($input['token'] ?? ''));
if ($dogId <= 0 || $token === '' || !publicDogProfileTokenValid($dogId, $token)) {
    apiJson(['success' => false, 'message' => 'Invalid public profile token.'], 404);
}

$dog = gpFoundDogFetchPublicDog($pdo, $dogId);
if (!$dog) {
    apiJson(['success' => false, 'message' => 'Dog not found.'], 404);
}

$honeypot = trim((string) ($input['website'] ?? ''));
$location = cleanText($input['finder_location'] ?? '', 240);
$finderName = cleanText($input['finder_name'] ?? '', 120);
$finderPhone = cleanText($input['finder_phone'] ?? '', 80);
$message = cleanTextarea($input['finder_message'] ?? '', 1000);
$lat = trim((string) ($input['finder_latitude'] ?? ''));
$lng = trim((string) ($input['finder_longitude'] ?? ''));
$accuracy = trim((string) ($input['finder_accuracy_m'] ?? ''));

if ($honeypot !== '') {
    apiJson(['success' => true, 'submitted' => true, 'message' => 'Reported.']);
}

if ($location === '' && ($lat === '' || $lng === '')) {
    apiJson(['success' => false, 'message' => 'Please enter a location or share GPS once.'], 422);
}
if ($finderPhone === '') {
    apiJson(['success' => false, 'message' => 'Please enter a phone number.'], 422);
}
if ($lat !== '' && !is_numeric($lat)) {
    apiJson(['success' => false, 'message' => 'Latitude was invalid.'], 422);
}
if ($lng !== '' && !is_numeric($lng)) {
    apiJson(['success' => false, 'message' => 'Longitude was invalid.'], 422);
}
if ($accuracy !== '' && !ctype_digit($accuracy)) {
    $accuracy = '';
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = $ip !== '' ? hash('sha256', $ip . '|guidepaw-found-dog') : null;
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$stmt = $pdo->prepare('INSERT INTO found_dog_reports (dog_id, finder_location, finder_latitude, finder_longitude, finder_accuracy_m, finder_name, finder_phone, finder_message, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id');
$stmt->execute([
    $dogId,
    $location !== '' ? $location : null,
    $lat !== '' ? $lat : null,
    $lng !== '' ? $lng : null,
    $accuracy !== '' ? (int) $accuracy : null,
    $finderName !== '' ? $finderName : null,
    $finderPhone,
    $message !== '' ? $message : null,
    $ipHash,
    $userAgent !== '' ? $userAgent : null,
]);
$reportId = (int) $stmt->fetchColumn();
$notified = gpNotifyFoundDogReport($pdo, $reportId);

apiJson([
    'success' => true,
    'submitted' => true,
    'report_id' => $reportId,
    'notified' => $notified,
    'message' => 'Location report sent.',
]);
