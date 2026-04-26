<?php

function cleanText(?string $value, int $maxLength = 255): string {
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_substr($value, 0, $maxLength);
}

function cleanTextarea(?string $value, int $maxLength = 5000): string {
    $value = trim((string) $value);
    return mb_substr($value, 0, $maxLength);
}

function cleanSkills($skills, array $allowedSkills): array {
    if (!is_array($skills)) {
        return [];
    }

    $filtered = [];
    foreach ($skills as $skill) {
        if (in_array($skill, $allowedSkills, true)) {
            $filtered[] = $skill;
        }
    }

    return array_values(array_unique($filtered));
}

function validateFocusLevel($value): int {
    $focus = (int) $value;
    if ($focus < 1 || $focus > 5) {
        return 3;
    }
    return $focus;
}

function validateLocationType(?string $value, array $allowedTypes): string {
    return in_array($value, $allowedTypes, true) ? $value : 'Other';
}

function validateCoordinate($value): ?float {
    if ($value === '' || $value === null) {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, 7);
}

function validateLatitude($value): ?float {
    $lat = validateCoordinate($value);
    if ($lat === null || $lat < -90 || $lat > 90) {
        return null;
    }
    return $lat;
}

function validateLongitude($value): ?float {
    $lon = validateCoordinate($value);
    if ($lon === null || $lon < -180 || $lon > 180) {
        return null;
    }
    return $lon;
}

function ensureUploadDirectories(string $basePath): void {
    $paths = [
        $basePath . '/uploads',
        $basePath . '/uploads/images',
        $basePath . '/uploads/videos',
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}

function handleTrainingMediaUpload(array $file, string $basePath): array {
    if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null, null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $imageTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $videoTypes = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    $maxImageBytes = 8 * 1024 * 1024;
    $maxVideoBytes = 50 * 1024 * 1024;

    if (isset($imageTypes[$mimeType])) {
        if (($file['size'] ?? 0) > $maxImageBytes) {
            throw new RuntimeException('Images must be 8MB or smaller.');
        }
        $folder = 'images';
        $extension = $imageTypes[$mimeType];
        $mediaType = 'image';
    } elseif (isset($videoTypes[$mimeType])) {
        if (($file['size'] ?? 0) > $maxVideoBytes) {
            throw new RuntimeException('Videos must be 50MB or smaller.');
        }
        $folder = 'videos';
        $extension = $videoTypes[$mimeType];
        $mediaType = 'video';
    } else {
        throw new RuntimeException('Only JPG, PNG, WEBP, MP4, WEBM, and MOV files are allowed.');
    }

    ensureUploadDirectories($basePath);

    $filename = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $extension);
    $absolutePath = $basePath . '/uploads/' . $folder . '/' . $filename;
    $relativePath = 'uploads/' . $folder . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('The file could not be saved.');
    }

    return [$relativePath, $mediaType, $mimeType];
}


function cleanPhone(?string $value, int $maxLength = 40): string {
    $value = trim((string) $value);
    return mb_substr($value, 0, $maxLength);
}

function cleanDateValue(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $dt = date_create($value);
    return $dt ? $dt->format('Y-m-d') : null;
}

function cleanDateTimeValue(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $dt = date_create($value);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

function handleDogDocumentUpload(array $file, string $basePath): array {
    if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose a document to upload.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Document upload failed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mimeType])) {
        throw new RuntimeException('Only PDF, JPG, PNG, and WEBP files are allowed for records and letters.');
    }

    if (($file['size'] ?? 0) > (12 * 1024 * 1024)) {
        throw new RuntimeException('Documents must be 12MB or smaller.');
    }

    $dir = $basePath . '/uploads/documents';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $allowed[$mimeType]);
    $absolutePath = $dir . '/' . $filename;
    $relativePath = 'uploads/documents/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('The document could not be saved.');
    }

    return [$relativePath, $mimeType];
}
