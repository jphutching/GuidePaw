<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once __DIR__ . '/includes/api_auth.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = trim((string) ($_GET['token'] ?? ''));
$dogId = (int) ($_GET['dog_id'] ?? 0);
$tokenRow = $token !== '' ? findApiTokenByPlainText($pdo, $token) : null;
$valid = $tokenRow && empty($tokenRow['revoked_at']) && (empty($tokenRow['expires_at']) || strtotime((string) $tokenRow['expires_at']) > time());
$bridgeTitle = $valid ? 'Open GuidePaw Companion on this phone' : 'Wearable pairing link';
$bridgeMessage = $valid
    ? 'This page opens from the QR code so the phone shows a normal pairing screen instead of a raw text note.'
    : 'Create a connect code from Wearable Integrations first.';
$bridgeEndpoint = rtrim((string) appEnv('APP_URL', 'https://guidepaw.app'), '/') . '/api/wearables.php';
$bridgeAppLink = $valid
    ? 'guidepawcompanion://pair?endpoint=' . rawurlencode($bridgeEndpoint) . '&token=' . rawurlencode($token) . '&dog_id=' . rawurlencode((string) $dogId) . '&dog_name=' . rawurlencode((string) $tokenRow['token_label']) . '&source=health_connect'
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GuidePaw Companion Pairing</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #d8dee4; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        .small { color: #5b6472; font-size: 14px; }
        code { display: block; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; word-break: break-all; }
        button, a.button { display: inline-block; padding: 10px 14px; border-radius: 10px; border: 0; background: #0f766e; color: #fff; text-decoration: none; font-weight: 700; cursor: pointer; }
        .row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
<?php guidepawBrandHeader(); ?>
<div class="wrap">
    <div class="card">
        <h1><?= h($bridgeTitle) ?></h1>
        <p class="small"><?= h($bridgeMessage) ?></p>
    </div>

    <?php if ($valid): ?>
        <div class="card">
            <h2 class="h5">Connection details</h2>
            <p class="small">Use this page on the phone that has Samsung Health or Health Connect. It opens here because the QR code points at a browser page instead of a plain text token.</p>
            <p class="small"><strong>1.</strong> Tap <strong>Copy connection code</strong>.<br><strong>2.</strong> Paste it into the GuidePaw Companion app.<br><strong>3.</strong> Return to Wearable Integrations to confirm the next sync.</p>
            <div class="small muted mb-2">Connected account: <?= h((string) $tokenRow['username']) ?></div>
            <div class="small muted mb-2">Label: <?= h((string) $tokenRow['token_label']) ?></div>
            <div class="small muted mb-2">API endpoint</div>
            <code><?= h($bridgeEndpoint) ?></code>
            <div class="small muted mt-3 mb-2">Connection code</div>
            <code id="bridgeToken"><?= h($token) ?></code>
            <div class="row mt-3">
                <button type="button" id="copyToken">Copy connection code</button>
                <?php if ($valid): ?><a class="button" href="<?= h($bridgeAppLink) ?>">Open in GuidePaw Companion</a><?php endif; ?>
                <a class="button" href="wearable_integrations.php">Back to wearable setup</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <h2 class="h5">No connection code yet</h2>
            <p class="small">Go back to Wearable Integrations and create a connect code first. The QR should open this page once a code exists.</p>
            <a class="button" href="wearable_integrations.php">Open wearable setup</a>
        </div>
    <?php endif; ?>
</div>
<?php if ($valid): ?>
<script>
(function () {
    var btn = document.getElementById('copyToken');
    var token = document.getElementById('bridgeToken');
    if (!btn || !token || !navigator.clipboard) return;
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(token.textContent || '').then(function () {
            btn.textContent = 'Copied';
            setTimeout(function () { btn.textContent = 'Copy connection code'; }, 1600);
        });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
