<?php
require 'includes/db_connect.php';
require_once 'includes/feature_flags.php';
if (!featureEnabled($pdo, 'training_program_enabled')) {
    header('Location: index.php?msg=feature_disabled');
    exit;
}
require 'includes/validation.php';
checkLogin();

$userId = (int) $_SESSION['user_id'];
$dog = requireActiveDog($pdo, $userId);
$dogId = (int) $dog['id'];
$canEdit = userCanEditDog($pdo, $userId, $dogId);
$errors = [];
$status = $_GET['status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if (!$canEdit) {
        $errors[] = 'You have read-only access for this dog.';
    } elseif ($action === 'seed_program') {
        seedDogTrainingProgram($pdo, $dogId);
        header('Location: training_program.php?status=program_loaded');
        exit;
    } elseif ($action === 'save_profile') {
        $mode = $_POST['training_mode'] ?? 'self_training';
        if (!in_array($mode, ['self_training', 'professional_trainer', 'hybrid'], true)) {
            $mode = 'self_training';
        }
        $candidateStage = $_POST['candidate_stage'] ?? 'prospect';
        if (!in_array($candidateStage, ['prospect', 'foundation', 'advanced', 'working', 'career_change', 'retired'], true)) {
            $candidateStage = 'prospect';
        }
        upsertDogTrainingProfile($pdo, $dogId, $userId, [
            'training_mode' => $mode,
            'trainer_name' => cleanText($_POST['trainer_name'] ?? '', 120) ?: null,
            'business_name' => cleanText($_POST['business_name'] ?? '', 150) ?: null,
            'credentials' => cleanTextarea($_POST['credentials'] ?? '', 500) ?: null,
            'trainer_phone' => cleanPhone($_POST['trainer_phone'] ?? ''),
            'trainer_email' => cleanText($_POST['trainer_email'] ?? '', 120) ?: null,
            'trainer_website' => cleanText($_POST['trainer_website'] ?? '', 150) ?: null,
            'handler_experience' => cleanText($_POST['handler_experience'] ?? '', 120) ?: null,
            'handler_goals' => cleanTextarea($_POST['handler_goals'] ?? '', 1000) ?: null,
            'candidate_stage' => $candidateStage,
            'candidate_notes' => cleanTextarea($_POST['candidate_notes'] ?? '', 1200) ?: null,
            'training_focus' => cleanTextarea($_POST['training_focus'] ?? '', 800) ?: null,
            'notes' => cleanTextarea($_POST['notes'] ?? '', 1500) ?: null,
        ]);
        header('Location: training_program.php?status=profile_saved');
        exit;
    } elseif ($action === 'update_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'not_started';
        $allowed = ['not_started', 'in_progress', 'proofing', 'mastered'];
        if (!in_array($newStatus, $allowed, true)) {
            $newStatus = 'not_started';
        }
        $notes = cleanTextarea($_POST['notes'] ?? '', 1000);
        $stmt = $pdo->prepare('UPDATE dog_training_items SET status=?, notes=?, last_worked_at=CURRENT_TIMESTAMP WHERE id=? AND dog_id=?');
        $stmt->execute([$newStatus, $notes ?: null, $itemId, $dogId]);
        header('Location: training_program.php?status=item_saved');
        exit;
    }
}

$profile = getDogTrainingProfile($pdo, $dogId) ?: [
    'training_mode' => 'self_training',
    'trainer_name' => '',
    'business_name' => '',
    'credentials' => '',
    'trainer_phone' => '',
    'trainer_email' => '',
    'trainer_website' => '',
    'handler_experience' => '',
    'handler_goals' => '',
    'candidate_stage' => 'prospect',
    'candidate_notes' => '',
    'training_focus' => '',
    'notes' => '',
];
$items = getDogTrainingItems($pdo, $dogId);
$grouped = [];
foreach ($items as $item) {
    $grouped[$item['category']][] = $item;
}
$total = count($items);
$mastered = count(array_filter($items, fn($i) => ($i['status'] ?? '') === 'mastered'));
$progress = count(array_filter($items, fn($i) => in_array(($i['status'] ?? ''), ['in_progress', 'proofing', 'mastered'], true)));
$readyPct = $total ? (int) round(($mastered / $total) * 100) : 0;
$suggestions = getTrainingSuggestions($pdo, $userId, $dogId);
$programGuide = getBeneficialTrainingPrograms();
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(appName()) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="styles.css" rel="stylesheet">
</head>
<body class="pb-5">
<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<?php
$activeGoals = [];
$recentSessions = [];
$nextModules = [];

try {
    $stmt = $pdo->prepare("
        SELECT g.*, d.name AS dog_name
        FROM training_goals g
        JOIN dogs d ON d.id = g.dog_id
        WHERE g.user_id = ? AND g.status = 'active'
        ORDER BY g.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $activeGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT s.*, d.name AS dog_name, m.title AS module_title
        FROM training_sessions s
        JOIN dogs d ON d.id = s.dog_id
        LEFT JOIN training_modules m ON m.id = s.module_id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT title, level_number, category
        FROM training_modules
        WHERE is_active = 1
        ORDER BY level_number, sort_order
        LIMIT 5
    ");
    $nextModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $activeGoals = [];
    $recentSessions = [];
    $nextModules = [];
}

$easyWin = 'Say your dog’s name once. Reward the moment they look at you. Repeat 3 times.';
if (!empty($activeGoals)) {
    $easyWin = 'Work 3 minutes on: ' . ($activeGoals[0]['desired_behavior'] ?: $activeGoals[0]['goal_category']);
}
?>

<div class="container my-3">
    <div class="alert alert-success">
        <strong>Today&apos;s Easy Win:</strong>
        <?= e($easyWin) ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Active Goals</h2>
                    <?php if (!$activeGoals): ?>
                        <p class="small text-muted mb-0">No active goals yet. Start with Goal Intake.</p>
                    <?php else: ?>
                        <?php foreach ($activeGoals as $goal): ?>
                            <div class="small mb-2">
                                <strong><?= e($goal['dog_name']) ?>:</strong>
                                <?= e($goal['success_criteria'] ?: $goal['desired_behavior']) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-primary mt-2" href="training_goal_intake.php">Goal Intake</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Current Modules</h2>
                    <?php foreach ($nextModules as $module): ?>
                        <div class="small mb-2">
                            Level <?= e($module['level_number']) ?>:
                            <?= e($module['title']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Progress / Regression</h2>
                    <?php if (!$recentSessions): ?>
                        <p class="small text-muted mb-0">No logged training sessions yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentSessions as $session): ?>
                            <div class="small mb-2">
                                <strong><?= e($session['dog_name']) ?>:</strong>
                                <?= e($session['progression_status']) ?>
                                <?php if (!empty($session['module_title'])): ?>
                                    on <?= e($session['module_title']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-primary mt-2" href="habit_repair.php">Habit Repair</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="topbar p-4 shadow-sm">
    <div class="page-shell p-0 d-flex justify-content-between align-items-start gap-3">
        <div>
            <div class="small opacity-75">Structured training</div>
            <h2 class="mb-1">🎓 <?= e($dog['name']) ?></h2>
            <div class="small opacity-75">Progression ladder, service-dog candidate screen, AKC tracks, and trainer workflow.</div>
        </div>
        <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
    </div>
</div>
<div class="page-shell">
    <?php if ($status): ?><div class="alert alert-success"><?= e(str_replace('_', ' ', $status)) ?>.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Skills listed</div><div class="fs-4 fw-bold"><?= $total ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Active / proofing</div><div class="fs-4 fw-bold"><?= $progress ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Mastered</div><div class="fs-4 fw-bold text-success"><?= $mastered ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kv-box"><div class="data-label">Readiness</div><div class="fs-4 fw-bold"><?= $readyPct ?>%</div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="section-title">Training setup</div>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="save_profile">
                        <div class="col-12">
                            <label class="form-label">Training path</label>
                            <select name="training_mode" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
                                <option value="self_training" <?= $profile['training_mode'] === 'self_training' ? 'selected' : '' ?>>Self training handler</option>
                                <option value="professional_trainer" <?= $profile['training_mode'] === 'professional_trainer' ? 'selected' : '' ?>>Professional trainer</option>
                                <option value="hybrid" <?= $profile['training_mode'] === 'hybrid' ? 'selected' : '' ?>>Hybrid: handler + trainer</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Trainer name</label><input type="text" name="trainer_name" class="form-control" value="<?= e((string) $profile['trainer_name']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">Business name</label><input type="text" name="business_name" class="form-control" value="<?= e((string) $profile['business_name']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">Credentials / certifications</label><textarea name="credentials" rows="2" class="form-control" <?= $canEdit ? '' : 'disabled' ?>><?= e((string) $profile['credentials']) ?></textarea></div>
                        <div class="col-6"><label class="form-label">Phone</label><input type="text" name="trainer_phone" class="form-control" value="<?= e((string) $profile['trainer_phone']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-6"><label class="form-label">Email</label><input type="text" name="trainer_email" class="form-control" value="<?= e((string) $profile['trainer_email']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">Website</label><input type="text" name="trainer_website" class="form-control" value="<?= e((string) $profile['trainer_website']) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">Handler experience</label><input type="text" name="handler_experience" class="form-control" value="<?= e((string) $profile['handler_experience']) ?>" placeholder="Example: first service dog, experienced trainer, puppy raiser" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">Handler goals</label><textarea name="handler_goals" rows="2" class="form-control" <?= $canEdit ? '' : 'disabled' ?>><?= e((string) $profile['handler_goals']) ?></textarea></div>
                        <div class="col-6">
                            <label class="form-label">Candidate stage</label>
                            <select name="candidate_stage" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
                                <?php foreach (['prospect' => 'Prospect', 'foundation' => 'Foundation', 'advanced' => 'Advanced in training', 'working' => 'Working team', 'career_change' => 'Career change / washout consideration', 'retired' => 'Retired'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($profile['candidate_stage'] ?? 'prospect') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label">Current focus</label><textarea name="training_focus" rows="2" class="form-control" <?= $canEdit ? '' : 'disabled' ?>><?= e((string) $profile['training_focus']) ?></textarea></div>
                        <div class="col-12"><label class="form-label">Candidate notes</label><textarea name="candidate_notes" rows="2" class="form-control" <?= $canEdit ? '' : 'disabled' ?>><?= e((string) $profile['candidate_notes']) ?></textarea></div>
                        <div class="col-12"><label class="form-label">Program notes</label><textarea name="notes" rows="3" class="form-control" <?= $canEdit ? '' : 'disabled' ?>><?= e((string) $profile['notes']) ?></textarea></div>
                        <?php if ($canEdit): ?><div class="col-12"><button class="btn btn-primary w-100">Save training setup</button></div><?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="section-title mb-0">Next suggestions</div>
                        <?php if ($canEdit && !$items): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="seed_program">
                                <button class="btn btn-primary btn-sm">Load starter program</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (!$items): ?>
                        <div class="alert alert-info mb-0">Load the starter program to get a candidate screen, command ladder, AKC tracks, public-access benchmark, service-task progression, and working foundations.</div>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($suggestions as $suggestion): ?>
                                <div class="kv-box small"><?= e($suggestion) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="section-title">Helpful programs and tests</div>
                    <div class="vstack gap-2">
                        <?php foreach ($programGuide as $program): ?>
                            <div class="kv-box">
                                <div class="fw-semibold"><?= e($program['name']) ?></div>
                                <div class="small-muted"><?= e($program['best_for']) ?></div>
                                <div class="small text-muted mt-1"><?= e($program['notes']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="section-title">Training ladder</div>
                    <?php if (!$items): ?>
                        <div class="alert alert-info mb-0">No training ladder loaded yet. Use the starter template to seed candidate screening, obedience, public access, service-task, AKC, and maintenance items.</div>
                    <?php else: ?>
                        <div class="accordion" id="trainingAccordion">
                            <?php $n = 0; foreach ($grouped as $category => $rows): $n++; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?= $n ?>">
                                        <button class="accordion-button <?= $n > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $n ?>">
                                            <?= e($category) ?> <span class="ms-2 badge bg-light text-dark"><?= count($rows) ?></span>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $n ?>" class="accordion-collapse collapse <?= $n === 1 ? 'show' : '' ?>" data-bs-parent="#trainingAccordion">
                                        <div class="accordion-body">
                                            <div class="vstack gap-3">
                                                <?php foreach ($rows as $item): ?>
                                                    <form method="post" class="kv-box">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                        <input type="hidden" name="action" value="update_item">
                                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                                            <div>
                                                                <div class="fw-semibold">Level <?= (int) $item['level'] ?> • <?= e($item['item_name']) ?></div>
                                                                <?php if (!empty($item['description'])): ?><div class="small-muted"><?= e($item['description']) ?></div><?php endif; ?>
                                                                <?php if (!empty($item['track_code'])): ?><div class="small text-muted mt-1">Track: <?= e(strtoupper((string) $item['track_code'])) ?></div><?php endif; ?>
                                                            </div>
                                                            <span class="badge bg-light text-dark"><?= e(str_replace('_', ' ', $item['status'])) ?></span>
                                                        </div>
                                                        <div class="row g-2 align-items-end mt-1">
                                                            <div class="col-md-4">
                                                                <label class="form-label small">Status</label>
                                                                <select name="status" class="form-select form-select-sm" <?= $canEdit ? '' : 'disabled' ?>>
                                                                    <option value="not_started" <?= $item['status'] === 'not_started' ? 'selected' : '' ?>>Not started</option>
                                                                    <option value="in_progress" <?= $item['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                                                                    <option value="proofing" <?= $item['status'] === 'proofing' ? 'selected' : '' ?>>Proofing</option>
                                                                    <option value="mastered" <?= $item['status'] === 'mastered' ? 'selected' : '' ?>>Mastered</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Session notes</label>
                                                                <input type="text" name="notes" class="form-control form-control-sm" value="<?= e((string) $item['notes']) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                                            </div>
                                                            <div class="col-md-2 d-grid"><?php if ($canEdit): ?><button class="btn btn-outline-primary btn-sm">Save</button><?php endif; ?></div>
                                                        </div>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
