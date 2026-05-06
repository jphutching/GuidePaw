<?php
require_once 'includes/db_connect.php';
checkLogin();

header('Content-Type: application/json');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$key = 'guidepaw_beta_qa_checklist_v1';

function gpQaEnsureStateTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS beta_qa_checklist_state (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        checklist_key TEXT NOT NULL DEFAULT 'guidepaw_beta_qa_checklist_v1',
        checked_items JSONB NOT NULL DEFAULT '{}'::jsonb,
        notes TEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, checklist_key)
    )");
}

function gpQaJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($userId <= 0) {
    gpQaJsonResponse(['ok' => false, 'error' => 'not_authenticated'], 401);
}

gpQaEnsureStateTable($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT checked_items, notes, updated_at FROM beta_qa_checklist_state WHERE user_id = ? AND checklist_key = ? LIMIT 1');
    $stmt->execute([$userId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        gpQaJsonResponse(['ok' => true, 'checked_items' => new stdClass(), 'notes' => '', 'updated_at' => null]);
    }
    $items = json_decode((string) ($row['checked_items'] ?? '{}'), true);
    if (!is_array($items)) {
        $items = [];
    }
    gpQaJsonResponse(['ok' => true, 'checked_items' => $items, 'notes' => (string) ($row['notes'] ?? ''), 'updated_at' => $row['updated_at'] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gpQaJsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    gpQaJsonResponse(['ok' => false, 'error' => 'invalid_json'], 400);
}

$checkedItems = $data['checked_items'] ?? [];
if (!is_array($checkedItems)) {
    $checkedItems = [];
}
$cleanItems = [];
foreach ($checkedItems as $id => $value) {
    $id = substr(preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $id), 0, 160);
    if ($id !== '' && !empty($value)) {
        $cleanItems[$id] = true;
    }
}

$notes = trim((string) ($data['notes'] ?? ''));
if (strlen($notes) > 12000) {
    $notes = substr($notes, 0, 12000);
}

$stmt = $pdo->prepare("INSERT INTO beta_qa_checklist_state (user_id, checklist_key, checked_items, notes, updated_at)
    VALUES (?, ?, ?::jsonb, ?, CURRENT_TIMESTAMP)
    ON CONFLICT (user_id, checklist_key)
    DO UPDATE SET checked_items = EXCLUDED.checked_items, notes = EXCLUDED.notes, updated_at = CURRENT_TIMESTAMP");
$stmt->execute([$userId, $key, json_encode($cleanItems), $notes !== '' ? $notes : null]);

gpQaJsonResponse(['ok' => true, 'checked_items' => $cleanItems, 'notes' => $notes]);
