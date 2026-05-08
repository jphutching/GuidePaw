<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/training_assistant.php';
require_once __DIR__ . '/includes/validation.php';

if (!featureEnabled($pdo, 'ai_training_assistant_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}

checkLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$topics = gpTrainingAssistantTopics();
$defaults = [
    'topic' => 'general',
    'issue' => '',
    'context' => 'home',
    'what_tried' => '',
    'safety_flags' => '',
];

$analysis = null;
$message = ($_GET['msg'] ?? '') === 'saved' ? 'Assistant feedback saved.' : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $input = [
        'topic' => (string) ($_POST['topic'] ?? 'general'),
        'issue' => cleanTextarea($_POST['issue'] ?? '', 1200),
        'context' => cleanText($_POST['context'] ?? '', 80),
        'what_tried' => cleanTextarea($_POST['what_tried'] ?? '', 900),
        'safety_flags' => cleanTextarea($_POST['safety_flags'] ?? '', 600),
    ];
    $analysis = gpTrainingAssistantAnalyze($input);
    writeAuditLog($pdo, 'ai_training_assistant_viewed', 'dogs', $dogId, 'Viewed bounded training assistant guidance.');
}

$csrf = generateCsrfToken();

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Training Assistant · <?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#0f172a}.wrap{max-width:1080px;margin:0 auto;padding:1rem 1rem 4rem}.hero{background:linear-gradient(135deg,#0d6efd,#7c3aed);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}.hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}.assistant-grid{display:grid;gap:1rem}.assistant-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;overflow:hidden;box-shadow:0 8px 18px rgba(15,23,42,.08)}.pill{display:inline-flex;align-items:center;border-radius:999px;padding:.24rem .6rem;background:#eef2ff;color:#4338ca;font-size:.76rem;font-weight:900}.guide-box{border-left:4px solid #0d6efd;background:#eff6ff;border-radius:14px;padding:.9rem}.guide-box.warn{border-left-color:#b45309;background:#fff7ed}.guide-box.danger{border-left-color:#dc2626;background:#fef2f2}.assistant-tip{border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:.8rem .9rem;background:#fff}.assistant-tip strong{display:block;margin-bottom:.15rem}.form-label{font-weight:800}.minor{color:#64748b;font-size:.92rem}
@media (min-width: 900px){.assistant-grid{grid-template-columns:1.15fr .85fr}}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once __DIR__ . '/includes/beta_banner.php'; ?>
<?php require_once __DIR__ . '/includes/mobile_nav.php'; ?>

<header class="hero">
    <div class="wrap px-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75">GuidePaw bounded training support</div>
                <h1 class="mb-2">AI Training Assistant</h1>
                <p class="mb-0 opacity-75">Give the assistant the problem, context, and what you tried. It will return a narrow, safety-aware next step plan.</p>
            </div>
            <a class="btn btn-light btn-sm" href="training_program.php">Back to training</a>
        </div>
    </div>
</header>

<main class="wrap">
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="assistant-grid">
        <section class="assistant-card">
            <div class="p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="pill">Active dog: <?= e($dog['name']) ?></div>
                        <div class="minor mt-2">This is support guidance, not diagnosis or certification advice.</div>
                    </div>
                </div>

                <form method="post" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label">What’s happening?</label>
                        <textarea class="form-control" name="issue" rows="4" placeholder="Short version of the problem"><?= e((string) ($_POST['issue'] ?? $defaults['issue'])) ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Problem type</label>
                            <select class="form-select" name="topic">
                                <?php foreach ($topics as $key => $topic): ?>
                                    <option value="<?= e($key) ?>" <?= (string) ($_POST['topic'] ?? $defaults['topic']) === $key ? 'selected' : '' ?>>
                                        <?= e($topic['icon'] . ' ' . $topic['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Context</label>
                            <input class="form-control" name="context" value="<?= e((string) ($_POST['context'] ?? $defaults['context'])) ?>" placeholder="home, truck, store, parking lot">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">What have you already tried?</label>
                        <textarea class="form-control" name="what_tried" rows="3" placeholder="Short list of attempts so far"><?= e((string) ($_POST['what_tried'] ?? $defaults['what_tried'])) ?></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Safety flags or concerns</label>
                        <textarea class="form-control" name="safety_flags" rows="2" placeholder="bite history, panic, pain, illness, shutdown"><?= e((string) ($_POST['safety_flags'] ?? $defaults['safety_flags'])) ?></textarea>
                    </div>
                    <div class="d-grid mt-3">
                        <button class="btn btn-primary" type="submit">Get plan</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="assistant-card">
            <div class="p-3 p-md-4">
                <h2 class="h5 mb-3">Bounded guidance</h2>
                <?php if (!$analysis): ?>
                    <div class="guide-box">
                        Enter the problem on the left and the assistant will return a narrow reset plan, a short list of next steps, and what to avoid.
                    </div>
                <?php else: ?>
                    <div class="guide-box">
                        <div class="fw-bold"><?= e($analysis['icon']) ?> <?= e($analysis['title']) ?></div>
                        <div class="mt-1"><?= e($analysis['summary']) ?></div>
                    </div>

                    <?php if (!empty($analysis['safety'])): ?>
                        <div class="guide-box danger mt-3">
                            <div class="fw-bold mb-1">Safety flags</div>
                            <?php foreach ($analysis['safety'] as $flag): ?>
                                <div><?= e($flag) ?></div>
                            <?php endforeach; ?>
                            <div class="minor mt-2">If this is pain, illness, or aggression, stop training and recheck health or get professional help first.</div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <div class="fw-bold mb-2">Next steps</div>
                        <?php foreach ($analysis['next_steps'] as $step): ?>
                            <div class="assistant-tip mb-2"><strong>Do this</strong><?= e($step) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-3">
                        <div class="fw-bold mb-2">Avoid</div>
                        <?php foreach ($analysis['avoid'] as $step): ?>
                            <div class="assistant-tip mb-2"><strong>Skip this</strong><?= e($step) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($analysis['follow_up'])): ?>
                        <div class="mt-3">
                            <div class="fw-bold mb-2">Questions to answer next</div>
                            <?php foreach ($analysis['follow_up'] as $step): ?>
                                <div class="assistant-tip mb-2"><strong>Check</strong><?= e($step) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>
