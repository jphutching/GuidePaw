<?php
require_once 'includes/db_connect.php';
checkLogin(); // Security Guard

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $user_id = $_SESSION['user_id'];
    $location_name = htmlspecialchars($_POST['location_name']);
    $handler_notes = htmlspecialchars($_POST['handler_notes']);
    $skills_json = json_encode($_POST['skills'] ?? []);

    try {
        // Strict Ownership Check: WHERE id = ? AND user_id = ?
        $sql = "UPDATE daily_logs 
                SET location_name = ?, 
                    skills_practiced = ?, 
                    handler_notes = ? 
                WHERE id = ? AND user_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$location_name, $skills_json, $handler_notes, $id, $user_id]);

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
