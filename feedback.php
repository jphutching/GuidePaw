<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php?msg=login_required');
    exit;
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function feedbackBuildErrorPrefill(?array $errorContext, string $requestedErrorId = ''): array
{
    $page = (string) ($errorContext['page'] ?? 'Unknown page');
    $method = (string) ($errorContext['method'] ?? 'Unknown method');
    $errorId = (string) ($errorContext['error_id'] ?? $requestedErrorId);
    $time = (string) ($errorContext['time'] ?? date('c'));
    $message = (string) ($errorContext['message'] ?? 'Application error was reported, but session details were not available.');
    $file = (string) ($errorContext['file'] ?? 'Unknown file');
    $line = (string) ($errorContext['line'] ?? 'Unknown line');
    $referer = (string) ($errorContext['referer'] ?? '');
    $userAgent = (string) ($errorContext['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $contextUserId = (string) ($errorContext['user_id'] ?? ($_SESSION['user_id'] ?? ''));

    $workflow = $page !== 'Unknown page'
        ? 'Application error on ' . $page
        : 'Application error' . ($errorId !== '' ? ' ' . $errorId : '');

    $details = "Auto-captured GuidePaw application error report\n\n" .
        "What I was doing:\n" .
        "[Please add what you clicked, what you expected, and whether it happens every time.]\n\n" .
        "Technical context captured by GuidePaw:\n" .
        "Error ID: {$errorId}\n" .
        "Time: {$time}\n" .
        "Page: {$page}\n" .
        "Method: {$method}\n" .
        "User ID: {$contextUserId}\n" .
        "Message: {$message}\n" .
        "File: {$file}\n" .
        "Line: {$line}\n" .
        "Referrer: {$referer}\n" .
        "User agent: {$userAgent}\n";

    return [$workflow, $details];
}

$message = '';
$uploadDebug = [];
$fromError = (($_GET['from_error'] ?? '') === '1');
$requestedErrorId = trim((string) ($_GET['error_id'] ?? ''));
$errorContext = null;
$errorContextMissing = false;

if ($fromError) {
    $candidate = $_SESSION['last_app_error'] ?? null;
    if (is_array($candidate) && ($requestedErrorId === '' || (string) ($candidate['error_id'] ?? '') === $requestedErrorId)) {
        $errorContext = $candidate;
    } else {
        $errorContextMissing = true;
    }
}

$prefillCategory = 'bug';
$prefillWorkflow = '';
$prefillDetails = '';
if ($fromError) {
    [$prefillWorkflow, $prefillDetails] = feedbackBuildErrorPrefill($errorContext, $requestedErrorId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = ($_POST['category'] ?? 'bug') === 'feature' ? 'feature' : 'bug';
    $pageWorkflow = trim($_POST['page_workflow'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $details = trim($_POST['details'] ?? '');

    if ($details === '') {
        header('Location: feedback.php?msg=missing');
        exit;
    }

    $legacyType = $category === 'feature' ? 'feature' : 'bug';
    $legacyTitle = $pageWorkflow !== '' ? $pageWorkflow : ucfirst($category) . ' report';
    $legacyDescription = $details;

    $stmt = $pdo->prepare("
        INSERT INTO feedback_reports
        (
            user_id,
            report_type,
            title,
            description,
            category,
            page_workflow,
            contact_email,
            details
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([
        $userId,
        $legacyType,
        $legacyTitle,
        $legacyDescription,
        $category,
        $pageWorkflow,
        $contactEmail,
        $details
    ]);
    $feedbackId = (int)$stmt->fetchColumn();

    $uploadDir = __DIR__ . '/uploads/feedback';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'log', 'csv', 'json', 'pdf', 'mp4', 'mov', 'webm', 'm4v', '3gp'];

    if ($feedbackId > 0 && !empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['name'] as $i => $originalName) {
            $error = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                $uploadDebug[] = 'Upload error for ' . (string)$originalName . ': code ' . $error;
                continue;
            }

            $tmpName = $_FILES['attachments']['tmp_name'][$i];
            $size = (int)($_FILES['attachments']['size'][$i] ?? 0);
            $ext = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));

            if ($size <= 0) {
                $uploadDebug[] = 'Skipped ' . (string)$originalName . ': empty file.';
                continue;
            }

            if ($size > 100 * 1024 * 1024) {
                $uploadDebug[] = 'Skipped ' . (string)$originalName . ': file too large (' . $size . ' bytes).';
                continue;
            }

            if (!in_array($ext, $allowedExtensions, true)) {
                $uploadDebug[] = 'Skipped ' . (string)$originalName . ': extension .' . $ext . ' not allowed.';
                continue;
            }

            $mime = function_exists('mime_content_type')
                ? (mime_content_type($tmpName) ?: 'application/octet-stream')
                : 'application/octet-stream';

            $safeName = 'feedback_' . $feedbackId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $storedPath = 'uploads/feedback/' . $safeName;

            if (@move_uploaded_file($tmpName, __DIR__ . '/' . $storedPath)) {
                $att = $pdo->prepare("
                    INSERT INTO feedback_attachments
                    (feedback_id, original_name, stored_path, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $att->execute([
                    $feedbackId,
                    basename((string)$originalName),
                    $storedPath,
                    $mime,
                    $size
                ]);
            } else {
                $uploadDebug[] = 'Could not move uploaded file: ' . (string)$originalName;
            }
        }
    }

    if ($uploadDebug) {
        $_SESSION['feedback_upload_debug'] = $uploadDebug;
        header('Location: feedback.php?msg=upload_debug');
        exit;
    }

    header('Location: feedback.php?msg=saved');
    exit;
}

if (($_GET['msg'] ?? '') === 'saved') {
    $message = 'Report saved successfully.';
} elseif (($_GET['msg'] ?? '') === 'missing') {
    $message = 'Please add details before saving.';
} elseif (($_GET['msg'] ?? '') === 'upload_debug') {
    $message = 'Report saved, but one or more attachments were skipped: ' . implode(' | ', $_SESSION['feedback_upload_debug'] ?? []);
    unset($_SESSION['feedback_upload_debug']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; padding-bottom: 90px; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 18px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .card { background: #fff; border: 1px solid #dfe3ea; border-radius: 18px; padding: 18px; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        label { display: block; font-weight: 800; margin-top: 16px; font-size: 1rem; }
        input, select, textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 13px 14px;
            margin-top: 7px;
            font-size: 1rem;
            background: #fff;
        }
        textarea { min-height: 260px; resize: vertical; }
        .btn, button {
            display: inline-block;
            border: 0;
            border-radius: 14px;
            padding: 13px 18px;
            background: #0d6efd;
            color: #fff;
            text-decoration: none;
            font-weight: 900;
            font-size: 1rem;
        }
        .btn.secondary { background: transparent; color: #6b7280; border: 1px solid #9ca3af; }
        .actions { display: flex; justify-content: flex-end; margin-top: 18px; }
        .small { color: #6b7280; font-size: .9rem; line-height: 1.35; }
        .alert { background: #d1e7dd; border: 1px solid #a3cfbb; color: #0f5132; padding: 12px; border-radius: 14px; margin: 14px 0; font-weight: 800; }
        .alert.warn { background: #fff3cd; border-color: #ffecb5; color: #664d03; }
        .alert.error { background: #f8d7da; border-color: #f5c2c7; color: #842029; }
        .upload-box {
            border: 3px dashed #64748b;
            border-radius: 16px;
            padding: 16px;
            background: #f8fafc;
            margin-top: 8px;
        }
        .upload-box input[type="file"] {
            border: 2px solid #2563eb;
            background: #fff;
            padding: 16px;
            font-weight: 800;
        }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>

<div class="wrap">
    <div class="top">
        <h1>Bug report / feature request</h1>
        <a class="btn secondary" href="index.php">Back</a>
    </div>

    <p class="small">Use this page to log issues, feature ideas, screenshots, pasted logs, or text files during development and beta testing.</p>

    <?php if ($fromError && $errorContext): ?>
        <div class="alert warn">GuidePaw attached the application error context automatically. Add what you clicked or expected, then save the report.</div>
    <?php elseif ($fromError && $errorContextMissing): ?>
        <div class="alert error">The error report link opened, but the session no longer had the matching error details. The Error ID is still included below.</div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" enctype="multipart/form-data">
            <label>Category</label>
            <select name="category">
                <option value="bug" <?= $prefillCategory === 'bug' ? 'selected' : '' ?>>Bug</option>
                <option value="feature" <?= $prefillCategory === 'feature' ? 'selected' : '' ?>>Feature request</option>
            </select>

            <label>Page or workflow</label>
            <input name="page_workflow" value="<?= h($prefillWorkflow) ?>" placeholder="dogs.php, onboarding, backup restore, training history">

            <label>Contact email optional</label>
            <input name="contact_email" type="email" placeholder="name@example.com">

            <label>Details</label>
            <textarea id="details" name="details" required placeholder="What happened, what you expected, or what feature would help? You can paste terminal output or logs here too."><?= h($prefillDetails) ?></textarea>
            <div class="small" id="clientContextNote">Browser/device context will be added automatically when useful.</div>

            <label>Attachments / screenshots / logs</label>
            <div class="upload-box">
                <input
                    id="attachments"
                    type="file"
                    name="attachments[]"
                    multiple
                    accept=".jpg,.jpeg,.png,.gif,.webp,.txt,.log,.csv,.json,.pdf,.mp4,.mov,.webm,.m4v,.3gp,image/*,video/*,text/plain,application/pdf"
                >
                <div class="small" style="margin-top:8px;">
                    Optional. Attach screenshots, photos, videos, TXT, LOG, CSV, JSON, or PDF files. Max 100 MB each.
                </div>
                <ul id="selectedFiles" class="small"></ul>
            </div>

            <div class="actions">
                <button type="submit">Save report</button>
            </div>
        </form>
    </div>
</div>

<script>
const detailsBox = document.getElementById('details');
const fromError = <?= $fromError ? 'true' : 'false' ?>;

function browserContextBlock() {
    return '\n\nBrowser/device context:\n' +
        'Report URL: ' + window.location.href + '\n' +
        'Screen: ' + window.screen.width + 'x' + window.screen.height + '\n' +
        'Viewport: ' + window.innerWidth + 'x' + window.innerHeight + '\n' +
        'Online: ' + (navigator.onLine ? 'yes' : 'no') + '\n' +
        'Platform: ' + (navigator.platform || '') + '\n' +
        'Timezone: ' + (Intl.DateTimeFormat().resolvedOptions().timeZone || '') + '\n' +
        'Browser UA: ' + navigator.userAgent + '\n';
}

if (detailsBox && fromError && !detailsBox.value.includes('Browser/device context:')) {
    detailsBox.value += browserContextBlock();
}

document.getElementById('attachments')?.addEventListener('change', function () {
    const list = document.getElementById('selectedFiles');
    list.innerHTML = '';
    Array.from(this.files || []).forEach(function (file) {
        const item = document.createElement('li');
        item.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
        list.appendChild(item);
    });
});
</script>
<?php guidepawFormUx(); ?>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>
</body>
</html>
