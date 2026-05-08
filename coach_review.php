<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/coach_reviews.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/validation.php';

if (!featureEnabled($pdo, 'coach_review_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$tableReady = gpCoachReviewTableReady($pdo);

$message = match ($_GET['msg'] ?? '') {
    'saved' => 'Coach review saved.',
    'created' => 'Coach review queued.',
    default => '',
};

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (!$canEdit) {
        $error = 'You have read-only access for this dog.';
    } elseif (!$tableReady) {
        $error = 'Coach review storage has not been deployed yet.';
    } else {
        try {
            $action = trim((string) ($_POST['action'] ?? ''));
            if ($action === 'create_from_event') {
                $reviewId = gpCoachReviewCreateFromRegressionEvent($pdo, $userId, $dogId, (int) ($_POST['event_id'] ?? 0), $_POST);
                writeAuditLog($pdo, 'coach_review_created', 'coach_reviews', $reviewId, 'Queued coach review from regression event.');
                header('Location: coach_review.php?msg=created#review-' . $reviewId);
                exit;
            }
            if ($action === 'update_review') {
                $reviewId = (int) ($_POST['review_id'] ?? 0);
                gpCoachReviewUpdate($pdo, $userId, $dogId, $reviewId, $_POST);
                writeAuditLog($pdo, 'coach_review_updated', 'coach_reviews', $reviewId, 'Updated coach review status and notes.');
                header('Location: coach_review.php?msg=saved#review-' . $reviewId);
                exit;
            }
            throw new RuntimeException('Unknown coach review action.');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$queue = gpCoachReviewQueue($pdo, $userId, $dogId, 12);
$events = gpCoachReviewRegressionEvents($pdo, $userId, $dogId, 8);
$csrf = generateCsrfToken();

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Coach Review · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.shell{max-width:1040px;margin:0 auto;padding:1rem 1rem 4rem}.hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}.review-grid{display:grid;gap:1rem}.review-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;overflow:hidden;box-shadow:0 8px 18px rgba(15,23,42,.08)}.review-note{border-left:4px solid #0d6efd;background:#eff6ff;border-radius:14px;padding:.85rem}.meta{color:#64748b;font-size:.84rem}.pill{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.74rem;font-weight:900;background:#eef2ff;color:#4338ca;margin:.15rem .25rem .15rem 0}.priority-high{border-left:5px solid #dc3545}.priority-normal{border-left:5px solid #0d6efd}.priority-low{border-left:5px solid #64748b}.compact-form textarea,.compact-form select{border-radius:12px}.event-grid{display:grid;gap:.75rem}.event-card{border:1px solid rgba(15,23,42,.08);border-radius:16px;background:#fff;padding:1rem}.event-title{font-weight:900}.event-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem}.event-actions .btn{white-space:normal}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<header class="hero">
    <div class="shell px-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw training review queue</div>
                <h1 class="mb-2">Coach Review</h1>
                <p class="mb-0 opacity-75">Route training regressions and safety concerns into a review queue with notes and follow-up status.</p>
            </div>
            <a class="btn btn-light btn-sm" href="training_program.php">Back to training</a>
        </div>
    </div>
</header>

<main class="shell">
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$tableReady): ?><div class="alert alert-warning">Coach review storage has not been deployed in this database yet.</div><?php endif; ?>
    <?php if (!$canEdit): ?><div class="alert alert-info">You have read-only access for this dog. You can view the queue, but only editors can create or update reviews.</div><?php endif; ?>

    <section class="review-card mb-3">
        <div class="p-3 p-md-4">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                <div>
                    <div class="fw-bold fs-5 text-break"><?= e($dog['name']) ?></div>
                    <div class="meta"><?= e($dog['breed'] ?: 'Breed not set') ?><?= !empty($dog['weight_lbs']) ? ' • ' . e((string) $dog['weight_lbs']) . ' lbs' : '' ?></div>
                </div>
                <span class="pill">Open reviews: <?= count(array_filter($queue, static fn($row) => ($row['status'] ?? 'open') === 'open')) ?></span>
            </div>
        </div>
    </section>

    <section class="review-card mb-3">
        <div class="p-3 p-md-4">
            <h2 class="h5 mb-3">Open regression events</h2>
            <?php if (!$events): ?>
                <div class="text-muted">No regression events are queued for this dog yet.</div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <article class="event-card" id="event-<?= (int) $event['id'] ?>">
                            <div class="d-flex justify-content-between gap-2 flex-wrap align-items-start">
                                <div class="min-w-0">
                                    <div class="event-title"><?= e($event['detected_reason'] ?: 'Regression event') ?></div>
                                    <div class="meta">
                                        <?= e(date('M d, Y g:i A', strtotime((string) $event['created_at']))) ?>
                                        <?php if (!empty($event['module_title'])): ?> • <?= e($event['module_title']) ?><?php endif; ?>
                                        <?php if (!empty($event['goal_category'])): ?> • <?= e($event['goal_category']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge text-bg-<?= ($event['status'] ?? 'open') === 'paused_for_review' ? 'warning' : 'secondary' ?>"><?= e((string) ($event['status'] ?? 'open')) ?></span>
                            </div>
                            <?php if (!empty($event['recommended_action'])): ?>
                                <div class="review-note mt-3"><?= nl2br(e($event['recommended_action'])) ?></div>
                            <?php endif; ?>
                            <form method="post" class="compact-form mt-3">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="create_from_event">
                                <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Review type</label>
                                        <input class="form-control" name="review_type" value="<?= e(($event['status'] ?? '') === 'paused_for_review' ? 'safety_review' : 'coach_review') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Priority</label>
                                        <select class="form-select" name="priority">
                                            <option value="normal" selected>Normal</option>
                                            <option value="high">High</option>
                                            <option value="low">Low</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="open" selected>Open</option>
                                            <option value="in_review">In review</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Reason</label>
                                        <textarea class="form-control" name="reason" rows="2" placeholder="Why does this need review?"><?= e((string) ($event['detected_reason'] ?? '')) ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Coach notes</label>
                                        <textarea class="form-control" name="coach_notes" rows="3" placeholder="What should the handler do next?"></textarea>
                                    </div>
                                </div>
                                <div class="event-actions">
                                    <button class="btn btn-primary">Queue coach review</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="review-card">
        <div class="p-3 p-md-4">
            <h2 class="h5 mb-3">Coach review queue</h2>
            <?php if (!$queue): ?>
                <div class="text-muted">No coach reviews yet. Create one from a regression event above.</div>
            <?php else: ?>
                <div class="review-grid">
                    <?php foreach ($queue as $review): ?>
                        <article class="event-card" id="review-<?= (int) $review['id'] ?>">
                            <div class="d-flex justify-content-between gap-2 flex-wrap align-items-start">
                                <div class="min-w-0">
                                    <div class="event-title"><?= e($review['review_type'] ?: 'coach_review') ?></div>
                                    <div class="meta">
                                        Created <?= e(date('M d, Y g:i A', strtotime((string) $review['created_at']))) ?>
                                        <?php if (!empty($review['session_status'])): ?> • Session <?= e($review['session_status']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="pill"><?= e($review['status'] ?: 'open') ?></span>
                                    <span class="pill"><?= e($review['priority'] ?: 'normal') ?></span>
                                </div>
                            </div>
                            <?php if (!empty($review['reason'])): ?><div class="review-note mt-3"><strong>Reason:</strong> <?= nl2br(e($review['reason'])) ?></div><?php endif; ?>
                            <?php if (!empty($review['session_context']) || !empty($review['session_notes'])): ?>
                                <div class="mt-3 small text-muted">
                                    <?php if (!empty($review['session_context'])): ?><div><strong>Session context:</strong> <?= e($review['session_context']) ?></div><?php endif; ?>
                                    <?php if (!empty($review['session_notes'])): ?><div><strong>Session notes:</strong> <?= nl2br(e($review['session_notes'])) ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <form method="post" class="compact-form mt-3">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="update_review">
                                <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Status</label>
                                        <select class="form-select" name="status">
                                            <?php foreach (['open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= ($review['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Priority</label>
                                        <select class="form-select" name="priority">
                                            <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= ($review['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Coach notes</label>
                                        <textarea class="form-control" name="coach_notes" rows="3" placeholder="What should the handler do next?"><?= e((string) ($review['coach_notes'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                                <div class="event-actions">
                                    <button class="btn btn-primary">Save review</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
