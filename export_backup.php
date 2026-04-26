<?php
require_once 'includes/db_connect.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$format = $_GET['format'] ?? 'json';
$allowedFormats = ['json', 'csv', 'package'];
if (!in_array($format, $allowedFormats, true)) {
    $format = 'json';
}

function slugifyValue(string $value, string $fallback = 'psd_logbook'): string {
    $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($value));
    $slug = trim((string) $slug, '_');
    return $slug !== '' ? $slug : $fallback;
}

function normalizeRelativePath(string $path): string {
    return ltrim(str_replace('\\', '/', $path), '/');
}

$userStmt = $pdo->prepare('SELECT id, username, dog_name, breed, chip_number, weight_lbs, is_2fa_enabled, created_at FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$ownedDogsStmt = $pdo->prepare('SELECT * FROM dogs WHERE owner_user_id = ? ORDER BY id ASC');
$ownedDogsStmt->execute([$userId]);
$ownedDogs = $ownedDogsStmt->fetchAll();

if ($format === 'csv') {
    $logsStmt = $pdo->prepare('SELECT d.name AS dog_name, dl.log_date, dl.location_name, dl.location_city_state, dl.location_type, dl.focus_level, dl.skills_practiced, dl.handler_notes, dl.media_url, dl.media_type, dl.media_mime, dl.latitude, dl.longitude FROM daily_logs dl JOIN dogs d ON d.id = dl.dog_id WHERE d.owner_user_id = ? ORDER BY dl.log_date DESC, dl.id DESC');
    $logsStmt->execute([$userId]);
    $logs = $logsStmt->fetchAll();

    $slug = slugifyValue((string) ($user['username'] ?: 'psd_logbook'));
    $timestamp = date('Ymd_His');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $slug . '_logs_' . $timestamp . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['dog_name', 'log_date', 'location_name', 'location_city_state', 'location_type', 'focus_level', 'skills_practiced', 'handler_notes', 'media_url', 'media_type', 'media_mime', 'latitude', 'longitude']);
    foreach ($logs as $log) {
        $skills = json_decode($log['skills_practiced'] ?? '[]', true);
        if (!is_array($skills)) {
            $skills = [];
        }
        fputcsv($out, [
            $log['dog_name'],
            $log['log_date'],
            $log['location_name'],
            $log['location_city_state'],
            $log['location_type'],
            $log['focus_level'],
            implode(' | ', $skills),
            $log['handler_notes'],
            $log['media_url'],
            $log['media_type'],
            $log['media_mime'],
            $log['latitude'],
            $log['longitude'],
        ]);
    }
    fclose($out);
    exit;
}

$payload = [
    'app' => 'psd_logbook',
    'backup_version' => 4,
    'exported_at' => date('c'),
    'scope' => 'owned_dogs',
    'user' => $user,
    'dogs' => [],
];

$baseDir = realpath(__DIR__);
$packageFiles = [];

foreach ($ownedDogs as $dogIndex => $dog) {
    $dogId = (int) $dog['id'];
    $dogKey = 'dog_' . ($dogIndex + 1);

    $dogPayload = [
        'profile' => [
            'name' => $dog['name'],
            'breed' => $dog['breed'],
            'chip_number' => $dog['chip_number'],
            'weight_lbs' => $dog['weight_lbs'],
            'date_of_birth' => $dog['date_of_birth'],
            'birth_is_approximate' => (int) $dog['birth_is_approximate'],
            'approx_age_years' => $dog['approx_age_years'],
            'notes' => $dog['notes'],
            'created_at' => $dog['created_at'],
            'updated_at' => $dog['updated_at'],
        ],
        'collaborators' => [],
        'vets' => [],
        'documents' => [],
        'appointments' => [],
        'logs' => [],
    ];

    $collabStmt = $pdo->prepare("SELECT u.username, dh.role, dh.permission_level, dh.status, dh.accepted_at
        FROM dog_handlers dh
        JOIN users u ON u.id = dh.user_id
        WHERE dh.dog_id = ? AND dh.status = 'accepted'
        ORDER BY u.username ASC");
    $collabStmt->execute([$dogId]);
    foreach ($collabStmt->fetchAll() as $c) {
        $dogPayload['collaborators'][] = [
            'username' => $c['username'],
            'role' => $c['role'],
            'permission_level' => $c['permission_level'],
            'status' => $c['status'],
            'accepted_at' => $c['accepted_at'],
        ];
    }

    $vetsStmt = $pdo->prepare('SELECT * FROM dog_vets WHERE dog_id = ? ORDER BY id ASC');
    $vetsStmt->execute([$dogId]);
    $vets = $vetsStmt->fetchAll();
    $vetIdToKey = [];
    foreach ($vets as $vetIndex => $vet) {
        $vetKey = 'vet_' . ($vetIndex + 1);
        $vetIdToKey[(int) $vet['id']] = $vetKey;
        $dogPayload['vets'][] = [
            'local_key' => $vetKey,
            'clinic_name' => $vet['clinic_name'],
            'vet_name' => $vet['vet_name'],
            'phone' => $vet['phone'],
            'address' => $vet['address'],
            'is_primary' => (int) $vet['is_primary'],
            'notes' => $vet['notes'],
            'created_at' => $vet['created_at'],
        ];
    }

    $docsStmt = $pdo->prepare('SELECT * FROM dog_documents WHERE dog_id = ? ORDER BY id ASC');
    $docsStmt->execute([$dogId]);
    foreach ($docsStmt->fetchAll() as $docIndex => $doc) {
        $row = [
            'doc_type' => $doc['doc_type'],
            'title' => $doc['title'],
            'provider_name' => $doc['provider_name'],
            'notes' => $doc['notes'],
            'file_path' => $doc['file_path'],
            'mime_type' => $doc['mime_type'],
            'created_at' => $doc['created_at'],
            'file_missing' => false,
        ];
        if (!empty($doc['file_path'])) {
            $relative = normalizeRelativePath((string) $doc['file_path']);
            $absolute = realpath($baseDir . DIRECTORY_SEPARATOR . $relative);
            if ($absolute && str_starts_with($absolute, $baseDir . DIRECTORY_SEPARATOR) && is_file($absolute)) {
                $ext = pathinfo($relative, PATHINFO_EXTENSION);
                $inside = 'documents/' . $dogKey . '/doc_' . ($docIndex + 1) . ($ext ? '.' . $ext : '');
                $row['package_path'] = $inside;
                $packageFiles[$inside] = $absolute;
            } else {
                $row['file_missing'] = true;
            }
        }
        $dogPayload['documents'][] = $row;
    }

    $apptStmt = $pdo->prepare('SELECT * FROM dog_vet_appointments WHERE dog_id = ? ORDER BY appointment_at ASC, id ASC');
    $apptStmt->execute([$dogId]);
    foreach ($apptStmt->fetchAll() as $appt) {
        $dogPayload['appointments'][] = [
            'title' => $appt['title'],
            'appointment_at' => $appt['appointment_at'],
            'reminder_at' => $appt['reminder_at'],
            'location_text' => $appt['location_text'],
            'notes' => $appt['notes'],
            'status' => $appt['status'],
            'vet_local_key' => $appt['dog_vet_id'] ? ($vetIdToKey[(int) $appt['dog_vet_id']] ?? null) : null,
            'created_at' => $appt['created_at'],
        ];
    }

    $logsStmt = $pdo->prepare('SELECT * FROM daily_logs WHERE dog_id = ? ORDER BY log_date ASC, id ASC');
    $logsStmt->execute([$dogId]);
    foreach ($logsStmt->fetchAll() as $logIndex => $log) {
        $row = [
            'log_date' => $log['log_date'],
            'location_name' => $log['location_name'],
            'location_city_state' => $log['location_city_state'],
            'location_type' => $log['location_type'],
            'focus_level' => (int) $log['focus_level'],
            'skills_practiced' => json_decode($log['skills_practiced'] ?? '[]', true),
            'handler_notes' => $log['handler_notes'],
            'media_url' => $log['media_url'],
            'media_type' => $log['media_type'],
            'media_mime' => $log['media_mime'],
            'latitude' => $log['latitude'],
            'longitude' => $log['longitude'],
            'media_missing' => false,
        ];
        if (!is_array($row['skills_practiced'])) {
            $row['skills_practiced'] = [];
        }
        if (!empty($log['media_url'])) {
            $relative = normalizeRelativePath((string) $log['media_url']);
            $absolute = realpath($baseDir . DIRECTORY_SEPARATOR . $relative);
            if ($absolute && str_starts_with($absolute, $baseDir . DIRECTORY_SEPARATOR) && is_file($absolute)) {
                $ext = pathinfo($relative, PATHINFO_EXTENSION);
                $inside = 'media/' . $dogKey . '/log_' . ($logIndex + 1) . ($ext ? '.' . $ext : '');
                $row['media_package_path'] = $inside;
                $packageFiles[$inside] = $absolute;
            } else {
                $row['media_missing'] = true;
            }
        }
        $dogPayload['logs'][] = $row;
    }

    $payload['dogs'][] = $dogPayload;
}

$slug = slugifyValue((string) ($user['username'] ?: 'psd_logbook'));
$timestamp = date('Ymd_His');

if ($format === 'package') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('ZipArchive is not available on this PHP build.');
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'psd_backup_');
    if ($tmpFile === false) {
        http_response_code(500);
        exit('Could not create temporary backup file.');
    }
    $zipPath = $tmpFile . '.zip';
    @unlink($zipPath);
    rename($tmpFile, $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('Unable to create backup package.');
    }

    $zip->addFromString('backup.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    foreach ($packageFiles as $inside => $absolute) {
        $zip->addFile($absolute, $inside);
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $slug . '_full_backup_' . $timestamp . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $slug . '_backup_' . $timestamp . '.json"');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
