<?php
require_once __DIR__ . '/includes/authz.php';
require_once __DIR__ . '/includes/form_ux.php';
require_once __DIR__ . '/includes/brand_header.php';
require_once 'includes/api_auth.php';
require_once 'includes/validation.php';
requireAdmin();
checkLogin();
$userId = (int)$_SESSION['user_id'];
$message = null;
$newToken = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (isset($_POST['create_token'])) {
        $label = cleanText($_POST['token_label'] ?? 'Mobile App', 80);
        $issued = issueApiToken($pdo, $userId, $label);
        $newToken = $issued['token'];
        $message = 'New API token created. Copy it now.';
    } elseif (isset($_POST['revoke_token'])) {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
        $stmt->execute([$tokenId, $userId]);
        $message = 'API token revoked.';
    }
}
$stmt = $pdo->prepare('SELECT id, token_label, token_prefix, created_at, last_used_at, revoked_at FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$tokens = $stmt->fetchAll() ?: [];
$csrf = generateCsrfToken();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>API Tokens</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="styles.css" rel="stylesheet"></head><body>
<?php guidepawBrandHeader(); ?>

<?php require_once 'includes/beta_banner.php'; ?>
<?php require_once 'includes/mobile_nav.php'; ?><div class="container py-4" style="max-width:820px"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">API Tokens</h3><a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a></div><div class="alert alert-info">Use these tokens for the starter mobile/API integration. Tokens are shown only once when created.</div><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($newToken): ?><div class="alert alert-warning"><strong>Copy this token now:</strong><div class="mt-2"><code><?= e($newToken) ?></code></div></div><?php endif; ?><div class="card shadow-sm mb-4"><div class="card-body"><form method="post" class="row g-3 align-items-end"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><div class="col-md-8"><label class="form-label">Token label</label><input type="text" class="form-control" name="token_label" placeholder="Flutter Dev Token"></div><div class="col-md-4"><button class="btn btn-primary w-100" type="submit" name="create_token" value="1">Create token</button></div></form></div></div><div class="card shadow-sm"><div class="card-body"><h5 class="mb-3">Existing tokens</h5><?php if (!$tokens): ?><div class="text-muted">No tokens yet.</div><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Label</th><th>Prefix</th><th>Created</th><th>Last used</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($tokens as $token): ?><tr><td><?= e($token['token_label']) ?></td><td><code><?= e($token['token_prefix']) ?>…</code></td><td><?= e((string)$token['created_at']) ?></td><td><?= e((string)($token['last_used_at'] ?? 'Never')) ?></td><td><?= !empty($token['revoked_at']) ? 'Revoked' : 'Active' ?></td><td><?php if (empty($token['revoked_at'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>"><button class="btn btn-outline-danger btn-sm" type="submit" name="revoke_token" value="1">Revoke</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div><?php guidepawFormUx(); ?>
</body></html>
