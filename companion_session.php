<?php
require_once __DIR__ . '/includes/api_auth.php';
require_once __DIR__ . '/includes/roles.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

$token = trim((string) ($_POST['access_token'] ?? ''));
$next = trim((string) ($_POST['next'] ?? 'index.php'));

if ($token === '') {
    header('Location: login.php?msg=login_required');
    exit;
}

$tokenRow = findApiTokenByPlainText($pdo, $token);
if (!$tokenRow || !empty($tokenRow['revoked_at']) || (!empty($tokenRow['expires_at']) && strtotime((string) $tokenRow['expires_at']) <= time())) {
    header('Location: login.php?msg=session_expired');
    exit;
}

$user = getUserRecord($pdo, (int) $tokenRow['user_id']);
if (!$user || ((string) ($user['account_status'] ?? '') === 'deactivated')) {
    header('Location: login.php?msg=session_expired');
    exit;
}

$path = parse_url($next, PHP_URL_PATH);
$query = parse_url($next, PHP_URL_QUERY);
$path = is_string($path) ? ltrim($path, '/') : 'index.php';
if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || !str_ends_with($path, '.php')) {
    $path = 'index.php';
    $query = null;
}
$target = $path . (is_string($query) && $query !== '' ? '?' . $query : '');

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['dog_name'] = $user['dog_name'] ?? '';
$_SESSION['username'] = $user['username'] ?? '';
$_SESSION['user_role'] = gpUserRole($user);
$_SESSION['is_admin'] = in_array($_SESSION['user_role'], ['master_admin', 'basic_admin'], true) ? 1 : 0;
$_SESSION['remember_me'] = 1;
$_SESSION['login_expires_at'] = time() + (60 * 60 * 24 * 30);

$stmt = $pdo->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?');
$stmt->execute([(int) $tokenRow['id']]);

header('Location: ' . $target);
exit;
