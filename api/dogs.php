<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user = requireApiUser($pdo);
$dogs = getAccessibleDogs($pdo, $user['id']);
apiJson(['success' => true, 'dogs' => $dogs]);
