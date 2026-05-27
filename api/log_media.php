<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/validation.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$logId = (int) ($_POST['log_id'] ?? 0);
if ($logId <= 0) {
    apiJson(['success' => false, 'message' => 'log_id is required.'], 422);
}

$stmt = $pdo->prepare("SELECT id FROM daily_logs WHERE id = ? AND user_id = ?");
$stmt->execute([$logId, $userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'Log not found or access denied.'], 404);
}

if (empty($_FILES['training_media']) || $_FILES['training_media']['error'] === UPLOAD_ERR_NO_FILE) {
    apiJson(['success' => false, 'message' => 'No file uploaded.'], 422);
}

try {
    [$relativePath, $mediaType, $mimeType] = handleTrainingMediaUpload($_FILES['training_media'], dirname(__DIR__));
} catch (RuntimeException $e) {
    apiJson(['success' => false, 'message' => $e->getMessage()], 422);
}

$pdo->prepare(
    "UPDATE daily_logs SET media_url = ?, media_type = ?, media_mime = ? WHERE id = ?"
)->execute([$relativePath, $mediaType, $mimeType, $logId]);

apiJson(['success' => true, 'message' => 'Media uploaded.', 'media_url' => $relativePath]);
