<?php
require_once __DIR__ . '/../includes/app_config.php';
require_once __DIR__ . '/../includes/changelog.php';

$limit   = max(1, min(100, (int) ($_GET['limit'] ?? 100)));
$entries = array_slice(gpChangelogEntries(), 0, $limit);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode(['success' => true, 'entries' => $entries]);
