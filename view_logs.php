<?php
require_once __DIR__ . '/includes/brand_header.php';
require 'includes/db_connect.php';

checkLogin();

$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$canEditLogs = userCanEditDog($pdo, $userId, (int) $dog['id']);

$stmt = $pdo->prepare("
    SELECT dl.*, u.username AS handler_username
    FROM daily_logs dl
    JOIN users u ON u.id = dl.user_id
    WHERE dl.dog_id = ?
      AND (
        dl.user_id = ?
        OR EXISTS (
            SELECT 1 FROM dog_handlers dh
            WHERE dh.dog_id = dl.dog_id
              AND dh.user_id = ?
              AND dh.status = 'accepted'
        )
      )
    ORDER BY dl.log_date DESC
");
$stmt->execute([(int) $dog['id'], $userId, $userId]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
        <div>
            <h1 class="h3 mb-0">📋 Training History</h1>
            <div class="small-muted">Active dog: <?= e($dog['name']) ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="log_entry.php" class="btn btn-primary btn-sm">New Log</a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
        </div>
    </div>

    <?php if (!empty($_GET['status'])): ?>
        <div class="alert alert-success"><?= e(str_replace('_', ' ', (string) $_GET['status'])) ?>.</div>
    <?php endif; ?>

    <?php if (!$canEditLogs): ?>
        <div class="alert alert-info">You have read-only access for this dog. Training logs can be viewed but not edited.</div>
    <?php endif; ?>

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span data-network-status class="badge bg-secondary">Checking...</span>
        <span class="badge bg-dark" data-queue-count style="display:none;">0</span>
        <small class="text-muted">Queued offline logs on this device</small>
        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-sync-queued>Sync queued logs</button>
    </div>

    <?php if (!$logs): ?>
        <div class="alert alert-info">No logs yet for this dog.</div>
    <?php else: ?>
        <div class="vstack gap-3">
            <?php foreach ($logs as $log): ?>
                <article class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="min-w-0">
                                <h2 class="h5 card-title mb-1"><?= e($log['location_name']) ?></h2>
                                <div class="small text-muted">
                                    <?= e(date('M d, Y g:i A', strtotime((string) $log['log_date']))) ?>
                                    <?= !empty($log['location_city_state']) ? ' • ' . e($log['location_city_state']) : '' ?>
                                    <?= !empty($log['location_type']) ? ' • ' . e($log['location_type']) : '' ?>
                                </div>
                                <div class="small text-muted">Handler: <?= e($log['handler_username']) ?></div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <span class="badge bg-primary align-self-start">Focus <?= (int) $log['focus_level'] ?>/5</span>
                                <?php if ($canEditLogs): ?>
                                    <a class="btn btn-outline-primary btn-sm" href="edit_log.php?id=<?= (int) $log['id'] ?>">Edit</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php $skills = json_decode($log['skills_practiced'] ?? '[]', true) ?: []; ?>
                        <?php if ($skills): ?>
                            <div class="mt-3">
                                <?php foreach ($skills as $skill): ?>
                                    <span class="badge text-bg-light border me-1 mb-1"><?= e($skill) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($log['handler_notes'])): ?>
                            <p class="mt-3 mb-2"><?= nl2br(e($log['handler_notes'])) ?></p>
                        <?php endif; ?>

                        <?php if ($log['latitude'] !== null && $log['longitude'] !== null): ?>
                            <div class="small text-muted mb-2">GPS: <?= e((string) $log['latitude']) ?>, <?= e((string) $log['longitude']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($log['media_url'])): ?>
                            <div class="mt-2">
                                <?php if ($log['media_type'] === 'image'): ?>
                                    <img src="<?= e($log['media_url']) ?>" alt="Training media" class="img-fluid rounded border">
                                <?php elseif ($log['media_type'] === 'video'): ?>
                                    <video controls class="w-100 rounded border" preload="metadata"><source src="<?= e($log['media_url']) ?>" type="<?= e($log['media_mime'] ?: 'video/mp4') ?>"></video>
                                <?php elseif ($log['media_type'] === 'audio'): ?>
                                    <audio controls class="w-100"><source src="<?= e($log['media_url']) ?>" type="<?= e($log['media_mime'] ?: 'audio/mpeg') ?>"></audio>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<script src="app.js"></script>
</body>
</html>
