<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/dog_access_notifications.php';
require_once 'includes/app_config.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (in_array(($_POST['action'] ?? ''), ['accept_dog_access_invite', 'decline_dog_access_invite'], true)) {
        $handlerId = (int) ($_POST['handler_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT dh.*, d.name AS dog_name, d.owner_user_id, owner.username AS owner_username, owner.display_name AS owner_display_name, owner.public_email AS owner_public_email, owner.email AS owner_email
            FROM dog_handlers dh
            JOIN dogs d ON d.id = dh.dog_id
            JOIN users owner ON owner.id = d.owner_user_id
            WHERE dh.id = ? AND dh.user_id = ? AND dh.status = 'pending'
            LIMIT 1");
        $stmt->execute([$handlerId, $userId]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            $errors[] = 'Invite was not found or is no longer pending.';
        } else {
            $dog = gpDogAccessFetchDogById($pdo, (int) $invite['dog_id']) ?: ['id' => (int) $invite['dog_id'], 'name' => (string) ($invite['dog_name'] ?? 'Dog')];
            $owner = gpDogAccessFetchUserById($pdo, (int) ($invite['invited_by_user_id'] ?? 0)) ?: [
                'id' => (int) ($invite['owner_user_id'] ?? 0),
                'username' => (string) ($invite['owner_username'] ?? ''),
                'display_name' => (string) ($invite['owner_display_name'] ?? ''),
                'public_email' => (string) ($invite['owner_public_email'] ?? ''),
                'email' => (string) ($invite['owner_email'] ?? ''),
            ];
            $recipient = gpDogAccessFetchUserById($pdo, $userId) ?: [];
            $pdo->beginTransaction();
            try {
                if (($_POST['action'] ?? '') === 'accept_dog_access_invite') {
                    upsertDogHandlerLink($pdo, (int) $invite['dog_id'], $userId, (int) $invite['invited_by_user_id'], (string) ($invite['role'] ?? 'co-op handler'), (string) ($invite['permission_level'] ?? 'view'), 'accepted');
                    $stmt = $pdo->prepare('UPDATE dog_handlers SET access_ends_at = ? WHERE id = ?');
                    $stmt->execute([$invite['access_ends_at'] ?? null, $handlerId]);
                    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
                    $stmt->execute([$userId, (int) $invite['dog_id']]);
                    $pdo->commit();
                    gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'accepted');
                    header('Location: notifications.php?status=invite_accepted');
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'declined', revoked_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$handlerId]);
                $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
                $stmt->execute([$userId, (int) $invite['dog_id']]);
                $pdo->commit();
                gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'declined');
                header('Location: notifications.php?status=invite_declined');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        gpMarkNotificationsRead($pdo, $userId);
        header('Location: notifications.php?status=read');
        exit;
    }
    if (($_POST['action'] ?? '') === 'mark_read') {
        gpMarkNotificationsRead($pdo, $userId, [(int) ($_POST['notification_id'] ?? 0)]);
        $next = trim((string) ($_POST['next'] ?? ''));
        if ($next !== '' && !str_starts_with($next, 'http://') && !str_starts_with($next, 'https://') && !str_starts_with($next, '//')) {
            header('Location: ' . $next);
        } else {
            header('Location: notifications.php');
        }
        exit;
    }
}

$csrf = generateCsrfToken();
$notifications = gpFetchUserNotifications($pdo, $userId, 100);
$unreadCount = gpUnreadNotificationCount($pdo, $userId);
$pendingInvites = gpDogAccessPendingInvites($pdo, $userId);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
.notification-card{border-radius:20px;border:1px solid rgba(15,23,42,.08);box-shadow:0 8px 20px rgba(15,23,42,.07);overflow:hidden}.notification-item{border-bottom:1px solid rgba(15,23,42,.08);padding:1rem;background:#fff}.notification-item:last-child{border-bottom:0}.notification-item.unread{background:#f0f7ff}.notification-title{font-weight:900;color:#0f172a}.notification-body{color:#475569;white-space:pre-wrap;overflow-wrap:anywhere}.priority-high{border-left:5px solid #dc3545}.priority-normal{border-left:5px solid #0d6efd}.priority-low{border-left:5px solid #64748b}.notification-meta{font-size:.82rem;color:#64748b}.notification-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem}.notification-actions .btn{white-space:normal}.empty-state{border:1px dashed rgba(22,163,74,.36);background:#f0fdf4;border-radius:16px;padding:1rem;color:#166534}</style>
</head>
<body class="bg-light pb-5">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>
<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
        <div>
            <h1 class="h3 mb-0">🔔 Notifications</h1>
            <div class="text-muted small"><?= (int) $unreadCount ?> unread alert<?= $unreadCount === 1 ? '' : 's' ?><?php if ($pendingInvites): ?> · <?= count($pendingInvites) ?> pending dog access invite<?= count($pendingInvites) === 1 ? '' : 's' ?><?php endif; ?>.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary btn-sm" href="index.php">Dashboard</a>
            <?php if ($unreadCount > 0): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button class="btn btn-primary btn-sm">Mark all read</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($_GET['status'] ?? '') === 'read'): ?><div class="alert alert-success">Notifications marked as read.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'invite_accepted'): ?><div class="alert alert-success">Dog access invite accepted.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'invite_declined'): ?><div class="alert alert-info">Dog access invite declined.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($pendingInvites): ?>
        <section class="card notification-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1">Pending Dog Access Invites</h2>
                        <div class="text-muted small">Accepting an invite adds the dog to your accessible profiles.</div>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($pendingInvites as $invite): ?>
                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-3 bg-white h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div class="min-w-0">
                                        <div class="fw-bold"><?= e($invite['dog_name']) ?></div>
                                        <div class="small text-muted">From <?= e($invite['owner_display_name'] ?: $invite['owner_username']) ?></div>
                                    </div>
                                    <span class="badge text-bg-warning text-wrap">Pending</span>
                                </div>
                                <div class="small mt-2">
                                    <div><strong>Role:</strong> <?= e($invite['role'] ?? '') ?></div>
                                    <div><strong>Permission:</strong> <?= e($invite['permission_level'] ?? '') ?></div>
                                    <div><strong>End date:</strong> <?= e($invite['access_ends_at'] ?? 'not set') ?></div>
                                </div>
                                <div class="notification-actions">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="accept_dog_access_invite">
                                        <input type="hidden" name="handler_id" value="<?= (int) $invite['id'] ?>">
                                        <button class="btn btn-success btn-sm">Accept</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="decline_dog_access_invite">
                                        <input type="hidden" name="handler_id" value="<?= (int) $invite['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm">Decline</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="card notification-card">
        <div class="card-body p-0">
            <?php if (!$notifications): ?>
                <div class="p-3"><div class="empty-state">✅ No notifications yet. Important GuidePaw alerts will appear here.</div></div>
            <?php else: ?>
                <?php foreach ($notifications as $notice): ?>
                    <?php $actionUrl = trim((string) ($notice['action_url'] ?? '')); ?>
                    <article class="notification-item <?= !empty($notice['is_read']) ? '' : 'unread' ?> priority-<?= e($notice['priority'] ?: 'normal') ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div class="min-w-0">
                                <div class="notification-title"><?= e($notice['title']) ?></div>
                                <div class="notification-meta">
                                    <?= e(date('M j, Y g:i A', strtotime((string) $notice['created_at']))) ?>
                                    <?php if (!empty($notice['dog_name'])): ?> · <?= e($notice['dog_name']) ?><?php endif; ?>
                                    <?php if (empty($notice['is_read'])): ?> · <span class="badge text-bg-primary">Unread</span><?php endif; ?>
                                </div>
                            </div>
                            <span class="badge text-bg-secondary text-wrap"><?= e($notice['notification_type'] ?? 'info') ?></span>
                        </div>
                        <?php if (!empty($notice['body'])): ?><div class="notification-body mt-2"><?= nl2br(e($notice['body'])) ?></div><?php endif; ?>
                        <div class="notification-actions">
                            <?php if ($actionUrl !== ''): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int) $notice['id'] ?>">
                                    <input type="hidden" name="next" value="<?= e($actionUrl) ?>">
                                    <button class="btn btn-outline-primary btn-sm">Open</button>
                                </form>
                            <?php endif; ?>
                            <?php if (empty($notice['is_read'])): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int) $notice['id'] ?>">
                                    <button class="btn btn-outline-secondary btn-sm">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<script src="app.js"></script>
</body>
</html>
