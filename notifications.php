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
    if (($_POST['action'] ?? '') === 'save_notification_preferences') {
        $enabled = array_fill_keys(array_keys(gpNotificationCategoryOptions()), false);
        foreach ((array) ($_POST['categories'] ?? []) as $key) {
            $key = strtolower(trim((string) $key));
            if (array_key_exists($key, $enabled)) {
                $enabled[$key] = true;
            }
        }
        gpSaveNotificationPreferences($pdo, $userId, $enabled);
        header('Location: notifications.php?status=prefs_saved');
        exit;
    }
    if (($_POST['action'] ?? '') === 'delete_selected_notifications') {
        $deleted = gpDeleteNotifications($pdo, $userId, (array) ($_POST['notification_ids'] ?? []));
        header('Location: notifications.php?status=deleted&count=' . $deleted);
        exit;
    }
    if (in_array(($_POST['action'] ?? ''), ['accept_dog_access_invite', 'decline_dog_access_invite'], true)) {
        $handlerId = (int) ($_POST['handler_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT dh.*, d.name AS dog_name, d.owner_user_id, owner.username AS owner_username, owner.display_name AS owner_display_name, owner.public_email AS owner_public_email, owner.email AS owner_email
            FROM dog_handlers dh
            JOIN dogs d ON d.id = dh.dog_id
            JOIN users owner ON owner.id = d.owner_user_id
            WHERE dh.id = ? AND dh.user_id = ? AND dh.status = 'accepted' AND dh.accepted_at IS NULL
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
                    $role = gpDogHandlerRoleLabel((string) ($invite['role'] ?? 'co-op handler'));
                    upsertDogHandlerLink($pdo, (int) $invite['dog_id'], $userId, (int) $invite['invited_by_user_id'], $role, (string) ($invite['permission_level'] ?? 'view'), 'accepted');
                    $stmt = $pdo->prepare('UPDATE dog_handlers SET access_ends_at = ? WHERE id = ?');
                    $stmt->execute([$invite['access_ends_at'] ?? null, $handlerId]);
                    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND related_dog_id = ? AND notification_type = 'dog_access_invite'");
                    $stmt->execute([$userId, (int) $invite['dog_id']]);
                    $pdo->commit();
                    gpDogAccessNotifySharedInviteResult($dog, $owner, $recipient, 'accepted');
                    header('Location: notifications.php?status=invite_accepted');
                    exit;
                }

                    $stmt = $pdo->prepare("UPDATE dog_handlers SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, accepted_at = NULL WHERE id = ?");
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
$notificationPrefs = gpFetchNotificationPreferences($pdo, $userId);
$notifications = gpFetchUserNotifications($pdo, $userId, 100);
$visibleNotifications = array_values(array_filter($notifications, static fn(array $notice): bool => gpNotificationVisibleByPreferences($notice, $notificationPrefs)));
$hiddenNotificationCount = max(0, count($notifications) - count($visibleNotifications));
$unreadCount = gpUnreadNotificationCount($pdo, $userId);
$visibleUnreadCount = count(array_filter($visibleNotifications, static fn(array $notice): bool => empty($notice['is_read'])));
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
<style>
.fold-card{border-radius:20px;border:1px solid rgba(15,23,42,.08);box-shadow:0 8px 20px rgba(15,23,42,.07);overflow:hidden;background:#fff}
.fold-card>summary{list-style:none;cursor:pointer;padding:1rem 1rem .85rem;display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}
.fold-card>summary::-webkit-details-marker{display:none}
.fold-card>summary::after{content:'⌄';color:#6b7280;font-size:1.2rem;line-height:1;transition:transform .15s ease;flex:0 0 auto;margin-top:.1rem}
.fold-card[open]>summary::after{transform:rotate(180deg)}
.fold-card .card-body{padding-top:0}
</style>
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
    <?php if (($_GET['status'] ?? '') === 'prefs_saved'): ?><div class="alert alert-success">Notification preferences saved.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'deleted'): ?><div class="alert alert-info">Deleted <?= (int) ($_GET['count'] ?? 0) ?> notification<?= (int) ($_GET['count'] ?? 0) === 1 ? '' : 's' ?>.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'invite_accepted'): ?><div class="alert alert-success">Dog access invite accepted.</div><?php endif; ?>
    <?php if (($_GET['status'] ?? '') === 'invite_declined'): ?><div class="alert alert-info">Dog access invite declined.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <details class="card notification-card mb-3 fold-card">
        <summary>
            <div>
                <div class="small text-uppercase text-muted fw-semibold">Filter</div>
                <h2 class="h5 mb-1">Notification Preferences</h2>
                <div class="text-muted small">Hide categories you do not want in the inbox.</div>
            </div>
        </summary>
        <div class="card-body">
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save_notification_preferences">
                <?php foreach (gpNotificationCategoryOptions() as $key => $label): ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label fw-bold d-flex align-items-center gap-2">
                            <input class="form-check-input mt-0" type="checkbox" name="categories[]" value="<?= e($key) ?>" <?= !empty($notificationPrefs[$key]) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                        <div class="text-muted small">
                            <?php if ($key === 'access'): ?>Dog access and transfer notices.
                            <?php elseif ($key === 'care'): ?>Found-dog and care alerts.
                            <?php elseif ($key === 'admin'): ?>Admin, beta, and system alerts.
                            <?php else: ?>General app notices.<?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-12">
                    <button class="btn btn-outline-primary btn-sm">Save preferences</button>
                </div>
            </form>
        </div>
    </details>

    <?php if ($pendingInvites): ?>
        <details class="card notification-card mb-3 fold-card">
            <summary>
                <div>
                    <div class="small text-uppercase text-muted fw-semibold">Pending</div>
                    <h2 class="h5 mb-1">Pending Dog Access Invites</h2>
                    <div class="text-muted small">Accepting an invite adds the dog to your accessible profiles.</div>
                </div>
            </summary>
            <div class="card-body">
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
        </details>
    <?php endif; ?>

    <details class="card notification-card fold-card" open>
        <summary>
            <div>
                <div class="small text-uppercase text-muted fw-semibold">Messages</div>
                <h2 class="h5 mb-1">Inbox</h2>
                <div class="text-muted small"><?= (int) $visibleUnreadCount ?> unread visible alert<?= $visibleUnreadCount === 1 ? '' : 's' ?><?php if ($hiddenNotificationCount > 0): ?> · <?= (int) $hiddenNotificationCount ?> hidden by category preferences<?php endif; ?>.</div>
            </div>
        </summary>
        <div class="card-body">
            <form id="notificationBulkDeleteForm" method="post" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete_selected_notifications">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <button class="btn btn-outline-danger btn-sm" <?= $visibleNotifications ? '' : 'disabled' ?>>Delete selected</button>
                    <span class="text-muted small"><?= $visibleNotifications ? 'Bulk delete selected notifications from your inbox.' : 'Bulk delete is available once notifications are visible.' ?></span>
                </div>
            </form>
            <?php if ($visibleNotifications): ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:48px;">Sel</th>
                                <th>Alert</th>
                                <th style="width:135px;">Type</th>
                                <th style="width:210px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visibleNotifications as $notice): ?>
                                <?php $actionUrl = trim((string) ($notice['action_url'] ?? '')); ?>
                                <tr class="<?= !empty($notice['is_read']) ? '' : 'table-primary' ?>">
                                    <td>
                                        <input class="form-check-input" type="checkbox" name="notification_ids[]" value="<?= (int) $notice['id'] ?>" form="notificationBulkDeleteForm" aria-label="Select notification <?= e($notice['title']) ?>">
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= e($notice['title']) ?></div>
                                        <div class="small text-muted">
                                            <?= e(date('M j, Y g:i A', strtotime((string) $notice['created_at']))) ?>
                                            <?php if (!empty($notice['dog_name'])): ?> · <?= e($notice['dog_name']) ?><?php endif; ?>
                                            <?php if (empty($notice['is_read'])): ?> · <span class="badge text-bg-primary">Unread</span><?php endif; ?>
                                        </div>
                                        <?php if (!empty($notice['body'])): ?><div class="notification-body mt-2"><?= nl2br(e($notice['body'])) ?></div><?php endif; ?>
                                    </td>
                                    <td><span class="badge text-bg-secondary text-wrap"><?= e($notice['notification_type'] ?? 'info') ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">✅ No visible notifications yet. Important GuidePaw alerts will appear here.</div>
            <?php endif; ?>
            <?php if ($hiddenNotificationCount > 0): ?>
                <div class="alert alert-light border mt-3 mb-0">Some alerts are hidden by your notification preferences.</div>
            <?php endif; ?>
        </div>
    </details>
</main>
<script src="app.js"></script>
</body>
</html>
