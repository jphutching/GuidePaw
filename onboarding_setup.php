<?php
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/app_config.php';
checkLogin();

gpEnsureOnboardingColumns($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = getUserRecord($pdo, $userId) ?: [];
$dogs = getAccessibleDogs($pdo, $userId);
$activeDog = getActiveDog($pdo, $userId);
$csrf = generateCsrfToken();
$previewMode = (string) ($_GET['preview'] ?? '') === '1';
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? 'index.php'));
if ($returnTo === '' || str_starts_with($returnTo, 'http://') || str_starts_with($returnTo, 'https://') || str_starts_with($returnTo, '//')) {
    $returnTo = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    gpMarkOnboardingComplete($pdo, $userId);
    $target = trim((string) ($_POST['return_to'] ?? 'index.php'));
    if ($target === '' || str_starts_with($target, 'http://') || str_starts_with($target, 'https://') || str_starts_with($target, '//')) {
        $target = 'index.php';
    }
    $separator = (strpos($target, '?') === false) ? '?' : '&';
    header('Location: ' . $target . $separator . 'msg=setup_complete');
    exit;
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Setup Walkthrough · <?= h(appName()) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; padding-bottom: 90px; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 18px; }
        .card { background: #fff; border: 1px solid #dfe3ea; border-radius: 18px; padding: 18px; margin: 14px 0; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        .step { border: 1px solid #dbeafe; background: #f8fbff; border-radius: 16px; padding: 14px; height: 100%; }
        .step strong { display: block; margin-bottom: .35rem; }
        .step .meta { color: #6b7280; font-size: .92rem; }
        .step-actions { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .8rem; }
        .btn-primary { background: #0d6efd; border-color: #0d6efd; }
        .btn-outline-secondary { border-color: #cbd5e1; color: #334155; }
        .notice { border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 16px; padding: 1rem; }
        .finish-box { border: 1px dashed #94a3b8; background: #f8fafc; border-radius: 18px; padding: 1rem; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>

<div class="wrap">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h1 class="h3 mb-1">Welcome to GuidePaw</h1>
            <div class="text-muted">This setup page helps a new handler get to the dashboard without hunting through menus.</div>
        </div>
        <?php if ($previewMode): ?>
            <span class="badge text-bg-secondary">Preview</span>
        <?php endif; ?>
    </div>

    <div class="notice mb-3">
        <strong>Quick path</strong>
        <div class="small mt-1">Finish your handler details, add your first dog, then go to the dashboard. You can come back here later from the login flow if you need a refresher.</div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="step h-100">
                <strong>1. Confirm your profile</strong>
                <div class="meta">This keeps your public QR card, lost-dog contact info, and ADA fallback state ready.</div>
                <div class="step-actions">
                    <a class="btn btn-outline-secondary btn-sm" href="handler_profile.php?return_to=<?= h('onboarding_setup.php?preview=1') ?>">Open profile</a>
                </div>
                <div class="small text-muted mt-2">Home state, phone, and email are the main items.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="step h-100">
                <strong>2. Add or review your dog</strong>
                <div class="meta">GuidePaw works around an active dog. Add one if you do not have one yet.</div>
                <div class="step-actions">
                    <a class="btn btn-outline-secondary btn-sm" href="dogs.php">Open dogs</a>
                    <?php if ($activeDog): ?><a class="btn btn-outline-primary btn-sm" href="index.php?set_dog=<?= (int) $activeDog['id'] ?>">Use <?= h($activeDog['name']) ?></a><?php endif; ?>
                </div>
                <div class="small text-muted mt-2"><?php if ($dogs): ?><?= count($dogs) ?> dog<?= count($dogs) === 1 ? '' : 's' ?> available.<?php else: ?>No dogs yet. That is fine for the walkthrough.<?php endif; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="step h-100">
                <strong>3. Open the home screen</strong>
                <div class="meta">The dashboard keeps the quick actions simple: session, log, training, ADA, and alerts.</div>
                <div class="step-actions">
                    <a class="btn btn-outline-primary btn-sm" href="index.php">Go to dashboard</a>
                </div>
                <div class="small text-muted mt-2">Everything else stays in the menu.</div>
            </div>
        </div>
    </div>

    <div class="finish-box mt-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <strong>Ready when you are</strong>
                <div class="small text-muted mt-1">Mark setup complete after you have checked the profile and added a dog. You can always open this page again later.</div>
            </div>
            <form method="post" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                <button class="btn btn-primary">Finish setup</button>
            </form>
        </div>
    </div>

    <?php if (!empty($_GET['required'])): ?>
        <div class="alert alert-warning mt-3 mb-0">GuidePaw opened setup because this account has not finished first-run setup yet.</div>
    <?php endif; ?>
</div>
</body>
</html>
