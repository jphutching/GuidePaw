<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/feedback_submission.php';
require_once __DIR__ . '/includes/brand_header.php';
requireAdmin();

gpEnsureFeedbackSourceColumns($pdo);

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedbackId = (int)($_POST['feedback_id'] ?? 0);
    $status = $_POST['status'] ?? 'new';

    if ($feedbackId > 0 && in_array($status, ['new', 'reviewing', 'planned', 'fixed', 'closed'], true)) {
        $stmt = $pdo->prepare("UPDATE feedback_reports SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $feedbackId]);
    }

    header('Location: admin_feedback.php?msg=updated');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$params = [];
$whereSql = '';

if ($statusFilter !== '' && in_array($statusFilter, ['new', 'reviewing', 'planned', 'fixed', 'closed'], true)) {
    $whereSql = 'WHERE r.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.user_id,
        u.username,
        COALESCE(r.category, r.report_type, 'bug') AS category,
        COALESCE(r.page_workflow, r.page_url, '') AS page_workflow,
        COALESCE(r.contact_email, '') AS contact_email,
        COALESCE(r.details, r.description, '') AS details,
        COALESCE(r.status, 'new') AS status,
        COALESCE(r.source_platform, 'web') AS source_platform,
        COALESCE(r.source_label, 'GuidePaw Website') AS source_label,
        COALESCE(r.source_version, '') AS source_version,
        COALESCE(r.source_device, '') AS source_device,
        r.created_at,
        a.id AS attachment_id,
        a.original_name,
        a.stored_path,
        a.mime_type,
        a.file_size
    FROM feedback_reports r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN feedback_attachments a ON a.feedback_id = r.id
    $whereSql
    ORDER BY r.created_at DESC, a.id ASC
    LIMIT 200
");
$stmt->execute($params);
$rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reports = [];
foreach ($rawRows as $row) {
    $id = (int)$row['id'];
    if (!isset($reports[$id])) {
        $reports[$id] = $row;
        $reports[$id]['attachments'] = [];
    }

    if (!empty($row['attachment_id'])) {
        $reports[$id]['attachments'][] = [
            'original_name' => $row['original_name'],
            'stored_path' => $row['stored_path'],
            'mime_type' => $row['mime_type'],
            'file_size' => $row['file_size'],
        ];
    }
}

function formatBytes($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Feedback Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; padding-bottom: 90px; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 18px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .card { background: #fff; border: 1px solid #dfe3ea; border-radius: 18px; padding: 18px; margin: 14px 0; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        .btn, button { display: inline-block; border: 0; border-radius: 12px; padding: 10px 14px; background: #1f2937; color: #fff; text-decoration: none; font-weight: 800; }
        .btn.secondary { background: transparent; color: #6b7280; border: 1px solid #9ca3af; }
        .filters a { margin-right: 8px; margin-bottom: 8px; }
        .meta { color: #6b7280; font-size: .9rem; }
        .badge { display: inline-block; border-radius: 999px; padding: 4px 9px; background: #e5e7eb; font-size: .8rem; font-weight: 800; }
        textarea { width: 100%; min-height: 100px; box-sizing: border-box; }
        select { padding: 8px; border-radius: 8px; }
        .attachments a { display: inline-block; margin: 4px 8px 4px 0; }
        .details { white-space: pre-wrap; border-left: 4px solid #dbeafe; padding-left: 12px; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>

<div class="wrap">
    <div class="top">
        <h1>Feedback Reports</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn secondary" href="admin_feedback_ai.php">AI Issue Assistant</a>
            <a class="btn secondary" href="admin.php">Back to Admin</a>
        </div>
    </div>

    <div class="card filters">
        <a class="btn" href="admin_feedback.php">All</a>
        <?php foreach (['new', 'reviewing', 'planned', 'fixed', 'closed'] as $s): ?>
            <a class="btn secondary" href="admin_feedback.php?status=<?= h($s) ?>"><?= h(ucfirst($s)) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (($_GET['msg'] ?? '') === 'updated'): ?>
        <div class="card"><strong>Updated successfully.</strong></div>
    <?php endif; ?>

    <?php if (!$reports): ?>
        <div class="card">No feedback reports found.</div>
    <?php endif; ?>

    <?php foreach ($reports as $report): ?>
        <div class="card">
            <h2>#<?= h($report['id']) ?> <?= h(ucfirst($report['category'])) ?>: <?= h($report['page_workflow'] ?: 'General') ?></h2>
            <p class="meta">
                User: <?= h($report['username'] ?: ('User #' . $report['user_id'])) ?> |
                Created: <?= h($report['created_at']) ?> |
                Status: <span class="badge"><?= h($report['status']) ?></span> |
                Source: <span class="badge"><?= h(ucfirst($report['source_platform'])) ?></span>
            </p>

            <p class="meta">
                <?= h($report['source_label']) ?>
                <?php if (!empty($report['source_version'])): ?>
                    · v<?= h($report['source_version']) ?>
                <?php endif; ?>
                <?php if (!empty($report['source_device'])): ?>
                    · <?= h($report['source_device']) ?>
                <?php endif; ?>
            </p>

            <?php if (!empty($report['contact_email'])): ?>
                <p class="meta">Contact: <?= h($report['contact_email']) ?></p>
            <?php endif; ?>

            <div class="details"><?= h($report['details']) ?></div>

            <?php if ($report['attachments']): ?>
                <h3>Attachments</h3>
                <div class="attachments">
                    <?php foreach ($report['attachments'] as $att): ?>
                        <a class="btn secondary" href="<?= h($att['stored_path']) ?>" target="_blank" rel="noopener">
                            <?= h($att['original_name']) ?> — <?= h(formatBytes($att['file_size'])) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" style="margin-top:14px;">
                <input type="hidden" name="feedback_id" value="<?= h($report['id']) ?>">
                <label>
                    Status
                    <select name="status">
                        <?php foreach (['new', 'reviewing', 'planned', 'fixed', 'closed'] as $s): ?>
                            <option value="<?= h($s) ?>" <?= $report['status'] === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Update</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>
</body>
</html>
