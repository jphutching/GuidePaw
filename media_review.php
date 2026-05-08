<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/media_reviews.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/feature_flags.php';

checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);

$message = match ($_GET['msg'] ?? '') {
    'saved' => 'Media review saved.',
    default => '',
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $logId = (int) ($_POST['log_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT id
        FROM daily_logs
        WHERE id = ? AND dog_id = ?
          AND media_url IS NOT NULL
          AND (
            user_id = ?
            OR EXISTS (
                SELECT 1 FROM dog_handlers dh
                WHERE dh.dog_id = daily_logs.dog_id
                  AND dh.user_id = ?
                  AND dh.status = 'accepted'
            )
          )
        LIMIT 1
    ");
    $stmt->execute([$logId, $dogId, $userId, $userId]);
    $allowedLog = (int) $stmt->fetchColumn();

    if ($allowedLog > 0 && $canEdit) {
        gpSaveMediaReview($pdo, $userId, $allowedLog, $_POST);
        writeAuditLog($pdo, 'media_review_saved', 'daily_logs', $allowedLog, 'Saved training media review.');
        header('Location: media_review.php?msg=saved');
        exit;
    }

    $message = 'You do not have permission to review that log.';
}

$entries = gpMediaReviewLogEntries($pdo, $userId, $dogId, 12);
$csrf = generateCsrfToken();
$focusLogId = (int) ($_GET['log_id'] ?? 0);
$tableReady = gpMediaReviewTableReady($pdo);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Media Review · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.review-shell{max-width:1040px;margin:0 auto;padding:1rem 1rem 4rem}.hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}.card-soft{border:1px solid rgba(15,23,42,.08);border-radius:18px;box-shadow:0 8px 18px rgba(15,23,42,.08)}.review-grid{display:grid;gap:1rem}.review-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;overflow:hidden}.media-box{border-radius:14px;border:1px solid #dbe3ef;background:#fff;overflow:hidden}.rating-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.rating-grid select,.rating-grid textarea{border-radius:12px}.meta{color:#64748b;font-size:.84rem}.pill{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.74rem;font-weight:900;background:#eef2ff;color:#4338ca}.review-note{border-left:4px solid #0d6efd;background:#eff6ff;border-radius:14px;padding:.85rem}.media-review-summary{display:flex;gap:.5rem;flex-wrap:wrap}.score{font-weight:900;color:#0f172a}.inline-media{max-height:420px;object-fit:contain;background:#000}.jump-target{scroll-margin-top:1rem}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<header class="hero">
    <div class="review-shell px-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw training media review</div>
                <h1 class="mb-2">Media Review</h1>
                <p class="mb-0 opacity-75">Review attached photos, videos, or audio for camera stability, audio clarity, and training value.</p>
            </div>
            <a class="btn btn-light btn-sm" href="view_logs.php">Back to history</a>
        </div>
    </div>
</header>

<main class="review-shell">
    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if (!$tableReady): ?>
        <div class="alert alert-warning">Media review storage has not been deployed in this database yet. The page can load, but reviews cannot be saved until the migration is applied.</div>
    <?php endif; ?>

    <?php if (!$entries): ?>
        <div class="alert alert-info">No media logs are available for this dog yet.</div>
    <?php else: ?>
        <div class="review-grid">
            <?php foreach ($entries as $entry): ?>
                <article class="review-card jump-target" id="log-<?= (int) $entry['id'] ?>">
                    <div class="p-3 p-md-4">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                            <div class="min-w-0">
                                <div class="fw-bold fs-5 text-break"><?= e($entry['location_name']) ?></div>
                                <div class="meta">
                                    <?= e(date('M d, Y g:i A', strtotime((string) $entry['log_date']))) ?>
                                    <?= !empty($entry['location_city_state']) ? ' • ' . e($entry['location_city_state']) : '' ?>
                                    <?= !empty($entry['location_type']) ? ' • ' . e($entry['location_type']) : '' ?>
                                </div>
                                <div class="meta">Handler: <?= e($entry['handler_username']) ?></div>
                            </div>
                            <a class="btn btn-outline-secondary btn-sm" href="view_logs.php#log-<?= (int) $entry['id'] ?>">Open log</a>
                        </div>

                        <?php if (!empty($entry['media_url'])): ?>
                            <div class="media-box mt-3">
                                <?php if ($entry['media_type'] === 'image'): ?>
                                    <img class="w-100 inline-media" src="<?= e($entry['media_url']) ?>" alt="Training media">
                                <?php elseif ($entry['media_type'] === 'video'): ?>
                                    <video class="w-100 inline-media" controls preload="metadata">
                                        <source src="<?= e($entry['media_url']) ?>" type="<?= e($entry['media_mime'] ?: 'video/mp4') ?>">
                                    </video>
                                <?php elseif ($entry['media_type'] === 'audio'): ?>
                                    <div class="p-3">
                                        <audio class="w-100" controls>
                                            <source src="<?= e($entry['media_url']) ?>" type="<?= e($entry['media_mime'] ?: 'audio/mpeg') ?>">
                                        </audio>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($entry['handler_notes'])): ?>
                            <div class="review-note mt-3">
                                <div class="fw-bold mb-1">Session notes</div>
                                <div><?= nl2br(e($entry['handler_notes'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($entry['reviewed_at'])): ?>
                            <div class="media-review-summary mt-3">
                                <span class="pill">Camera <?= (int) $entry['rating_camera_stability'] ?>/5</span>
                                <span class="pill">Audio <?= (int) $entry['rating_audio_clarity'] ?>/5</span>
                                <span class="pill">Value <?= (int) $entry['rating_training_value'] ?>/5</span>
                                <span class="meta">Reviewed <?= e(date('M d, Y g:i A', strtotime((string) $entry['reviewed_at']))) ?></span>
                            </div>
                            <?php if (!empty($entry['review_notes']) || !empty($entry['next_step'])): ?>
                                <div class="mt-2 small">
                                    <?php if (!empty($entry['review_notes'])): ?><div><strong>Notes:</strong> <?= nl2br(e($entry['review_notes'])) ?></div><?php endif; ?>
                                    <?php if (!empty($entry['next_step'])): ?><div class="mt-1"><strong>Next step:</strong> <?= e($entry['next_step']) ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($canEdit && $tableReady): ?>
                            <form method="post" class="mt-4">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="log_id" value="<?= (int) $entry['id'] ?>">
                                <div class="rating-grid">
                                    <div>
                                        <label class="form-label fw-bold">Camera stability</label>
                                        <select class="form-select" name="rating_camera_stability">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?= $i ?>" <?= (int) ($entry['rating_camera_stability'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label fw-bold">Audio clarity</label>
                                        <select class="form-select" name="rating_audio_clarity">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?= $i ?>" <?= (int) ($entry['rating_audio_clarity'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label fw-bold">Training value</label>
                                        <select class="form-select" name="rating_training_value">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?= $i ?>" <?= (int) ($entry['rating_training_value'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label fw-bold">Review notes</label>
                                    <textarea class="form-control" name="review_notes" rows="3" placeholder="What is visible or audible in this clip?"><?= e($entry['review_notes'] ?? '') ?></textarea>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label fw-bold">Next step</label>
                                    <textarea class="form-control" name="next_step" rows="2" placeholder="What should the handler do next?"><?= e($entry['next_step'] ?? '') ?></textarea>
                                </div>
                                <div class="d-grid mt-3">
                                    <button class="btn btn-primary" type="submit">Save review</button>
                                </div>
                            </form>
                        <?php elseif ($canEdit): ?>
                            <div class="mt-3 small text-muted">Review form is unavailable until the media review table exists in this database.</div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
