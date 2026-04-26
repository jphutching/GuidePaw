<?php
require_once __DIR__ . '/../includes/api_auth.php';
$user = requireApiUser($pdo);
apiJson(['success' => true, 'user' => $user, 'db_driver' => dbDriverName(), 'schema_version' => currentSchemaVersion($pdo)]);
