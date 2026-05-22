<?php
require_once __DIR__ . '/app_config.php';

if (!function_exists('gpFeedbackColumnExists')) {
    function gpFeedbackColumnExists(PDO $pdo, string $column): bool
    {
        $column = strtolower(trim($column));
        if ($column === '') {
            return false;
        }

        $stmt = $pdo->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'feedback_reports'
        ");
        $columns = array_fill_keys(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
        return !empty($columns[$column]);
    }
}

if (!function_exists('gpEnsureFeedbackSourceColumns')) {
    function gpEnsureFeedbackSourceColumns(PDO $pdo): void
    {
        $columns = [
            'source_platform' => "TEXT NULL",
            'source_label' => "TEXT NULL",
            'source_version' => "TEXT NULL",
            'source_device' => "TEXT NULL",
        ];

        foreach ($columns as $column => $definition) {
            if (!gpFeedbackColumnExists($pdo, $column)) {
                $pdo->exec("ALTER TABLE feedback_reports ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            }
        }
    }
}

if (!function_exists('gpFeedbackAllowedExtensions')) {
    function gpFeedbackAllowedExtensions(): array
    {
        return [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'heic',
            'heif',
            'txt',
            'log',
            'csv',
            'json',
            'pdf',
            'doc',
            'docx',
            'rtf',
            'odt',
            'mp3',
            'm4a',
            'wav',
            'aac',
            'ogg',
            'mp4',
            'mov',
            'webm',
            'm4v',
            '3gp',
        ];
    }
}

if (!function_exists('gpFeedbackAcceptAttribute')) {
    function gpFeedbackAcceptAttribute(): string
    {
        return implode(',', [
            '.jpg',
            '.jpeg',
            '.png',
            '.gif',
            '.webp',
            '.heic',
            '.heif',
            '.txt',
            '.log',
            '.csv',
            '.json',
            '.pdf',
            '.doc',
            '.docx',
            '.rtf',
            '.odt',
            '.mp3',
            '.m4a',
            '.wav',
            '.aac',
            '.ogg',
            '.mp4',
            '.mov',
            '.webm',
            '.m4v',
            '.3gp',
            'image/*',
            'video/*',
            'audio/*',
            'text/plain',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/rtf',
            'application/vnd.oasis.opendocument.text',
        ]);
    }
}

if (!function_exists('gpSaveFeedbackSubmission')) {
    function gpSaveFeedbackSubmission(PDO $pdo, ?int $userId, array $input): int
    {
        gpEnsureFeedbackSourceColumns($pdo);

        $category = strtolower(trim((string) ($input['category'] ?? 'bug')));
        if (!in_array($category, ['bug', 'feature', 'enhancement'], true)) {
            $category = 'bug';
        }

        $pageWorkflow = trim((string) ($input['page_workflow'] ?? ''));
        $contactEmail = trim((string) ($input['contact_email'] ?? ''));
        $details = trim((string) ($input['details'] ?? ''));
        $sourcePlatform = trim((string) ($input['source_platform'] ?? 'web'));
        $sourceLabel = trim((string) ($input['source_label'] ?? 'Web'));
        $sourceVersion = trim((string) ($input['source_version'] ?? ''));
        $sourceDevice = trim((string) ($input['source_device'] ?? ''));

        $legacyType = in_array($category, ['feature', 'enhancement'], true) ? 'feature' : 'bug';
        $legacyTitle = $pageWorkflow !== '' ? $pageWorkflow : ucfirst($category) . ' report';
        $legacyDescription = $details;

        $resolvedUserId = ($userId !== null && $userId > 0) ? $userId : null;

        $columns = [
            'user_id',
            'report_type',
            'title',
            'description',
            'category',
            'page_workflow',
            'contact_email',
            'details',
        ];
        $values = [
            $resolvedUserId,
            $legacyType,
            $legacyTitle,
            $legacyDescription,
            $category,
            $pageWorkflow,
            $contactEmail,
            $details,
        ];

        if (gpFeedbackColumnExists($pdo, 'source_platform')) {
            $columns[] = 'source_platform';
            $values[] = $sourcePlatform;
        }
        if (gpFeedbackColumnExists($pdo, 'source_label')) {
            $columns[] = 'source_label';
            $values[] = $sourceLabel;
        }
        if (gpFeedbackColumnExists($pdo, 'source_version')) {
            $columns[] = 'source_version';
            $values[] = $sourceVersion;
        }
        if (gpFeedbackColumnExists($pdo, 'source_device')) {
            $columns[] = 'source_device';
            $values[] = $sourceDevice;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO feedback_reports (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ') RETURNING id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('gpStoreFeedbackAttachments')) {
    function gpStoreFeedbackAttachments(PDO $pdo, int $feedbackId, array $files, string $uploadDir, array &$uploadDebug): void
    {
        if ($feedbackId <= 0 || empty($files['name'][0])) {
            return;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedExtensions = gpFeedbackAllowedExtensions();

        foreach ($files['name'] as $i => $originalName) {
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                $uploadDebug[] = 'Upload error for ' . (string) $originalName . ': code ' . $error;
                continue;
            }

            $tmpName = $files['tmp_name'][$i];
            $size = (int) ($files['size'][$i] ?? 0);
            $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

            if ($size <= 0) {
                $uploadDebug[] = 'Skipped ' . (string) $originalName . ': empty file.';
                continue;
            }

            if ($size > 100 * 1024 * 1024) {
                $uploadDebug[] = 'Skipped ' . (string) $originalName . ': file too large (' . $size . ' bytes).';
                continue;
            }

            if (!in_array($ext, $allowedExtensions, true)) {
                $uploadDebug[] = 'Skipped ' . (string) $originalName . ': extension .' . $ext . ' not allowed.';
                continue;
            }

            $mime = function_exists('mime_content_type')
                ? (mime_content_type($tmpName) ?: 'application/octet-stream')
                : 'application/octet-stream';

            $safeName = 'feedback_' . $feedbackId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $storedPath = 'uploads/feedback/' . $safeName;

            if (@move_uploaded_file($tmpName, $uploadDir . '/' . $safeName)) {
                $att = $pdo->prepare("
                    INSERT INTO feedback_attachments
                    (feedback_id, original_name, stored_path, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $att->execute([
                    $feedbackId,
                    basename((string) $originalName),
                    $storedPath,
                    $mime,
                    $size,
                ]);
            } else {
                $uploadDebug[] = 'Could not move uploaded file: ' . (string) $originalName;
            }
        }
    }
}
