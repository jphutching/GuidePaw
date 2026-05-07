<?php
declare(strict_types=1);

/**
 * Local GuidePaw QA crawler / smoke tester.
 *
 * Usage:
 *   php scripts/local_qa_crawler.php --base-url=http://10.147.18.184 --admin-user=admin --admin-pass='password'
 *
 * Optional:
 *   --regular-user=test --regular-pass='password'
 *   --mark-checklist=yes
 *
 * This script is intentionally conservative. It does not change user roles or delete data.
 */

$options = getopt('', [
    'base-url:',
    'admin-user:',
    'admin-pass:',
    'regular-user::',
    'regular-pass::',
    'mark-checklist::',
]);

$baseUrl = rtrim((string)($options['base-url'] ?? getenv('GUIDEPAW_BASE_URL') ?: 'http://10.147.18.184'), '/');
$adminUser = (string)($options['admin-user'] ?? getenv('GUIDEPAW_ADMIN_USER') ?: 'admin');
$adminPass = (string)($options['admin-pass'] ?? getenv('GUIDEPAW_ADMIN_PASS') ?: '');
$regularUser = (string)($options['regular-user'] ?? getenv('GUIDEPAW_REGULAR_USER') ?: '');
$regularPass = (string)($options['regular-pass'] ?? getenv('GUIDEPAW_REGULAR_PASS') ?: '');
$markChecklist = strtolower((string)($options['mark-checklist'] ?? 'no')) === 'yes';

if ($adminPass === '') {
    fwrite(STDERR, "Missing --admin-pass or GUIDEPAW_ADMIN_PASS.\n");
    exit(2);
}

$results = [];

function gpQaResult(array &$results, string $id, bool $passed, string $detail = ''): void
{
    $results[] = ['id' => $id, 'passed' => $passed, 'detail' => $detail];
    echo ($passed ? 'PASS' : 'FAIL') . " {$id}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function gpQaRequest(string $baseUrl, string $path, string $method = 'GET', array $fields = [], string $cookieFile = ''): array
{
    $url = $baseUrl . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = ['User-Agent: GuidePawLocalQACrawler/1.0'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true,
    ]);
    if ($cookieFile !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'url' => $finalUrl, 'error' => $err];
    }
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize), 'url' => $finalUrl, 'error' => $err];
}

function gpQaLogin(string $baseUrl, string $username, string $password, string $cookieFile): bool
{
    $res = gpQaRequest($baseUrl, 'login.php', 'POST', ['username' => $username, 'password' => $password], $cookieFile);
    return $res['status'] >= 200 && $res['status'] < 400 && !str_contains(strtolower($res['body']), 'invalid username or password');
}

function gpQaPageLooksOk(array $res): bool
{
    $body = strtolower($res['body']);
    if ($res['status'] < 200 || $res['status'] >= 400) return false;
    foreach (['fatal error', 'application error', 'database connection failed', 'uncaught error', 'warning: require'] as $bad) {
        if (str_contains($body, $bad)) return false;
    }
    return true;
}

$adminCookie = tempnam(sys_get_temp_dir(), 'gpqa_admin_');
$regularCookie = tempnam(sys_get_temp_dir(), 'gpqa_user_');

$adminLoggedIn = gpQaLogin($baseUrl, $adminUser, $adminPass, $adminCookie);
gpQaResult($results, 'crawler_admin_login', $adminLoggedIn, $adminLoggedIn ? 'admin login succeeded' : 'admin login failed');

if ($adminLoggedIn) {
    $pages = [
        'dashboard_loads' => 'index.php',
        'notifications_page_loads' => 'notifications.php',
        'qa_checklist_page_loads' => 'beta_qa_checklist.php',
        'admin_users_page_loads' => 'admin_users.php',
        'dog_access_page_loads' => 'dog_access.php',
        'dog_audit_page_loads' => 'dog_access_audit.php',
        'handler_profile_page_loads' => 'handler_profile.php',
        'feedback_page_loads' => 'feedback.php',
    ];
    foreach ($pages as $id => $path) {
        $res = gpQaRequest($baseUrl, $path, 'GET', [], $adminCookie);
        gpQaResult($results, $id, gpQaPageLooksOk($res), 'HTTP ' . $res['status'] . ' ' . basename(parse_url($res['url'], PHP_URL_PATH) ?: $path));
    }

    $adminUsers = gpQaRequest($baseUrl, 'admin_users.php?q=admin', 'GET', [], $adminCookie);
    $adminProtected = gpQaPageLooksOk($adminUsers)
        && str_contains(strtolower($adminUsers['body']), 'protected')
        && str_contains(strtolower($adminUsers['body']), 'built-in admin cannot be downgraded');
    gpQaResult($results, 'builtin_admin_protected_in_ui', $adminProtected, $adminProtected ? 'protected badge/message found' : 'protected marker missing');

    $qaAdmin = gpQaRequest($baseUrl, 'beta_qa_checklist.php', 'GET', [], $adminCookie);
    $adminSeesRoleChecks = str_contains($qaAdmin['body'], 'User Role Permissions') && str_contains($qaAdmin['body'], 'Admin/beta checks');
    gpQaResult($results, 'qa_admin_sees_admin_sections', gpQaPageLooksOk($qaAdmin) && $adminSeesRoleChecks, 'admin checklist visibility');
}

if ($regularUser !== '' && $regularPass !== '') {
    $regularLoggedIn = gpQaLogin($baseUrl, $regularUser, $regularPass, $regularCookie);
    gpQaResult($results, 'crawler_regular_login', $regularLoggedIn, $regularLoggedIn ? 'regular login succeeded' : 'regular login failed');
    if ($regularLoggedIn) {
        $adminPage = gpQaRequest($baseUrl, 'admin_users.php', 'GET', [], $regularCookie);
        $blocked = $adminPage['status'] === 403 || str_contains(strtolower($adminPage['body']), 'admin access required') || str_contains($adminPage['url'], 'index.php');
        gpQaResult($results, 'regular_user_blocked_from_admin_users', $blocked, 'HTTP ' . $adminPage['status']);

        $qaUser = gpQaRequest($baseUrl, 'beta_qa_checklist.php', 'GET', [], $regularCookie);
        $userHidesAdmin = gpQaPageLooksOk($qaUser)
            && str_contains($qaUser['body'], 'admin-only beta checks are hidden')
            && !str_contains($qaUser['body'], 'User Role Permissions');
        gpQaResult($results, 'qa_regular_hides_admin_sections', $userHidesAdmin, 'regular checklist visibility');
    }
} else {
    gpQaResult($results, 'crawler_regular_login', true, 'skipped: no regular credentials provided');
}

if ($markChecklist && $adminLoggedIn) {
    $passedIds = [];
    foreach ($results as $r) {
        if (!empty($r['passed'])) {
            $passedIds['crawler:' . $r['id']] = true;
        }
    }
    $payload = json_encode(['checked_items' => $passedIds, 'notes' => 'Local QA crawler passed ' . count($passedIds) . ' checks at ' . date('c')]);
    $ch = curl_init($baseUrl . '/beta_qa_checklist_state.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_COOKIEJAR => $adminCookie,
        CURLOPT_COOKIEFILE => $adminCookie,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: GuidePawLocalQACrawler/1.0'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    gpQaResult($results, 'crawler_mark_checklist', $status >= 200 && $status < 300 && str_contains((string)$body, '"ok":true'), 'HTTP ' . $status);
}

$failed = array_values(array_filter($results, static fn($r) => empty($r['passed'])));
echo PHP_EOL . 'Summary: ' . (count($results) - count($failed)) . '/' . count($results) . ' passed.' . PHP_EOL;
if ($failed) {
    echo 'Failed checks:' . PHP_EOL;
    foreach ($failed as $r) echo ' - ' . $r['id'] . ': ' . $r['detail'] . PHP_EOL;
}
@unlink($adminCookie);
@unlink($regularCookie);
exit($failed ? 1 : 0);
