<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/regression_engine.php';
require_once __DIR__ . '/includes/validation.php';

if (!featureEnabled($pdo, 'regression_engine_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);

$message = match ($_GET['msg'] ?? '') {
    'saved' => 'Regression event updated.',
    default => '',
};
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (!$canEdit) {
        $error = 'You have read-only access for this dog.';
    } elseif (!gpRegressionEngineTableReady($pdo)) {
        $error = 'Regression engine storage has not been deployed yet.';
    } else {
        try {
            $action = trim((string) ($_POST['action'] ?? ''));
            if ($action !== 'update_event') {
                throw new RuntimeException('Unknown regression engine action.');
            }
            $eventId = (int) ($_POST['event_id'] ?? 0);
            gpRegressionEngineUpdateEvent($pdo, $userId, $dogId, $eventId, $_POST);
            writeAuditLog($pdo, 'regression_event_updated', 'regression_events', $eventId, 'Updated regression event status and reset plan.');
            header('Location: regression_engine.php?msg=saved#event-' . $eventId);
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$openEvents = gpRegressionEngineOpenEvents($pdo, $userId, $dogId, 12);
$openCount = gpRegressionEngineOpenCount($pdo, $userId, $dogId);
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
<title>Regression Engine · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.shell{max-width:1040px;margin:0 auto;padding:1rem 1rem 4rem}.hero{background:linear-gradient(135deg,#f59e0b,#0d6efd);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}.event-grid{display:grid;gap:1rem}.event-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;overflow:hidden;box-shadow:0 8px 18px rgba(15,23,42,.08)}.event-note{border-left:4px solid #f59e0b;background:#fff7ed;border-radius:14px;padding:.85rem}.meta{color:#64748b;font-size:.84rem}.pill{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.74rem;font-weight:900;background:#eef2ff;color:#4338ca;margin:.15rem .25rem .15rem 0}.compact-form textarea,.compact-form select{border-radius:12px}.plan-list{display:grid;gap:.55rem}.plan-step{border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:.8rem 1rem;background:#fffdf7}.plan-step strong{display:block}
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
                <div class="small opacity-75">GuidePaw regression tracking</div>
                <h1 class="mb-2">Regression Engine</h1>
                <p class="mb-0 opacity-75">Open regressions stay in the easier lane until the reset plan is reviewed and the dog is stable again.</p>
            </div>
            <a class="btn btn-light btn-sm" href="training_program.php">Back to training</a>
        </div>
    </div>
</header>

<main class="shell">
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$canEdit): ?><div class="alert alert-info">You have read-only access for this dog. Only editors can update regression events.</div><?php endif; ?>

    <section class="event-card mb-3">
        <div class="p-3 p-md-4">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                <div>
                    <div class="fw-bold fs-5 text-break"><?= e($dog['name']) ?></div>
                    <div class="meta"><?= e($dog['breed'] ?: 'Breed not set') ?><?= !empty($dog['weight_lbs']) ? ' • ' . e((string) $dog['weight_lbs']) . ' lbs' : '' ?></div>
                </div>
                <span class="pill">Open regressions: <?= (int) $openCount ?></span>
            </div>
        </div>
    </section>

    <section class="event-card mb-3">
        <div class="p-3 p-md-4">
            <h2 class="h5 mb-3">Reset plan</h2>
            <div class="plan-list">
                <div class="plan-step"><strong>1. Return to the easier step.</strong><span class="small text-muted">Keep the current behavior short, quiet, and predictable until success returns.</span></div>
                <div class="plan-step"><strong>2. Keep reinforcement high.</strong><span class="small text-muted">Use the best reward available for one clean win instead of extending the session.</span></div>
                <div class="plan-step"><strong>3. Re-check the same event before raising difficulty.</strong><span class="small text-muted">If the pattern repeats, keep the plan easy and revisit coach review when needed.</span></div>
            </div>
        </div>
    </section>

    <section class="event-card">
        <div class="p-3 p-md-4">
            <h2 class="h5 mb-3">Open regression events</h2>
            <?php if (!$openEvents): ?>
                <div class="text-muted">No open regression events are queued for this dog yet.</div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($openEvents as $event): ?>
                        <article class="event-card" id="event-<?= (int) $event['id'] ?>">
                            <div class="p-3 p-md-4">
                                <div class="d-flex justify-content-between gap-2 flex-wrap align-items-start">
                                    <div class="min-w-0">
                                        <div class="fw-bold text-break"><?= e($event['detected_reason'] ?: 'Regression event') ?></div>
                                        <div class="meta">
                                            <?= e(date('M d, Y g:i A', strtotime((string) $event['created_at']))) ?>
                                            <?php if (!empty($event['module_title'])): ?> • <?= e($event['module_title']) ?><?php endif; ?>
                                            <?php if (!empty($event['goal_category'])): ?> • <?= e($event['goal_category']) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge text-bg-<?= ($event['status'] ?? 'open') === 'paused_for_review' ? 'warning' : 'secondary' ?>"><?= e((string) ($event['status'] ?? 'open')) ?></span>
                                </div>
                                <?php if (!empty($event['recommended_action'])): ?>
                                    <div class="event-note mt-3"><?= nl2br(e($event['recommended_action'])) ?></div>
                                <?php endif; ?>
                                <form method="post" class="compact-form mt-3">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="update_event">
                                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Status</label>
                                            <select class="form-select" name="status" <?= $canEdit ? '' : 'disabled' ?>>
                                                <?php foreach (['open' => 'Open', 'in_review' => 'In review', 'paused_for_review' => 'Paused for review', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label): ?>
                                                    <option value="<?= e($value) ?>" <?= ($event['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Reset plan</label>
                                            <textarea class="form-control" name="recommended_action" rows="3" placeholder="What should the handler do next?" <?= $canEdit ? '' : 'disabled' ?>><?= e((string) ($event['recommended_action'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                    <?php if ($canEdit): ?>
                                        <div class="mt-3">
                                            <button class="btn btn-warning">Save regression update</button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php guidepawFormUx(); ?>
</body>
</html>
