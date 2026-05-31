<?php
/**
 * Admin endpoint — manually set a breed photo URL.
 * POST /api/breed_photo_set.php
 * Body: {"breed": "Belgian Laekenois", "url": "https://..."}
 * Saves the URL, auto-locks the row so it survives wipe/re-fetch.
 * Returns: {"success": true, "breed": "...", "url": "..."}
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

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$breed = trim((string) ($body['breed'] ?? ''));
$url   = trim((string) ($body['url']   ?? ''));

if ($breed === '') {
    echo json_encode(['success' => false, 'message' => 'breed required.']);
    exit;
}
if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL.']);
    exit;
}

$pdo->prepare(
    "INSERT INTO breed_images (breed_name, color_variant, image_url, source, photo_locked)
     VALUES (?, '', ?, 'manual', true)
     ON CONFLICT (breed_name, color_variant)
     DO UPDATE SET image_url = EXCLUDED.image_url, source = 'manual',
                   photo_locked = true, fetched_at = CURRENT_TIMESTAMP,
                   verified_at = NULL, verification_score = NULL, verification_notes = NULL"
)->execute([$breed, $url]);

echo json_encode(['success' => true, 'breed' => $breed, 'url' => $url]);
