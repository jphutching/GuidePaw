<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feedback_triage.php';
requireAdmin();

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$feedbackRows = gpFeedbackTriageRows($pdo, 25);
if ($feedbackId <= 0 && $feedbackRows) {
    foreach ($feedbackRows as $row) {
        if (in_array((string)($row['status'] ?? 'new'), ['new', 'reviewing', 'planned'], true)) {
            $feedbackId = (int) $row['id'];
            break;
        }
    }
    if ($feedbackId <= 0) {
        $feedbackId = (int) ($feedbackRows[0]['id'] ?? 0);
    }
}

$selected = null;
foreach ($feedbackRows as $row) {
    if ((int) $row['id'] === $feedbackId) {
        $selected = $row;
        break;
    }
}

$analysis = $selected ? gpFeedbackTriageAnalyze($selected) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AI Issue Assistant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; padding-bottom: 90px; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 18px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .card { background: #fff; border: 1px solid #dfe3ea; border-radius: 18px; padding: 18px; margin: 14px 0; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        .grid { display: grid; gap: 14px; }
        @media (min-width: 900px) { .grid { grid-template-columns: 1fr 1fr; } }
        .btn { display: inline-block; border: 0; border-radius: 12px; padding: 10px 14px; background: #1f2937; color: #fff; text-decoration: none; font-weight: 800; }
        .btn.secondary { background: transparent; color: #6b7280; border: 1px solid #9ca3af; }
        .badge { display: inline-block; border-radius: 999px; padding: 4px 10px; background: #e5e7eb; font-size: .8rem; font-weight: 800; }
        .muted { color: #6b7280; }
        .tip { border-left: 4px solid #0d6efd; background: #eff6ff; border-radius: 14px; padding: .9rem; margin-top: .75rem; }
        .draft { white-space: pre-wrap; border: 1px dashed #94a3b8; background: #f8fafc; border-radius: 14px; padding: .9rem; }
        select { padding: 10px; border-radius: 10px; border: 1px solid #cbd5e1; width: 100%; max-width: 480px; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<div class="wrap">
    <div class="top">
        <div>
            <h1>AI Issue Assistant</h1>
            <div class="muted">Bounded triage for feedback reports. It recommends a status and next steps without auto-changing anything.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn secondary" href="admin_feedback.php">Back to Feedback</a>
            <a class="btn secondary" href="admin.php">Admin Home</a>
        </div>
    </div>

    <div class="card">
        <form method="get" class="d-flex flex-wrap gap-3 align-items-end">
            <div>
                <label for="feedback_id" class="form-label fw-bold">Feedback item</label><br>
                <select id="feedback_id" name="feedback_id">
                    <?php foreach ($feedbackRows as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) $row['id'] === $feedbackId ? 'selected' : '' ?>>
                            #<?= (int) $row['id'] ?> <?= h(ucfirst((string) $row['category'])) ?> - <?= h((string) $row['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit">Analyze</button>
        </form>
    </div>

    <?php if (!$selected || !$analysis): ?>
        <div class="card">No feedback item selected.</div>
    <?php else: ?>
        <div class="grid">
            <div class="card">
                <div class="badge">#<?= (int) $selected['id'] ?> <?= h(ucfirst((string) $selected['status'])) ?></div>
                <h2 class="mt-3 mb-2"><?= h((string) $selected['title']) ?></h2>
                <div class="muted mb-2">
                    Area: <?= h($analysis['area']) ?> ·
                    Source: <?= h(ucfirst((string) ($selected['source_platform'] ?? 'web'))) ?>
                    <?php if (!empty($selected['source_label'])): ?>
                        · <?= h((string) $selected['source_label']) ?>
                    <?php endif; ?>
                </div>
                <div><strong>Signals</strong></div>
                <?php foreach ($analysis['signals'] as $signal): ?>
                    <div class="tip"><?= h($signal) ?></div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="badge">Recommended status: <?= h($analysis['recommended_status']) ?></div>
                <h2 class="mt-3 mb-2">Next steps</h2>
                <?php foreach ($analysis['next_steps'] as $step): ?>
                    <div class="tip"><?= h($step) ?></div>
                <?php endforeach; ?>
                <h2 class="mt-4 mb-2">Draft reply</h2>
                <div class="draft"><?= h($analysis['draft_reply']) ?></div>
            </div>
        </div>

        <div class="card">
            <strong>Feedback text</strong>
            <div class="muted mt-1"><?= h((string) $selected['page_workflow']) ?></div>
            <div class="mt-2" style="white-space: pre-wrap;"><?= h((string) $selected['description']) ?></div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
