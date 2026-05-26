<?php
require_once __DIR__ . '/../includes/api_auth.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input           = json_decode(file_get_contents('php://input'), true) ?: [];
$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword     = (string) ($input['new_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    apiJson(['success' => false, 'message' => 'Current and new passwords are required.'], 422);
}
if (strlen($newPassword) < 8) {
    apiJson(['success' => false, 'message' => 'New password must be at least 8 characters.'], 422);
}

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
    apiJson(['success' => false, 'message' => 'Current password is incorrect.'], 403);
}

$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
    ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

apiJson(['success' => true, 'message' => 'Password changed successfully.']);
