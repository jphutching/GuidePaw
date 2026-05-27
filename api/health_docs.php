<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/seo.php';

$user   = requireApiUser($pdo);
$userId = (int) $user['id'];
$dogId  = isset($user['active_dog_id']) ? (int) $user['active_dog_id'] : 0;

function gpApiNormalizeVetFull(array $row): array
{
    return [
        'id'          => (int) $row['id'],
        'clinic_name' => (string) ($row['clinic_name'] ?? ''),
        'vet_name'    => (string) ($row['vet_name'] ?? ''),
        'phone'       => (string) ($row['phone'] ?? ''),
        'address'     => (string) ($row['address'] ?? ''),
        'notes'       => (string) ($row['notes'] ?? ''),
        'is_primary'  => !empty($row['is_primary']),
    ];
}

function gpApiNormalizeDoc(array $row): array
{
    $path = (string) ($row['file_path'] ?? '');
    $url  = $path ? guidepawSeoAbsoluteUrl($path) : '';
    return [
        'id'            => (int) $row['id'],
        'doc_type'      => (string) ($row['doc_type'] ?? 'vet_record'),
        'title'         => (string) ($row['title'] ?? ''),
        'provider_name' => (string) ($row['provider_name'] ?? ''),
        'notes'         => (string) ($row['notes'] ?? ''),
        'file_url'      => $url,
        'created_at'    => (string) ($row['created_at'] ?? ''),
    ];
}

function gpHealthResolveDog(PDO $pdo, int $userId, int $dogId): ?array
{
    $stmt = $pdo->prepare("SELECT id, name FROM dogs WHERE id = ? AND owner_user_id = ?");
    $stmt->execute([$dogId, $userId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dog) return $dog;

    $stmt = $pdo->prepare("
        SELECT d.id, d.name FROM dogs d
        JOIN dog_handlers dh ON dh.dog_id = d.id
        WHERE d.id = ? AND dh.user_id = ? AND dh.status = 'accepted'
        LIMIT 1
    ");
    $stmt->execute([$dogId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($dogId <= 0) {
        apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
    }
    $dog = gpHealthResolveDog($pdo, $userId, $dogId);
    if (!$dog) {
        apiJson(['success' => false, 'message' => 'Active dog not found.'], 404);
    }

    $vetsStmt = $pdo->prepare("SELECT * FROM dog_vets WHERE dog_id = ? ORDER BY is_primary DESC, clinic_name ASC");
    $vetsStmt->execute([$dogId]);
    $vets = $vetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $docsStmt = $pdo->prepare("SELECT * FROM dog_documents WHERE dog_id = ? ORDER BY created_at DESC");
    $docsStmt->execute([$dogId]);
    $docs = $docsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    apiJson([
        'success'   => true,
        'dog_name'  => (string) $dog['name'],
        'vets'      => array_map('gpApiNormalizeVetFull', $vets),
        'documents' => array_map('gpApiNormalizeDoc', $docs),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string) ($input['action'] ?? '');

if ($dogId <= 0) {
    apiJson(['success' => false, 'message' => 'No active dog set.'], 422);
}

$stmt = $pdo->prepare("SELECT id FROM dogs WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$dogId, $userId]);
if (!$stmt->fetch()) {
    apiJson(['success' => false, 'message' => 'You do not have write access for this dog.'], 403);
}

if ($action === 'add_vet') {
    $clinic    = trim((string) ($input['clinic_name'] ?? ''));
    if ($clinic === '') {
        apiJson(['success' => false, 'message' => 'Clinic name is required.'], 422);
    }
    $vetName   = trim((string) ($input['vet_name'] ?? ''));
    $phone     = trim((string) ($input['phone'] ?? ''));
    $address   = trim((string) ($input['address'] ?? ''));
    $notes     = trim((string) ($input['notes'] ?? ''));
    $isPrimary = !empty($input['is_primary']) ? 1 : 0;

    if ($isPrimary) {
        $pdo->prepare("UPDATE dog_vets SET is_primary = 0 WHERE dog_id = ?")->execute([$dogId]);
    }
    $pdo->prepare("
        INSERT INTO dog_vets (dog_id, clinic_name, vet_name, phone, address, is_primary, notes, created_by_user_id)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$dogId, $clinic, $vetName ?: null, $phone ?: null, $address ?: null, $isPrimary, $notes ?: null, $userId]);

    apiJson(['success' => true, 'message' => 'Vet saved.']);
}

if ($action === 'add_document') {
    require_once __DIR__ . '/../includes/validation.php';
    if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        apiJson(['success' => false, 'message' => 'No file uploaded.'], 422);
    }
    $docType      = trim((string) ($input['doc_type'] ?? 'vet_record'));
    $title        = trim((string) ($input['title'] ?? ''));
    $providerName = trim((string) ($input['provider_name'] ?? ''));
    $notes        = trim((string) ($input['notes'] ?? ''));

    if ($title === '') {
        apiJson(['success' => false, 'message' => 'Document title is required.'], 422);
    }

    try {
        [$relativePath, $mimeType] = handleDogDocumentUpload($_FILES['document_file'], dirname(__DIR__));
    } catch (RuntimeException $e) {
        apiJson(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $pdo->prepare("
        INSERT INTO dog_documents (dog_id, uploaded_by_user_id, doc_type, title, provider_name, notes, file_path, mime_type)
        VALUES (?,?,?,?,?,?,?,?)
    ")->execute([$dogId, $userId, $docType ?: 'vet_record', $title, $providerName ?: null, $notes ?: null, $relativePath, $mimeType]);

    apiJson(['success' => true, 'message' => 'Document uploaded.']);
}

apiJson(['success' => false, 'message' => 'Unknown action.'], 422);
