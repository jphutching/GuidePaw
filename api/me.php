<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user = requireApiUser($pdo);
apiJson([
    'success' => true,
    'user' => $user,
    'active_dog_id' => apiGetActiveDogId($pdo, (int) ($user['token_id'] ?? 0)),
]);
