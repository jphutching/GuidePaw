<?php
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/tactical_access.php';

checkLogin();
gpTacticalAccessEnsureSchema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = getUserRecord($pdo, $userId) ?: [];
$approved = gpTacticalAccessCanCurrentUserView($pdo, $userId);
$request = gpTacticalAccessCurrentRequest($pdo, $userId);
$message = '';
$error = '';
$serviceTypes = gpTacticalAccessServiceTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'request_access' && !$approved) {
        $fullName = trim((string) ($_POST['full_name'] ?? ($user['full_name'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? ($user['email'] ?? ''))));
        $organization = trim((string) ($_POST['organization'] ?? ''));
        $roleTitle = trim((string) ($_POST['role_title'] ?? ''));
        $serviceType = (string) ($_POST['service_type'] ?? 'other');
        $verificationNotes = trim((string) ($_POST['verification_notes'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($fullName === '' || $email === '' || $organization === '' || $roleTitle === '') {
            $error = 'Name, email, organization, and role are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!isset($serviceTypes[$serviceType])) {
            $error = 'Please choose a valid service type.';
        } elseif ($reason === '') {
            $error = 'Tell us why you need tactical access.';
        } else {
            gpTacticalAccessUpsertRequest($pdo, $userId, [
                'full_name' => $fullName,
                'email' => $email,
                'organization' => $organization,
                'role_title' => $roleTitle,
                'service_type' => $serviceType,
                'verification_notes' => $verificationNotes,
                'reason' => $reason,
            ]);
            header('Location: tactical_training.php?msg=request_saved');
            exit;
        }
    }
}

if (($_GET['msg'] ?? '') === 'request_saved') {
    $message = 'Access request saved. An admin will review the verification details.';
    $request = gpTacticalAccessCurrentRequest($pdo, $userId);
}

$csrf = generateCsrfToken();
$prefillName = trim((string) ($user['full_name'] ?? $user['display_name'] ?? ''));
$prefillEmail = trim((string) ($user['email'] ?? ''));
$requestStatus = (string) ($request['status'] ?? '');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tactical Training | <?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body{background:#f1f5f9;color:#0f172a}
        .wrap{max-width:1100px;margin:0 auto;padding:1rem 1rem 4rem}
        .hero{background:linear-gradient(135deg,#0d6efd,#0f766e);color:#fff;border-radius:0 0 28px 28px;padding:1.1rem 1rem 1.35rem;box-shadow:0 10px 24px rgba(15,23,42,.18)}
        .hero h1{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;line-height:1.05}
        .tactical-grid{display:grid;gap:1rem}
        .tactical-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;overflow:hidden;box-shadow:0 8px 18px rgba(15,23,42,.08)}
        .tactical-note{border-left:4px solid #0d6efd;background:#eff6ff;border-radius:14px;padding:.9rem}
        .tactical-pill{display:inline-flex;align-items:center;padding:.22rem .6rem;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:.76rem;font-weight:900}
        .req-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
        .req-grid .full{grid-column:1 / -1}
        .req-grid textarea,.req-grid select,.req-grid input{border-radius:12px}
        .module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
        .module-item{border:1px solid rgba(15,23,42,.08);border-radius:16px;background:#f8fafc;padding:1rem}
        .module-item .btn{margin-right:.4rem;margin-top:.5rem}
        @media (max-width: 720px){.req-grid,.module-grid{grid-template-columns:1fr}}
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
                <div class="small opacity-75">Verified working-team access</div>
                <h1 class="mb-2">Tactical Training</h1>
                <p class="mb-0 opacity-75">Built for verified handlers in security, law enforcement, fire, military, and search and rescue. This area is not open to the general public.</p>
            </div>
            <a class="btn btn-light btn-sm" href="paywalls.php">Plans & Access</a>
        </div>
    </div>
</header>

<main class="wrap">
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if ($approved || (function_exists('currentUserIsAdmin') && currentUserIsAdmin())): ?>
        <div class="tactical-grid">
            <section class="tactical-card">
                <div class="p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <div class="tactical-pill">Access approved</div>
                            <h2 class="h4 mt-2 mb-1">Tactical readiness path</h2>
                            <div class="text-muted">Use this space for work-focused training plans, proofing, and log capture.</div>
                        </div>
                        <a class="btn btn-outline-primary btn-sm" href="admin_tactical_requests.php">Review requests</a>
                    </div>

                    <div class="module-grid mt-4">
                        <div class="module-item">
                            <div class="fw-bold mb-1">Operational foundation</div>
                            <div class="small text-muted">Build neutrality, handler focus, and reliable off-switch behavior before adding higher-distraction work.</div>
                            <a class="btn btn-outline-primary btn-sm" href="candidate_assessment.php">Open assessment</a>
                            <a class="btn btn-outline-secondary btn-sm" href="training_program.php">Open ladder</a>
                        </div>
                        <div class="module-item">
                            <div class="fw-bold mb-1">Search / response work</div>
                            <div class="small text-muted">Use a measured plan for search behavior, recall, and recovery after the rep.</div>
                            <a class="btn btn-outline-primary btn-sm" href="goal_builder.php">Build goal</a>
                            <a class="btn btn-outline-secondary btn-sm" href="log_entry.php">Log session</a>
                        </div>
                        <div class="module-item">
                            <div class="fw-bold mb-1">Distraction resilience</div>
                            <div class="small text-muted">Track noise, crowd, vehicle, and threshold work without stacking too much difficulty at once.</div>
                            <a class="btn btn-outline-primary btn-sm" href="behavior_risk_scoring.php">Open scoring</a>
                            <a class="btn btn-outline-secondary btn-sm" href="trucking_mode.php">Open trucking mode</a>
                        </div>
                        <div class="module-item">
                            <div class="fw-bold mb-1">Team proofing</div>
                            <div class="small text-muted">Tie daily reps to objective notes so the team can see what actually holds up in the field.</div>
                            <a class="btn btn-outline-primary btn-sm" href="view_logs.php">Open history</a>
                            <a class="btn btn-outline-secondary btn-sm" href="training_history.php">Training history</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="tactical-card">
                <div class="p-3 p-md-4">
                    <h2 class="h5 mb-3">Suggested tactical focus</h2>
                    <div class="tactical-note mb-3">Keep it measurable: one behavior, one context, one success definition. Avoid turning this into a general tricks page.</div>
                    <ul class="mb-0">
                        <li>Handler engagement under pressure</li>
                        <li>Noise and crowd resilience</li>
                        <li>Search pattern reliability</li>
                        <li>Recovery and reset after each rep</li>
                    </ul>
                </div>
            </section>
        </div>
    <?php else: ?>
        <div class="tactical-grid">
            <section class="tactical-card">
                <div class="p-3 p-md-4">
                    <div class="tactical-pill">Application required</div>
                    <h2 class="h4 mt-2 mb-1">Request tactical access</h2>
                    <div class="text-muted mb-3">Tell us which verified team you work with and how GuidePaw should help.</div>
                    <?php if ($request && $requestStatus === 'pending'): ?>
                        <div class="alert alert-info">
                            Your request is pending review. You can update the details below if your team information changes.
                        </div>
                    <?php elseif ($request && $requestStatus === 'denied'): ?>
                        <div class="alert alert-warning">
                            Your prior request was denied. You can submit an updated request with better verification details.
                        </div>
                    <?php endif; ?>

                    <form method="post" class="req-grid">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="request_access">
                        <div>
                            <label class="form-label fw-semibold">Your name</label>
                            <input class="form-control" name="full_name" required value="<?= e($_POST['full_name'] ?? $prefillName) ?>">
                        </div>
                        <div>
                            <label class="form-label fw-semibold">Email</label>
                            <input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? $prefillEmail) ?>">
                        </div>
                        <div>
                            <label class="form-label fw-semibold">Organization / unit</label>
                            <input class="form-control" name="organization" required value="<?= e($_POST['organization'] ?? ($request['organization'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="form-label fw-semibold">Role / title</label>
                            <input class="form-control" name="role_title" required value="<?= e($_POST['role_title'] ?? ($request['role_title'] ?? '')) ?>">
                        </div>
                        <div class="full">
                            <label class="form-label fw-semibold">Service type</label>
                            <select class="form-select" name="service_type" required>
                                <?php foreach ($serviceTypes as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= (($_POST['service_type'] ?? ($request['service_type'] ?? '')) === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="full">
                            <label class="form-label fw-semibold">Verification notes</label>
                            <textarea class="form-control" name="verification_notes" rows="3" placeholder="Badge link, agency site, command contact, or other public verification details"><?= e($_POST['verification_notes'] ?? ($request['verification_notes'] ?? '')) ?></textarea>
                        </div>
                        <div class="full">
                            <label class="form-label fw-semibold">Why do you need tactical access?</label>
                            <textarea class="form-control" name="reason" rows="4" required placeholder="Describe the working team, mission, and what you need from GuidePaw."><?= e($_POST['reason'] ?? ($request['reason'] ?? '')) ?></textarea>
                        </div>
                        <div class="full d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary">Submit request</button>
                            <a class="btn btn-outline-secondary" href="support_funding.php">Support funding</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="tactical-card">
                <div class="p-3 p-md-4">
                    <h2 class="h5 mb-3">Who this is for</h2>
                    <div class="tactical-note mb-3">Verified handlers only. If you are not on an operational team, use the regular training path instead.</div>
                    <ul class="mb-0">
                        <li>Security teams</li>
                        <li>Police K9 handlers</li>
                        <li>Fire / EMS support teams</li>
                        <li>Military working-dog teams</li>
                        <li>Search and rescue teams</li>
                    </ul>
                </div>
            </section>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

