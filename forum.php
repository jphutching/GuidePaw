<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';
require_once 'includes/community_hub.php';
require_once 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
gpCommunityForumEnsureSchema($pdo);
$csrf = generateCsrfToken();
$errors = [];
$message = '';
$threadId = isset($_GET['thread_id']) ? (int) $_GET['thread_id'] : 0;

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
        } elseif (($thread['is_locked'] ?? false) && !currentUserIsAdmin()) {
            $errors[] = 'This thread is locked.';
        } elseif ($replyBody === '') {
            $errors[] = 'Reply text is required.';
        } else {
            gpCommunityForumAddReply($pdo, $replyThreadId, $userId, $replyBody);
            header('Location: forum.php?thread_id=' . $replyThreadId . '&msg=replied');
            exit;
        }
        $threadId = $replyThreadId;
    }
}

if (($_GET['msg'] ?? '') === 'thread_created') {
    $message = 'Thread posted.';
} elseif (($_GET['msg'] ?? '') === 'replied') {
    $message = 'Reply posted.';
}

$thread = $threadId > 0 ? gpCommunityForumGetThread($pdo, $threadId) : null;
$threads = gpCommunityForumListThreads($pdo, 30);
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
                            <a class="thread-item" href="forum.php?thread_id=<?= (int) $item['id'] ?>">
                                <div class="fw-semibold"><?= e($item['title']) ?></div>
                                <div class="small text-muted"><?= e($categories[$item['category']] ?? ucfirst((string) $item['category'])) ?> · <?= e((string) ($item['creator_name'] ?: 'Handler')) ?> · <?= (int) ($item['reply_count'] ?? 0) ?> replies</div>
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
                        <div class="d-flex justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="h4 mb-1"><?= e($thread['title']) ?></h2>
                                <div class="forum-muted"><?= e($categories[$thread['category']] ?? ucfirst((string) $thread['category'])) ?> · <?= e((string) ($thread['creator_name'] ?: 'Handler')) ?></div>
                            </div>
                            <?php if (!empty($thread['is_locked']) && !currentUserIsAdmin()): ?>
                                <span class="badge bg-secondary align-self-start">Locked</span>
                            <?php endif; ?>
                        </div>
                        <div class="post-card mb-3"><?= nl2br(e((string) $thread['body'])) ?></div>
                        <h3 class="h6 mb-3">Replies</h3>
                        <?php if (!$posts): ?>
                            <div class="forum-muted mb-3">No replies yet.</div>
                        <?php else: ?>
                            <div class="vstack gap-3 mb-4">
                                <?php foreach ($posts as $post): ?>
                                    <div class="post-card">
                                        <div class="d-flex justify-content-between gap-3 mb-2">
                                            <strong><?= e((string) $post['author_name']) ?></strong>
                                            <span class="forum-muted"><?= e(date('M j, Y g:i A', strtotime((string) $post['created_at']))) ?></span>
                                        </div>
                                        <div><?= nl2br(e((string) $post['body'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($thread['is_locked']) || currentUserIsAdmin()): ?>
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
                            <div class="alert alert-warning mb-0">This thread is locked.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="forum-muted">Choose a thread on the left or start a new one.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>
<script src="app.js"></script>
</body>
</html>
