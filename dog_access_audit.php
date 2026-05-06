<?php
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once 'includes/db_connect.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dogId = (int) ($_GET['dog_id'] ?? getActiveDogId($pdo, $userId) ?? 0);

function gpAuditTableExists(PDO $pdo): bool
{
    return tableExists($pdo, 'dog_access_audit_events');
}

function gpAuditFetchDog(PDO $pdo, int $dogId, int $userId): ?array
{
    if ($dogId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT d.*, u.username AS owner_username
        FROM dogs d
        JOIN users u ON u.id = d.owner_user_id
        LEFT JOIN dog_handlers dh ON dh.dog_id = d.id AND dh.user_id = ? AND dh.status = 'accepted'
        WHERE d.id = ? AND (d.owner_user_id = ? OR dh.id IS NOT NULL OR ? = 1)
        LIMIT 1");
    $stmt->execute([$userId, $dogId, $userId, !empty($_SESSION['is_admin']) ? 1 : 0]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    return $dog ?: null;
}

function gpAuditFetchEvents(PDO $pdo, int $dogId): array
{
    if (!gpAuditTableExists($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT ev.*, actor.username AS actor_username, actor.display_name AS actor_display_name, target.username AS target_username, target.display_name AS target_display_name
        FROM dog_access_audit_events ev
        LEFT JOIN users actor ON actor.id = ev.actor_user_id
        LEFT JOIN users target ON target.id = ev.target_user_id
        WHERE ev.dog_id = ?
        ORDER BY ev.created_at DESC, ev.id DESC
        LIMIT 100");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpAuditEventBadge(string $type): string
{
    return match ($type) {
        'dog_owner_changed' => 'Ownership',
        'dog_transfer_requested', 'dog_transfer_status_changed' => 'Transfer',
        'dog_handler_access_added', 'dog_handler_access_changed' => 'Shared Access',
        'dog_status_changed' => 'Status',
        default => 'Audit',
    };
}

$dog = gpAuditFetchDog($pdo, $dogId, $userId);
$events = $dog ? gpAuditFetchEvents($pdo, (int) $dog['id']) : [];
$tableReady = gpAuditTableExists($pdo);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dog Audit Trail · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.audit-card{border-radius:20px;border:1px solid rgba(15,23,42,.08);box-shadow:0 8px 20px rgba(15,23,42,.07)}
.audit-event{border-left:4px solid #0d6efd;background:#fff;border-radius:16px;padding:1rem;margin-bottom:.75rem;box-shadow:0 5px 14px rgba(15,23,42,.06)}
.audit-meta{font-size:.82rem;color:#64748b}.audit-badge{font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;font-weight:900}.audit-values{font-size:.85rem;background:#f8fafc;border-radius:12px;padding:.65rem;margin-top:.6rem;}
</style>
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Dog Audit Trail</h1>
            <div class="text-muted small">Ownership, co-op access, transfer, and lifecycle history.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?= $dog ? 'dog_access.php?dog_id=' . (int) $dog['id'] : 'dogs.php' ?>">Back</a>
    </div>

    <?php if (!$tableReady): ?>
        <div class="alert alert-warning">Audit table is not ready yet. It should appear after migrations run on Render.</div>
    <?php elseif (!$dog): ?>
        <div class="card audit-card"><div class="card-body"><p class="text-muted mb-0">No accessible dog profile selected.</p></div></div>
    <?php else: ?>
        <section class="card audit-card mb-3"><div class="card-body">
            <h2 class="h5 mb-1"><?= e($dog['name']) ?></h2>
            <div class="text-muted small">Current owner: <?= e($dog['owner_username'] ?? 'unknown') ?> · Status: <?= e($dog['lifecycle_status'] ?? 'active') ?></div>
        </div></section>

        <?php if (!$events): ?>
            <div class="alert alert-info">No audit events have been recorded for this dog yet. New status, access, and transfer changes will appear here.</div>
        <?php else: ?>
            <section class="mb-4">
                <?php foreach ($events as $event): ?>
                    <article class="audit-event">
                        <div class="d-flex justify-content-between gap-2 flex-wrap align-items-start">
                            <div>
                                <span class="badge text-bg-primary audit-badge"><?= e(gpAuditEventBadge((string) $event['event_type'])) ?></span>
                                <h2 class="h6 mt-2 mb-1"><?= e($event['event_summary']) ?></h2>
                            </div>
                            <div class="audit-meta"><?= e(date('M j, Y g:i A', strtotime((string) $event['created_at']))) ?></div>
                        </div>
                        <div class="audit-meta mt-1">
                            Actor: <?= e(($event['actor_display_name'] ?? '') ?: ($event['actor_username'] ?? 'system')) ?>
                            <?php if (!empty($event['target_user_id'])): ?>
                                · Target: <?= e(($event['target_display_name'] ?? '') ?: ($event['target_username'] ?? 'unknown')) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($event['old_value']) || !empty($event['new_value'])): ?>
                            <div class="audit-values">
                                <?php if (!empty($event['old_value'])): ?><div><strong>Before:</strong> <?= e($event['old_value']) ?></div><?php endif; ?>
                                <?php if (!empty($event['new_value'])): ?><div><strong>After:</strong> <?= e($event['new_value']) ?></div><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php guidepawFormUx(); ?>
<script src="app.js"></script>
</body>
</html>
