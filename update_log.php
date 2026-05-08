<?php
require_once 'includes/db_connect.php';
checkLogin(); // Security Guard

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['id'];
    $user_id = (int) $_SESSION['user_id'];
    $location_name = trim((string) ($_POST['location_name'] ?? ''));
    $handler_notes = trim((string) ($_POST['handler_notes'] ?? ''));
    $skills_json = json_encode($_POST['skills'] ?? []);

    try {
        $stmt = $pdo->prepare("SELECT dog_id FROM daily_logs WHERE id = ?");
        $stmt->execute([$id]);
        $dogId = (int) $stmt->fetchColumn();
        if (!$dogId || !userCanEditDog($pdo, $user_id, $dogId)) {
            http_response_code(403);
            die('You do not have permission to edit this training log.');
        }

        $sql = "UPDATE daily_logs 
                SET location_name = ?, 
                    skills_practiced = ?, 
                    handler_notes = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$location_name, $skills_json, $handler_notes, $id]);

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
