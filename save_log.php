<?php
require 'includes/db_connect.php';
require 'includes/validation.php';
checkLogin();
header('Content-Type: application/json');

$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$allowedSkills = ['Sit/Stay', 'Heel', 'Leave It', 'Under Tuck', 'DPT Task', 'PA Focus'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $userId = (int) $_SESSION['user_id'];
    $dogId = (int) ($_POST['dog_id'] ?? 0);
    if (!$dogId || !userCanEditDog($pdo, $userId, $dogId)) {
        throw new RuntimeException('You do not have edit access for that dog.');
    }

    $locationName = cleanText($_POST['location_name'] ?? '', 100);
    $cityState = cleanText($_POST['location_city_state'] ?? '', 100);
    $locationType = validateLocationType($_POST['location_type'] ?? 'Other', $allowedTypes);
    $focusLevel = validateFocusLevel($_POST['focus_level'] ?? 3);
    $handlerNotes = cleanTextarea($_POST['handler_notes'] ?? '', 5000);
    $skills = cleanSkills($_POST['skills'] ?? [], $allowedSkills);
    $latitude = validateLatitude($_POST['latitude'] ?? null);
    $longitude = validateLongitude($_POST['longitude'] ?? null);

    if ($locationName === '') {
        throw new RuntimeException('Location name is required.');
    }

    $media = null; $mediaType = null; $mimeType = null;
    if (!empty($_FILES['training_media']['name'])) {
        [$media, $mediaType, $mimeType] = handleTrainingMediaUpload($_FILES['training_media'], __DIR__);
    }

    $stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, media_url, media_type, media_mime, latitude, longitude) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$userId, $dogId, $locationName, $cityState, $locationType, $focusLevel, json_encode($skills), $handlerNotes, $media, $mediaType, $mimeType, $latitude, $longitude]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
