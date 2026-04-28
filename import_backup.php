<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/authz.php';
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/validation.php';
requireAdmin();
checkLogin();

$allowedTypes = ['In-Cab', 'Truck Stop', 'Shipper/Receiver', 'Public Store', 'Rest Area', 'Other'];
$allowedSkills = ['Sit/Stay', 'Heel', 'Leave It', 'Under Tuck', 'DPT Task', 'PA Focus'];
$allowedDocTypes = ['vet_record', 'esa_letter', 'service_dog_letter', 'service_letter'];

$result = [
    'success' => false,
    'message' => '',
    'dogs_restored' => 0,
    'logs_restored' => 0,
    'vets_restored' => 0,
    'documents_restored' => 0,
    'appointments_restored' => 0,
    'collaborators_restored' => 0,
    'skipped' => 0,
    'errors' => [],
    'profile_updated' => false,
    'media_restored' => 0,
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: backup.php');
    exit;
}

function normalizeSkillsForImport($skills, array $allowedSkills): array {
    if (is_string($skills)) {
        $decoded = json_decode($skills, true);
        if (is_array($decoded)) {
            $skills = $decoded;
        } elseif (strpos($skills, '|') !== false) {
            $skills = array_map('trim', explode('|', $skills));
        } else {
            $skills = [];
        }
    }
    return cleanSkills($skills, $allowedSkills);
}

function ensureImportDirectories(string $basePath): void {
    foreach ([$basePath . '/uploads', $basePath . '/uploads/images', $basePath . '/uploads/videos', $basePath . '/uploads/documents'] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function normalizeRelativeImportPath(string $path): string {
    return ltrim(str_replace('\\', '/', $path), '/');
}

function restorePackagedFile(array $row, array $packageFiles, string $basePath, string $defaultSubdir, array &$result, string $pathKey = 'package_path'): array {
    if (empty($row[$pathKey])) {
        return [$row['file_path'] ?? null, $row['mime_type'] ?? null, false];
    }

    $inside = $row[$pathKey];
    if (empty($packageFiles[$inside]) || !is_file($packageFiles[$inside])) {
        $result['errors'][] = 'Missing packaged file: ' . $inside;
        return [$row['file_path'] ?? null, $row['mime_type'] ?? null, false];
    }

    $ext = strtolower(pathinfo($inside, PATHINFO_EXTENSION));
    $safeExt = preg_match('/^[a-z0-9]{1,5}$/', $ext) ? $ext : 'bin';
    $filename = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $safeExt);
    $targetDir = $basePath . '/uploads/' . trim($defaultSubdir, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $absolute = $targetDir . '/' . $filename;
    $relative = 'uploads/' . trim($defaultSubdir, '/') . '/' . $filename;
    if (!copy($packageFiles[$inside], $absolute)) {
        $result['errors'][] = 'Could not restore packaged file: ' . $inside;
        return [$row['file_path'] ?? null, $row['mime_type'] ?? null, false];
    }

    $result['media_restored']++;
    return [$relative, $row['mime_type'] ?? null, true];
}

try {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if (empty($_FILES['backup_file']['name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
        throw new RuntimeException('Choose a backup file first.');
    }

    $mode = ($_POST['restore_mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
    $importProfile = !empty($_POST['import_profile']);
    $userId = (int) $_SESSION['user_id'];
    $uploadedName = strtolower($_FILES['backup_file']['name']);
    $tmpPath = $_FILES['backup_file']['tmp_name'];
    $payload = null;
    $packageFiles = [];
    $mediaTempDir = null;
    $basePath = __DIR__;

    if (str_ends_with($uploadedName, '.zip')) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Zip restore is not available on this PHP build.');
        }

        $mediaTempDir = sys_get_temp_dir() . '/psd_import_' . bin2hex(random_bytes(6));
        if (!mkdir($mediaTempDir, 0700, true) && !is_dir($mediaTempDir)) {
            throw new RuntimeException('Could not prepare import directory.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new RuntimeException('Could not open backup package.');
        }

        $backupJson = $zip->getFromName('backup.json');
        if ($backupJson === false) {
            $zip->close();
            throw new RuntimeException('This backup package does not contain backup.json.');
        }
        $payload = json_decode($backupJson, true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || empty($stat['name']) || substr($stat['name'], -1) === '/') {
                continue;
            }
            $name = str_replace('\\', '/', $stat['name']);
            if ($name === 'backup.json') {
                continue;
            }
            $stream = $zip->getStream($name);
            if (!$stream) {
                continue;
            }
            $target = $mediaTempDir . '/' . basename($name);
            $out = fopen($target, 'wb');
            if ($out) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
                $packageFiles[$name] = $target;
            }
            fclose($stream);
        }
        $zip->close();
    } else {
        $payload = json_decode((string) file_get_contents($tmpPath), true);
    }

    if (!is_array($payload) || ($payload['app'] ?? '') !== 'psd_logbook') {
        throw new RuntimeException('This file is not a valid PSD Logbook backup.');
    }

    $dogs = $payload['dogs'] ?? [];
    if (!is_array($dogs)) {
        $dogs = [];
    }

    ensureImportDirectories($basePath);

    $pdo->beginTransaction();

    if ($mode === 'replace') {
        $ownedIdsStmt = $pdo->prepare('SELECT id FROM dogs WHERE owner_user_id = ?');
        $ownedIdsStmt->execute([$userId]);
        $ownedDogIds = array_map('intval', array_column($ownedIdsStmt->fetchAll(), 'id'));
        if ($ownedDogIds) {
            $placeholders = implode(',', array_fill(0, count($ownedDogIds), '?'));
            $pdo->prepare("DELETE FROM dog_handlers WHERE dog_id IN ($placeholders)")->execute($ownedDogIds);
            $pdo->prepare("DELETE FROM handler_handshakes WHERE dog_id IN ($placeholders)")->execute($ownedDogIds);
            $pdo->prepare("DELETE FROM dogs WHERE id IN ($placeholders)")->execute($ownedDogIds);
        }
    }

    $insertDoc = $pdo->prepare('INSERT INTO dog_documents (dog_id, uploaded_by_user_id, doc_type, title, provider_name, notes, file_path, mime_type) VALUES (?,?,?,?,?,?,?,?)');
    $insertAppt = $pdo->prepare('INSERT INTO dog_vet_appointments (dog_id, dog_vet_id, created_by_user_id, title, appointment_at, reminder_at, location_text, notes, status) VALUES (?,?,?,?,?,?,?,?,?)');
    $insertLog = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, log_date, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, media_url, media_type, media_mime, latitude, longitude) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

    $findCollaborator = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $existingDogCheck = $pdo->prepare('SELECT id FROM dogs WHERE owner_user_id = ? AND name = ? LIMIT 1');
    $existingLogCheck = $pdo->prepare('SELECT id FROM daily_logs WHERE user_id = ? AND dog_id = ? AND log_date = ? AND location_name = ? AND focus_level = ? LIMIT 1');

    foreach ($dogs as $dogRow) {
        if (!is_array($dogRow) || !is_array($dogRow['profile'] ?? null)) {
            $result['skipped']++;
            $result['errors'][] = 'Skipped an invalid dog record.';
            continue;
        }

        $profile = $dogRow['profile'];
        $dogName = cleanText($profile['name'] ?? '', 80);
        if ($dogName === '') {
            $result['skipped']++;
            $result['errors'][] = 'Skipped a dog with no name.';
            continue;
        }

        if ($mode === 'merge') {
            $existingDogCheck->execute([$userId, $dogName]);
            if ($existingDogCheck->fetchColumn()) {
                $result['skipped']++;
                $result['errors'][] = 'Skipped duplicate dog profile: ' . $dogName;
                continue;
            }
        }

        $insertDog->execute([
            $userId,
            $dogName,
            cleanText($profile['breed'] ?? '', 120) ?: null,
            cleanText($profile['chip_number'] ?? '', 80) ?: null,
            ($profile['weight_lbs'] ?? '') !== '' ? round((float) $profile['weight_lbs'], 2) : null,
            cleanDateValue($profile['date_of_birth'] ?? ''),
            !empty($profile['birth_is_approximate']) ? 1 : 0,
            ($profile['approx_age_years'] ?? '') !== '' ? round((float) $profile['approx_age_years'], 1) : null,
            cleanTextarea($profile['notes'] ?? '', 5000) ?: null,
        ]);
        $newDogId = (int) $insertDog->fetchColumn();
        $result['dogs_restored']++;

        if ($result['dogs_restored'] === 1) {
            setActiveDogId($pdo, $userId, $newDogId);
        }

        $vetKeyMap = [];
        foreach (($dogRow['vets'] ?? []) as $vetRow) {
            if (!is_array($vetRow)) {
                continue;
            }
            $clinic = cleanText($vetRow['clinic_name'] ?? '', 120);
            if ($clinic === '') {
                continue;
            }
            $insertVet->execute([
                $newDogId,
                $clinic,
                cleanText($vetRow['vet_name'] ?? '', 120) ?: null,
                cleanPhone($vetRow['phone'] ?? '') ?: null,
                cleanTextarea($vetRow['address'] ?? '', 500) ?: null,
                !empty($vetRow['is_primary']) ? 1 : 0,
                cleanTextarea($vetRow['notes'] ?? '', 2000) ?: null,
                $userId,
            ]);
            $newVetId = (int) $insertVet->fetchColumn();
            if (!empty($vetRow['local_key'])) {
                $vetKeyMap[$vetRow['local_key']] = $newVetId;
            }
            $result['vets_restored']++;
        }

        foreach (($dogRow['documents'] ?? []) as $docRow) {
            if (!is_array($docRow)) {
                continue;
            }
            $title = cleanText($docRow['title'] ?? '', 150);
            if ($title === '') {
                continue;
            }
            $docType = $docRow['doc_type'] ?? 'vet_record';
            if ($docType === 'service_letter') {
                $docType = 'service_dog_letter';
            }
            if (!in_array($docType, $allowedDocTypes, true)) {
                $docType = 'vet_record';
            }
            [$restoredPath, $mimeType] = restorePackagedFile($docRow, $packageFiles, $basePath, 'documents', $result);
            $insertDoc->execute([
                $newDogId,
                $userId,
                $docType,
                $title,
                cleanText($docRow['provider_name'] ?? '', 150) ?: null,
                cleanTextarea($docRow['notes'] ?? '', 2000) ?: null,
                $restoredPath ?: cleanText($docRow['file_path'] ?? '', 255),
                cleanText($mimeType ?? ($docRow['mime_type'] ?? ''), 100) ?: null,
            ]);
            $result['documents_restored']++;
        }

        foreach (($dogRow['appointments'] ?? []) as $apptRow) {
            if (!is_array($apptRow)) {
                continue;
            }
            $title = cleanText($apptRow['title'] ?? '', 150);
            $appointmentAt = cleanDateTimeValue($apptRow['appointment_at'] ?? '');
            if ($title === '' || !$appointmentAt) {
                continue;
            }
            $status = in_array(($apptRow['status'] ?? 'scheduled'), ['scheduled', 'completed', 'cancelled'], true) ? $apptRow['status'] : 'scheduled';
            $vetId = !empty($apptRow['vet_local_key']) && isset($vetKeyMap[$apptRow['vet_local_key']]) ? $vetKeyMap[$apptRow['vet_local_key']] : null;
            $insertAppt->execute([
                $newDogId,
                $vetId,
                $userId,
                $title,
                $appointmentAt,
                cleanDateTimeValue($apptRow['reminder_at'] ?? ''),
                cleanText($apptRow['location_text'] ?? '', 255) ?: null,
                cleanTextarea($apptRow['notes'] ?? '', 2000) ?: null,
                $status,
            ]);
            $result['appointments_restored']++;
        }

        foreach (($dogRow['logs'] ?? []) as $logRow) {
            if (!is_array($logRow)) {
                continue;
            }
            $logDate = cleanDateTimeValue($logRow['log_date'] ?? '') ?: date('Y-m-d H:i:s');
            $locationName = cleanText($logRow['location_name'] ?? '', 100);
            if ($locationName === '') {
                continue;
            }
            $focus = validateFocusLevel($logRow['focus_level'] ?? 3);
            if ($mode === 'merge') {
                $existingLogCheck->execute([$userId, $newDogId, $logDate, $locationName, $focus]);
                if ($existingLogCheck->fetchColumn()) {
                    $result['skipped']++;
                    continue;
                }
            }

            $mediaUrl = cleanText($logRow['media_url'] ?? '', 255) ?: null;
            $mediaType = in_array(($logRow['media_type'] ?? ''), ['image', 'video'], true) ? $logRow['media_type'] : null;
            $mediaMime = cleanText($logRow['media_mime'] ?? '', 100) ?: null;
            if (!empty($logRow['media_package_path'])) {
                $subdir = $mediaType === 'video' ? 'videos' : 'images';
                [$mediaUrl, $mediaMime] = restorePackagedFile([
                    'package_path' => $logRow['media_package_path'],
                    'file_path' => $mediaUrl,
                    'mime_type' => $mediaMime,
                ], $packageFiles, $basePath, $subdir, $result);
            }

            $insertLog->execute([
                $userId,
                $newDogId,
                $logDate,
                $locationName,
                cleanText($logRow['location_city_state'] ?? '', 100) ?: null,
                validateLocationType($logRow['location_type'] ?? 'Other', $allowedTypes),
                $focus,
                json_encode(normalizeSkillsForImport($logRow['skills_practiced'] ?? [], $allowedSkills), JSON_UNESCAPED_SLASHES),
                cleanTextarea($logRow['handler_notes'] ?? '', 5000) ?: null,
                $mediaUrl,
                $mediaType,
                $mediaMime,
                validateLatitude($logRow['latitude'] ?? null),
                validateLongitude($logRow['longitude'] ?? null),
            ]);
            $result['logs_restored']++;
        }

        foreach (($dogRow['collaborators'] ?? []) as $collabRow) {
            if (!is_array($collabRow) || empty($collabRow['username'])) {
                continue;
            }
            $username = strtolower(cleanText($collabRow['username'], 50));
            if ($username === '') {
                continue;
            }
            $findCollaborator->execute([$username]);
            $collabUserId = (int) ($findCollaborator->fetchColumn() ?: 0);
            if (!$collabUserId || $collabUserId === $userId) {
                if (!$collabUserId) {
                    $result['errors'][] = 'Skipped collaborator not found on this install: ' . $username;
                }
                continue;
            }
            $permission = ($collabRow['permission_level'] ?? 'edit') === 'view' ? 'view' : 'edit';
            $role = ($collabRow['role'] ?? 'collaborator') === 'owner' ? 'owner' : 'collaborator';
            $insertHandler->execute([$newDogId, $collabUserId, $userId, $role, $permission]);
            $result['collaborators_restored']++;
        }
    }

    if ($importProfile && !empty($payload['dogs'][0]['profile'])) {
        $firstDog = $payload['dogs'][0]['profile'];
        $pdo->prepare('UPDATE users SET dog_name = ?, breed = ?, chip_number = ?, weight_lbs = ? WHERE id = ?')
            ->execute([
                cleanText($firstDog['name'] ?? '', 80),
                cleanText($firstDog['breed'] ?? '', 120) ?: null,
                cleanText($firstDog['chip_number'] ?? '', 80) ?: null,
                ($firstDog['weight_lbs'] ?? '') !== '' ? round((float) $firstDog['weight_lbs'], 2) : null,
                $userId,
            ]);
        $result['profile_updated'] = true;
    }

    $pdo->commit();
    $result['success'] = true;
    $result['message'] = 'Backup restore completed.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $result['message'] = $e->getMessage();
} finally {
    if (!empty($mediaTempDir) && is_dir($mediaTempDir)) {
        foreach (glob($mediaTempDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($mediaTempDir);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Restore Result</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>


<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">♻️ Restore Result</h2>
        <a href="backup.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-danger' ?>">
        <strong><?= e($result['message']) ?></strong>
    </div>

    <?php if ($result['success']): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 text-center">
                <?php foreach ([
                    ['Dogs restored', $result['dogs_restored']],
                    ['Logs restored', $result['logs_restored']],
                    ['Vets restored', $result['vets_restored']],
                    ['Docs restored', $result['documents_restored']],
                    ['Appointments', $result['appointments_restored']],
                    ['Collaborators', $result['collaborators_restored']],
                    ['Files restored', $result['media_restored']],
                    ['Skipped', $result['skipped']],
                ] as [$label, $value]): ?>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-3 bg-white h-100">
                        <div class="fs-4 fw-bold"><?= (int) $value ?></div>
                        <div class="text-muted small"><?= e($label) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 text-muted small">Profile updated: <strong><?= $result['profile_updated'] ? 'Yes' : 'No' ?></strong></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title">What this restore covers now</h5>
            <p class="mb-0 text-muted">This restore now supports owned dogs, dog profiles, collaborators that already exist as users on this install, vet contacts, appointments, dog documents, training logs, and packaged files from full zip backups.</p>
        </div>
    </div>

    <?php if (!empty($result['errors'])): ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Skipped items / warnings</h5>
            <ul class="mb-0">
                <?php foreach ($result['errors'] as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
