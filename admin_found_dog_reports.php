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
body{background:#f3f6fb;color:#1f2937;padding-bottom:90px}.wrap{max-width:1100px;margin:0 auto;padding:18px}.cardx{background:#fff;border:1px solid #dfe3ea;border-radius:18px;padding:18px;margin:14px 0;box-shadow:0 8px 24px rgba(15,23,42,.08)}.meta{color:#6b7280;font-size:.9rem}.badge-status{border-radius:999px;padding:4px 9px;background:#e5e7eb;font-size:.8rem;font-weight:800}.details{white-space:pre-wrap;border-left:4px solid #dbeafe;padding-left:12px}.btn{border-radius:12px;font-weight:800}.maplink{word-break:break-word}
</style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
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
    <?php if (!$reports): ?><div class="cardx">No found dog location reports yet.</div><?php endif; ?>

    <?php foreach ($reports as $report):
        $lat = $report['finder_latitude'] !== null ? (string) $report['finder_latitude'] : '';
        $lng = $report['finder_longitude'] !== null ? (string) $report['finder_longitude'] : '';
        $location = (string) ($report['finder_location'] ?? '');
        $mapUrl = gpFoundDogMapUrl($lat, $lng, $location);
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
                <div class="col-md-6"><strong>Map</strong><br><a class="maplink" href="<?= h($mapUrl) ?>" target="_blank" rel="noopener"><?= h($mapUrl) ?></a></div>
                <?php if ($lat !== '' && $lng !== ''): ?><div class="col-md-6"><strong>GPS</strong><br><?= h($lat) ?>, <?= h($lng) ?><?= $report['finder_accuracy_m'] !== null ? ' ±' . h($report['finder_accuracy_m']) . 'm' : '' ?></div><?php endif; ?>
                <div class="col-md-6"><strong>Finder</strong><br><?= h($report['finder_name'] ?: 'Name not provided') ?> · <?= h($report['finder_phone'] ?: 'Phone not provided') ?></div>
                <div class="col-md-6"><strong>Handler</strong><br><?= h($report['handler_name'] ?: 'Not listed') ?> · <?= h($report['handler_phone'] ?: 'No phone') ?> · <?= h($report['handler_email'] ?: 'No email') ?></div>
            </div>

            <?php if (!empty($report['finder_message'])): ?><div class="details mt-3"><?= h($report['finder_message']) ?></div><?php endif; ?>

            <form method="post" class="d-flex gap-2 align-items-center mt-3 flex-wrap">
                <input type="hidden" name="report_id" value="<?= h($report['id']) ?>">
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
</body>
</html>
