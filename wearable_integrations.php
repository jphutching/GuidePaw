<?php
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/feature_flags.php';
require_once __DIR__ . '/includes/api_auth.php';
require_once __DIR__ . '/includes/wearable_integrations.php';

checkLogin();

if (!featureEnabled($pdo, 'wearable_integrations_enabled')) {
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

$dogsStmt = $pdo->prepare("SELECT id, name FROM dogs WHERE owner_user_id = ? ORDER BY name");
$dogsStmt->execute([$userId]);
$dogs = $dogsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$defaultDogId = (int) ($dogs[0]['id'] ?? 0);
$status = $_GET['status'] ?? '';
$message = '';
$bridgePayload = '';
$bridgeQrUrl = '';
$bridgeLink = '';
$bridgeTokenLabel = '';
$bridgeDogName = '';
$bridgeTokenIssued = false;
$appBaseUrl = rtrim((string) appEnv('APP_URL', 'https://beta.guidepaw.app'), '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (!$dogs) {
        header('Location: wearable_integrations.php?status=need_dog');
        exit;
    }

    if (isset($_POST['connect_wearable'])) {
        $selectedDogId = (int) ($_POST['dog_id'] ?? $defaultDogId);
        if ($selectedDogId <= 0 || !in_array($selectedDogId, array_map(static fn($dog) => (int) $dog['id'], $dogs), true)) {
            $selectedDogId = $defaultDogId;
        }
        $selectedDog = null;
        foreach ($dogs as $dog) {
            if ((int) $dog['id'] === $selectedDogId) {
                $selectedDog = $dog;
                break;
            }
        }

        $bridgeTokenLabel = 'Wearable Bridge';
        if ($selectedDog && !empty($selectedDog['name'])) {
            $bridgeTokenLabel .= ' - ' . trim((string) $selectedDog['name']);
            $bridgeDogName = trim((string) $selectedDog['name']);
        }
        $issued = issueApiToken($pdo, $userId, $bridgeTokenLabel);
        $bridgeTokenIssued = true;
        $bridgeLink = $appBaseUrl . '/wearable_bridge.php?token=' . rawurlencode($issued['token']) . '&dog_id=' . $selectedDogId;
        $bridgeQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($bridgeLink);
        $message = 'Wearable connection code created.';
    }

    if (isset($_POST['save_device_setup'])) {
        $selectedDogId = (int) ($_POST['dog_id'] ?? $defaultDogId);
        if ($selectedDogId <= 0 || !in_array($selectedDogId, array_map(static fn($dog) => (int) $dog['id'], $dogs), true)) {
            $selectedDogId = $defaultDogId;
        }
        gpWearableSaveSetup($pdo, $userId, $selectedDogId, [
            'handler_wearable_slug' => $_POST['handler_wearable_slug'] ?? '',
            'dog_tracker_slug' => $_POST['dog_tracker_slug'] ?? '',
            'sync_mode' => $_POST['sync_mode'] ?? '',
            'notes' => $_POST['notes'] ?? '',
        ]);
        header('Location: wearable_integrations.php?status=setup_saved&dog_id=' . $selectedDogId);
        exit;
    }

    $payload = trim((string) ($_POST['wearable_payload'] ?? ''));
    if (isset($_POST['save_snapshot'])) {
        $parsed = gpWearableParseSummary($payload);
        $data = array_merge($parsed, [
            'dog_id' => (int) ($_POST['dog_id'] ?? $defaultDogId),
            'source' => trim((string) ($_POST['source'] ?? ($parsed['source'] ?? 'manual'))),
            'device_name' => trim((string) ($_POST['device_name'] ?? ($parsed['device_name'] ?? ''))),
            'recorded_for_date' => trim((string) ($_POST['recorded_for_date'] ?? ($parsed['recorded_for_date'] ?? ''))),
            'steps' => $_POST['steps'] ?? ($parsed['steps'] ?? ''),
            'active_minutes' => $_POST['active_minutes'] ?? ($parsed['active_minutes'] ?? ''),
            'distance_miles' => $_POST['distance_miles'] ?? ($parsed['distance_miles'] ?? ''),
            'avg_heart_rate' => $_POST['avg_heart_rate'] ?? ($parsed['avg_heart_rate'] ?? ''),
            'sleep_hours' => $_POST['sleep_hours'] ?? ($parsed['sleep_hours'] ?? ''),
            'summary_text' => trim((string) ($_POST['summary_text'] ?? ($parsed['summary_text'] ?? ''))),
            'notes' => trim((string) ($_POST['notes'] ?? ($parsed['notes'] ?? ''))),
            'raw_payload' => $payload,
        ]);
        $eventId = gpWearableRecordEvent($pdo, $userId, $data);
        writeAuditLog($pdo, 'wearable_sync_recorded', 'wearable_sync_events', $eventId, 'Wearable snapshot recorded.');
        header('Location: wearable_integrations.php?status=saved');
        exit;
    }
}

$selectedDogId = (int) ($_GET['dog_id'] ?? ($_POST['dog_id'] ?? $defaultDogId));
if ($selectedDogId > 0) {
    $allowedDogIds = array_map(static fn($dog) => (int) $dog['id'], $dogs);
    if (!in_array($selectedDogId, $allowedDogIds, true)) {
        $selectedDogId = $defaultDogId;
    }
}
$deviceCatalog = gpWearableCatalogEntries($pdo);
$handlerWearables = array_values(array_filter($deviceCatalog, static fn(array $row): bool => ($row['device_type'] ?? '') === 'handler_wearable'));
$dogTrackers = array_values(array_filter($deviceCatalog, static fn(array $row): bool => ($row['device_type'] ?? '') === 'dog_tracker'));
$syncModes = gpWearableSyncModeOptions();
$currentSetup = $selectedDogId > 0 ? gpWearableCurrentSetup($pdo, $userId, $selectedDogId) : null;
$events = gpWearableRecentEvents($pdo, $userId, $selectedDogId > 0 ? $selectedDogId : null, 12);
$summary = gpWearableTrendSummary($events);
$csrf = generateCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GuidePaw Wearable Integrations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1180px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        label { display:block; font-weight:700; margin-top:12px; }
        input, select, textarea { width:100%; padding:8px; margin-top:4px; box-sizing:border-box; }
        textarea { min-height: 88px; }
        button { margin-top:16px; padding:10px 14px; font-weight:700; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .metric { background:#f8f9fa; border:1px solid #ddd; border-radius:12px; padding:12px; }
        .metric strong { display:block; font-size:1.5rem; }
        .small { color:#666; font-size:13px; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        th, td { border:1px solid #ddd; padding:8px; text-align:left; vertical-align:top; }
        th { background:#eee; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<?php require_once 'includes/mobile_nav.php'; ?>

<div class="wrap">
    <p><a href="index.php">← Dashboard</a></p>
    <h1>Wearable Integrations</h1>
    <p class="small">Connect a watch or collar once, then let GuidePaw bring in the summary automatically.</p>

    <?php if ($status === 'saved'): ?>
        <div class="card">Wearable snapshot saved.</div>
    <?php elseif ($status === 'setup_saved'): ?>
        <div class="card">Wearable device setup saved.</div>
    <?php elseif ($status === 'need_dog'): ?>
        <div class="card">Add a dog profile before saving wearable snapshots.</div>
    <?php endif; ?>
    <?php if ($bridgeTokenIssued && $bridgeQrUrl !== ''): ?>
        <div class="card">Wearable connection code created. Scan the QR from the phone bridge to finish pairing.</div>
    <?php endif; ?>

    <div class="grid">
        <div class="metric"><div class="small">Sync events</div><strong><?= (int) $summary['event_count'] ?></strong></div>
        <div class="metric"><div class="small">Total steps</div><strong><?= (int) $summary['total_steps'] ?></strong></div>
        <div class="metric"><div class="small">Active minutes</div><strong><?= (int) $summary['total_active_minutes'] ?></strong></div>
        <div class="metric"><div class="small">Avg heart rate</div><strong><?= $summary['avg_heart_rate'] === null ? '—' : h(number_format((float) $summary['avg_heart_rate'], 0)) ?></strong></div>
    </div>

    <div class="card">
        <h2 class="h5 mb-2">Device setup</h2>
        <p class="small mb-3">Choose the handler wearable, dog tracker, and sync mode that match the devices you actually use. This is the setup GuidePaw should expect when it pulls meaningful data.</p>
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="save_device_setup" value="1">
            <div class="col-md-6">
                <label class="form-label">Dog</label>
                <select class="form-select" name="dog_id">
                    <?php foreach ($dogs as $dog): ?>
                        <option value="<?= (int) $dog['id'] ?>" <?= $selectedDogId === (int) $dog['id'] ? 'selected' : '' ?>><?= h($dog['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Handler wearable</label>
                <select class="form-select" name="handler_wearable_slug">
                    <option value="">Choose one</option>
                    <?php foreach ($handlerWearables as $row): ?>
                        <option value="<?= h((string) $row['slug']) ?>" <?= ($currentSetup['handler_wearable_slug'] ?? '') === $row['slug'] ? 'selected' : '' ?>><?= h((string) $row['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dog tracker</label>
                <select class="form-select" name="dog_tracker_slug">
                    <option value="">Choose one</option>
                    <?php foreach ($dogTrackers as $row): ?>
                        <option value="<?= h((string) $row['slug']) ?>" <?= ($currentSetup['dog_tracker_slug'] ?? '') === $row['slug'] ? 'selected' : '' ?>><?= h((string) $row['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sync mode</label>
                <select class="form-select" name="sync_mode">
                    <option value="">Choose one</option>
                    <?php foreach ($syncModes as $slug => $mode): ?>
                        <option value="<?= h((string) $slug) ?>" <?= ($currentSetup['sync_mode'] ?? '') === $slug ? 'selected' : '' ?>><?= h((string) $mode['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" placeholder="Use this to note what data you want GuidePaw to trust: steps, sleep, heart rate, GPS, battery alerts, or manual bridge import."><?= h((string) ($currentSetup['notes'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 d-grid d-md-flex gap-2">
                <button type="submit" class="btn btn-primary">Save device setup</button>
            </div>
        </form>
        <?php if ($currentSetup): ?>
            <div class="grid mt-3">
                <div class="metric">
                    <div class="small">Handler wearable</div>
                    <strong style="font-size:1rem;line-height:1.25;"><?= h((string) ($currentSetup['handler_wearable_label'] ?? $currentSetup['handler_wearable_slug'] ?? '')) ?></strong>
                    <?php if (!empty($currentSetup['handler_wearable_pairing_mode'])): ?><div class="small mt-2">Route: <?= h((string) $currentSetup['handler_wearable_pairing_mode']) ?></div><?php endif; ?>
                    <?php if (!empty($currentSetup['handler_wearable_data_focus'])): ?><div class="small mt-1">Focus: <?= h((string) $currentSetup['handler_wearable_data_focus']) ?></div><?php endif; ?>
                </div>
                <div class="metric">
                    <div class="small">Dog tracker</div>
                    <strong style="font-size:1rem;line-height:1.25;"><?= h((string) ($currentSetup['dog_tracker_label'] ?? $currentSetup['dog_tracker_slug'] ?? '')) ?></strong>
                    <?php if (!empty($currentSetup['dog_tracker_pairing_mode'])): ?><div class="small mt-2">Route: <?= h((string) $currentSetup['dog_tracker_pairing_mode']) ?></div><?php endif; ?>
                    <?php if (!empty($currentSetup['dog_tracker_data_focus'])): ?><div class="small mt-1">Focus: <?= h((string) $currentSetup['dog_tracker_data_focus']) ?></div><?php endif; ?>
                </div>
                <div class="metric">
                    <div class="small">Sync mode</div>
                    <strong style="font-size:1rem;line-height:1.25;"><?= h((string) ($currentSetup['sync_mode_label'] ?? $currentSetup['sync_mode'] ?? '')) ?></strong>
                </div>
            </div>
            <p class="small mt-3 mb-0">GuidePaw should expect the metrics that belong to the selected sync mode, then merge them into the same active dog timeline as snapshots, logs, and alerts.</p>
        <?php else: ?>
            <p class="small mt-3 mb-0">Save a setup so GuidePaw knows which vendor data to expect for this dog.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="h5 mb-2">Wearable compatibility catalog</h2>
        <p class="small mb-3">Use this as a practical pairing guide for dog trackers and handler wearables that fit the GuidePaw sync flow.</p>
        <div class="grid">
            <div class="metric">
                <div class="small">Dog trackers</div>
                <div class="small mt-2">
                    Tractive GPS Tracker (Dog 6 / XL)<br>
                    Fi Series 3+ Smart Collar<br>
                    Garmin Alpha / TT series<br>
                    PetPace (2.0 / 3.0)<br>
                    Invoxia Biotracker<br>
                    Halo Collar<br>
                    FitBark<br>
                    PitPat<br>
                    Whistle
                </div>
            </div>
            <div class="metric">
                <div class="small">Handler wearables</div>
                <div class="small mt-2">
                    Garmin (Vivoactive, Forerunner, Fenix, Instinct)<br>
                    Apple Watch (Series / Ultra)<br>
                    Samsung Galaxy Watch (Ultra / 7 / 8)<br>
                    Google Pixel Watch (3 / 4)<br>
                    Whoop (5.0)<br>
                    Oura Ring<br>
                    Fitbit<br>
                    Polar / Suunto
                </div>
            </div>
            <div class="metric">
                <div class="small">Integration tip</div>
                <div class="small mt-2">Garmin watch + Garmin dog system gives the cleanest handler + dog pairing when both sides are in the Garmin ecosystem.</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="h5 mb-2">Connect Wearable</h2>
        <p class="small mb-3">Pick the dog once, then create a connection code. The phone bridge scans the QR and sends future summaries automatically. You do not need to copy an API token.</p>
        <div class="small mb-3">
            Android bridge app:
            <a href="bridge_apk.php" class="btn btn-sm btn-outline-primary ms-1">Bridge APK</a>
            <span class="ms-2">for the current Android phone you are pairing.</span>
        </div>
        <div class="small mb-3">
            Prerequisites on the phone:
            <a href="https://play.google.com/store/apps/details?id=com.sec.android.app.shealth" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary ms-1">Samsung Health</a>
            <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.healthdata" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary ms-1">Health Connect</a>
        </div>
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div class="col-md-6">
                <label>Dog</label>
                <select name="dog_id">
                    <?php foreach ($dogs as $dog): ?>
                        <option value="<?= (int) $dog['id'] ?>" <?= $selectedDogId === (int) $dog['id'] ? 'selected' : '' ?>><?= h($dog['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 d-grid">
                <button type="submit" name="connect_wearable" value="1">Create Connect Code</button>
            </div>
        </form>
        <?php if ($bridgeTokenIssued && $bridgeQrUrl !== ''): ?>
            <div class="row g-3 align-items-center mt-3">
                <div class="col-md-5 text-center">
                    <a href="<?= h($bridgeLink) ?>" class="d-inline-block" aria-label="Open wearable pairing page">
                        <img src="<?= h($bridgeQrUrl) ?>" alt="Wearable connect QR code" style="max-width:260px;width:100%;height:auto;border:1px solid #ddd;border-radius:12px;background:#fff;padding:10px;">
                    </a>
                </div>
                <div class="col-md-7">
                    <div class="fw-semibold mb-2">Connection ready</div>
                    <div class="small mb-2">Scan the QR or tap the button below. Both open the GuidePaw pairing page instead of a raw text note.</div>
                    <div class="small mb-2"><strong>Device:</strong> <?= h($bridgeDogName !== '' ? $bridgeDogName : 'Selected dog') ?></div>
                    <div class="small mb-2"><strong>Source:</strong> Health Connect</div>
                    <div class="small mb-2"><strong>Open link:</strong> <code style="display:inline-block;padding:4px 6px;word-break:break-all;"><?= h($appBaseUrl . '/wearable_bridge.php') ?></code></div>
                    <div class="mt-3">
                        <a href="<?= h($bridgeLink) ?>" style="display:inline-block;padding:10px 14px;background:#0d6efd;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;">Open pairing page</a>
                    </div>
                    <div class="small text-muted">Keep this page open while you pair. The next syncs will show up here automatically.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="small text-muted mt-2">Samsung Health on the phone can feed Health Connect, and the GuidePaw bridge can send data here automatically after pairing on the current phone.</div>
        <?php endif; ?>
    </div>

    <details class="card">
        <summary class="h5 mb-0">Manual entry</summary>
        <div class="mt-3">
            <p class="small mb-2">For a pasted summary or a one-off import, use the manual form below.</p>
            <div class="small mb-2">Source values you can use: <code>health_connect</code>, <code>samsung_health</code>, <code>fitbit</code>, <code>garmin</code>, or <code>manual</code>.</div>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="save_snapshot" value="1">
            <label>Dog</label>
            <select name="dog_id">
                <option value="0" <?= $selectedDogId === 0 ? 'selected' : '' ?>>All dogs</option>
                <?php foreach ($dogs as $dog): ?>
                    <option value="<?= (int) $dog['id'] ?>" <?= $selectedDogId === (int) $dog['id'] ? 'selected' : '' ?>><?= h($dog['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Source</label>
            <input type="text" name="source" value="manual" placeholder="manual, apple_health, fitbit, garmin, collar">
            <label>Device name</label>
            <input type="text" name="device_name" placeholder="Apple Watch, Fitbit Charge, Garmin, etc.">
            <label>Date</label>
            <input type="date" name="recorded_for_date">
            <div class="grid">
                <div><label>Steps</label><input type="number" name="steps" min="0" step="1"></div>
                <div><label>Active minutes</label><input type="number" name="active_minutes" min="0" step="1"></div>
                <div><label>Distance miles</label><input type="number" name="distance_miles" min="0" step="0.01"></div>
                <div><label>Avg heart rate</label><input type="number" name="avg_heart_rate" min="0" step="1"></div>
                <div><label>Sleep hours</label><input type="number" name="sleep_hours" min="0" step="0.1"></div>
            </div>
            <label>Summary</label>
            <textarea name="summary_text" placeholder="Short readable summary of the wearable data."></textarea>
            <label>Notes</label>
            <textarea name="notes" placeholder="What happened? Was the dog calmer on rest days, or more active after outings?"></textarea>
            <label>Paste wearable JSON or key/value text</label>
            <textarea name="wearable_payload" placeholder='{"source":"fitbit","steps":8421,"active_minutes":77,"distance_miles":3.9,"avg_heart_rate":92,"sleep_hours":7.4,"summary_text":"Long walk and calm evening."}'></textarea>
            <button type="submit">Save Snapshot</button>
        </form>
    </div>

    <div class="card">
        <h2 class="h5">Recent syncs</h2>
        <?php if (!$events): ?>
            <p class="small">No wearable snapshots have been recorded yet.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Dog</th>
                    <th>Source</th>
                    <th>Steps</th>
                    <th>Active</th>
                    <th>Distance</th>
                    <th>HR</th>
                    <th>Sleep</th>
                    <th>Summary</th>
                </tr>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= h((string) ($event['recorded_for_date'] ?? $event['created_at'])) ?></td>
                        <td><?= h((string) ($event['dog_name'] ?? 'All dogs')) ?></td>
                        <td><?= h((string) ($event['source'] ?? 'manual')) ?></td>
                        <td><?= h((string) ($event['steps'] ?? '')) ?></td>
                        <td><?= h((string) ($event['active_minutes'] ?? '')) ?></td>
                        <td><?= h((string) ($event['distance_miles'] ?? '')) ?></td>
                        <td><?= h((string) ($event['avg_heart_rate'] ?? '')) ?></td>
                        <td><?= h((string) ($event['sleep_hours'] ?? '')) ?></td>
                        <td><?= h((string) ($event['summary_text'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php guidepawFormUx(); ?>
</body>
</html>
