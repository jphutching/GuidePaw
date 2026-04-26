<?php
require_once 'includes/db_connect.php';
require_once 'includes/app_config.php';
checkLogin();

$userStmt = $pdo->prepare('SELECT username, dog_name, breed FROM users WHERE id = ?');
$userStmt->execute([(int) $_SESSION['user_id']]);
$user = $userStmt->fetch();

$dogCountStmt = $pdo->prepare('SELECT COUNT(*) FROM dogs WHERE owner_user_id = ?');
$dogCountStmt->execute([(int) $_SESSION['user_id']]);
$dogCount = (int) $dogCountStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<div class="container py-4" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">💾 Backup & Restore</h2>
            <small class="text-muted">
                <?= e($user['username'] ?? 'Handler') ?> • <?= $dogCount ?> owned dog<?= $dogCount === 1 ? '' : 's' ?> included in full backups
            </small>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title">JSON Backup</h5>
            <p class="text-muted">Structured export of your owned dogs, profiles, collaborators by username, vet contacts, appointments, dog documents, and training logs.</p>
            <a href="export_backup.php?format=json" class="btn btn-primary">Download JSON Backup</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title">CSV Log Export</h5>
            <p class="text-muted">Best for Excel, Sheets, inspections, or reviewing training history across all of your owned dogs.</p>
            <a href="export_backup.php?format=csv" class="btn btn-outline-primary">Download CSV Export</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3 border-primary">
        <div class="card-body">
            <h5 class="card-title">Full Backup Package</h5>
            <p class="text-muted">Creates a zip with <code>backup.json</code> plus packaged training media and dog documents that still exist in <code>/uploads/</code>.</p>
            <a href="export_backup.php?format=package" class="btn btn-success">Download Full Package (.zip)</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3" id="restore">
        <div class="card-body">
            <h5 class="card-title">Restore Backup</h5>
            <p class="text-muted">Supports JSON backups and full backup packages. Merge adds dogs that do not already exist by name under your account. Replace clears your currently owned dogs and restores from the backup.</p>
            <form action="import_backup.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <div class="mb-3">
                    <label class="form-label">Backup file</label>
                    <input type="file" name="backup_file" class="form-control" accept="application/json,.json,application/zip,.zip" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Restore mode</label>
                    <select name="restore_mode" class="form-select">
                        <option value="merge">Merge into current account</option>
                        <option value="replace">Replace my owned dogs and restore from backup</option>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="importProfile" name="import_profile" checked>
                    <label class="form-check-label" for="importProfile">Also refresh my legacy handler profile fields from the first restored dog</label>
                </div>
                <div class="alert alert-warning small mb-0">
                    Replace mode removes dogs you own before restoring. Shared dogs owned by other handlers are not removed. Collaborator links restore only when those usernames already exist on this install.
                </div>
                <button type="submit" class="btn btn-primary mt-3">Restore Backup</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title">What is included now</h5>
            <ul class="mb-0">
                <li>Owned dog profiles with birthday or approximate age fields</li>
                <li>Collaborator usernames and permission levels</li>
                <li>Vet contacts, appointments, dog documents, and training logs</li>
                <li>GPS coordinates, media metadata, and packaged files in full zip backups</li>
            </ul>
        </div>
    </div>
</div>
<script src="app.js"></script>
</body>
</html>
