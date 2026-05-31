<?php
/**
 * Admin endpoint — wipes all cached breed photos from breed_images.
 * POST /api/breed_photos_wipe.php
 * Returns: {"success": true, "wiped": N}
 */
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

if (!gpCurrentUserIsAdmin($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$stmt = $pdo->query('DELETE FROM breed_images WHERE photo_locked = false');
$locked = (int) $pdo->query('SELECT COUNT(*) FROM breed_images WHERE photo_locked = true')->fetchColumn();
echo json_encode(['success' => true, 'wiped' => $stmt->rowCount(), 'locked_preserved' => $locked]);
