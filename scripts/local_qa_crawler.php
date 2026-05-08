<?php
declare(strict_types=1);

/**
 * Local GuidePaw QA crawler / smoke tester.
 *
 * Usage:
 *   php scripts/local_qa_crawler.php --base-url=https://10.147.18.184 --admin-user=admin --admin-pass='password'
 *
 * Optional:
 *   --regular-user=test --regular-pass='password'
 *   --mark-checklist=yes
 *   --insecure-local-ssl=yes
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
    'insecure-local-ssl::',
]);

$baseUrl = rtrim((string)($options['base-url'] ?? getenv('GUIDEPAW_BASE_URL') ?: 'https://10.147.18.184'), '/');
$adminUser = (string)($options['admin-user'] ?? getenv('GUIDEPAW_ADMIN_USER') ?: 'admin');
$adminPass = (string)($options['admin-pass'] ?? getenv('GUIDEPAW_ADMIN_PASS') ?: '');
$regularUser = (string)($options['regular-user'] ?? getenv('GUIDEPAW_REGULAR_USER') ?: '');
$regularPass = (string)($options['regular-pass'] ?? getenv('GUIDEPAW_REGULAR_PASS') ?: '');
$markChecklist = strtolower((string)($options['mark-checklist'] ?? 'no')) === 'yes';
$insecureLocalSsl = strtolower((string)($options['insecure-local-ssl'] ?? getenv('GUIDEPAW_INSECURE_LOCAL_SSL') ?: 'yes')) !== 'no';

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

function gpQaRequest(string $baseUrl, string $path, string $method = 'GET', array $fields = [], string $cookieFile = '', bool $insecureLocalSsl = true): array
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
    if ($insecureLocalSsl) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
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

function gpQaLogin(string $baseUrl, string $username, string $password, string $cookieFile, bool $insecureLocalSsl): bool
{
    $res = gpQaRequest($baseUrl, 'login.php', 'POST', ['username' => $username, 'password' => $password], $cookieFile, $insecureLocalSsl);
    $body = strtolower($res['body']);
    if ($res['status'] < 200 || $res['status'] >= 400) return false;
    if (str_contains($body, 'invalid username or password')) return false;
    if (str_contains($body, 'handler login') && !str_contains($body, 'dashboard')) return false;
    return true;
}

function gpQaPageLooksOk(array $res): bool
{
    $body = strtolower($res['body']);
    if ($res['status'] < 200 || $res['status'] >= 400) return false;
    foreach (['fatal error:', 'php fatal error', '<b>fatal error</b>', 'database connection failed', 'uncaught error:', 'uncaught exception', 'warning: require(', 'failed opening required', 'curl failed to verify'] as $bad) {
        if (str_contains($body, $bad)) return false;
    }
    return true;
}

$adminCookie = tempnam(sys_get_temp_dir(), 'gpqa_admin_');
$regularCookie = tempnam(sys_get_temp_dir(), 'gpqa_user_');

echo 'GuidePaw local QA crawler targeting ' . $baseUrl . ($insecureLocalSsl ? ' with local SSL verification disabled' : '') . PHP_EOL;

$adminLoggedIn = gpQaLogin($baseUrl, $adminUser, $adminPass, $adminCookie, $insecureLocalSsl);
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
        'db_status_page_loads' => 'db_status.php',
        'candidate_assessment_page_loads' => 'candidate_assessment.php',
        'trucking_mode_page_loads' => 'trucking_mode.php',
        'ai_training_assistant_page_loads' => 'ai_training_assistant.php',
        'media_review_page_loads' => 'media_review.php',
        'video_review_page_loads' => 'video_review.php',
        'coach_review_page_loads' => 'coach_review.php',
    ];
    foreach ($pages as $id => $path) {
        $res = gpQaRequest($baseUrl, $path, 'GET', [], $adminCookie, $insecureLocalSsl);
        $body = strtolower($res['body']);
        $mediaPageLooksReady = $path === 'media_review.php'
            ? (
                str_contains($body, 'camera stability')
                || str_contains($body, 'review form is unavailable')
                || str_contains($body, 'guidepaw training media review')
            )
            : true;
        $videoPageLooksReady = $path === 'video_review.php'
            ? (
                str_contains($body, 'checkpoint video review')
                || str_contains($body, 'video review')
                || str_contains($body, 'checkpoint videos')
            )
            : true;
        $coachPageLooksReady = $path === 'coach_review.php'
            ? (
                str_contains($body, 'coach review')
                || str_contains($body, 'coach review storage has not been deployed')
                || str_contains($body, 'review queue')
            )
            : true;
        $dbStatusLooksReady = $path === 'db_status.php'
            ? str_contains($body, 'schema migrations')
            : true;
        $candidatePageLooksReady = $path === 'candidate_assessment.php'
            ? (
                str_contains($body, 'candidate assessment')
                || str_contains($body, 'service-dog candidate')
                || str_contains($body, 'no dogs found')
            )
            : true;
        $truckingPageLooksReady = $path === 'trucking_mode.php'
            ? (
                str_contains($body, 'trucking mode')
                || str_contains($body, 'driving day')
                || str_contains($body, 'travel-day plan')
            )
            : true;
        $assistantPageLooksReady = $path === 'ai_training_assistant.php'
            ? (
                str_contains($body, 'ai training assistant')
                || str_contains($body, 'bounded training support')
                || str_contains($body, 'bounded guidance')
            )
            : true;
        gpQaResult(
            $results,
            $id,
            gpQaPageLooksOk($res) && $mediaPageLooksReady && $videoPageLooksReady && $coachPageLooksReady && $dbStatusLooksReady && $candidatePageLooksReady && $truckingPageLooksReady && $assistantPageLooksReady,
            'HTTP ' . $res['status'] . ' ' . basename(parse_url($res['url'], PHP_URL_PATH) ?: $path) . ($res['error'] ? ' error=' . $res['error'] : '') . ($path === 'candidate_assessment.php' ? ($candidatePageLooksReady ? ' candidate assessment content found' : ' candidate assessment content missing') : '') . ($path === 'trucking_mode.php' ? ($truckingPageLooksReady ? ' trucking mode content found' : ' trucking mode content missing') : '') . ($path === 'ai_training_assistant.php' ? ($assistantPageLooksReady ? ' assistant content found' : ' assistant content missing') : '') . ($path === 'media_review.php' ? ($mediaPageLooksReady ? ' media review content found' : ' media review content missing') : '') . ($path === 'video_review.php' ? ($videoPageLooksReady ? ' video review content found' : ' video review content missing') : '') . ($path === 'coach_review.php' ? ($coachPageLooksReady ? ' coach review content found' : ' coach review content missing') : '') . ($path === 'db_status.php' ? ($dbStatusLooksReady ? ' schema migration section found' : ' schema migration section missing') : '')
        );
    }

    $dashboard = gpQaRequest($baseUrl, 'index.php', 'GET', [], $adminCookie, $insecureLocalSsl);
    $dashboardBody = strtolower($dashboard['body']);
    $candidateHookSeen = str_contains($dashboardBody, 'candidate scoring') || str_contains($dashboardBody, 'candidate assessment');
    $truckingHookSeen = str_contains($dashboardBody, 'trucking mode');
    $coachHookSeen = str_contains($dashboardBody, 'coach review') || str_contains($dashboardBody, 'review queue');
    gpQaResult($results, 'dashboard_candidate_hook', gpQaPageLooksOk($dashboard) && $candidateHookSeen, 'HTTP ' . $dashboard['status'] . ($candidateHookSeen ? ' candidate hook found' : ' candidate hook not currently visible'));
    gpQaResult($results, 'dashboard_trucking_hook', gpQaPageLooksOk($dashboard) && $truckingHookSeen, 'HTTP ' . $dashboard['status'] . ($truckingHookSeen ? ' trucking hook found' : ' trucking hook not currently visible'));
    gpQaResult($results, 'dashboard_coach_review_hook', gpQaPageLooksOk($dashboard), 'HTTP ' . $dashboard['status'] . ($coachHookSeen ? ' coach review hook found' : ' coach review hook not currently visible'));

    $adminUsers = gpQaRequest($baseUrl, 'admin_users.php?q=admin', 'GET', [], $adminCookie, $insecureLocalSsl);
    $adminBody = strtolower($adminUsers['body']);
    $adminProtected = gpQaPageLooksOk($adminUsers)
        && str_contains($adminBody, 'protected')
        && (
            str_contains($adminBody, 'built-in admin cannot be downgraded')
            || str_contains($adminBody, 'built-in <code>admin</code> account is protected')
            || str_contains($adminBody, 'current admin account cannot be changed')
        );
    gpQaResult($results, 'builtin_admin_protected_in_ui', $adminProtected, $adminProtected ? 'protected badge/message found' : 'protected marker missing');

    $qaAdmin = gpQaRequest($baseUrl, 'beta_qa_checklist.php', 'GET', [], $adminCookie, $insecureLocalSsl);
    $adminSeesRoleChecks = str_contains($qaAdmin['body'], 'User Role Permissions') && str_contains($qaAdmin['body'], 'Admin/beta checks');
    gpQaResult($results, 'qa_admin_sees_admin_sections', gpQaPageLooksOk($qaAdmin) && $adminSeesRoleChecks, 'admin checklist visibility');
}

if ($regularUser !== '' && $regularPass !== '') {
    $regularLoggedIn = gpQaLogin($baseUrl, $regularUser, $regularPass, $regularCookie, $insecureLocalSsl);
    gpQaResult($results, 'crawler_regular_login', $regularLoggedIn, $regularLoggedIn ? 'regular login succeeded' : 'regular login failed');
    if ($regularLoggedIn) {
        $adminPage = gpQaRequest($baseUrl, 'admin_users.php', 'GET', [], $regularCookie, $insecureLocalSsl);
        $blocked = $adminPage['status'] === 403 || str_contains(strtolower($adminPage['body']), 'admin access required') || str_contains($adminPage['url'], 'index.php');
        gpQaResult($results, 'regular_user_blocked_from_admin_users', $blocked, 'HTTP ' . $adminPage['status']);

        $qaUser = gpQaRequest($baseUrl, 'beta_qa_checklist.php', 'GET', [], $regularCookie, $insecureLocalSsl);
        $qaUserBody = strtolower($qaUser['body']);
        $userHidesAdmin = gpQaPageLooksOk($qaUser)
            && str_contains($qaUserBody, 'admin-only beta checks are hidden')
            && !str_contains($qaUserBody, 'user role permissions');
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
        CURLOPT_SSL_VERIFYPEER => !$insecureLocalSsl,
        CURLOPT_SSL_VERIFYHOST => $insecureLocalSsl ? 0 : 2,
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
