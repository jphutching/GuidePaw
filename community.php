<?php
require_once 'includes/db_connect.php';
require_once 'includes/brand_header.php';
require_once 'includes/app_config.php';
require_once 'includes/community_hub.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$user = getUserRecord($pdo, $userId);
$discordUrl = gpCommunityDiscordUrl();
$merchUrl = gpCommunityMerchUrl();
$threads = gpCommunityForumListThreads($pdo, 3);
$swagItems = gpCommunitySwagItems();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="manifest.json">
<title>Community · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
    .community-card { border: 1px solid rgba(15,23,42,.08); border-radius: 20px; box-shadow: 0 8px 20px rgba(15,23,42,.07); overflow: hidden; }
    .community-link { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-top:1px solid rgba(15,23,42,.08); color:#1f2937; text-decoration:none; }
    .community-link:first-of-type { border-top:0; }
    .community-muted { color:#6b7280; font-size:.92rem; }
    .swag-card { height: 100%; border:1px solid rgba(15,23,42,.08); border-radius:18px; }
    .swag-emoji { font-size:1.6rem; }
</style>
</head>
<body class="pb-5 bg-light">
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<main class="page-shell mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">🤝 Community</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <section class="card community-card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">Handler Community</h2>
            <p class="community-muted mb-3">Swag, live discussion, and a place for handlers to stay connected.</p>
            <a class="community-link" href="support_funding.php">
                <span><span class="me-2">💙</span>Support GuidePaw</span>
                <span>›</span>
            </a>
            <a class="community-link" href="forum.php">
                <span><span class="me-2">💬</span>Open forum</span>
                <span>›</span>
            </a>
            <?php if ($discordUrl !== ''): ?>
                <a class="community-link" href="<?= e($discordUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <span><span class="me-2">🟣</span>Join Discord</span>
                    <span>›</span>
                </a>
            <?php else: ?>
                <div class="community-muted">Discord invite link not set yet.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card community-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">GuidePaw Swag</h2>
                    <p class="community-muted mb-0">Swag items for handlers, trainers, and supporters.</p>
                </div>
                <?php if ($merchUrl !== ''): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e($merchUrl) ?>" target="_blank" rel="noopener noreferrer">Open store</a>
                <?php endif; ?>
            </div>
            <div class="row g-3">
                <?php foreach ($swagItems as $item): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="swag-card p-3 bg-white">
                            <div class="swag-emoji mb-2"><?= e($item['emoji']) ?></div>
                            <div class="fw-semibold mb-1"><?= e($item['name']) ?></div>
                            <div class="small text-muted mb-2"><?= e($item['description']) ?></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-dark"><?= e($item['price']) ?></span>
                                <?php if ($merchUrl !== ''): ?>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= e($merchUrl) ?>" target="_blank" rel="noopener noreferrer">Shop</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($merchUrl === ''): ?>
                <div class="alert alert-info mt-3 mb-0">Merch store link is not configured yet. Set the store URL to turn these items into live checkout links.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card community-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Latest forum threads</h2>
                    <p class="community-muted mb-0">Recent handler conversations from the GuidePaw forum.</p>
                </div>
                <a href="forum.php" class="btn btn-outline-secondary btn-sm">Open forum</a>
            </div>
            <?php if (!$threads): ?>
                <div class="community-muted">No forum threads yet. Start the first discussion in the forum.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($threads as $thread): ?>
                        <a class="list-group-item list-group-item-action px-0" href="forum.php?thread_id=<?= (int) $thread['id'] ?>">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold"><?= e($thread['title']) ?></div>
                                    <div class="small text-muted"><?= e(gpCommunityForumCategories()[$thread['category']] ?? ucfirst((string) $thread['category'])) ?> · <?= e((string) ($thread['creator_name'] ?: 'Handler')) ?></div>
                                </div>
                                <div class="small text-muted text-end"><?= (int) ($thread['reply_count'] ?? 0) ?> replies</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script src="app.js"></script>
</body>
</html>
