<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/trucking_mode.php';
require_once __DIR__ . '/includes/validation.php';

if (!featureEnabled($pdo, 'trucking_mode_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$current = gpTruckingModeState($userId, $dogId);
$options = gpTruckingModeOptions();

$message = ($_GET['msg'] ?? '') === 'saved' ? 'Trucking mode saved.' : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $mode = (string) ($_POST['mode'] ?? gpTruckingModeDefault());
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $current = gpTruckingModeSaveState($userId, $dogId, $mode, $notes);
    writeAuditLog($pdo, 'trucking_mode_saved', 'dogs', $dogId, 'Saved trucking mode for active dog.');
    header('Location: trucking_mode.php?msg=saved');
    exit;
}

$plan = gpTruckingModePlan((string) $current['mode']);
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
<title>Trucking Mode · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.wrap{max-width:1060px;margin:0 auto;padding:1rem 1rem 4rem}.hero{background:linear-gradient(135deg,#0d6efd,#b45309);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}.mode-grid{display:grid;gap:1rem}.mode-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;overflow:hidden;box-shadow:0 8px 18px rgba(15,23,42,.08)}.mode-choicelist{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.mode-choice{display:flex;align-items:flex-start;gap:.75rem;border:1px solid rgba(15,23,42,.1);border-radius:16px;padding:.85rem;background:#fff;cursor:pointer}.mode-choice input{margin-top:.25rem}.mode-pill{display:inline-flex;align-items:center;padding:.22rem .6rem;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:.76rem;font-weight:900}.mode-note{border-left:4px solid #0d6efd;background:#eff6ff;border-radius:14px;padding:.9rem}.minor{color:#64748b;font-size:.9rem}
@media (max-width: 720px){.mode-choicelist{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<header class="hero">
    <div class="wrap px-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw trucking-day planning</div>
                <h1 class="mb-2">Trucking Mode</h1>
                <p class="mb-0 opacity-75">Tune the day around driving, weather, fatigue, and stress without overloading the dog.</p>
            </div>
            <a class="btn btn-light btn-sm" href="training_program.php">Back to training</a>
        </div>
    </div>
</header>

<main class="wrap">
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="mode-grid">
        <section class="mode-card">
            <div class="p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="mode-pill"><?= e(gpTruckingModeDashboardLabel($current)) ?></div>
                        <h2 class="h4 mt-2 mb-1"><?= e($dog['name']) ?></h2>
                        <div class="minor">Current mode is stored per dog in this session so you can switch based on the day.</div>
                    </div>
                    <a class="btn btn-outline-secondary btn-sm" href="index.php#today">Dashboard</a>
                </div>

                <form method="post" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="mode-choicelist">
                        <?php foreach ($options as $key => $option): ?>
                            <label class="mode-choice">
                                <input type="radio" name="mode" value="<?= e($key) ?>" <?= $current['mode'] === $key ? 'checked' : '' ?>>
                                <div>
                                    <div class="fw-bold"><?= e($option['icon'] . ' ' . $option['label']) ?></div>
                                    <div class="minor"><?= e($option['summary']) ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold">Notes for this dog and route</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Low fuel, long haul, bad weather, motel night, or other constraints"><?= e($current['notes']) ?></textarea>
                    </div>

                    <div class="d-grid mt-3">
                        <button class="btn btn-primary" type="submit">Save trucking mode</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="mode-card">
            <div class="p-3 p-md-4">
                <h2 class="h5 mb-2">Plan for <?= e($plan['label']) ?></h2>
                <div class="mode-note">
                    <div class="fw-bold mb-1">Session length</div>
                    <div><?= e($plan['session_length']) ?></div>
                </div>
                <div class="mode-note mt-3">
                    <div class="fw-bold mb-1">Priority</div>
                    <div><?= e($plan['priority']) ?></div>
                </div>
                <div class="mode-note mt-3">
                    <div class="fw-bold mb-1">Avoid</div>
                    <div><?= e($plan['avoid']) ?></div>
                </div>
                <div class="mode-note mt-3">
                    <div class="fw-bold mb-1">Next step</div>
                    <div><?= e($plan['next_step']) ?></div>
                </div>
                <?php if (!empty($current['notes'])): ?>
                    <div class="mt-3 small text-muted">
                        <strong>Saved notes:</strong> <?= nl2br(e($current['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>
