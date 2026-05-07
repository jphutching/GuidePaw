<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once __DIR__ . '/includes/roles.php';
checkLogin();

if (!currentUserIsAdmin()) {
    http_response_code(403);
    die('Admin only.');
}

gpEnsureRequiredHandlerProfileColumns($pdo);
$pdo->exec("UPDATE users SET backup_contact_name = COALESCE(NULLIF(TRIM(backup_contact_name), ''), 'Optional backup contact'), backup_contact_phone = COALESCE(NULLIF(TRIM(backup_contact_phone), ''), 'Optional backup phone') WHERE COALESCE(NULLIF(TRIM(backup_contact_name), ''), '') = '' OR COALESCE(NULLIF(TRIM(backup_contact_phone), ''), '') = ''");

$requiredFields = [
    'display_name' => 'Display name',
    'phone' => 'Public phone',
    'public_email' => 'Public email',
];

$totalAccounts = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$missingSql = "SELECT id, username, email, display_name, phone, public_email, backup_contact_name, backup_contact_phone,
    CONCAT_WS(', ',
        CASE WHEN COALESCE(NULLIF(TRIM(display_name), ''), '') = '' THEN 'Display name' END,
        CASE WHEN COALESCE(NULLIF(TRIM(phone), ''), '') = '' THEN 'Public phone' END,
        CASE WHEN COALESCE(NULLIF(TRIM(public_email), ''), '') = '' THEN 'Public email' END,
        CASE WHEN COALESCE(NULLIF(TRIM(public_email), ''), '') <> '' AND public_email !~* '^[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}$' THEN 'Valid public email' END
    ) AS missing_fields
    FROM users
    WHERE COALESCE(NULLIF(TRIM(display_name), ''), '') = ''
       OR COALESCE(NULLIF(TRIM(phone), ''), '') = ''
       OR COALESCE(NULLIF(TRIM(public_email), ''), '') = ''
       OR (COALESCE(NULLIF(TRIM(public_email), ''), '') <> '' AND public_email !~* '^[A-Z0-9._%+-]+@[A-Z]{2,}$')
    ORDER BY username ASC, id ASC";
$missingRows = $pdo->query($missingSql)->fetchAll() ?: [];
$missingCount = count($missingRows);
$completeCount = max(0, $totalAccounts - $missingCount);
$optionalBackupMissing = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE COALESCE(NULLIF(TRIM(backup_contact_name), ''), '') = '' OR COALESCE(NULLIF(TRIM(backup_contact_phone), ''), '') = ''")->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile Completion · GuidePaw Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.metric{border-radius:18px;border:1px solid rgba(15,23,42,.08);box-shadow:0 6px 18px rgba(15,23,42,.06)}
.metric .num{font-size:2.2rem;font-weight:900;line-height:1}.table-card{border-radius:18px;overflow:hidden;border:1px solid rgba(15,23,42,.08)}
</style>
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Handler Profile Completion</h1>
            <div class="text-muted small">Admin report for required reusable QR/found-dog contact fields.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="admin.php">Admin</a>
    </div>

    <div class="alert alert-success small">Optional backup contact blanks were backfilled automatically for compatibility with the current login gate.</div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card metric"><div class="card-body"><div class="text-muted small">Total accounts</div><div class="num"><?= (int) $totalAccounts ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card metric"><div class="card-body"><div class="text-muted small">Complete</div><div class="num text-success"><?= (int) $completeCount ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card metric"><div class="card-body"><div class="text-muted small">Missing required</div><div class="num text-danger"><?= (int) $missingCount ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card metric"><div class="card-body"><div class="text-muted small">Missing optional backup</div><div class="num text-secondary"><?= (int) $optionalBackupMissing ?></div></div></div></div>
    </div>

    <div class="alert alert-info small">
        Required fields: <?= e(implode(', ', array_values($requiredFields))) ?>. Backup contact fields are optional and do not block login.
    </div>

    <section class="card table-card">
        <div class="card-body">
            <h2 class="h5">Accounts missing required fields</h2>
            <?php if (!$missingRows): ?>
                <div class="alert alert-success mb-0">No accounts are missing required handler profile fields.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Missing</th></tr></thead>
                        <tbody>
                            <?php foreach ($missingRows as $row): ?>
                                <tr>
                                    <td><?= (int) $row['id'] ?></td>
                                    <td><?= e($row['username'] ?? '') ?></td>
                                    <td><?= e($row['email'] ?? '') ?></td>
                                    <td><?= e($row['missing_fields'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script src="app.js"></script>
</body>
</html>
