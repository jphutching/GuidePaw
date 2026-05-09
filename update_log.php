<?php
require_once 'includes/db_connect.php';
require_once 'includes/validation.php';
checkLogin(); // Security Guard

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['id'];
    $user_id = (int) $_SESSION['user_id'];
    $postedDogId = (int) ($_POST['dog_id'] ?? 0);
    $location_name = trim((string) ($_POST['location_name'] ?? ''));
    $log_date = cleanDateTimeValue($_POST['log_date'] ?? '');
    $location_city_state = cleanText($_POST['location_city_state'] ?? '', 100);
    $allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
    $location_type = validateLocationType($_POST['location_type'] ?? 'Other', $allowedTypes);
    $focus_level = validateFocusLevel($_POST['focus_level'] ?? 3);
    $handler_notes = trim((string) ($_POST['handler_notes'] ?? ''));
    $skills_json = json_encode($_POST['skills'] ?? []);
    $latitude = validateLatitude($_POST['latitude'] ?? null);
    $longitude = validateLongitude($_POST['longitude'] ?? null);

    try {
        $stmt = $pdo->prepare("SELECT dog_id FROM daily_logs WHERE id = ?");
        $stmt->execute([$id]);
        $dogId = (int) $stmt->fetchColumn();
        if (!$dogId || !userCanEditDog($pdo, $user_id, $dogId) || ($postedDogId > 0 && $postedDogId !== $dogId)) {
            http_response_code(403);
            die('You do not have permission to edit this training log.');
        }

        $sql = "UPDATE daily_logs 
                SET location_name = ?, 
                    log_date = ?,
                    location_city_state = ?,
                    location_type = ?,
                    focus_level = ?,
                    skills_practiced = ?, 
                    handler_notes = ?,
                    latitude = ?,
                    longitude = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $location_name,
            $log_date ?? date('Y-m-d H:i:s'),
            $location_city_state ?: null,
            $location_type,
            $focus_level,
            $skills_json,
            $handler_notes,
            $latitude,
            $longitude,
            $id
        ]);

        if ($stmt->rowCount() > 0) {
            header("Location: view_logs.php?status=updated");
        } else {
            // No rows changed or permission denied
            header("Location: view_logs.php?status=no_change");
        }
        exit;

    } catch (PDOException $e) {
        die("System Error: " . $e->getMessage());
    }
} else {
    header("Location: view_logs.php");
    exit;
}
