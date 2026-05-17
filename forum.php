<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';
require_once 'includes/community_hub.php';
require_once 'includes/validation.php';
require_once 'includes/support_badges.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
gpCommunityForumEnsureSchema($pdo);
$currentRole = gpCurrentUserRole($pdo);
$csrf = generateCsrfToken();
$errors = [];
$message = '';
$threadId = isset($_GET['thread_id']) ? (int) $_GET['thread_id'] : 0;
$searchQuery = trim((string) ($_GET['q'] ?? ''));

function forumCanModerateThread(string $role): bool
{
    return in_array($role, ['master_admin', 'basic_admin', 'moderator'], true);
}

function forumCanDeleteContent(string $role): bool
{
    return forumCanModerateThread($role);
}

function forumCanReviewArchivedThreads(string $role): bool
{
    return in_array($role, ['master_admin', 'basic_admin'], true);
}

function forumRoleBadgeClass(string $role): string
{
    return match ($role) {
        'master_admin' => 'text-bg-danger',
        'basic_admin' => 'text-bg-primary',
        'moderator' => 'text-bg-warning',
        'pro_trainer' => 'text-bg-info',
        default => 'text-bg-secondary',
    };
}

function forumAuthorMeta(array $row): array
{
    return [
        'id' => (int) ($row['created_by_user_id'] ?? $row['user_id'] ?? 0),
        'username' => (string) ($row['creator_username'] ?? $row['author_username'] ?? ''),
        'email' => (string) ($row['creator_email'] ?? $row['author_email'] ?? ''),
        'display_name' => (string) ($row['creator_name'] ?? $row['author_name'] ?? 'Handler'),
        'user_role' => (string) ($row['creator_role'] ?? $row['author_role'] ?? 'user'),
        'is_admin' => (int) ($row['creator_is_admin'] ?? $row['author_is_admin'] ?? 0),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_thread') {
        $title = cleanText($_POST['title'] ?? '', 120);
        $body = cleanTextarea($_POST['body'] ?? '', 4000);
        $category = (string) ($_POST['category'] ?? 'general');
        if (!array_key_exists($category, gpCommunityForumCategories())) {
            $category = 'general';
        }
        if ($title === '') {
            $errors[] = 'Thread title is required.';
        }
        if ($body === '') {
            $errors[] = 'Thread body is required.';
        }
        if (!$errors) {
            $newThreadId = gpCommunityForumCreateThread($pdo, $userId, $category, $title, $body);
            header('Location: forum.php?thread_id=' . $newThreadId . '&msg=thread_created');
            exit;
        }
    } elseif ($action === 'reply_thread') {
        $replyThreadId = (int) ($_POST['thread_id'] ?? 0);
        $replyBody = cleanTextarea($_POST['reply_body'] ?? '', 2500);
        $thread = gpCommunityForumGetThread($pdo, $replyThreadId);
        if (!$thread) {
            $errors[] = 'Thread not found.';
        } elseif (!empty($thread['is_archived'])) {
            $errors[] = 'This thread is archived.';
        } elseif (!empty($thread['is_locked'])) {
            $errors[] = 'This thread is closed.';
        } elseif ($replyBody === '') {
            $errors[] = 'Reply text is required.';
        } else {
            gpCommunityForumAddReply($pdo, $replyThreadId, $userId, $replyBody);
            header('Location: forum.php?thread_id=' . $replyThreadId . '&msg=replied');
            exit;
        }
        $threadId = $replyThreadId;
    } elseif ($action === 'moderate_thread') {
        $moderateThreadId = (int) ($_POST['thread_id'] ?? 0);
        $moderationAction = (string) ($_POST['moderation_action'] ?? '');
        $thread = gpCommunityForumGetThread($pdo, $moderateThreadId);
        if (!$thread) {
            $errors[] = 'Thread not found.';
        } elseif (!forumCanModerateThread($currentRole)) {
            $errors[] = 'Moderation access required.';
        } else {
            if ($moderationAction === 'pin') {
                gpCommunityForumSetPinned($pdo, $moderateThreadId, true);
                $message = 'Thread pinned.';
            } elseif ($moderationAction === 'unpin') {
                gpCommunityForumSetPinned($pdo, $moderateThreadId, false);
                $message = 'Thread unpinned.';
            } elseif ($moderationAction === 'close') {
                gpCommunityForumSetLocked($pdo, $moderateThreadId, true);
                $message = 'Thread closed.';
            } elseif ($moderationAction === 'open') {
                gpCommunityForumSetLocked($pdo, $moderateThreadId, false);
                $message = 'Thread reopened.';
            } elseif ($moderationAction === 'archive') {
                gpCommunityForumSetArchived($pdo, $moderateThreadId, true);
                $message = 'Thread archived.';
            } elseif ($moderationAction === 'unarchive') {
                gpCommunityForumSetArchived($pdo, $moderateThreadId, false);
                $message = 'Thread restored.';
            } elseif ($moderationAction === 'delete_thread') {
                gpCommunityForumDeleteThread($pdo, $moderateThreadId);
                header('Location: forum.php?msg=thread_deleted');
                exit;
            } else {
                $errors[] = 'Unknown moderation action.';
            }
            if (!$errors) {
                header('Location: forum.php?thread_id=' . $moderateThreadId . '&msg=' . rawurlencode($message));
                exit;
            }
        }
    } elseif ($action === 'delete_reply') {
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $replyThreadId = (int) ($_POST['thread_id'] ?? 0);
        if (!forumCanDeleteContent($currentRole)) {
            $errors[] = 'Moderation access required.';
        } else {
            $thread = gpCommunityForumGetThread($pdo, $replyThreadId);
            if (!$thread) {
                $errors[] = 'Thread not found.';
            } else {
                gpCommunityForumDeleteReply($pdo, $replyId);
                header('Location: forum.php?thread_id=' . $replyThreadId . '&msg=reply_deleted');
                exit;
            }
        }
    }
}

if (($_GET['msg'] ?? '') === 'thread_created') {
    $message = 'Thread posted.';
} elseif (($_GET['msg'] ?? '') === 'replied') {
    $message = 'Reply posted.';
} elseif (($_GET['msg'] ?? '') === 'reply_deleted') {
    $message = 'Reply deleted.';
} elseif (($_GET['msg'] ?? '') === 'thread_deleted') {
    $message = 'Thread deleted.';
} elseif (($_GET['msg'] ?? '') !== '') {
    $message = ucwords(str_replace('_', ' ', (string) $_GET['msg']));
}

$thread = $threadId > 0 ? gpCommunityForumGetThread($pdo, $threadId) : null;
$threads = gpCommunityForumListThreads($pdo, 30, $searchQuery);
$archivedThreads = forumCanReviewArchivedThreads($currentRole) ? gpCommunityForumListArchivedThreads($pdo, 8, $searchQuery) : [];
$posts = $thread ? gpCommunityForumGetPosts($pdo, (int) $thread['id']) : [];
$categories = gpCommunityForumCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Forum · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .forum-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .forum-muted { color:#6b7280; font-size:.92rem; }
    .thread-item { display:block; text-decoration:none; color: inherit; border-top: 1px solid rgba(15,23,42,.08); padding: .9rem 0; }
    .thread-item:first-child { border-top: 0; }
    .post-card { border: 1px solid rgba(15,23,42,.08); border-radius: 16px; background: #fff; padding: 1rem; }
    .forum-tools { display: inline-block; }
    .forum-tools > summary { list-style: none; }
    .forum-tools > summary::-webkit-details-marker { display: none; }
    .forum-tools[open] > summary { margin-bottom: .5rem; }
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">💬 Forum</h1>
        <a href="community.php" class="btn btn-outline-secondary btn-sm">Community</a>
    </div>

    <form method="get" class="card forum-card mb-3">
        <div class="card-body">
            <label class="form-label">Search threads</label>
            <div class="input-group">
                <input type="search" class="form-control" name="q" value="<?= e($searchQuery) ?>" placeholder="Search title, body, category, or handler">
                <button class="btn btn-primary">Search</button>
                <a class="btn btn-outline-secondary" href="forum.php">Clear</a>
            </div>
        </div>
    </form>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="card forum-card mb-3">
                <div class="card-body">
                    <h2 class="h5 mb-1">Start a thread</h2>
                    <p class="forum-muted mb-3">Ask a question, share a tip, or start a discussion.</p>
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="create_thread">
                        <div>
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" maxlength="120" placeholder="What are you working on?">
                        </div>
                        <div>
                            <label class="form-label">Message</label>
                            <textarea name="body" class="form-control" rows="6" placeholder="Share details, ask a question, or give advice."></textarea>
                        </div>
                        <button class="btn btn-primary">Post thread</button>
                    </form>
                </div>
            </section>

            <section class="card forum-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h5 mb-0">Recent threads</h2>
                        <span class="badge bg-light text-dark"><?= count($threads) ?></span>
                    </div>
                    <?php if (!$threads): ?>
                        <div class="forum-muted">No threads yet.</div>
                    <?php else: ?>
                        <?php foreach ($threads as $item): ?>
                            <?php
                                $creator = forumAuthorMeta($item);
                                $creatorRole = gpUserRole($creator);
                                $creatorBadge = gpSupportBadgeForUser($pdo, $creator);
                            ?>
                            <a class="thread-item" href="forum.php?thread_id=<?= (int) $item['id'] ?>">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <div class="fw-semibold"><?= e($item['title']) ?></div>
                                    <div class="d-flex gap-1 flex-wrap justify-content-end">
                                        <?php if (!empty($item['is_pinned'])): ?><span class="badge text-bg-warning">Pinned</span><?php endif; ?>
                                        <?php if (!empty($item['is_locked'])): ?><span class="badge text-bg-secondary">Closed</span><?php endif; ?>
                                        <?php if (!empty($item['is_archived'])): ?><span class="badge text-bg-dark">Archived</span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="small text-muted d-flex flex-wrap gap-1 align-items-center">
                                    <span><?= e($categories[$item['category']] ?? ucfirst((string) $item['category'])) ?></span>
                                    <span>·</span>
                                    <span><?= e((string) ($item['creator_name'] ?: 'Handler')) ?></span>
                                    <span class="badge <?= e(forumRoleBadgeClass($creatorRole)) ?>"><?= e(gpRoleDisplayLabel($creatorRole)) ?></span>
                                    <?php if ($creatorBadge): ?>
                                        <img src="<?= e($creatorBadge['image']) ?>" alt="<?= e($creatorBadge['label']) ?>" style="width:20px;height:20px;object-fit:contain;">
                                        <span class="small text-muted"><?= e($creatorBadge['label']) ?></span>
                                    <?php endif; ?>
                                    <span>·</span>
                                    <span><?= (int) ($item['reply_count'] ?? 0) ?> replies</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="card forum-card">
                <div class="card-body">
                    <?php if ($thread): ?>
                        <?php
                            $threadAuthor = forumAuthorMeta($thread);
                            $threadAuthorRole = gpUserRole($threadAuthor);
                            $threadAuthorBadge = gpSupportBadgeForUser($pdo, $threadAuthor);
                        ?>
                        <div class="d-flex justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="h4 mb-1"><?= e($thread['title']) ?></h2>
                                <div class="forum-muted d-flex flex-wrap gap-1 align-items-center">
                                    <span><?= e($categories[$thread['category']] ?? ucfirst((string) $thread['category'])) ?></span>
                                    <span>·</span>
                                    <span><?= e((string) ($thread['creator_name'] ?: 'Handler')) ?></span>
                                    <span class="badge <?= e(forumRoleBadgeClass($threadAuthorRole)) ?>"><?= e(gpRoleDisplayLabel($threadAuthorRole)) ?></span>
                                    <?php if ($threadAuthorBadge): ?>
                                        <img src="<?= e($threadAuthorBadge['image']) ?>" alt="<?= e($threadAuthorBadge['label']) ?>" style="width:20px;height:20px;object-fit:contain;">
                                        <span><?= e($threadAuthorBadge['label']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <?php if (!empty($thread['is_pinned'])): ?><span class="badge text-bg-warning align-self-start">Pinned</span><?php endif; ?>
                                <?php if (!empty($thread['is_locked'])): ?><span class="badge bg-secondary align-self-start">Closed</span><?php endif; ?>
                                <?php if (!empty($thread['is_archived'])): ?><span class="badge text-bg-dark align-self-start">Archived</span><?php endif; ?>
                                <?php if (forumCanModerateThread($currentRole)): ?>
                                    <details class="forum-tools align-self-end">
                                        <summary class="btn btn-sm btn-outline-secondary">Thread tools</summary>
                                        <form method="post" class="d-flex gap-2 flex-wrap justify-content-end mt-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="moderate_thread">
                                            <input type="hidden" name="thread_id" value="<?= (int) $thread['id'] ?>">
                                            <?php if (empty($thread['is_pinned'])): ?>
                                                <button class="btn btn-sm btn-outline-warning" name="moderation_action" value="pin">Pin</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-warning" name="moderation_action" value="unpin">Unpin</button>
                                            <?php endif; ?>
                                            <?php if (empty($thread['is_locked'])): ?>
                                                <button class="btn btn-sm btn-outline-secondary" name="moderation_action" value="close">Close</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" name="moderation_action" value="open">Reopen</button>
                                            <?php endif; ?>
                                            <?php if (empty($thread['is_archived'])): ?>
                                                <button class="btn btn-sm btn-outline-dark" name="moderation_action" value="archive">Archive</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-dark" name="moderation_action" value="unarchive">Restore</button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger" name="moderation_action" value="delete_thread" onclick="return confirm('Delete this thread and all replies?');">Delete Thread</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($thread['is_archived'])): ?>
                            <div class="alert alert-dark">This thread is archived.</div>
                        <?php elseif (!empty($thread['is_locked'])): ?>
                            <div class="alert alert-secondary">This thread is closed.</div>
                            <?php endif; ?>
                        <div class="post-card mb-3"><?= nl2br(e((string) $thread['body'])) ?></div>
                        <h3 class="h6 mb-3">Replies</h3>
                        <?php if (!$posts): ?>
                            <div class="forum-muted mb-3">No replies yet.</div>
                        <?php else: ?>
                            <div class="vstack gap-3 mb-4">
                                <?php foreach ($posts as $post): ?>
                                    <?php
                                        $postAuthor = forumAuthorMeta($post);
                                        $postAuthorRole = gpUserRole($postAuthor);
                                        $postBadge = gpSupportBadgeForUser($pdo, $postAuthor);
                                    ?>
                                    <div class="post-card">
                                        <div class="d-flex justify-content-between gap-3 mb-2">
                                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                                <strong><?= e((string) $post['author_name']) ?></strong>
                                                <span class="badge <?= e(forumRoleBadgeClass($postAuthorRole)) ?>"><?= e(gpRoleDisplayLabel($postAuthorRole)) ?></span>
                                                <?php if ($postBadge): ?>
                                                    <img src="<?= e($postBadge['image']) ?>" alt="<?= e($postBadge['label']) ?>" style="width:20px;height:20px;object-fit:contain;">
                                                    <span class="small text-muted"><?= e($postBadge['label']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="forum-muted"><?= e(date('M j, Y g:i A', strtotime((string) $post['created_at']))) ?></span>
                                                <?php if (forumCanDeleteContent($currentRole)): ?>
                                                    <details class="forum-tools">
                                                        <summary class="btn btn-sm btn-outline-danger">Reply tools</summary>
                                                        <form method="post" class="mt-2" onsubmit="return confirm('Delete this reply?');">
                                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                            <input type="hidden" name="action" value="delete_reply">
                                                            <input type="hidden" name="thread_id" value="<?= (int) $thread['id'] ?>">
                                                            <input type="hidden" name="reply_id" value="<?= (int) $post['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-danger" aria-label="Delete reply">Delete Reply</button>
                                                        </form>
                                                    </details>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div><?= nl2br(e((string) $post['body'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($thread['is_locked']) && empty($thread['is_archived'])): ?>
                            <form method="post" class="vstack gap-3">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="reply_thread">
                                <input type="hidden" name="thread_id" value="<?= (int) $thread['id'] ?>">
                                <div>
                                    <label class="form-label">Reply</label>
                                    <textarea name="reply_body" class="form-control" rows="4" placeholder="Add a reply for other handlers."></textarea>
                                </div>
                                <button class="btn btn-outline-primary">Post reply</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0"><?= !empty($thread['is_archived']) ? 'This thread is archived.' : 'This thread is closed.' ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="forum-muted">Choose a thread on the left or start a new one.</div>
                    <?php endif; ?>
                </div>
            </section>
            <?php if (forumCanReviewArchivedThreads($currentRole)): ?>
                <section class="card forum-card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h2 class="h5 mb-1">Archived review</h2>
                                <div class="forum-muted">Admin-only review of archived threads.</div>
                            </div>
                            <span class="badge text-bg-dark"><?= count($archivedThreads) ?></span>
                        </div>
                        <?php if (!$archivedThreads): ?>
                            <div class="forum-muted">No archived threads right now.</div>
                        <?php else: ?>
                            <div class="vstack gap-3">
                                <?php foreach ($archivedThreads as $archived): ?>
                                    <?php
                                        $archivedAuthor = forumAuthorMeta($archived);
                                        $archivedRole = gpUserRole($archivedAuthor);
                                    ?>
                                    <div class="post-card">
                                        <div class="d-flex justify-content-between gap-2 mb-2">
                                            <div>
                                                <div class="fw-semibold">
                                                    <a class="text-decoration-none" href="forum.php?thread_id=<?= (int) $archived['id'] ?>"><?= e($archived['title']) ?></a>
                                                </div>
                                                <div class="forum-muted d-flex flex-wrap gap-1 align-items-center">
                                                    <span><?= e($categories[$archived['category']] ?? ucfirst((string) $archived['category'])) ?></span>
                                                    <span>·</span>
                                                    <span><?= e((string) ($archived['creator_name'] ?: 'Handler')) ?></span>
                                                    <span class="badge <?= e(forumRoleBadgeClass($archivedRole)) ?>"><?= e(gpRoleDisplayLabel($archivedRole)) ?></span>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column align-items-end gap-2">
                                                <span class="badge text-bg-dark">Archived</span>
                                                <div class="forum-muted"><?= e(date('M j, Y g:i A', strtotime((string) $archived['updated_at']))) ?></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="action" value="moderate_thread">
                                                <input type="hidden" name="thread_id" value="<?= (int) $archived['id'] ?>">
                                                <input type="hidden" name="moderation_action" value="unarchive">
                                                <button class="btn btn-sm btn-outline-secondary">Restore</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Delete this archived thread and all replies?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="action" value="moderate_thread">
                                                <input type="hidden" name="thread_id" value="<?= (int) $archived['id'] ?>">
                                                <input type="hidden" name="moderation_action" value="delete_thread">
                                                <button class="btn btn-sm btn-outline-danger">Delete Thread</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="app.js"></script>
</body>
</html>
