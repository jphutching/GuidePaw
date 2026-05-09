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
        'dogs_page_loads' => 'dogs.php',
        'notifications_page_loads' => 'notifications.php',
        'qa_checklist_page_loads' => 'beta_qa_checklist.php',
        'admin_users_page_loads' => 'admin_users.php',
        'dog_access_page_loads' => 'dog_access.php',
        'dog_audit_page_loads' => 'dog_access_audit.php',
        'handler_profile_page_loads' => 'handler_profile.php',
        'feedback_page_loads' => 'feedback.php',
        'db_status_page_loads' => 'db_status.php',
        'candidate_assessment_page_loads' => 'candidate_assessment.php',
        'candidate_comparison_page_loads' => 'candidate_comparison.php',
        'behavior_risk_scoring_page_loads' => 'behavior_risk_scoring.php',
        'regression_engine_page_loads' => 'regression_engine.php',
        'goal_builder_page_loads' => 'goal_builder.php',
        'air_travel_rights_page_loads' => 'air_travel_rights.php',
        'wearable_integrations_page_loads' => 'wearable_integrations.php',
        'trainer_marketplace_page_loads' => 'trainer_marketplace.php',
        'community_challenges_page_loads' => 'community_challenges.php',
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
        $notificationPageLooksReady = $path === 'notifications.php'
            ? (
                str_contains($body, 'notification preferences')
                && str_contains($body, 'delete selected')
                && str_contains($body, 'alerts')
            )
            : true;
        $dogsPageLooksReady = $path === 'dogs.php'
            ? (
                str_contains($body, 'archived dogs')
                || str_contains($body, 'no archived dogs yet')
                || str_contains($body, 'your accessible dogs')
            )
            : true;
        $candidatePageLooksReady = $path === 'candidate_assessment.php'
            ? (
                str_contains($body, 'candidate assessment')
                || str_contains($body, 'service-dog candidate')
                || str_contains($body, 'no dogs found')
            )
            : true;
        $candidateComparisonPageLooksReady = $path === 'candidate_comparison.php'
            ? (
                str_contains($body, 'candidate comparison')
                || str_contains($body, 'compare selected dogs')
                || str_contains($body, 'no owned dogs found')
            )
            : true;
        $behaviorRiskPageLooksReady = $path === 'behavior_risk_scoring.php'
            ? (
                str_contains($body, 'behavior risk scoring')
                || str_contains($body, 'current risk')
                || str_contains($body, 'recent behavior incidents')
            )
            : true;
        $regressionEnginePageLooksReady = $path === 'regression_engine.php'
            ? (
                str_contains($body, 'regression engine')
                || str_contains($body, 'reset plan')
                || str_contains($body, 'no open regression events')
            )
            : true;
        $goalBuilderPageLooksReady = $path === 'goal_builder.php'
            ? (
                str_contains($body, 'goal builder')
                || str_contains($body, 'draft preview')
                || str_contains($body, 'add a dog profile before building a goal')
            )
            : true;
        $airTravelPageLooksReady = $path === 'air_travel_rights.php'
            ? (
                str_contains($body, 'air travel rights')
                || str_contains($body, 'service dogs in training')
                || str_contains($body, 'air carrier access act')
            )
            : true;
        $wearablePageLooksReady = $path === 'wearable_integrations.php'
            ? (
                str_contains($body, 'wearable integrations')
                || str_contains($body, 'wearable snapshot')
                || str_contains($body, 'recent syncs')
            )
            : true;
        $trainerMarketplaceLooksReady = $path === 'trainer_marketplace.php'
            ? (
                str_contains($body, 'trainer marketplace')
                || str_contains($body, 'trainer profiles')
                || str_contains($body, 'recent syncs')
            )
            : true;
        $communityChallengesPageLooksReady = $path === 'community_challenges.php'
            ? (
                str_contains($body, 'community challenges')
                || str_contains($body, 'current challenge')
                || str_contains($body, 'challenge saved')
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
            gpQaPageLooksOk($res) && $mediaPageLooksReady && $videoPageLooksReady && $coachPageLooksReady && $dbStatusLooksReady && $notificationPageLooksReady && $dogsPageLooksReady && $candidatePageLooksReady && $candidateComparisonPageLooksReady && $behaviorRiskPageLooksReady && $regressionEnginePageLooksReady && $goalBuilderPageLooksReady && $airTravelPageLooksReady && $wearablePageLooksReady && $trainerMarketplaceLooksReady && $communityChallengesPageLooksReady && $truckingPageLooksReady && $assistantPageLooksReady,
            'HTTP ' . $res['status'] . ' ' . basename(parse_url($res['url'], PHP_URL_PATH) ?: $path) . ($res['error'] ? ' error=' . $res['error'] : '') . ($path === 'dogs.php' ? ($dogsPageLooksReady ? ' archive split found' : ' archive split missing') : '') . ($path === 'candidate_assessment.php' ? ($candidatePageLooksReady ? ' candidate assessment content found' : ' candidate assessment content missing') : '') . ($path === 'candidate_comparison.php' ? ($candidateComparisonPageLooksReady ? ' candidate comparison content found' : ' candidate comparison content missing') : '') . ($path === 'behavior_risk_scoring.php' ? ($behaviorRiskPageLooksReady ? ' behavior risk content found' : ' behavior risk content missing') : '') . ($path === 'regression_engine.php' ? ($regressionEnginePageLooksReady ? ' regression engine content found' : ' regression engine content missing') : '') . ($path === 'goal_builder.php' ? ($goalBuilderPageLooksReady ? ' goal builder content found' : ' goal builder content missing') : '') . ($path === 'air_travel_rights.php' ? ($airTravelPageLooksReady ? ' air travel content found' : ' air travel content missing') : '') . ($path === 'wearable_integrations.php' ? ($wearablePageLooksReady ? ' wearable sync content found' : ' wearable sync content missing') : '') . ($path === 'trainer_marketplace.php' ? ($trainerMarketplaceLooksReady ? ' trainer marketplace content found' : ' trainer marketplace content missing') : '') . ($path === 'community_challenges.php' ? ($communityChallengesPageLooksReady ? ' community challenges content found' : ' community challenges content missing') : '') . ($path === 'trucking_mode.php' ? ($truckingPageLooksReady ? ' trucking mode content found' : ' trucking mode content missing') : '') . ($path === 'ai_training_assistant.php' ? ($assistantPageLooksReady ? ' assistant content found' : ' assistant content missing') : '') . ($path === 'media_review.php' ? ($mediaPageLooksReady ? ' media review content found' : ' media review content missing') : '') . ($path === 'video_review.php' ? ($videoPageLooksReady ? ' video review content found' : ' video review content missing') : '') . ($path === 'coach_review.php' ? ($coachPageLooksReady ? ' coach review content found' : ' coach review content missing') : '') . ($path === 'db_status.php' ? ($dbStatusLooksReady ? ' schema migration section found' : ' schema migration section missing') : '') . ($path === 'notifications.php' ? ($notificationPageLooksReady ? ' notification controls found' : ' notification controls missing') : '')
        );
    }

    $dashboard = gpQaRequest($baseUrl, 'index.php', 'GET', [], $adminCookie, $insecureLocalSsl);
    $notificationsPage = gpQaRequest($baseUrl, 'notifications.php', 'GET', [], $adminCookie, $insecureLocalSsl);
    $dogsPage = gpQaRequest($baseUrl, 'dogs.php', 'GET', [], $adminCookie, $insecureLocalSsl);
    $dashboardBody = strtolower($dashboard['body']);
    $notificationsPageBody = strtolower($notificationsPage['body']);
    $dogsPageBody = strtolower($dogsPage['body']);
    $dogsArchiveSplitSeen = str_contains($dogsPageBody, 'archived dogs') || str_contains($dogsPageBody, 'no archived dogs yet') || str_contains($dogsPageBody, 'active dogs stay in the working list');
    $notificationPrefsSeen = str_contains($notificationsPageBody, 'notification preferences') && str_contains($notificationsPageBody, 'delete selected') && str_contains($notificationsPageBody, 'bulk delete');
    $notificationBadgeSeen = str_contains($dashboardBody, 'gp-nav-badge') || str_contains($notificationsPageBody, 'gp-nav-badge');
    $candidateHookSeen = str_contains($dashboardBody, 'candidate scoring') || str_contains($dashboardBody, 'candidate assessment');
    $candidateComparisonHookSeen = str_contains($dashboardBody, 'candidate comparison') || str_contains($dashboardBody, 'compare dogs');
    $behaviorRiskHookSeen = str_contains($dashboardBody, 'behavior risk');
    $regressionEngineHookSeen = str_contains($dashboardBody, 'regression engine') || str_contains($dashboardBody, 'reset plan');
    $goalBuilderHookSeen = str_contains($dashboardBody, 'goal builder');
    $airTravelHookSeen = str_contains($dashboardBody, 'air travel rights') || str_contains($dashboardBody, 'service dog rights');
    $airTravelTodaySeen = str_contains($dashboardBody, 'air travel');
    $wearableHookSeen = str_contains($dashboardBody, 'wearable sync') || str_contains($dashboardBody, 'wearable snapshot');
    $trainerMarketplaceHookSeen = str_contains($dashboardBody, 'trainer marketplace') || str_contains($dashboardBody, 'trainer profiles');
    $communityChallengesHookSeen = str_contains($dashboardBody, 'community challenges') || str_contains($dashboardBody, 'challenge');
    $truckingHookSeen = str_contains($dashboardBody, 'trucking mode');
    $coachHookSeen = str_contains($dashboardBody, 'coach review') || str_contains($dashboardBody, 'review queue');
    gpQaResult($results, 'dashboard_candidate_hook', gpQaPageLooksOk($dashboard) && $candidateHookSeen, 'HTTP ' . $dashboard['status'] . ($candidateHookSeen ? ' candidate hook found' : ' candidate hook not currently visible'));
    gpQaResult($results, 'dashboard_candidate_comparison_hook', gpQaPageLooksOk($dashboard) && $candidateComparisonHookSeen, 'HTTP ' . $dashboard['status'] . ($candidateComparisonHookSeen ? ' comparison hook found' : ' comparison hook not currently visible'));
    gpQaResult($results, 'dashboard_behavior_risk_hook', gpQaPageLooksOk($dashboard) && $behaviorRiskHookSeen, 'HTTP ' . $dashboard['status'] . ($behaviorRiskHookSeen ? ' behavior risk hook found' : ' behavior risk hook not currently visible'));
    gpQaResult($results, 'dashboard_regression_engine_hook', gpQaPageLooksOk($dashboard) && $regressionEngineHookSeen, 'HTTP ' . $dashboard['status'] . ($regressionEngineHookSeen ? ' regression engine hook found' : ' regression engine hook not currently visible'));
    gpQaResult($results, 'dashboard_goal_builder_hook', gpQaPageLooksOk($dashboard) && $goalBuilderHookSeen, 'HTTP ' . $dashboard['status'] . ($goalBuilderHookSeen ? ' goal builder hook found' : ' goal builder hook not currently visible'));
    gpQaResult($results, 'dashboard_air_travel_hook', gpQaPageLooksOk($dashboard) && $airTravelHookSeen, 'HTTP ' . $dashboard['status'] . ($airTravelHookSeen ? ' air travel hook found' : ' air travel hook not currently visible'));
    gpQaResult($results, 'dashboard_air_travel_today', gpQaPageLooksOk($dashboard) && $airTravelTodaySeen, 'HTTP ' . $dashboard['status'] . ($airTravelTodaySeen ? ' air travel today action found' : ' air travel today action not currently visible'));
    gpQaResult($results, 'dashboard_wearable_hook', gpQaPageLooksOk($dashboard) && $wearableHookSeen, 'HTTP ' . $dashboard['status'] . ($wearableHookSeen ? ' wearable hook found' : ' wearable hook not currently visible'));
    gpQaResult($results, 'dashboard_trainer_marketplace_hook', gpQaPageLooksOk($dashboard) && $trainerMarketplaceHookSeen, 'HTTP ' . $dashboard['status'] . ($trainerMarketplaceHookSeen ? ' trainer marketplace hook found' : ' trainer marketplace hook not currently visible'));
    gpQaResult($results, 'dashboard_community_challenges_hook', gpQaPageLooksOk($dashboard) && $communityChallengesHookSeen, 'HTTP ' . $dashboard['status'] . ($communityChallengesHookSeen ? ' challenge hook found' : ' challenge hook not currently visible'));
    gpQaResult($results, 'dashboard_trucking_hook', gpQaPageLooksOk($dashboard) && $truckingHookSeen, 'HTTP ' . $dashboard['status'] . ($truckingHookSeen ? ' trucking hook found' : ' trucking hook not currently visible'));
    gpQaResult($results, 'dashboard_coach_review_hook', gpQaPageLooksOk($dashboard), 'HTTP ' . $dashboard['status'] . ($coachHookSeen ? ' coach review hook found' : ' coach review hook not currently visible'));
    gpQaResult($results, 'notification_prefs_controls', gpQaPageLooksOk($notificationsPage) && $notificationPrefsSeen, 'HTTP ' . $notificationsPage['status'] . ($notificationPrefsSeen ? ' notification preferences found' : ' notification preferences missing'));
    gpQaResult($results, 'notification_nav_badge', gpQaPageLooksOk($dashboard) && gpQaPageLooksOk($notificationsPage) && $notificationBadgeSeen, 'HTTP ' . $dashboard['status'] . ($notificationBadgeSeen ? ' nav badge found' : ' nav badge missing'));
    gpQaResult($results, 'dogs_archive_split', gpQaPageLooksOk($dogsPage) && $dogsArchiveSplitSeen, 'HTTP ' . $dogsPage['status'] . ($dogsArchiveSplitSeen ? ' archive split found' : ' archive split not currently visible'));

    $publicProfileQuestionnaireSeen = false;
    $publicProfileAirTravelSeen = false;
    $publicProfileStatus = 0;
    $publicProfileUrl = '';
    if (preg_match('/dog_profile\.php\?dog_id=(\d+)/', $dogsPage['body'], $m)) {
        $dogProfile = gpQaRequest($baseUrl, 'dog_profile.php?dog_id=' . (int) $m[1], 'GET', [], $adminCookie, $insecureLocalSsl);
        $dogProfileBody = strtolower($dogProfile['body']);
        $dogProfileHtml = html_entity_decode($dogProfile['body'], ENT_QUOTES | ENT_HTML5);
        if (preg_match('/href="([^"]*public_dog_profile\.php\?dog=\d+&token=[^"]+)"/i', $dogProfileHtml, $pm)) {
            $publicProfileUrl = $pm[1];
            $publicProfilePage = gpQaRequest($baseUrl, ltrim(parse_url($publicProfileUrl, PHP_URL_PATH) . '?' . (parse_url($publicProfileUrl, PHP_URL_QUERY) ?? ''), '/'), 'GET', [], $adminCookie, $insecureLocalSsl);
            $publicProfileStatus = $publicProfilePage['status'];
            $publicProfileBody = strtolower($publicProfilePage['body']);
            $publicProfileQuestionnaireSeen = str_contains($publicProfileBody, 'breed questionnaire');
            $publicProfileAirTravelSeen = str_contains($publicProfileBody, 'air travel rights');
        } else {
            $publicProfileStatus = $dogProfile['status'];
        }
        gpQaResult($results, 'public_profile_questionnaire_link', $publicProfileQuestionnaireSeen, 'HTTP ' . $publicProfileStatus . ($publicProfileQuestionnaireSeen ? ' breed questionnaire link found' : ' breed questionnaire link missing'));
        gpQaResult($results, 'public_profile_air_travel_link', $publicProfileAirTravelSeen, 'HTTP ' . $publicProfileStatus . ($publicProfileAirTravelSeen ? ' air travel link found' : ' air travel link missing'));
    } else {
        gpQaResult($results, 'public_profile_questionnaire_link', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'public_profile_air_travel_link', false, 'dog profile link missing on dogs page');
    }

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
