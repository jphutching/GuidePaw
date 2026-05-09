<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/trainer_marketplace.php';

checkLogin();

if (!featureEnabled($pdo, 'trainer_marketplace_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo 'Login required.';
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$entries = gpTrainerMarketplaceEntries($pdo, $userId);
$summary = gpTrainerMarketplaceSummary($entries);
$search = strtolower(trim((string) ($_GET['q'] ?? '')));
$filteredEntries = $entries;
if ($search !== '') {
    $filteredEntries = array_values(array_filter($entries, static function (array $entry) use ($search): bool {
        $haystack = strtolower(implode(' ', [
            $entry['trainer_name'] ?? '',
            $entry['business_name'] ?? '',
            $entry['credentials'] ?? '',
            $entry['trainer_email'] ?? '',
            $entry['trainer_phone'] ?? '',
            $entry['training_focus'] ?? '',
            $entry['candidate_stage'] ?? '',
            implode(' ', array_map(static fn($dog) => $dog['dog_name'] ?? '', $entry['dogs'] ?? [])),
        ]));

        return str_contains($haystack, $search);
    }));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Trainer Marketplace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1180px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .metric { background:#f8f9fa; border:1px solid #ddd; border-radius:12px; padding:12px; }
        .metric strong { display:block; font-size:1.5rem; }
        .small { color:#666; font-size:13px; }
        .trainer-card { border:1px solid #dbe2ea; border-radius:14px; padding:14px; background:#fff; }
        .trainer-name { font-size:1.1rem; font-weight:900; }
        .dog-pill { display:inline-flex; align-items:center; gap:.3rem; border-radius:999px; padding:.25rem .6rem; background:#eef2ff; color:#4338ca; font-size:.8rem; font-weight:800; margin: .2rem .35rem .2rem 0; }
        .actions a { display:inline-flex; align-items:center; gap:.35rem; margin-right:.5rem; margin-top:.45rem; }
        input { width:100%; padding:8px; box-sizing:border-box; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wrap">
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Trainer Marketplace</h1>
    <p class="small">A directory of trainer contacts and profiles already saved on your dogs. This stays within the data you have entered and the people your account already knows about.</p>

    <div class="grid">
        <div class="metric"><div class="small">Trainer profiles</div><strong><?= (int) $summary['trainer_count'] ?></strong></div>
        <div class="metric"><div class="small">Dogs covered</div><strong><?= (int) $summary['dog_count'] ?></strong></div>
    </div>

    <div class="card">
        <form method="get">
            <label for="q">Search trainers, businesses, dogs, or specialties</label>
            <input type="search" id="q" name="q" value="<?= h($search) ?>" placeholder="Search trainer name, business, focus, or dog">
        </form>
    </div>

    <div class="card">
        <?php if (!$filteredEntries): ?>
            <p class="small">No trainer profiles found yet. Set a dog to Professional Trainer or Hybrid in Training Program to build the directory.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($filteredEntries as $entry): ?>
                    <div class="trainer-card">
                        <div class="trainer-name"><?= h($entry['trainer_name'] ?: ($entry['business_name'] ?: 'Unnamed trainer')) ?></div>
                        <?php if (!empty($entry['business_name']) && $entry['business_name'] !== $entry['trainer_name']): ?>
                            <div class="small text-muted"><?= h($entry['business_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($entry['credentials'])): ?>
                            <div class="small mt-2"><?= h($entry['credentials']) ?></div>
                        <?php endif; ?>
                        <div class="small mt-2"><strong>Candidate stage:</strong> <?= h($entry['candidate_stage'] ?: 'unspecified') ?></div>
                        <?php if (!empty($entry['training_focus'])): ?>
                            <div class="small mt-2"><strong>Focus:</strong> <?= h($entry['training_focus']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($entry['handler_goals'])): ?>
                            <div class="small mt-2"><strong>Handler goals:</strong> <?= h($entry['handler_goals']) ?></div>
                        <?php endif; ?>
                        <div class="mt-2">
                            <strong class="small">Dogs:</strong><br>
                            <?php foreach ($entry['dogs'] as $dog): ?>
                                <span class="dog-pill"><?= h($dog['dog_name']) ?> · <?= h(ucfirst((string) ($dog['training_mode'] ?? ''))) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="actions mt-2">
                            <?php if (!empty($entry['trainer_phone'])): ?><a class="btn btn-outline-primary btn-sm" href="tel:<?= h($entry['trainer_phone']) ?>">Call</a><?php endif; ?>
                            <?php if (!empty($entry['trainer_email'])): ?><a class="btn btn-outline-secondary btn-sm" href="mailto:<?= h($entry['trainer_email']) ?>">Email</a><?php endif; ?>
                            <?php if (!empty($entry['trainer_website'])): ?><a class="btn btn-outline-dark btn-sm" href="<?= h($entry['trainer_website']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
                        </div>
                        <?php if (!empty($entry['notes'])): ?>
                            <div class="small mt-2 text-muted"><?= h($entry['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
