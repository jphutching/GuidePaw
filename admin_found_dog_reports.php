<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/found_dog_reports.php';
requireAdmin();

gpEnsureFoundDogReportsTable($pdo);

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'update_status');
    if ($action === 'send_found_email' && $reportId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM found_dog_reports WHERE id = ? LIMIT 1');
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        $dog = $report ? gpFoundDogFetchPublicDog($pdo, (int) $report['dog_id']) : null;
        if (!$report || !$dog) {
            header('Location: admin_found_dog_reports.php?msg=email_failed');
            exit;
        }

        $allowedRecipients = gpFoundDogRecipientEmails($dog);
        $selectedRecipients = array_values(array_intersect((array) ($_POST['recipients'] ?? []), $allowedRecipients));
        if (!$selectedRecipients) {
            $selectedRecipients = $allowedRecipients;
        }

        $lat = $report['finder_latitude'] !== null ? (string) $report['finder_latitude'] : '';
        $lng = $report['finder_longitude'] !== null ? (string) $report['finder_longitude'] : '';
        $subject = trim((string) ($_POST['email_subject'] ?? ''));
        $body = trim((string) ($_POST['email_body'] ?? ''));
        if ($subject === '') {
            $subject = gpFoundDogEmailSubject($dog);
        }
        if ($body === '') {
            $body = gpFoundDogEmailBody($dog, $report, gpFoundDogLocationLink($dog, $report));
        }

        $sentCount = 0;
        foreach ($selectedRecipients as $email) {
            try {
                if (gpSendMail($email, mb_substr($subject, 0, 180), $body)) {
                    $sentCount++;
                }
            } catch (Throwable $e) {
                error_log('GuidePaw custom found dog email failed: ' . $e->getMessage());
            }
        }

        if ($sentCount > 0) {
            $stmt = $pdo->prepare("UPDATE found_dog_reports SET status = CASE WHEN status IN ('resolved','closed') THEN status ELSE 'contacted' END, notification_sent = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$reportId]);
        }
        header('Location: admin_found_dog_reports.php?msg=' . ($sentCount > 0 ? 'email_sent' : 'email_failed') . '&sent=' . $sentCount);
        exit;
    }

    $status = (string) ($_POST['status'] ?? 'new');
    if ($reportId > 0 && in_array($status, ['new', 'reviewing', 'contacted', 'resolved', 'closed'], true)) {
        $stmt = $pdo->prepare('UPDATE found_dog_reports SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $reportId]);
    }
    header('Location: admin_found_dog_reports.php?msg=updated');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$params = [];
$where = '';
if ($statusFilter !== '' && in_array($statusFilter, ['new', 'reviewing', 'contacted', 'resolved', 'closed'], true)) {
    $where = 'WHERE r.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT r.*, d.name AS dog_name, d.breed AS dog_breed, d.handler_name, d.handler_phone, d.handler_email
    FROM found_dog_reports r
    JOIN dogs d ON d.id = r.dog_id
    $where
    ORDER BY r.created_at DESC
    LIMIT 200");
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Found Dog Reports · GuidePaw Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f3f6fb;color:#1f2937;padding-bottom:90px}.wrap{max-width:1100px;margin:0 auto;padding:18px}.cardx{background:#fff;border:1px solid #dfe3ea;border-radius:18px;padding:18px;margin:14px 0;box-shadow:0 8px 24px rgba(15,23,42,.08)}.meta{color:#6b7280;font-size:.9rem}.badge-status{border-radius:999px;padding:4px 9px;background:#e5e7eb;font-size:.8rem;font-weight:800}.details{white-space:pre-wrap;border-left:4px solid #dbeafe;padding-left:12px}.btn{border-radius:12px;font-weight:800}.maplink{word-break:break-word}.email-template{border:1px solid #bfdbfe;background:#eff6ff;border-radius:16px;padding:14px}.email-template textarea{min-height:320px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.9rem;white-space:pre-wrap}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/beta_banner.php'; ?>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="h3 mb-0">Found Dog Location Reports</h1><div class="text-muted small">Public QR location reports submitted by finders.</div></div>
        <a class="btn btn-outline-secondary" href="admin.php">Back to Admin</a>
    </div>

    <div class="cardx d-flex flex-wrap gap-2">
        <a class="btn btn-dark" href="admin_found_dog_reports.php">All</a>
        <?php foreach (['new', 'reviewing', 'contacted', 'resolved', 'closed'] as $s): ?>
            <a class="btn btn-outline-secondary" href="admin_found_dog_reports.php?status=<?= h($s) ?>"><?= h(ucfirst($s)) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (($_GET['msg'] ?? '') === 'updated'): ?><div class="alert alert-success">Report updated.</div><?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'email_sent'): ?><div class="alert alert-success">Found-dog email sent to <?= (int) ($_GET['sent'] ?? 0) ?> recipient(s).</div><?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'email_failed'): ?><div class="alert alert-warning">Found-dog email was not sent. Check recipients and mail settings.</div><?php endif; ?>
    <?php if (!$reports): ?><div class="cardx">No found dog location reports yet.</div><?php endif; ?>

    <?php foreach ($reports as $report):
        $location = (string) ($report['finder_location'] ?? '');
        $emailDog = gpFoundDogFetchPublicDog($pdo, (int) $report['dog_id']);
        $locationLink = gpFoundDogLocationLink($emailDog ?: ['id' => (int) $report['dog_id']], $report);
        $emailRecipients = $emailDog ? gpFoundDogRecipientEmails($emailDog) : [];
        $emailSubject = $emailDog ? gpFoundDogEmailSubject($emailDog) : '';
        $emailBody = $emailDog ? gpFoundDogEmailBody($emailDog, $report, $locationLink) : '';
        $emailId = 'foundDogEmail' . (int) $report['id'];
    ?>
        <section class="cardx">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div>
                    <h2 class="h5 mb-1">#<?= h($report['id']) ?> <?= h($report['dog_name']) ?></h2>
                    <div class="meta">Submitted <?= h($report['created_at']) ?> · Status <span class="badge-status"><?= h($report['status']) ?></span> · Notification <?= !empty($report['notification_sent']) ? 'sent' : 'not sent' ?></div>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="dog_profile.php?dog_id=<?= (int) $report['dog_id'] ?>">Dog Profile</a>
            </div>

            <hr>
            <div class="row g-3">
                <div class="col-md-6"><strong>Reported location</strong><br><?= h($location ?: 'GPS only / not typed') ?></div>
                <div class="col-md-6"><strong>Open in Google Maps</strong><br><a class="maplink" href="<?= h($locationLink) ?>" target="_blank" rel="noopener"><?= h($locationLink) ?></a></div>
                <?php if ($report['finder_latitude'] !== null && $report['finder_longitude'] !== null): ?><div class="col-md-6"><strong>GPS</strong><br><?= h($report['finder_latitude']) ?>, <?= h($report['finder_longitude']) ?><?= $report['finder_accuracy_m'] !== null ? ' ±' . h($report['finder_accuracy_m']) . 'm' : '' ?></div><?php endif; ?>
                <div class="col-md-6"><strong>Finder</strong><br><?= h($report['finder_name'] ?: 'Name not provided') ?> · <?= h($report['finder_phone'] ?: 'Phone not provided') ?></div>
                <div class="col-md-6"><strong>Handler</strong><br><?= h($report['handler_name'] ?: 'Not listed') ?> · <?= h($report['handler_phone'] ?: 'No phone') ?> · <?= h($report['handler_email'] ?: 'No email') ?></div>
            </div>

            <?php if (!empty($report['finder_message'])): ?><div class="details mt-3"><?= h($report['finder_message']) ?></div><?php endif; ?>

            <div class="email-template mt-3">
                <h3 class="h6 mb-2">Found-dog email template</h3>
                <p class="text-muted small mb-3">Review or update this preset message before sending it to the handler, owner, or admin fallback.</p>
                <?php if ($emailRecipients): ?>
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="action" value="send_found_email">
                        <input type="hidden" name="report_id" value="<?= h($report['id']) ?>">
                        <div>
                            <label class="form-label fw-semibold">Recipients</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($emailRecipients as $email): ?>
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recipients[]" value="<?= h($email) ?>" checked>
                                        <span class="form-check-label"><?= h($email) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="form-label fw-semibold">Subject</label>
                            <input class="form-control" name="email_subject" value="<?= h($emailSubject) ?>">
                        </div>
                        <div>
                            <label class="form-label fw-semibold" for="<?= h($emailId) ?>">Message</label>
                            <textarea class="form-control" id="<?= h($emailId) ?>" name="email_body"><?= h($emailBody) ?></textarea>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-success">Send Email</button>
                            <button class="btn btn-outline-secondary" type="button" data-copy-target="<?= h($emailId) ?>">Copy Message</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">No valid handler, owner, or admin email address is available for this report.</div>
                <?php endif; ?>
            </div>

            <form method="post" class="d-flex gap-2 align-items-center mt-3 flex-wrap">
                <input type="hidden" name="report_id" value="<?= h($report['id']) ?>">
                <input type="hidden" name="action" value="update_status">
                <label class="form-label mb-0">Status</label>
                <select name="status" class="form-select" style="max-width:220px;">
                    <?php foreach (['new', 'reviewing', 'contacted', 'resolved', 'closed'] as $s): ?>
                        <option value="<?= h($s) ?>" <?= $report['status'] === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary">Update</button>
                <?php if (!empty($report['finder_phone'])): ?><a class="btn btn-outline-success" href="tel:<?= h(preg_replace('/[^0-9+]/', '', (string) $report['finder_phone'])) ?>">Call Finder</a><?php endif; ?>
            </form>
        </section>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>
<script>
document.querySelectorAll('[data-copy-target]').forEach(function(button){
    button.addEventListener('click', function(){
        var target = document.getElementById(button.getAttribute('data-copy-target'));
        if (!target) return;
        target.select();
        navigator.clipboard?.writeText(target.value).then(function(){ button.textContent = 'Copied'; }).catch(function(){ document.execCommand('copy'); button.textContent = 'Copied'; });
    });
});
</script>
</body>
</html>
