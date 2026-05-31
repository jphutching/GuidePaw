<?php
/**
 * Admin endpoint — lock or unlock a breed photo so it survives wipe/re-fetch.
 * POST /api/breed_photo_lock.php
 * Body: {"breed": "Labrador Retriever", "locked": true}
 * Returns: {"success": true, "locked": true}
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

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$breed  = trim((string) ($body['breed'] ?? ''));
$locked = !empty($body['locked']);

if ($breed === '') {
    echo json_encode(['success' => false, 'message' => 'breed required.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE breed_images SET photo_locked = ? WHERE breed_name = ? AND color_variant = ''");
$stmt->execute([$locked, $breed]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Breed not found in cache.']);
    exit;
}

echo json_encode(['success' => true, 'breed' => $breed, 'locked' => $locked]);
