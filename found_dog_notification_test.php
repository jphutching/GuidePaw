<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/found_dog_reports.php';
checkLogin();

gpEnsureFoundDogReportsTable($pdo);

$userId = (int) $_SESSION['user_id'];
$dogId = isset($_GET['dog_id']) ? (int) $_GET['dog_id'] : getActiveDogId($pdo, $userId);
if (!$dogId || !hasDogAccess($pdo, $userId, $dogId)) {
    die('Dog not found.');
}

$dog = gpFoundDogFetchPublicDog($pdo, $dogId);
if (!$dog) {
    die('Dog not found.');
}

$handlerEmail = trim((string) ($dog['handler_email'] ?? ''));
$ownerEmail = trim((string) ($dog['owner_email'] ?? ''));
$adminEmail = trim((string) gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app')));
$csrf = generateCsrfToken();
$sent = null;
$reportId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $stmt = $pdo->prepare('INSERT INTO found_dog_reports (dog_id, finder_location, finder_name, finder_phone, finder_message, status) VALUES (?, ?, ?, ?, ?, ?) RETURNING id');
    $stmt->execute([
        $dogId,
        'GuidePaw test found-dog location report',
        'GuidePaw Test',
        '000-000-0000',
        'This is a test found-dog location alert from the dog profile notification test page.',
        'closed',
    ]);
    $reportId = (int) $stmt->fetchColumn();
    $sent = gpNotifyFoundDogReport($pdo, $reportId);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Found Dog Alert Test · GuidePaw</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.cardx{border:1px solid rgba(15,23,42,.08);border-radius:20px;box-shadow:0 8px 20px rgba(15,23,42,.07)}
.route-row{display:flex;justify-content:space-between;gap:1rem;border-top:1px solid #e5e7eb;padding:.75rem 0}.route-row:first-child{border-top:0}.status-ok{color:#166534;font-weight:800}.status-warn{color:#92400e;font-weight:800}
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-0">📍 Found Dog Alert Test</h1><div class="text-muted small"><?= e($dog['name'] ?? 'Dog') ?></div></div>
        <a href="dog_profile.php?dog_id=<?= (int) $dogId ?>" class="btn btn-outline-secondary btn-sm">Dog Profile</a>
    </div>

    <?php if ($sent !== null): ?>
        <div class="alert <?= $sent ? 'alert-success' : 'alert-warning' ?>">
            <?= $sent ? 'Test found-dog alert sent or queued successfully.' : 'A test report was saved, but no notification channel reported success. Check email/Telegram environment settings.' ?>
            <?php if ($reportId): ?> Report #<?= (int) $reportId ?>.<?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="card cardx mb-3"><div class="card-body">
        <h2 class="h5">Notification route</h2>
        <p class="text-muted small">A real QR location report uses these destinations to reunite the dog with the handler.</p>
        <div class="route-row"><span>Dog handler email</span><span class="<?= $handlerEmail !== '' ? 'status-ok' : 'status-warn' ?>"><?= $handlerEmail !== '' ? e($handlerEmail) : 'Missing' ?></span></div>
        <div class="route-row"><span>Dog owner account email</span><span class="<?= $ownerEmail !== '' ? 'status-ok' : 'status-warn' ?>"><?= $ownerEmail !== '' ? e($ownerEmail) : 'Missing' ?></span></div>
        <div class="route-row"><span>Admin fallback email</span><span class="<?= $adminEmail !== '' ? 'status-ok' : 'status-warn' ?>"><?= $adminEmail !== '' ? e($adminEmail) : 'Missing' ?></span></div>
        <div class="route-row"><span>Telegram</span><span class="<?= gpFoundDogFlag('FOUND_DOG_NOTIFY_TELEGRAM_ENABLED', gpFoundDogFlag('BETA_NOTIFY_TELEGRAM_ENABLED', false)) ? 'status-ok' : 'status-warn' ?>"><?= gpFoundDogFlag('FOUND_DOG_NOTIFY_TELEGRAM_ENABLED', gpFoundDogFlag('BETA_NOTIFY_TELEGRAM_ENABLED', false)) ? 'Enabled if bot token/chat ID are set' : 'Disabled' ?></span></div>
    </div></section>

    <?php if ($handlerEmail === ''): ?>
        <div class="alert alert-warning">Add a handler email on the Dog Profile so the actual handler receives found-dog location reports directly.</div>
    <?php endif; ?>

    <section class="card cardx"><div class="card-body">
        <h2 class="h5">Send test alert</h2>
        <p class="text-muted">This creates a closed test found-dog report and sends it through the same notification helper as the public QR report form.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button class="btn btn-primary w-100">Send Test Found-Dog Alert</button>
        </form>
    </div></section>
</main>
</body>
</html>
