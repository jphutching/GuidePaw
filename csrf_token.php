<?php
require 'includes/db_connect.php';
require 'includes/validation.php';
checkLogin();
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'csrf_token' => generateCsrfToken(),
]);
