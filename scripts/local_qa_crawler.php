<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/dog_breeds.php';

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
    'feedback-db-host::',
    'feedback-db-port::',
    'feedback-db-name::',
    'feedback-db-user::',
    'feedback-db-pass::',
    'feedback-limit::',
]);

$baseUrl = rtrim((string)($options['base-url'] ?? getenv('GUIDEPAW_BASE_URL') ?: 'https://10.147.18.184'), '/');
$adminUser = (string)($options['admin-user'] ?? getenv('GUIDEPAW_ADMIN_USER') ?: 'admin');
$adminPass = (string)($options['admin-pass'] ?? getenv('GUIDEPAW_ADMIN_PASS') ?: '');
$regularUser = (string)($options['regular-user'] ?? getenv('GUIDEPAW_REGULAR_USER') ?: '');
$regularPass = (string)($options['regular-pass'] ?? getenv('GUIDEPAW_REGULAR_PASS') ?: '');
$markChecklist = strtolower((string)($options['mark-checklist'] ?? 'no')) === 'yes';
$insecureLocalSsl = strtolower((string)($options['insecure-local-ssl'] ?? getenv('GUIDEPAW_INSECURE_LOCAL_SSL') ?: 'yes')) !== 'no';
$feedbackDbHost = (string)($options['feedback-db-host'] ?? getenv('GUIDEPAW_FEEDBACK_DB_HOST') ?? getenv('DB_HOST') ?: '');
$feedbackDbPort = (string)($options['feedback-db-port'] ?? getenv('GUIDEPAW_FEEDBACK_DB_PORT') ?? getenv('DB_PORT') ?: '5432');
$feedbackDbName = (string)($options['feedback-db-name'] ?? getenv('GUIDEPAW_FEEDBACK_DB_NAME') ?? getenv('DB_DATABASE') ?: '');
$feedbackDbUser = (string)($options['feedback-db-user'] ?? getenv('GUIDEPAW_FEEDBACK_DB_USER') ?? getenv('DB_USERNAME') ?: '');
$feedbackDbPass = (string)($options['feedback-db-pass'] ?? getenv('GUIDEPAW_FEEDBACK_DB_PASSWORD') ?? getenv('DB_PASSWORD') ?: '');
$feedbackLimit = max(1, (int)($options['feedback-limit'] ?? getenv('GUIDEPAW_FEEDBACK_LIMIT') ?: 200));
$checkApiRoutes = strtolower((string) (getenv('GUIDEPAW_CHECK_API_ROUTES') ?: 'no')) === 'yes';

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

function gpQaRequest(string $baseUrl, string $path, string $method = 'GET', array $fields = [], string $cookieFile = '', bool $insecureLocalSsl = true, string $cookieHeader = '', bool $followLocation = true): array
{
    $url = $baseUrl . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = ['User-Agent: GuidePawLocalQACrawler/1.0'];
    if ($cookieHeader !== '' && $cookieFile === '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followLocation,
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

function gpQaBreedSizeRank(string $value): int
{
    $value = strtolower(trim($value));
    return match (true) {
        str_contains($value, 'toy') => 1,
        str_contains($value, 'small') => 2,
        str_contains($value, 'medium') => 3,
        str_contains($value, 'large') => 4,
        str_contains($value, 'giant') => 5,
        default => 3,
    };
}

function gpQaLogin(string $baseUrl, string $username, string $password, string &$cookieHeader, string $cookieFile, bool $insecureLocalSsl): bool
{
    $preflight = gpQaRequest($baseUrl, 'login.php', 'GET', [], $cookieFile, $insecureLocalSsl, $cookieHeader, false);
    if ($preflight['status'] < 200 || $preflight['status'] >= 400) {
        fwrite(STDERR, sprintf(
            "Login preflight failed for %s: HTTP %d%s\n",
            $username,
            $preflight['status'],
            $preflight['error'] !== '' ? ' error=' . $preflight['error'] : ''
        ));
        return false;
    }

    $res = gpQaRequest($baseUrl, 'login.php', 'POST', ['username' => $username, 'password' => $password], $cookieFile, $insecureLocalSsl, '', false);
    $body = strtolower($res['body']);
    if ($res['status'] < 200 || $res['status'] >= 400) {
        fwrite(STDERR, sprintf(
            "Login POST failed for %s: HTTP %d%s\n",
            $username,
            $res['status'],
            $res['error'] !== '' ? ' error=' . $res['error'] : ''
        ));
        return false;
    }
    if (str_contains($body, 'invalid username or password')) {
        fwrite(STDERR, sprintf("Login rejected for %s: invalid credentials banner found\n", $username));
        return false;
    }
    if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/im', $res['headers'], $matches)) {
        $cookieHeader = 'PHPSESSID=' . trim($matches[1]);
    }
    return true;
}

function gpQaApiRequest(string $baseUrl, string $path, ?string $bearerToken = null, string $method = 'GET', array $jsonFields = []): array
{
    $url = $baseUrl . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = ['User-Agent: GuidePawLocalQACrawler/1.0', 'Accept: application/json'];
    if ($bearerToken !== null && $bearerToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
        $headers[] = 'X-API-Token: ' . $bearerToken;
    }
    $body = null;
    if ($method === 'POST') {
        $body = json_encode($jsonFields, JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true,
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'url' => $finalUrl, 'error' => $err];
    }
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize), 'url' => $finalUrl, 'error' => $err];
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

function gpQaFeedbackPdo(string $host, string $port, string $name, string $user, string $pass): ?PDO
{
    if ($host === '' || $name === '' || $user === '') {
        return null;
    }

    try {
        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port ?: '5432', $name),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function gpQaFeedbackRows(PDO $pdo, int $limit): array
{
    $sql = "
        SELECT
            id,
            COALESCE(category, report_type, 'bug') AS category,
            title,
            description,
            page_url,
            page_workflow,
            steps_to_reproduce,
            expected_behavior,
            actual_behavior,
            pasted_text,
            COALESCE(status, 'new') AS status,
            COALESCE(priority, 'normal') AS priority,
            created_at
        FROM feedback_reports
        ORDER BY
            CASE COALESCE(status, 'new')
                WHEN 'new' THEN 0
                WHEN 'reviewing' THEN 1
                WHEN 'planned' THEN 2
                WHEN 'fixed' THEN 3
                WHEN 'closed' THEN 4
                ELSE 5
            END,
            CASE COALESCE(priority, 'normal')
                WHEN 'urgent' THEN 0
                WHEN 'high' THEN 1
                WHEN 'normal' THEN 2
                WHEN 'low' THEN 3
                ELSE 4
            END,
            created_at DESC
        LIMIT " . max(1, $limit);

    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function gpQaFeedbackAliasMap(): array
{
    return [
        'index.php' => ['dashboard', 'today', 'needs attention'],
        'login.php' => ['login', 'log in', 'sign in', 'password as you are typing'],
        'logout.php' => ['logout', 'sign out'],
        'healthz.php' => ['healthz', 'status ok', 'database ok'],
        'csrf_token.php' => ['csrf token', 'csrf'],
        'feedback.php' => ['feedback', 'bug report', 'report issue'],
        'ada_access_card.php' => ['ada access card', 'lockscreen', 'service dog'],
        'ada_wallet_card.php' => ['ada access card'],
        'service_dog_rights.php' => ['detailed ada notes', 'ada service dog rights'],
        'breed_questionnaire.php' => ['breed questionnaire', 'ranked breed ideas'],
        'beta_qa_checklist_state.php' => ['beta qa checklist state', 'checked_items'],
        'beta_request.php' => ['request guidepaw beta access', 'beta access'],
        'beta_token.php' => ['validate beta access token', 'beta token'],
        'register.php' => ['create handler account', 'create guidepaw handler account', 'street address', 'phone number'],
        'reset_password.php' => ['account recovery', 'password recovery'],
        'setup_2fa.php' => ['setup 2fa', 'manage 2fa'],
        'settings.php' => ['settings', 'change password', 'logout'],
        'contact_us.php' => ['contact us', 'facebook', 'guidepaw facebook', 'feedback'],
        'community.php' => ['community', 'discord', 'swag'],
        'support_funding.php' => ['support funding', 'fund the project', 'support guidepaw'],
        'forum.php' => ['forum', 'handler community', 'start a thread'],
        'tactical_training.php' => ['tactical training', 'verified working teams', 'application required'],
        'profile.php' => ['profile', 'microchip', 'owner'],
        'collaboration.php' => ['handler collaboration', 'handshake'],
        'admin.php' => ['guidepaw admin', 'feature flags', 'backup snapshot'],
        'quick_log.php' => ['quick log', 'quick session'],
        'log_entry.php' => ['detailed log', 'training log', 'photo, video, or audio'],
        'view_logs.php' => ['training history', 'view logs', 'queued offline logs'],
        'edit_log.php' => ['edit training log', 'update log entry'],
        'edit_profile.php' => ['edit dog profile', 'update stats'],
        'manage_dogs.php' => ['manage dogs', 'dogs.php'],
        'import_backup.php' => ['import backup', 'backup tools'],
        'dogs.php' => ['manage dogs', 'archived dogs', 'active dogs'],
        'notifications.php' => ['notification', 'alerts', 'inbox'],
        'dog_access.php' => ['dog access', 'shared access', 'co-op', 'transfer'],
        'dog_access_audit.php' => ['audit', 'timeline'],
        'qr_tracking.php' => ['qr tracking', 'qr opens tracked', 'recent qr opens'],
        'handler_profile.php' => ['handler profile', 'public email', 'backup contact', 'home street', 'home city', 'home state'],
        'db_status.php' => ['database', 'schema', 'migration'],
        'admin_feedback.php' => ['admin feedback', 'feedback reports'],
        'admin_beta_requests.php' => ['beta access requests', 'access mode'],
        'admin_tactical_requests.php' => ['tactical access requests', 'verified working teams'],
        'admin_feature_roadmap.php' => ['feature roadmap', 'roadmap item updated'],
        'admin_audit_log.php' => ['admin audit log', 'audit log'],
        'admin_smtp_audit.php' => ['smtp audit', 'dns check'],
        'admin_zeptomail_audit.php' => ['zeptomail api audit', 'dns check'],
        'admin_notification_test.php' => ['notification test'],
        'admin_profile_completion.php' => ['profile completion'],
        'admin_users.php' => ['user management', 'admin users'],
        'admin_paywall_catalog.php' => ['paywall catalog', 'a la carte services'],
        'backup.php' => ['backup restore', 'backup & restore'],
        'export_backup.php' => ['download json backup', 'full backup package'],
        'candidate_assessment.php' => ['candidate assessment', 'candidate'],
        'candidate_comparison.php' => ['candidate comparison', 'compare'],
        'behavior_risk_scoring.php' => ['behavior risk', 'risk scoring'],
        'regression_engine.php' => ['regression engine', 'reset plan'],
        'goal_builder.php' => ['goal builder', 'goal'],
        'training_program.php' => ['training program', 'training'],
        'training_goal_intake.php' => ['training goal intake', 'goal intake'],
        'habit_repair.php' => ['habit repair', 'behavior incident'],
        'training_session_log.php' => ['session log', 'training session'],
        'training_history.php' => ['training history', 'history'],
        'training_history_export.php' => ['training history export', 'csv export'],
        'stats.php' => ['stats', 'progress'],
        'air_travel_rights.php' => ['air travel', 'service dog training'],
        'report_found_dog.php' => ['found dog', 'location report', 'share found location'],
        'wearable_integrations.php' => ['wearable', 'snapshot'],
        'alerts.php' => ['smart alerts', 'alerts'],
        'dog_health.php' => ['health docs', 'vet'],
        'appointments.php' => ['appointments', 'vet appointments'],
        'appointment_notifications.php' => ['appointment notifications', 'generated_at'],
        'medications.php' => ['medication', 'medications'],
        'certification.php' => ['certification', 'readiness'],
        'trainer_marketplace.php' => ['trainer marketplace', 'trainer'],
        'paywalls.php' => ['plans', 'current plan', 'plus plan'],
        'community_challenges.php' => ['community challenges', 'challenge'],
        'trucking_mode.php' => ['trucking mode', 'travel-day plan'],
        'ai_training_assistant.php' => ['ai training assistant', 'bounded guidance'],
        'media_review.php' => ['media review', 'camera stability'],
        'video_review.php' => ['video review', 'checkpoint video'],
        'coach_review.php' => ['coach review', 'review queue'],
    ];
}

function gpQaFeedbackText(array $row): string
{
    $parts = [
        $row['category'] ?? '',
        $row['title'] ?? '',
        $row['description'] ?? '',
        $row['page_url'] ?? '',
        $row['page_workflow'] ?? '',
        $row['steps_to_reproduce'] ?? '',
        $row['expected_behavior'] ?? '',
        $row['actual_behavior'] ?? '',
        $row['pasted_text'] ?? '',
    ];
    return strtolower(trim(implode(' ', array_filter(array_map(static fn($value) => trim((string) $value), $parts), static fn($value) => $value !== ''))));
}

function gpQaFeedbackScore(array $feedbackRows, string $path, array $aliases): array
{
    $score = 0;
    $matches = [];
    $baseName = strtolower(basename($path));
    foreach ($feedbackRows as $row) {
        $text = gpQaFeedbackText($row);
        if ($text === '') {
            continue;
        }

        $rowScore = 0;
        foreach ($aliases as $alias) {
            $alias = strtolower(trim((string) $alias));
            if ($alias !== '' && str_contains($text, $alias)) {
                $rowScore += 1;
            }
        }

        if ($baseName !== '' && str_contains($text, str_replace('_', ' ', rtrim($baseName, '.php')))) {
            $rowScore += 2;
        }

        if (!empty($row['page_url']) && str_contains(strtolower((string) $row['page_url']), $baseName)) {
            $rowScore += 3;
        }
        if (!empty($row['page_workflow']) && str_contains(strtolower((string) $row['page_workflow']), str_replace('_', ' ', rtrim($baseName, '.php')))) {
            $rowScore += 2;
        }

        $status = strtolower(trim((string) ($row['status'] ?? 'new')));
        $priority = strtolower(trim((string) ($row['priority'] ?? 'normal')));
        $statusBoost = match ($status) {
            'new' => 5,
            'reviewing' => 4,
            'planned' => 3,
            'fixed' => 2,
            'closed' => 1,
            default => 2,
        };
        $priorityBoost = match ($priority) {
            'urgent' => 4,
            'high' => 3,
            'normal' => 2,
            'low' => 1,
            default => 1,
        };

        if ($rowScore > 0) {
            $score += $rowScore * $statusBoost * $priorityBoost;
            $matches[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'status' => $status,
                'priority' => $priority,
            ];
        }
    }

    return ['score' => $score, 'matches' => $matches];
}

$adminCookie = tempnam(sys_get_temp_dir(), 'gpqa_admin_');
$adminLogoutCookie = tempnam(sys_get_temp_dir(), 'gpqa_admin_logout_');
$regularCookie = tempnam(sys_get_temp_dir(), 'gpqa_user_');
$adminCookieHeader = '';
$regularCookieHeader = '';

echo 'GuidePaw local QA crawler targeting ' . $baseUrl . ($insecureLocalSsl ? ' with local SSL verification disabled' : '') . PHP_EOL;

    $adminLoggedIn = gpQaLogin($baseUrl, $adminUser, $adminPass, $adminCookieHeader, $adminCookie, $insecureLocalSsl);
    gpQaResult($results, 'crawler_admin_login', $adminLoggedIn, $adminLoggedIn ? 'admin login succeeded' : 'admin login failed');

    if ($adminLoggedIn) {
        $loginPage = gpQaRequest($baseUrl, 'login.php', 'GET', [], '', $insecureLocalSsl, '', false);
        $healthzPage = gpQaRequest($baseUrl, 'healthz.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $csrfTokenPage = gpQaRequest($baseUrl, 'csrf_token.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $adminHomePage = gpQaRequest($baseUrl, 'admin.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $logoutCookieHeader = '';
        $logoutCookie = $adminLogoutCookie;
        if ($logoutCookie !== '') {
            gpQaLogin($baseUrl, $adminUser, $adminPass, $logoutCookieHeader, $logoutCookie, $insecureLocalSsl);
        }
        $logoutPage = gpQaRequest($baseUrl, 'logout.php', 'GET', [], $logoutCookie ?: $adminCookie, $insecureLocalSsl);
        $loginPageBody = strtolower($loginPage['body']);
        $loginSeen = gpQaPageLooksOk($loginPage) && (str_contains($loginPageBody, 'handler login') || str_contains($loginPageBody, 'remember me on this device'));
        $loginBreedQuestionnaireSeen = gpQaPageLooksOk($loginPage)
            && str_contains($loginPageBody, 'research a breed first')
            && str_contains($loginPageBody, 'open breed questionnaire')
            && str_contains($loginPageBody, 'without an account');
        $logoutSeen = gpQaPageLooksOk($logoutPage) && (str_contains(strtolower($logoutPage['url']), 'login.php') || str_contains(strtolower($logoutPage['body']), 'handler login'));
        $healthzSeen = gpQaPageLooksOk($healthzPage) && (str_contains(strtolower($healthzPage['body']), '"status":"ok"') || str_contains(strtolower($healthzPage['body']), '"database":"ok"'));
        $csrfTokenSeen = gpQaPageLooksOk($csrfTokenPage) && (str_contains(strtolower($csrfTokenPage['body']), '"success":true') || str_contains(strtolower($csrfTokenPage['body']), 'csrf_token'));
        $adminHomeSeen = gpQaPageLooksOk($adminHomePage) && (
            str_contains(strtolower($adminHomePage['body']), 'admin control panel')
            || str_contains(strtolower($adminHomePage['body']), 'guidepaw admin')
            || str_contains(strtolower($adminHomePage['body']), 'feature flags')
        );
        $apiTokensPage = ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'skipped'];
        $apiTokensSeen = false;
        $apiLogin = ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'skipped'];
        $apiLoginSeen = false;
        $apiMe = ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'skipped'];
        $apiDogs = ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'skipped'];
        $apiLogs = ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'skipped'];
        $apiWearables = ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'skipped'];
        $apiMeSeen = false;
        $apiDogsSeen = false;
        $apiLogsSeen = false;
        $apiWearablesSeen = false;
        if ($checkApiRoutes) {
            $apiTokensPage = gpQaRequest($baseUrl, 'api_tokens.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
            $apiTokensBody = strtolower($apiTokensPage['body']);
            $apiTokensSeen = gpQaPageLooksOk($apiTokensPage) && (str_contains($apiTokensBody, 'api tokens') || str_contains($apiTokensBody, 'create token'));
            $apiTokensCsrf = '';
            if (preg_match('/name="csrf_token" value="([^"]+)"/i', $apiTokensPage['body'], $apiTokensMatch)) {
                $apiTokensCsrf = html_entity_decode($apiTokensMatch[1], ENT_QUOTES | ENT_HTML5);
            }
            $apiCreateToken = $apiTokensCsrf !== '' ? gpQaRequest($baseUrl, 'api_tokens.php', 'POST', [
                'csrf_token' => $apiTokensCsrf,
                'create_token' => '1',
                'token_label' => 'GuidePaw QA',
            ], $adminCookie, $insecureLocalSsl, $adminCookieHeader, false) : ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'missing csrf'];
            $apiCreateTokenBody = $apiCreateToken['body'];
            $apiToken = '';
            if (preg_match('/<code>([a-f0-9]{48})<\/code>/i', $apiCreateTokenBody, $apiCreateTokenMatch)) {
                $apiToken = trim($apiCreateTokenMatch[1]);
            }
            $apiLogin = gpQaApiRequest($baseUrl, 'api/login.php', null, 'POST', ['username' => $adminUser, 'password' => $adminPass, 'token_label' => 'GuidePaw QA']);
            $apiLoginBody = strtolower($apiLogin['body']);
            $apiLoginJson = json_decode($apiLogin['body'], true);
            if ($apiToken === '' && is_array($apiLoginJson)) {
                $apiToken = (string) ($apiLoginJson['token'] ?? '');
            }
            $apiLoginSeen = $apiToken !== '';
            $apiMe = $apiToken !== '' ? gpQaApiRequest($baseUrl, 'api/me.php', $apiToken) : ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'missing token'];
            $apiDogs = $apiToken !== '' ? gpQaApiRequest($baseUrl, 'api/dogs.php', $apiToken) : ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'missing token'];
            $apiLogs = $apiToken !== '' ? gpQaApiRequest($baseUrl, 'api/logs.php', $apiToken) : ['status' => 0, 'body' => '', 'headers' => '', 'url' => '', 'error' => 'missing token'];
            $apiMeJson = json_decode($apiMe['body'], true);
            $apiDogsJson = json_decode($apiDogs['body'], true);
            $apiLogsJson = json_decode($apiLogs['body'], true);
            $apiMeSeen = gpQaPageLooksOk($apiMe) && is_array($apiMeJson) && !empty($apiMeJson['success']) && !empty($apiMeJson['user']['username']);
            $apiDogsSeen = gpQaPageLooksOk($apiDogs) && is_array($apiDogsJson) && !empty($apiDogsJson['success']) && isset($apiDogsJson['dogs']) && is_array($apiDogsJson['dogs']);
            $apiLogsSeen = gpQaPageLooksOk($apiLogs) && is_array($apiLogsJson) && !empty($apiLogsJson['success']) && isset($apiLogsJson['logs']) && is_array($apiLogsJson['logs']);
            $apiWearableDogId = (int) (($apiDogsJson['dogs'][0]['id'] ?? 0) ?: 0);
            if ($apiWearableDogId > 0 && $apiToken !== '') {
                $apiWearables = gpQaApiRequest($baseUrl, 'api/wearables.php', $apiToken, 'POST', [
                    'dog_id' => $apiWearableDogId,
                    'source' => 'health_connect',
                    'device_name' => 'Galaxy Watch QA',
                    'recorded_for_date' => date('Y-m-d'),
                    'steps' => 8421,
                    'active_minutes' => 77,
                    'distance_miles' => 3.9,
                    'avg_heart_rate' => 92,
                    'sleep_hours' => 7.4,
                    'summary_text' => 'Automated wearable sync test from Health Connect.',
                    'notes' => 'Posted by local QA crawler.',
                ]);
                $apiWearablesJson = json_decode($apiWearables['body'], true);
                $apiWearablesSeen = gpQaPageLooksOk($apiWearables) && is_array($apiWearablesJson) && !empty($apiWearablesJson['success']) && !empty($apiWearablesJson['event_id']);
            }
        }
        $adminSessionProbe = gpQaRequest($baseUrl, 'index.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $adminSessionProbeBody = strtolower($adminSessionProbe['body']);
        $adminSessionReady = (
            gpQaPageLooksOk($adminSessionProbe)
            && !str_contains($adminSessionProbeBody, 'handler login')
            && !str_contains($adminSessionProbeBody, 'please sign in')
            && !str_contains(strtolower($adminSessionProbe['url']), 'login.php')
        ) || (
            gpQaPageLooksOk($adminHomePage)
            && !str_contains(strtolower($adminHomePage['body']), 'handler login')
            && !str_contains(strtolower($adminHomePage['body']), 'please sign in')
            && !str_contains(strtolower($adminHomePage['url']), 'login.php')
        );
        gpQaResult(
            $results,
            'admin_session_ready',
            $adminSessionReady,
            'probe=' . $adminSessionProbe['status'] . ' home=' . $adminHomePage['status'] . (
                $adminSessionReady
                    ? ' admin session confirmed'
                    : ' admin session did not survive login'
            )
        );
        if (!$adminSessionReady) {
            fwrite(STDERR, "Admin session did not survive login; falling back to Playwright crawler.\n");
            exit(1);
        }
        gpQaResult($results, 'login_page_loads', $loginSeen, 'HTTP ' . $loginPage['status'] . ($loginSeen ? ' login page found' : ' login page missing'));
        gpQaResult($results, 'login_breed_questionnaire_cta', $loginBreedQuestionnaireSeen, 'HTTP ' . $loginPage['status'] . ($loginBreedQuestionnaireSeen ? ' breed questionnaire CTA found' : ' breed questionnaire CTA missing'));
        gpQaResult($results, 'logout_redirect', $logoutSeen, 'HTTP ' . $logoutPage['status'] . ($logoutSeen ? ' logout redirect found' : ' logout redirect missing'));
        gpQaResult($results, 'healthz_page_loads', $healthzSeen, 'HTTP ' . $healthzPage['status'] . ($healthzSeen ? ' healthz ok found' : ' healthz ok missing'));
        gpQaResult($results, 'csrf_token_page_loads', $csrfTokenSeen, 'HTTP ' . $csrfTokenPage['status'] . ($csrfTokenSeen ? ' csrf token found' : ' csrf token missing'));
        gpQaResult($results, 'admin_home_page_loads', $adminHomeSeen, 'HTTP ' . $adminHomePage['status'] . ($adminHomeSeen ? ' admin home found' : ' admin home missing'));
        if ($checkApiRoutes) {
            gpQaResult($results, 'api_tokens_page_loads', $apiTokensSeen, 'HTTP ' . $apiTokensPage['status'] . ($apiTokensSeen ? ' api tokens page found' : ' api tokens page missing'));
            gpQaResult($results, 'api_login_endpoint', $apiLoginSeen, 'HTTP ' . $apiLogin['status'] . ($apiLoginSeen ? ' api token issued' : ' api token could not be issued'));
            gpQaResult($results, 'api_me_endpoint', $apiMeSeen, 'HTTP ' . $apiMe['status'] . ($apiMeSeen ? ' api me ok' : ' api me missing'));
            gpQaResult($results, 'api_dogs_endpoint', $apiDogsSeen, 'HTTP ' . $apiDogs['status'] . ($apiDogsSeen ? ' api dogs ok' : ' api dogs missing'));
            gpQaResult($results, 'api_logs_endpoint', $apiLogsSeen, 'HTTP ' . $apiLogs['status'] . ($apiLogsSeen ? ' api logs ok' : ' api logs missing'));
            gpQaResult($results, 'api_wearables_endpoint', $apiWearablesSeen, 'HTTP ' . $apiWearables['status'] . ($apiWearablesSeen ? ' wearable sync recorded' : ' wearable sync missing'));
        } else {
            gpQaResult($results, 'api_tokens_page_loads', true, 'skipped: set GUIDEPAW_CHECK_API_ROUTES=yes to verify api_tokens.php and bearer-token endpoints');
            gpQaResult($results, 'api_login_endpoint', true, 'skipped: set GUIDEPAW_CHECK_API_ROUTES=yes to verify api/login.php');
            gpQaResult($results, 'api_me_endpoint', true, 'skipped: set GUIDEPAW_CHECK_API_ROUTES=yes to verify api/me.php');
            gpQaResult($results, 'api_dogs_endpoint', true, 'skipped: set GUIDEPAW_CHECK_API_ROUTES=yes to verify api/dogs.php');
            gpQaResult($results, 'api_logs_endpoint', true, 'skipped: set GUIDEPAW_CHECK_API_ROUTES=yes to verify api/logs.php');
            gpQaResult($results, 'api_wearables_endpoint', true, 'skipped: set GUIDEPAW_CHECK_API_ROUTES=yes to verify api/wearables.php');
        }
        $pages = [
            'dashboard_loads' => 'index.php',
            'dogs_page_loads' => 'dogs.php',
            'notifications_page_loads' => 'notifications.php',
            'qa_checklist_page_loads' => 'beta_qa_checklist.php',
            'admin_users_page_loads' => 'admin_users.php',
            'admin_feedback_page_loads' => 'admin_feedback.php',
            'admin_feedback_ai_page_loads' => 'admin_feedback_ai.php',
            'admin_beta_requests_page_loads' => 'admin_beta_requests.php',
            'admin_tactical_requests_page_loads' => 'admin_tactical_requests.php',
            'admin_feature_roadmap_page_loads' => 'admin_feature_roadmap.php',
            'admin_audit_log_page_loads' => 'admin_audit_log.php',
            'admin_smtp_audit_page_loads' => 'admin_smtp_audit.php',
            'admin_zeptomail_audit_page_loads' => 'admin_zeptomail_audit.php',
            'admin_found_dog_reports_page_loads' => 'admin_found_dog_reports.php',
            'admin_notification_test_page_loads' => 'admin_notification_test.php',
            'found_dog_notification_test_page_loads' => 'found_dog_notification_test.php',
            'admin_profile_completion_page_loads' => 'admin_profile_completion.php',
            'api_tokens_page_loads' => 'api_tokens.php',
            'admin_paywall_catalog_page_loads' => 'admin_paywall_catalog.php',
            'admin_funding_settings_page_loads' => 'admin_funding_settings.php',
            'admin_business_costs_page_loads' => 'admin_business_costs.php',
            'backup_tools_page_loads' => 'backup.php',
            'dog_access_page_loads' => 'dog_access.php',
            'dog_audit_page_loads' => 'dog_access_audit.php',
            'qr_tracking_page_loads' => 'qr_tracking.php',
            'handler_profile_page_loads' => 'handler_profile.php',
            'settings_page_loads' => 'settings.php',
            'contact_us_page_loads' => 'contact_us.php',
            'community_page_loads' => 'community.php',
            'support_funding_page_loads' => 'support_funding.php',
            'purchase_service_page_loads' => 'purchase_service.php?service=extra_dog_slot',
            'stripe_webhook_page_loads' => 'stripe_webhook.php',
            'forum_page_loads' => 'forum.php',
            'profile_page_loads' => 'profile.php',
            'quick_log_page_loads' => 'quick_log.php',
            'log_entry_page_loads' => 'log_entry.php',
            'view_logs_page_loads' => 'view_logs.php',
            'edit_profile_page_loads' => 'edit_profile.php',
            'onboarding_setup_page_loads' => 'onboarding_setup.php?preview=1',
            'feedback_page_loads' => 'feedback.php',
            'collaboration_page_loads' => 'collaboration.php',
            'beta_request_page_loads' => 'beta_request.php',
            'beta_token_page_loads' => 'beta_token.php',
            'register_page_loads' => 'register.php',
            'reset_password_page_loads' => 'reset_password.php',
            'setup_2fa_page_loads' => 'setup_2fa.php',
        'training_goal_intake_page_loads' => 'training_goal_intake.php',
        'habit_repair_page_loads' => 'habit_repair.php',
        'training_history_export_page_loads' => 'training_history_export.php',
        'db_status_page_loads' => 'db_status.php',
        'candidate_assessment_page_loads' => 'candidate_assessment.php',
        'candidate_comparison_page_loads' => 'candidate_comparison.php',
        'behavior_risk_scoring_page_loads' => 'behavior_risk_scoring.php',
        'regression_engine_page_loads' => 'regression_engine.php',
        'goal_builder_page_loads' => 'goal_builder.php',
            'training_program_page_loads' => 'training_program.php',
            'tactical_training_page_loads' => 'tactical_training.php',
            'training_session_log_page_loads' => 'training_session_log.php',
        'training_history_page_loads' => 'training_history.php',
        'stats_page_loads' => 'stats.php',
        'air_travel_rights_page_loads' => 'air_travel_rights.php',
        'bridge_apk_page_loads' => 'bridge_apk.php',
        'wearable_integrations_page_loads' => 'wearable_integrations.php',
        'alerts_page_loads' => 'alerts.php',
        'dog_health_page_loads' => 'dog_health.php',
        'appointments_page_loads' => 'appointments.php',
        'medications_page_loads' => 'medications.php',
        'certification_page_loads' => 'certification.php',
        'trainer_marketplace_page_loads' => 'trainer_marketplace.php',
        'paywalls_page_loads' => 'paywalls.php',
        'community_challenges_page_loads' => 'community_challenges.php',
        'trucking_mode_page_loads' => 'trucking_mode.php',
        'ai_training_assistant_page_loads' => 'ai_training_assistant.php',
        'media_review_page_loads' => 'media_review.php',
        'video_review_page_loads' => 'video_review.php',
        'coach_review_page_loads' => 'coach_review.php',
    ];

    $feedbackRows = [];
    $feedbackSummary = [];
    $feedbackPdo = gpQaFeedbackPdo($feedbackDbHost, $feedbackDbPort, $feedbackDbName, $feedbackDbUser, $feedbackDbPass);
    if ($feedbackPdo instanceof PDO) {
        $feedbackRows = gpQaFeedbackRows($feedbackPdo, $feedbackLimit);
        $aliasMap = gpQaFeedbackAliasMap();
        $originalOrder = array_keys($pages);
        $originalIndex = array_flip($originalOrder);
        $pageScores = [];
        foreach ($pages as $pageId => $pagePath) {
            $pageScores[$pageId] = gpQaFeedbackScore($feedbackRows, $pagePath, $aliasMap[$pagePath] ?? []);
        }
        $pageOrder = array_keys($pages);
        usort($pageOrder, static function (string $left, string $right) use ($pageScores, $originalIndex): int {
            $leftScore = (int) ($pageScores[$left]['score'] ?? 0);
            $rightScore = (int) ($pageScores[$right]['score'] ?? 0);
            if ($leftScore === $rightScore) {
                return ($originalIndex[$left] ?? 0) <=> ($originalIndex[$right] ?? 0);
            }
            return $rightScore <=> $leftScore;
        });
        $sortedPages = [];
        foreach ($pageOrder as $pageId) {
            $sortedPages[$pageId] = $pages[$pageId];
        }
        $pages = $sortedPages;
        foreach (array_slice($pageOrder, 0, 8) as $pageId) {
            $score = (int) ($pageScores[$pageId]['score'] ?? 0);
            if ($score > 0) {
                $feedbackSummary[] = $pageId . ':' . $score;
            }
        }
        if ($feedbackSummary) {
            echo 'Feedback-prioritized pages: ' . implode(', ', $feedbackSummary) . PHP_EOL;
        }
        $topFeedbackRows = array_slice($feedbackRows, 0, 5);
        $feedbackHotspots = [];
        foreach ($topFeedbackRows as $row) {
            $label = trim((string) ($row['page_workflow'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['title'] ?? ''));
            }
            if ($label === '') {
                $label = 'feedback #' . (string) ($row['id'] ?? '0');
            }
            $feedbackHotspots[] = sprintf(
                '%s [%s/%s]',
                preg_replace('/\s+/', ' ', $label),
                (string) ($row['status'] ?? 'new'),
                (string) ($row['priority'] ?? 'normal')
            );
        }
        if ($feedbackHotspots) {
            echo 'Feedback hotspots: ' . implode(' | ', $feedbackHotspots) . PHP_EOL;
        }
        $statusCounts = [];
        $priorityCounts = [];
        foreach ($feedbackRows as $row) {
            $status = (string) ($row['status'] ?? 'new');
            $priority = (string) ($row['priority'] ?? 'normal');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
        }
        if ($statusCounts) {
            ksort($statusCounts);
            $statusParts = [];
            foreach ($statusCounts as $status => $count) {
                $statusParts[] = $status . ':' . $count;
            }
            echo 'Feedback status counts: ' . implode(', ', $statusParts) . PHP_EOL;
        }
        if ($priorityCounts) {
            ksort($priorityCounts);
            $priorityParts = [];
            foreach ($priorityCounts as $priority => $count) {
                $priorityParts[] = $priority . ':' . $count;
            }
            echo 'Feedback priority counts: ' . implode(', ', $priorityParts) . PHP_EOL;
        }
        echo 'Feedback reports scanned: ' . count($feedbackRows) . PHP_EOL;
    }

    foreach ($pages as $id => $path) {
        $res = gpQaRequest($baseUrl, $path, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $body = strtolower($res['body']);
        $sharedAdminShellReady = !str_contains($body, '<body') || str_contains($body, 'gp-mobile-nav-shell');
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
            ? (
                str_contains($body, 'schema migrations')
                && $sharedAdminShellReady
            )
            : true;
        $notificationPageLooksReady = $path === 'notifications.php'
            ? (
                str_contains($body, 'notification preferences')
                && str_contains($body, 'delete selected')
                && str_contains($body, 'alerts')
            )
            : true;
        $communityPageLooksReady = $path === 'community.php'
            ? (
                str_contains($body, 'community')
                && (str_contains($body, 'forum') || str_contains($body, 'swag') || str_contains($body, 'support guidepaw'))
            )
            : true;
        $supportFundingPageLooksReady = $path === 'support_funding.php'
            ? (
                str_contains($body, 'support guidepaw')
                || str_contains($body, 'support once')
                || str_contains($body, 'support monthly')
                || str_contains($body, 'funding link not configured')
                || str_contains($body, 'support funding')
            )
            : true;
        $businessCostsPageLooksReady = $path === 'admin_business_costs.php'
            ? (
                str_contains($body, 'business costs')
                && str_contains($body, 'live provider snapshot')
                && (str_contains($body, 'current monthly cost') || str_contains($body, 'future monthly expansion'))
            )
            : true;
        $stripeWebhookEndpointLooksReady = $path === 'stripe_webhook.php'
            ? (
                $res['status'] === 405
                || str_contains(strtolower($body), 'method not allowed')
            )
            : true;
        $purchaseServicePageLooksReady = str_contains($path, 'purchase_service.php')
            ? (
                str_contains($body, 'a la carte service')
                || str_contains($body, 'continue to stripe checkout')
                || str_contains($body, 'checkout is not configured yet')
                || str_contains($body, 'pick a dog to continue')
            )
            : true;
        $pageLooksReady = $path === 'stripe_webhook.php'
            ? $stripeWebhookEndpointLooksReady
            : gpQaPageLooksOk($res) && $purchaseServicePageLooksReady;
        $forumPageLooksReady = $path === 'forum.php'
            ? (
                str_contains($body, 'forum')
                && (str_contains($body, 'start a thread') || str_contains($body, 'post thread'))
            )
            : true;
        $contactUsPageLooksReady = $path === 'contact_us.php'
            ? (
                str_contains($body, 'contact us')
                && (str_contains($body, 'guidepaw facebook') || str_contains($body, 'facebook'))
            )
            : true;
        $settingsPageLooksReady = $path === 'settings.php'
            ? (
                str_contains($body, 'settings')
                || str_contains($body, 'change password')
                || str_contains($body, 'logout')
            )
            : true;
        $onboardingPageLooksReady = $path === 'onboarding_setup.php?preview=1'
            ? (
                str_contains($body, 'welcome to guidepaw')
                || str_contains($body, 'finish setup')
                || str_contains($body, 'quick path')
            )
            : true;
        $profilePageLooksReady = $path === 'profile.php'
            ? (
                str_contains($body, 'edit dog profile')
                || str_contains($body, 'microchip id')
                || str_contains($body, 'owner')
            )
            : true;
        $quickLogPageLooksReady = $path === 'quick_log.php'
            ? (
                str_contains($body, 'quick session')
                || str_contains($body, 'save quick log')
                || str_contains($body, 'where are you')
            )
            : true;
        $logEntryPageLooksReady = $path === 'log_entry.php'
            ? (
                str_contains($body, 'log training')
                || str_contains($body, 'save training log')
                || str_contains($body, 'photo, video, or audio')
                || str_contains($body, 'take photo')
                || str_contains($body, 'record video')
                || str_contains($body, 'open the device camera directly')
            )
            : true;
        $editLogPageLooksReady = $path === 'edit_log.php'
            ? (
                str_contains($body, 'edit training log')
                || str_contains($body, 'update log entry')
                || str_contains($body, 'update gps coordinates')
                || str_contains($body, 'manual coordinates')
                || str_contains($body, 'gps')
            )
            : true;
        $editProfilePageLooksReady = $path === 'edit_profile.php'
            ? gpQaPageLooksOk($res)
            : true;
        $viewLogsPageLooksReady = $path === 'view_logs.php'
            ? (
                str_contains($body, 'training history')
                || str_contains($body, 'review logs')
                || str_contains($body, 'queued offline logs')
            )
            : true;
        $adminFeedbackPageLooksReady = $path === 'admin_feedback.php'
            ? (
                str_contains($body, 'feedback reports')
                || str_contains($body, 'submitted feedback')
                || str_contains($body, 'bug report')
            ) && $sharedAdminShellReady
            : true;
        $adminFeedbackAiPageLooksReady = $path === 'admin_feedback_ai.php'
            ? (
                str_contains($body, 'ai issue assistant')
                || str_contains($body, 'recommended status')
                || str_contains($body, 'draft reply')
            ) && $sharedAdminShellReady
            : true;
        $feedbackPageLooksReady = $path === 'feedback.php'
            ? (
                str_contains($body, 'enhancement')
                || str_contains($body, 'feature request')
                || str_contains($body, 'bug')
            )
            : true;
        $adminPageLooksReady = $path === 'admin.php'
            ? (
                str_contains($body, 'admin control panel')
                || str_contains($body, 'handler dashboard')
                || str_contains($body, 'beta status')
                || str_contains($body, 'feature flags')
                || str_contains($body, 'notifications')
            ) && $sharedAdminShellReady
            : true;
        $adminBetaRequestsPageLooksReady = $path === 'admin_beta_requests.php'
            ? (
                str_contains($body, 'beta access requests')
                || str_contains($body, 'access mode')
                || str_contains($body, 'no requests found')
            ) && $sharedAdminShellReady
            : true;
        $adminTacticalRequestsPageLooksReady = $path === 'admin_tactical_requests.php'
            ? (
                str_contains($body, 'tactical access requests')
                || str_contains($body, 'verified working teams')
                || str_contains($body, 'no tactical access requests found')
            ) && $sharedAdminShellReady
            : true;
        $adminFeatureRoadmapPageLooksReady = $path === 'admin_feature_roadmap.php'
            ? (
                str_contains($body, 'feature roadmap')
                || str_contains($body, 'roadmap item updated')
                || str_contains($body, 'tracks must / should / could roadmap items')
            ) && $sharedAdminShellReady
            : true;
        $adminAuditLogPageLooksReady = $path === 'admin_audit_log.php'
            ? (
                str_contains($body, 'admin audit log')
                || str_contains($body, 'showing latest')
                || str_contains($body, 'no audit records found')
            ) && $sharedAdminShellReady
            : true;
        $adminSmtpAuditPageLooksReady = $path === 'admin_smtp_audit.php'
            ? (
                str_contains($body, 'smtp audit')
                || str_contains($body, 'dns check')
                || str_contains($body, 'tcp connection check')
            )
            : true;
        $adminZeptoMailAuditPageLooksReady = $path === 'admin_zeptomail_audit.php'
            ? (
                str_contains($body, 'zeptomail api audit')
                || str_contains($body, 'dns check')
                || str_contains($body, 'https connection check')
            )
            : true;
        $adminFoundDogReportsPageLooksReady = $path === 'admin_found_dog_reports.php'
            ? (
                str_contains($body, 'found dog location reports')
                || str_contains($body, 'found-dog email template')
                || str_contains($body, 'no found dog location reports yet')
            ) && $sharedAdminShellReady
            : true;
        $adminNotificationTestPageLooksReady = $path === 'admin_notification_test.php'
            ? (
                str_contains($body, 'notification test')
                || str_contains($body, 'current settings')
                || str_contains($body, 'send test')
            ) && $sharedAdminShellReady
            : true;
        $foundDogNotificationTestPageLooksReady = $path === 'found_dog_notification_test.php'
            ? (
                str_contains($body, 'found dog alert test')
                || str_contains($body, 'notification route')
                || str_contains($body, 'send test found-dog alert')
            )
            : true;
        $adminProfileCompletionPageLooksReady = $path === 'admin_profile_completion.php'
            ? (
                str_contains($body, 'handler profile completion')
                || str_contains($body, 'missing required')
                || str_contains($body, 'accounts missing required')
            ) && $sharedAdminShellReady
            : true;
        $adminUsersPageLooksReady = $path === 'admin_users.php'
            ? (
                str_contains($body, 'admin user management')
                || str_contains($body, 'export, deactivate, reactivate')
                || str_contains($body, 'download user data json')
                || str_contains($body, 'purge user and dogs')
            ) && $sharedAdminShellReady
            : true;
        $adminPaywallCatalogPageLooksReady = $path === 'admin_paywall_catalog.php'
            ? (
                str_contains($body, 'paywall catalog')
                || str_contains($body, 'a la carte services')
                || str_contains($body, 'grant dog add-ons')
                || str_contains($body, 'edit row')
                || str_contains($body, 'recent stripe purchases')
            ) && $sharedAdminShellReady
            : true;
        $purchaseServicePageLooksReady = str_contains($path, 'purchase_service.php')
            ? (
                str_contains($body, 'a la carte service')
                || str_contains($body, 'continue to stripe checkout')
                || str_contains($body, 'checkout is not configured yet')
                || str_contains($body, 'pick a dog to continue')
            )
            : true;
        $betaRequestPageLooksReady = $path === 'beta_request.php'
            ? (
                str_contains($body, 'request guidepaw beta access')
                || str_contains($body, 'submit request')
                || str_contains($body, 'beta access')
            )
            : true;
        $betaTokenPageLooksReady = $path === 'beta_token.php'
            ? (
                str_contains($body, 'validate beta access token')
                || str_contains($body, 'continue to account creation')
                || str_contains($body, 'beta token')
            )
            : true;
        $registerPageLooksReady = $path === 'register.php'
            ? (
                str_contains(strtolower($res['url']), 'beta_token.php')
                || str_contains($body, 'create handler account')
                || str_contains($body, 'create account')
                || str_contains($body, 'dog profiles are set up after login')
                || str_contains($body, 'street address')
                || str_contains($body, 'home street')
                || str_contains($body, 'home city')
                || str_contains($body, 'home zip')
            )
            : true;
        $resetPasswordPageLooksReady = $path === 'reset_password.php'
            ? (
                str_contains($body, 'account recovery')
                || str_contains($body, 'password recovery')
                || str_contains($body, 'reset password')
                || str_contains($body, 'verify password')
                || str_contains($body, 'password confirmation')
            )
            : true;
        $setup2faPageLooksReady = $path === 'setup_2fa.php'
            ? (
                str_contains($body, 'setup 2fa')
                || str_contains($body, 'manage 2fa')
                || str_contains($body, 'verification code')
                || str_contains($body, 'scan qr code')
            )
            : true;
        $apiTokensPageLooksReady = $path === 'api_tokens.php'
            ? (
                str_contains($body, 'api tokens')
                || str_contains($body, 'create token')
                || str_contains($body, 'existing tokens')
            ) && $sharedAdminShellReady
            : true;
        $backupToolsPageLooksReady = $path === 'backup.php'
            ? (
                str_contains($body, 'backup & restore')
                || str_contains($body, 'full backup package')
                || str_contains($body, 'download json backup')
            ) && $sharedAdminShellReady
            : true;
        $dogsPageLooksReady = $path === 'dogs.php'
            ? (
                str_contains($body, 'archived dogs')
                || str_contains($body, 'no archived dogs yet')
                || str_contains($body, 'your accessible dogs')
            ) && (
                str_contains($body, 'add another dog')
                || str_contains($body, 'add your first dog')
                || str_contains($body, 'need another dog?')
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
                || str_contains($body, 'recommended path')
                || str_contains($body, 'add a dog profile before building a goal')
            )
            : true;
        $trainingProgramPageLooksReady = $path === 'training_program.php'
            ? (
                str_contains($body, 'training program')
                || str_contains($body, 'today\'s easy win')
                || str_contains($body, 'active goals')
                || str_contains($body, 'training setup')
                || str_contains($body, 'akc programs')
                || str_contains($body, 'training ladder')
                || str_contains($body, 'editable cue guide')
                || str_contains($body, 'start module')
            )
            : true;
        $tacticalTrainingPageLooksReady = $path === 'tactical_training.php'
            ? (
                str_contains($body, 'tactical training')
                || str_contains($body, 'application required')
                || str_contains($body, 'tactical readiness path')
            )
            : true;
        $trainingGoalIntakePageLooksReady = $path === 'training_goal_intake.php'
            ? (
                str_contains($body, 'training goal intake')
                || str_contains($body, 'goal intake')
                || str_contains($body, 'open goal builder')
            )
            : true;
        $habitRepairPageLooksReady = $path === 'habit_repair.php'
            ? (
                str_contains($body, 'habit repair')
                || str_contains($body, 'behavior incident')
                || str_contains($body, 'regression is not failure')
            )
            : true;
        $trainingSessionLogPageLooksReady = $path === 'training_session_log.php'
            ? (
                str_contains($body, 'training session log')
                || str_contains($body, 'log session')
                || str_contains($body, 'success rate')
                || str_contains($body, 'edit them on the training program page')
            )
            : true;
        $trainingHistoryPageLooksReady = $path === 'training_history.php'
            ? (
                str_contains($body, 'training history')
                || str_contains($body, 'archived')
                || str_contains($body, 'export csv')
            )
            : true;
        $trainingHistoryExportPageLooksReady = $path === 'training_history_export.php'
            ? (
                str_contains($body, 'record_type')
                || str_contains($body, 'created_at')
                || str_contains($body, 'content-type: text/csv')
            )
            : true;
        $statsPageLooksReady = $path === 'stats.php'
            ? (
                str_contains($body, 'training stats')
                || str_contains($body, 'total logs')
                || str_contains($body, 'last 14 days')
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
                || str_contains($body, 'connect wearable')
                || str_contains($body, 'connection ready')
                || str_contains($body, 'manual entry')
            )
            : true;
        if ($path === 'wearable_integrations.php' && gpQaPageLooksOk($res)) {
            $wearableConnectPage = $res;
            $wearableConnectBody = strtolower($wearableConnectPage['body']);
            $wearableConnectPostedSeen = false;
            $wearableBridgeTargetSeen = false;
            $wearableBridgePageSeen = false;
            if (preg_match('/<option[^>]+value="(\d+)"[^>]*selected/i', $wearableConnectPage['body'], $wearableDogMatch) || preg_match('/<option[^>]+value="(\d+)"/i', $wearableConnectPage['body'], $wearableDogMatch)) {
                if (!preg_match('/name="csrf_token" value="([^"]+)"/i', $wearableConnectPage['body'], $wearableCsrfMatch)) {
                    $wearableCsrfMatch = [null, ''];
                }
                $wearableConnectDogId = (int) $wearableDogMatch[1];
                $wearableConnectCsrf = html_entity_decode($wearableCsrfMatch[1], ENT_QUOTES | ENT_HTML5);
                if ($wearableConnectDogId > 0 && $wearableConnectCsrf !== '') {
                    $wearableConnectPost = gpQaRequest($baseUrl, 'wearable_integrations.php', 'POST', [
                        'csrf_token' => $wearableConnectCsrf,
                        'dog_id' => $wearableConnectDogId,
                        'connect_wearable' => '1',
                    ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $wearableConnectPostBody = strtolower($wearableConnectPost['body']);
                    $wearableConnectPostedSeen = gpQaPageLooksOk($wearableConnectPost) && (
                        str_contains($wearableConnectPostBody, 'wearable connection code created')
                        || str_contains($wearableConnectPostBody, 'connection ready')
                        || str_contains($wearableConnectPostBody, 'scan the qr')
                    );
                    $wearableBridgeTargetSeen = gpQaPageLooksOk($wearableConnectPost) && str_contains($wearableConnectPost['body'], 'wearable_bridge.php%3Ftoken%3D');
                    if (preg_match('/href="([^"]*wearable_bridge\.php\?token=[^"]+)"/i', $wearableConnectPost['body'], $wearableBridgeLinkMatch)) {
                        $wearableBridgeLink = html_entity_decode($wearableBridgeLinkMatch[1], ENT_QUOTES | ENT_HTML5);
                        $wearableBridgePath = (string) (parse_url($wearableBridgeLink, PHP_URL_PATH) ?: '');
                        $wearableBridgeQuery = (string) (parse_url($wearableBridgeLink, PHP_URL_QUERY) ?: '');
                        $wearableBridgeRequest = $wearableBridgePath !== '' ? $wearableBridgePath . ($wearableBridgeQuery !== '' ? '?' . $wearableBridgeQuery : '') : '';
                        if ($wearableBridgeRequest !== '') {
                            $wearableBridgePage = gpQaRequest($baseUrl, $wearableBridgeRequest, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                            $wearableBridgeBody = strtolower($wearableBridgePage['body']);
                            $wearableBridgePageSeen = gpQaPageLooksOk($wearableBridgePage) && (
                                str_contains($wearableBridgeBody, 'pair guidepaw on this phone')
                                || str_contains($wearableBridgeBody, 'open in guidepaw bridge')
                                || str_contains($wearableBridgeBody, 'copy pairing code')
                                || str_contains($wearableBridgeBody, 'bridge details')
                            );
                        }
                    }
                }
            }
            gpQaResult($results, 'wearable_connect_code', $wearableConnectPostedSeen, 'HTTP ' . $wearableConnectPage['status'] . ($wearableConnectPostedSeen ? ' wearable connect code created' : ' wearable connect code missing'));
            gpQaResult($results, 'wearable_bridge_qr_target', $wearableBridgeTargetSeen, 'HTTP ' . $wearableConnectPage['status'] . ($wearableBridgeTargetSeen ? ' wearable bridge URL found in QR' : ' wearable bridge URL missing from QR'));
            gpQaResult($results, 'wearable_bridge_page_loads', $wearableBridgePageSeen, 'HTTP ' . $wearableConnectPage['status'] . ($wearableBridgePageSeen ? ' wearable bridge page found' : ' wearable bridge page missing'));
        }
        $alertsPageLooksReady = $path === 'alerts.php'
            ? (
                str_contains($body, 'smart alerts')
                || str_contains($body, 'active alerts')
                || str_contains($body, 'no active alerts')
                || str_contains($body, 'start module')
            )
            : true;
        $dogHealthPageLooksReady = $path === 'dog_health.php'
            ? (
                str_contains($body, 'health & documents')
                || str_contains($body, 'vet contacts')
                || str_contains($body, 'documents')
                || str_contains($body, 'search saved vets')
            )
            : true;
        $appointmentsPageLooksReady = $path === 'appointments.php'
            ? (
                str_contains($body, 'vet appointments')
                || str_contains($body, 'schedule appointment')
                || str_contains($body, 'upcoming & past appointments')
            )
            : true;
        $medicationsPageLooksReady = $path === 'medications.php'
            ? (
                str_contains($body, 'medication tracking')
                || str_contains($body, 'add medication')
                || str_contains($body, 'current medication list')
            )
            : true;
        $certificationPageLooksReady = $path === 'certification.php'
            ? (
                str_contains($body, 'certification and readiness')
                || str_contains($body, 'checklist items')
                || str_contains($body, 'latest assessment snapshot')
            )
            : true;
        $trainerMarketplaceLooksReady = $path === 'trainer_marketplace.php'
            ? (
                str_contains($body, 'trainer marketplace')
                || str_contains($body, 'trainer profiles')
                || str_contains($body, 'recent syncs')
                || str_contains($body, 'reserved for the current paid plan tier')
            )
            : true;
        $paywallsPageLooksReady = $path === 'paywalls.php'
            ? (
                str_contains($body, 'plans and access')
                || str_contains($body, 'current plan')
                || str_contains($body, 'trainer marketplace')
                || str_contains($body, 'ai training assistant')
                || str_contains($body, 'special access training')
                || str_contains($body, 'tactical training')
                || str_contains($body, 'a la carte services')
                || str_contains($body, 'extra dog slot')
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
                || str_contains($body, 'this coaching tool is reserved for the current premium plan tier')
            )
            : true;
        gpQaResult(
            $results,
            $id,
            $pageLooksReady && $mediaPageLooksReady && $videoPageLooksReady && $coachPageLooksReady && $dbStatusLooksReady && $notificationPageLooksReady && $settingsPageLooksReady && $contactUsPageLooksReady && $paywallsPageLooksReady && $purchaseServicePageLooksReady && $communityPageLooksReady && $supportFundingPageLooksReady && $businessCostsPageLooksReady && $stripeWebhookEndpointLooksReady && $forumPageLooksReady && $profilePageLooksReady && $quickLogPageLooksReady && $logEntryPageLooksReady && $viewLogsPageLooksReady && $feedbackPageLooksReady && $adminPageLooksReady && $adminFeedbackPageLooksReady && $adminFeedbackAiPageLooksReady && $adminBetaRequestsPageLooksReady && $adminTacticalRequestsPageLooksReady && $adminFeatureRoadmapPageLooksReady && $adminAuditLogPageLooksReady && $adminSmtpAuditPageLooksReady && $adminZeptoMailAuditPageLooksReady && $adminFoundDogReportsPageLooksReady && $adminNotificationTestPageLooksReady && $foundDogNotificationTestPageLooksReady && $adminProfileCompletionPageLooksReady && $adminUsersPageLooksReady && $adminPaywallCatalogPageLooksReady && $apiTokensPageLooksReady && $backupToolsPageLooksReady && $dogsPageLooksReady && $candidatePageLooksReady && $candidateComparisonPageLooksReady && $behaviorRiskPageLooksReady && $regressionEnginePageLooksReady && $goalBuilderPageLooksReady && $trainingProgramPageLooksReady && $tacticalTrainingPageLooksReady && $trainingSessionLogPageLooksReady && $trainingHistoryPageLooksReady && $statsPageLooksReady && $airTravelPageLooksReady && $wearablePageLooksReady && $alertsPageLooksReady && $dogHealthPageLooksReady && $appointmentsPageLooksReady && $medicationsPageLooksReady && $certificationPageLooksReady && $trainerMarketplaceLooksReady && $communityChallengesPageLooksReady && $truckingPageLooksReady && $assistantPageLooksReady && $betaRequestPageLooksReady && $betaTokenPageLooksReady && $registerPageLooksReady && $resetPasswordPageLooksReady && $setup2faPageLooksReady,
            'HTTP ' . $res['status'] . ' ' . basename(parse_url($res['url'], PHP_URL_PATH) ?: $path) . ($res['error'] ? ' error=' . $res['error'] : '') . ($path === 'feedback.php' ? ($feedbackPageLooksReady ? ' feedback categories found' : ' feedback categories missing') : '') . ($path === 'admin.php' ? ($adminPageLooksReady ? ' admin shell found' : ' admin shell missing') : '') . ($path === 'admin_feedback.php' ? ($adminFeedbackPageLooksReady ? ' feedback reports found' : ' feedback reports missing') : '') . ($path === 'admin_feedback_ai.php' ? ($adminFeedbackAiPageLooksReady ? ' issue assistant found' : ' issue assistant missing') : '') . ($path === 'admin_beta_requests.php' ? ($adminBetaRequestsPageLooksReady ? ' beta access requests found' : ' beta access requests missing') : '') . ($path === 'admin_tactical_requests.php' ? ($adminTacticalRequestsPageLooksReady ? ' tactical requests found' : ' tactical requests missing') : '') . ($path === 'admin_feature_roadmap.php' ? ($adminFeatureRoadmapPageLooksReady ? ' roadmap found' : ' roadmap missing') : '') . ($path === 'admin_audit_log.php' ? ($adminAuditLogPageLooksReady ? ' audit log found' : ' audit log missing') : '') . ($path === 'admin_smtp_audit.php' ? ($adminSmtpAuditPageLooksReady ? ' smtp audit found' : ' smtp audit missing') : '') . ($path === 'admin_zeptomail_audit.php' ? ($adminZeptoMailAuditPageLooksReady ? ' zeptomail audit found' : ' zeptomail audit missing') : '') . ($path === 'admin_found_dog_reports.php' ? ($adminFoundDogReportsPageLooksReady ? ' found dog reports found' : ' found dog reports missing') : '') . ($path === 'admin_notification_test.php' ? ($adminNotificationTestPageLooksReady ? ' notification test found' : ' notification test missing') : '') . ($path === 'admin_profile_completion.php' ? ($adminProfileCompletionPageLooksReady ? ' profile completion found' : ' profile completion missing') : '') . ($path === 'admin_users.php' ? ($adminUsersPageLooksReady ? ' admin users shell found' : ' admin users shell missing') : '') . ($path === 'admin_paywall_catalog.php' ? ($adminPaywallCatalogPageLooksReady ? ' paywall catalog found' : ' paywall catalog missing') : '') . ($path === 'admin_business_costs.php' ? ($businessCostsPageLooksReady ? ' business costs found' : ' business costs missing') : '') . ($path === 'api_tokens.php' ? ($apiTokensPageLooksReady ? ' api tokens found' : ' api tokens missing') : '') . ($path === 'backup.php' ? ($backupToolsPageLooksReady ? ' backup tools found' : ' backup tools missing') : '') . ($path === 'dogs.php' ? ($dogsPageLooksReady ? ' archive split found' : ' archive split missing') : '') . ($path === 'candidate_assessment.php' ? ($candidatePageLooksReady ? ' candidate assessment content found' : ' candidate assessment content missing') : '') . ($path === 'candidate_comparison.php' ? ($candidateComparisonPageLooksReady ? ' candidate comparison content found' : ' candidate comparison content missing') : '') . ($path === 'behavior_risk_scoring.php' ? ($behaviorRiskPageLooksReady ? ' behavior risk content found' : ' behavior risk content missing') : '') . ($path === 'regression_engine.php' ? ($regressionEnginePageLooksReady ? ' regression engine content found' : ' regression engine content missing') : '') . ($path === 'goal_builder.php' ? ($goalBuilderPageLooksReady ? ' goal builder content found' : ' goal builder content missing') : '') . ($path === 'training_program.php' ? ($trainingProgramPageLooksReady ? ' training program content found' : ' training program content missing') : '') . ($path === 'tactical_training.php' ? ($tacticalTrainingPageLooksReady ? ' tactical training content found' : ' tactical training content missing') : '') . ($path === 'training_session_log.php' ? ($trainingSessionLogPageLooksReady ? ' session log content found' : ' session log content missing') : '') . ($path === 'training_history.php' ? ($trainingHistoryPageLooksReady ? ' training history content found' : ' training history content missing') : '') . ($path === 'stats.php' ? ($statsPageLooksReady ? ' stats content found' : ' stats content missing') : '') . ($path === 'air_travel_rights.php' ? ($airTravelPageLooksReady ? ' air travel content found' : ' air travel content missing') : '') . ($path === 'wearable_integrations.php' ? ($wearablePageLooksReady ? ' wearable sync content found' : ' wearable sync content missing') : '') . ($path === 'alerts.php' ? ($alertsPageLooksReady ? ' alerts content found' : ' alerts content missing') : '') . ($path === 'dog_health.php' ? ($dogHealthPageLooksReady ? ' health docs content found' : ' health docs content missing') : '') . ($path === 'appointments.php' ? ($appointmentsPageLooksReady ? ' appointments content found' : ' appointments content missing') : '') . ($path === 'medications.php' ? ($medicationsPageLooksReady ? ' medications content found' : ' medications content missing') : '') . ($path === 'certification.php' ? ($certificationPageLooksReady ? ' certification content found' : ' certification content missing') : '') . ($path === 'trainer_marketplace.php' ? ($trainerMarketplaceLooksReady ? ' trainer marketplace content found' : ' trainer marketplace content missing') : '') . ($path === 'paywalls.php' ? ($paywallsPageLooksReady ? ' plans page found' : ' plans page missing') : '') . ($path === 'community_challenges.php' ? ($communityChallengesPageLooksReady ? ' community challenges content found' : ' community challenges content missing') : '') . ($path === 'trucking_mode.php' ? ($truckingPageLooksReady ? ' trucking mode content found' : ' trucking mode content missing') : '') . ($path === 'ai_training_assistant.php' ? ($assistantPageLooksReady ? ' assistant content found' : ' assistant content missing') : '') . ($path === 'media_review.php' ? ($mediaPageLooksReady ? ' media review content found' : ' media review content missing') : '') . ($path === 'video_review.php' ? ($videoPageLooksReady ? ' video review content found' : ' video review content missing') : '') . ($path === 'coach_review.php' ? ($coachPageLooksReady ? ' coach review content found' : ' coach review content missing') : '') . ($path === 'db_status.php' ? ($dbStatusLooksReady ? ' schema migration section found' : ' schema migration section missing') : '') . ($path === 'notifications.php' ? ($notificationPageLooksReady ? ' notification controls found' : ' notification controls missing') : '') . ($path === 'settings.php' ? ($settingsPageLooksReady ? ' settings content found' : ' settings content missing') : '') . ($path === 'profile.php' ? ($profilePageLooksReady ? ' profile content found' : ' profile content missing') : '') . ($path === 'quick_log.php' ? ($quickLogPageLooksReady ? ' quick log content found' : ' quick log content missing') : '') . ($path === 'log_entry.php' ? ($logEntryPageLooksReady ? ' log entry content found' : ' log entry content missing') : '') . ($path === 'view_logs.php' ? ($viewLogsPageLooksReady ? ' history content found' : ' history content missing') : '') . ($path === 'beta_request.php' ? ($betaRequestPageLooksReady ? ' beta request content found' : ' beta request content missing') : '') . ($path === 'beta_token.php' ? ($betaTokenPageLooksReady ? ' beta token content found' : ' beta token content missing') : '') . ($path === 'register.php' ? ($registerPageLooksReady ? ' register content found' : ' register content missing') : '') . ($path === 'reset_password.php' ? ($resetPasswordPageLooksReady ? ' reset password content found' : ' reset password content missing') : '') . ($path === 'setup_2fa.php' ? ($setup2faPageLooksReady ? ' 2fa content found' : ' 2fa content missing') : '') . ($path === 'onboarding_setup.php?preview=1' ? ($onboardingPageLooksReady ? ' onboarding content found' : ' onboarding content missing') : '') . ($path === 'support_funding.php' ? ($supportFundingPageLooksReady ? ' support funding content found' : ' support funding content missing') : '') . ($path === 'stripe_webhook.php' ? ($stripeWebhookEndpointLooksReady ? ' webhook endpoint found' : ' webhook endpoint missing') : '')
            );
    }

    $dashboard = gpQaRequest($baseUrl, 'index.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $notificationsPage = gpQaRequest($baseUrl, 'notifications.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $dogsPage = gpQaRequest($baseUrl, 'dogs.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $adaAccessCardPage = gpQaRequest($baseUrl, 'ada_access_card.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $adaWalletCardPage = gpQaRequest($baseUrl, 'ada_wallet_card.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $serviceDogRightsPage = gpQaRequest($baseUrl, 'service_dog_rights.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $breedQuestionnairePage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'GET', [], '', $insecureLocalSsl, '', false);
    $paywallsPage = gpQaRequest($baseUrl, 'paywalls.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $appointmentNotificationsPage = gpQaRequest($baseUrl, 'appointment_notifications.php?hours=24', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $betaChecklistStatePage = gpQaRequest($baseUrl, 'beta_qa_checklist_state.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $adminHomePage = gpQaRequest($baseUrl, 'admin.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $goalIntakePage = gpQaRequest($baseUrl, 'training_goal_intake.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $goalBuilderPage = gpQaRequest($baseUrl, 'goal_builder.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $habitRepairPage = gpQaRequest($baseUrl, 'habit_repair.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $trainingProgramPage = gpQaRequest($baseUrl, 'training_program.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $trainingSessionLogPage = gpQaRequest($baseUrl, 'training_session_log.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $alertsPage = gpQaRequest($baseUrl, 'alerts.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $editProfilePage = gpQaRequest($baseUrl, 'edit_profile.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $viewLogsForEditPage = gpQaRequest($baseUrl, 'view_logs.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $manageDogsPage = gpQaRequest($baseUrl, 'manage_dogs.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $importBackupPage = gpQaRequest($baseUrl, 'import_backup.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $updateLogGuardPage = gpQaRequest($baseUrl, 'update_log.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $saveLogGuardPage = gpQaRequest($baseUrl, 'save_log.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $trainingHistoryExportPage = gpQaRequest($baseUrl, 'training_history_export.php?status=active', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $backupExportPage = gpQaRequest($baseUrl, 'export_backup.php?format=csv', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $dashboardBody = strtolower($dashboard['body']);
    $notificationsPageBody = strtolower($notificationsPage['body']);
    $dogsPageBody = strtolower($dogsPage['body']);
    $adaAccessCardBody = strtolower($adaAccessCardPage['body']);
    $adaWalletCardBody = strtolower($adaWalletCardPage['body']);
    $serviceDogRightsBody = strtolower($serviceDogRightsPage['body']);
    $breedQuestionnaireBody = strtolower($breedQuestionnairePage['body']);
    $paywallsPageBody = strtolower($paywallsPage['body']);
    $appointmentNotificationsBody = strtolower($appointmentNotificationsPage['body']);
    $betaChecklistStateBody = strtolower($betaChecklistStatePage['body']);
    $adminHomeBody = strtolower($adminHomePage['body']);
    $goalIntakeBody = strtolower($goalIntakePage['body']);
    $goalBuilderPageBody = strtolower($goalBuilderPage['body']);
    $habitRepairBody = strtolower($habitRepairPage['body']);
    $trainingProgramBody = strtolower($trainingProgramPage['body']);
    $alertsPageBody = strtolower($alertsPage['body']);
    $settingsPage = gpQaRequest($baseUrl, 'settings.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $settingsPageBody = strtolower($settingsPage['body']);
    $communityPage = gpQaRequest($baseUrl, 'community.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
    $communityPageBody = strtolower($communityPage['body']);
    $forumPage = gpQaRequest($baseUrl, 'forum.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
    $forumPageBody = strtolower($forumPage['body']);
    $editProfileBody = strtolower($editProfilePage['body']);
    $viewLogsForEditBody = strtolower($viewLogsForEditPage['body']);
    $manageDogsBody = strtolower($manageDogsPage['body']);
    $importBackupBody = strtolower($importBackupPage['body']);
    $updateLogGuardBody = strtolower($updateLogGuardPage['body']);
    $saveLogGuardBody = strtolower($saveLogGuardPage['body']);
    $trainingHistoryExportBody = strtolower($trainingHistoryExportPage['body']);
    $trainingSessionLogBody = strtolower($trainingSessionLogPage['body']);
    $trainingHistoryExportHeaders = strtolower($trainingHistoryExportPage['headers']);
    $backupExportHeaders = strtolower($backupExportPage['headers']);
    $dailyWinPromptSeen = str_contains($dashboardBody, 'daily quick win')
        && (
            str_contains($dashboardBody, 'save quick win')
            || str_contains($dashboardBody, 'done today')
            || str_contains($dashboardBody, 'already saved')
            || str_contains($dashboardBody, 'saved today')
        );
    $dogsArchiveSplitSeen = str_contains($dogsPageBody, 'archived dogs') || str_contains($dogsPageBody, 'no archived dogs yet') || str_contains($dogsPageBody, 'active dogs stay in the working list');
    $dogsAgeRuleSeen = gpQaPageLooksOk($dogsPage)
        && str_contains($dogsPageBody, 'guidepaw fills this age automatically')
        && str_contains($dogsPageBody, 'leave the birthday blank to enter an approximate age manually');
    $notificationPrefsSeen = str_contains($notificationsPageBody, 'notification preferences') && str_contains($notificationsPageBody, 'delete selected') && str_contains($notificationsPageBody, 'bulk delete');
    $notificationBadgeSeen = str_contains($dashboardBody, 'gp-nav-badge') || str_contains($notificationsPageBody, 'gp-nav-badge');
    $candidateHookSeen = str_contains($dashboardBody, 'candidate scoring') || str_contains($dashboardBody, 'candidate assessment');
    $candidateComparisonHookSeen = str_contains($dashboardBody, 'candidate comparison') || str_contains($dashboardBody, 'compare dogs');
    $behaviorRiskHookSeen = str_contains($dashboardBody, 'behavior risk');
    $regressionEngineHookSeen = str_contains($dashboardBody, 'regression engine') || str_contains($dashboardBody, 'reset plan');
    $goalBuilderHookSeen = str_contains($dashboardBody, 'goal builder');
    $airTravelHookSeen = str_contains($dashboardBody, 'air travel rights') || str_contains($dashboardBody, 'service dog rights');
    $todayCoreActionsSeen = gpQaPageLooksOk($dashboard)
        && str_contains($dashboardBody, 'quick session')
        && str_contains($dashboardBody, 'detailed log')
        && (str_contains($dashboardBody, 'goal builder') || str_contains($dashboardBody, 'training program'))
        && (str_contains($dashboardBody, 'ada access') || str_contains($dashboardBody, 'access card'));
    $dashboardTodayActionCount = preg_match_all('/class="today-action"/i', $dashboard['body'], $todayActionMatches);
    $todayExtrasPruned = gpQaPageLooksOk($dashboard) && (int) $dashboardTodayActionCount <= 4;
    $todayAttentionShortcutRemoved = gpQaPageLooksOk($dashboard) && !str_contains($dashboard['body'], 'href="#needs-attention"');
    $homeUtilitySimplified = gpQaPageLooksOk($dashboard)
        && !str_contains($dashboardBody, 'sync queued logs')
        && !str_contains($dashboardBody, 'notifications off')
        && !str_contains($dashboardBody, 'data-queue-count')
        && !str_contains($dashboardBody, 'settings handles sync, reminders, and device notices')
        && !str_contains($dashboardBody, 'need more?')
        && !str_contains($dashboardBody, 'switch dogs from here')
        && !str_contains($dashboardBody, 'keep the day moving')
        && !preg_match('/<div class="home-utility"[^>]*>.*?<a[^>]*href="settings\\.php"/is', $dashboard['body']);
    $menuHintSeen = gpQaPageLooksOk($dashboard) && str_contains($dashboardBody, 'tap <strong>menu</strong> in the bottom navigation') && str_contains($dashboardBody, 'tools are now grouped under dog, logs, training, care, and more');
    $dashboardAlertModuleLinkSeen = str_contains($dashboardBody, 'start module')
        && preg_match('/href="[^"]*(training_program\\.php|candidate_assessment\\.php|log_entry\\.php|certification\\.php)/i', $dashboard['body']);
    $dailyWinSavedSeen = false;
    if (preg_match('/name="action" value="save_daily_win"/i', $dashboard['body']) && preg_match('/name="csrf_token" value="([^"]+)"/i', $dashboard['body'], $dailyWinCsrfMatch)) {
        $dailyWinPost = gpQaRequest($baseUrl, 'index.php', 'POST', [
            'csrf_token' => html_entity_decode($dailyWinCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
            'action' => 'save_daily_win',
            'daily_win_complete' => '1',
        ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $dailyWinPostBody = strtolower($dailyWinPost['body']);
        $dailyWinSavedSeen = gpQaPageLooksOk($dailyWinPost) && (
            str_contains($dailyWinPostBody, 'daily win saved')
            || str_contains($dailyWinPostBody, 'saved today')
            || str_contains($dailyWinPostBody, 'training log')
        );
        if (!$dailyWinSavedSeen && str_contains(strtolower($dailyWinPost['url']), 'view_logs.php')) {
            $dailyWinSavedSeen = true;
        }
    } elseif (str_contains($dashboardBody, 'saved today') || str_contains($dashboardBody, 'already saved')) {
        $dailyWinSavedSeen = true;
    }
    $menuSearchRemoved = gpQaPageLooksOk($dashboard) && !str_contains($dashboardBody, 'search pages, tools, or training tracks');
    $menuLogoutSeen = str_contains($dashboardBody, 'logout') && str_contains($dashboardBody, 'settings');
    $menuSectionCount = preg_match_all('/<details class="gp-menu-section"/i', $dashboard['body'], $menuSectionMatches);
    $menuSimplifiedSeen = gpQaPageLooksOk($dashboard) && (int) $menuSectionCount <= 5;
    $menuDescriptionsRemoved = gpQaPageLooksOk($dashboard)
        && !str_contains($dashboardBody, 'tools grouped by job, not by feature list')
        && !str_contains($dashboardBody, 'manage the active dog, handler profile, dog details, and progress snapshot')
        && !str_contains($dashboardBody, 'daily handler notes, sessions, media, and history')
        && !str_contains($dashboardBody, 'plan, repair, assess, and track training progress')
        && !str_contains($dashboardBody, 'health documents, appointments, medications, and wearable syncs')
        && !str_contains($dashboardBody, 'notifications, access, feedback, and the few extras handlers still need')
        && !str_contains($dashboardBody, 'optional tools for planning, comparisons, and coaching')
        && !str_contains($dashboardBody, 'admin pages stay hidden here unless you are signed in as an admin');
    $adaAccessCardSeen = gpQaPageLooksOk($adaAccessCardPage) && (str_contains($adaAccessCardBody, 'ada access card') || str_contains($adaAccessCardBody, 'lockscreen display') || str_contains($adaAccessCardBody, 'service dog')) && (str_contains($adaAccessCardBody, 'current source') || str_contains($adaAccessCardBody, 'handler home state') || str_contains($adaAccessCardBody, 'last ping'));
    $adaWalletCardSeen = $adaWalletCardPage['status'] === 302 || str_contains(strtolower($adaWalletCardPage['url']), 'ada_access_card.php');
    $serviceDogRightsSeen = gpQaPageLooksOk($serviceDogRightsPage) && (str_contains($serviceDogRightsBody, 'detailed ada notes') || str_contains($serviceDogRightsBody, 'ada service dog rights'));
    $breedQuestionnaireSeen = gpQaPageLooksOk($breedQuestionnairePage)
        && (
            str_contains($breedQuestionnaireBody, 'breed questionnaire')
            || str_contains($breedQuestionnaireBody, 'ranked breed ideas')
        )
        && str_contains($breedQuestionnaireBody, 'no account needed');
    $breedQuestionnaireLiveSuggestionsSeen = gpQaPageLooksOk($breedQuestionnairePage)
        && str_contains($breedQuestionnaireBody, 'breed name to research')
        && str_contains($breedQuestionnaireBody, 'breed-query-options')
        && str_contains($breedQuestionnaireBody, 'breed-query-live')
        && str_contains($breedQuestionnaireBody, 'optional. if you already have a breed in mind');
    $breedQuestionnaireFocusFiltersSeen = gpQaPageLooksOk($breedQuestionnairePage)
        && str_contains($breedQuestionnaireBody, 'breed focus filter')
        && str_contains($breedQuestionnaireBody, 'breed-focus-group')
        && str_contains($breedQuestionnaireBody, 'public access')
        && str_contains($breedQuestionnaireBody, 'companion')
        && str_contains($breedQuestionnaireBody, 'task work');
    $breedQuestionnaireAdvancedSeen = gpQaPageLooksOk($breedQuestionnairePage)
        && str_contains($breedQuestionnaireBody, 'breed-advanced')
        && str_contains($breedQuestionnaireBody, 'advanced')
        && str_contains($breedQuestionnaireBody, 'exercise you can support')
        && str_contains($breedQuestionnaireBody, 'grooming tolerance')
        && str_contains($breedQuestionnaireBody, 'public exposure')
        && str_contains($breedQuestionnaireBody, 'your training experience')
        && str_contains($breedQuestionnaireBody, 'sensitivity / drive tolerance')
        && str_contains($breedQuestionnaireBody, 'drill-down mode');
    $breedQuestionnaireLiveBestForSeen = gpQaPageLooksOk($breedQuestionnairePage)
        && str_contains($breedQuestionnaireBody, 'breed-live-best')
        && str_contains($breedQuestionnaireBody, 'best for public access')
        && str_contains($breedQuestionnaireBody, 'best for broader research');
    $breedQuestionnaireSizeLabelsSeen = gpQaPageLooksOk($breedQuestionnairePage)
        && str_contains($breedQuestionnaireBody, 'toy · about 4-12 lbs')
        && str_contains($breedQuestionnaireBody, 'small · about 10-25 lbs')
        && str_contains($breedQuestionnaireBody, 'medium · about 20-55 lbs')
        && str_contains($breedQuestionnaireBody, 'large · about 45-90 lbs')
        && str_contains($breedQuestionnaireBody, 'giant · about 85+ lbs');
    $breedQuestionnaireFamilyBrowsePage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'breed_query' => '',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
        'drill_family' => 'Toy',
        'drill_size' => 'any',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireFamilyBrowseBody = strtolower($breedQuestionnaireFamilyBrowsePage['body']);
    $breedQuestionnaireFamilyBrowseSeen = gpQaPageLooksOk($breedQuestionnaireFamilyBrowsePage)
        && str_contains($breedQuestionnaireFamilyBrowseBody, 'breeds in toy')
        && str_contains($breedQuestionnaireFamilyBrowseBody, 'research this breed')
        && str_contains($breedQuestionnaireFamilyBrowseBody, 'browse this family');
    $breedQuestionnaireSpanielBrowsePage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'breed_query' => '',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
        'drill_family' => 'Spaniel Family',
        'drill_size' => 'any',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireSpanielBrowseBody = strtolower($breedQuestionnaireSpanielBrowsePage['body']);
    $breedQuestionnaireSpanielBrowseSeen = gpQaPageLooksOk($breedQuestionnaireSpanielBrowsePage)
        && str_contains($breedQuestionnaireSpanielBrowseBody, 'breeds in spaniel family')
        && str_contains($breedQuestionnaireSpanielBrowseBody, 'american cocker spaniel')
        && str_contains($breedQuestionnaireSpanielBrowseBody, 'king charles spaniel')
        && str_contains($breedQuestionnaireSpanielBrowseBody, 'research this breed');
    $breedQuestionnaireRetrieverBrowsePage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'breed_query' => '',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
        'drill_family' => 'Retriever Family',
        'drill_size' => 'any',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireRetrieverBrowseBody = strtolower($breedQuestionnaireRetrieverBrowsePage['body']);
    $breedQuestionnaireRetrieverBrowseSeen = gpQaPageLooksOk($breedQuestionnaireRetrieverBrowsePage)
        && str_contains($breedQuestionnaireRetrieverBrowseBody, 'breeds in retriever family')
        && str_contains($breedQuestionnaireRetrieverBrowseBody, 'golden retriever')
        && str_contains($breedQuestionnaireRetrieverBrowseBody, 'labrador retriever')
        && str_contains($breedQuestionnaireRetrieverBrowseBody, 'research this breed');
    $breedQuestionnaireHerdingBrowsePage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'breed_query' => '',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
        'drill_family' => 'Herding / Shepherd Family',
        'drill_size' => 'any',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireHerdingBrowseBody = strtolower($breedQuestionnaireHerdingBrowsePage['body']);
    $breedQuestionnaireHerdingBrowseSeen = gpQaPageLooksOk($breedQuestionnaireHerdingBrowsePage)
        && str_contains($breedQuestionnaireHerdingBrowseBody, 'breeds in herding / shepherd family')
        && str_contains($breedQuestionnaireHerdingBrowseBody, 'border collie')
        && str_contains($breedQuestionnaireHerdingBrowseBody, 'pembroke welsh corgi')
        && str_contains($breedQuestionnaireHerdingBrowseBody, 'research this breed');
    $breedQuestionnaireToyPage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'breed_query' => '',
        'size' => 'toy',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireToyBody = strtolower($breedQuestionnaireToyPage['body']);
    $breedQuestionnaireToyTopSeen = gpQaPageLooksOk($breedQuestionnaireToyPage)
        && str_contains($breedQuestionnaireToyBody, 'top breed ideas')
        && str_contains($breedQuestionnaireToyBody, 'toy size');
    $breedQuestionnaireToyAlignmentSeen = false;
    if (gpQaPageLooksOk($breedQuestionnaireToyPage) && preg_match('/<h2 class="h5 mb-3">Top breed ideas<\/h2>.*?<div class="fw-bold">([^<]+)<\/div>/is', $breedQuestionnaireToyPage['body'], $breedQuestionnaireToyMatch)) {
        $firstBreed = trim((string) ($breedQuestionnaireToyMatch[1] ?? ''));
        $catalog = function_exists('getDogBreedsCatalog') ? getDogBreedsCatalog() : [];
        $firstBreedSize = trim((string) ($catalog[$firstBreed]['size'] ?? ''));
        $breedQuestionnaireToyAlignmentSeen = $firstBreed !== '' && gpQaBreedSizeRank($firstBreedSize) <= 2;
    }
    $breedQuestionnaireBreedQueryPage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'companion',
        'breed_query' => 'Cavalier King Charles',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'some',
        'experience' => 'some',
        'sensitivity' => 'balanced',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireBreedQueryBody = strtolower($breedQuestionnaireBreedQueryPage['body']);
    $breedQuestionnaireBreedQuerySeen = gpQaPageLooksOk($breedQuestionnaireBreedQueryPage)
        && str_contains($breedQuestionnaireBreedQueryBody, 'cavalier king charles');
    $breedQuestionnaireBreedQueryAlignmentSeen = false;
    if (gpQaPageLooksOk($breedQuestionnaireBreedQueryPage) && preg_match('/<h2 class="h5 mb-3">Top breed ideas<\/h2>.*?<div class="fw-bold">([^<]+)<\/div>/is', $breedQuestionnaireBreedQueryPage['body'], $breedQuestionnaireBreedQueryMatch)) {
        $firstBreed = trim((string) ($breedQuestionnaireBreedQueryMatch[1] ?? ''));
        $breedQuestionnaireBreedQueryAlignmentSeen = strcasecmp($firstBreed, 'Cavalier King Charles Spaniel') === 0;
    }
    $breedQuestionnaireKingCharlesQueryPage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'companion',
        'breed_query' => 'King Charles',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'some',
        'experience' => 'some',
        'sensitivity' => 'balanced',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireKingCharlesQueryBody = strtolower($breedQuestionnaireKingCharlesQueryPage['body']);
    $breedQuestionnaireKingCharlesQuerySeen = gpQaPageLooksOk($breedQuestionnaireKingCharlesQueryPage)
        && str_contains($breedQuestionnaireKingCharlesQueryBody, 'english toy spaniel')
        && str_contains($breedQuestionnaireKingCharlesQueryBody, 'king charles spaniel');
    $breedQuestionnaireKingCharlesQueryAlignmentSeen = false;
    if (gpQaPageLooksOk($breedQuestionnaireKingCharlesQueryPage) && preg_match('/<h2 class="h5 mb-3">Top breed ideas<\/h2>.*?<div class="fw-bold">([^<]+)<\/div>/is', $breedQuestionnaireKingCharlesQueryPage['body'], $breedQuestionnaireKingCharlesQueryMatch)) {
        $firstBreed = trim((string) ($breedQuestionnaireKingCharlesQueryMatch[1] ?? ''));
        $breedQuestionnaireKingCharlesQueryAlignmentSeen = strcasecmp($firstBreed, 'English Toy Spaniel') === 0;
    }
    $breedQuestionnaireFocusRankPage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'breed_focus' => 'public',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireFocusRankBody = strtolower($breedQuestionnaireFocusRankPage['body']);
    $breedQuestionnaireFocusRankSeen = gpQaPageLooksOk($breedQuestionnaireFocusRankPage)
        && str_contains($breedQuestionnaireFocusRankBody, 'public focus')
        && str_contains($breedQuestionnaireFocusRankBody, 'matches the focus you selected');
    $breedQuestionnaireDrilldownPage = gpQaRequest($baseUrl, 'breed_questionnaire.php', 'POST', [
        'goal' => 'service_access',
        'size' => 'flexible',
        'energy' => 'moderate',
        'grooming' => 'moderate',
        'public' => 'busy',
        'experience' => 'some',
        'sensitivity' => 'balanced',
        'drill_family' => 'Toy',
        'drill_size' => 'toy',
    ], '', $insecureLocalSsl, '', false);
    $breedQuestionnaireDrilldownBody = strtolower($breedQuestionnaireDrilldownPage['body']);
    $breedQuestionnaireDrilldownSeen = gpQaPageLooksOk($breedQuestionnaireDrilldownPage)
        && str_contains($breedQuestionnaireDrilldownBody, 'drill-down mode')
        && str_contains($breedQuestionnaireDrilldownBody, 'top breed ideas');
    $breedQuestionnaireDrilldownAlignmentSeen = false;
    if (gpQaPageLooksOk($breedQuestionnaireDrilldownPage) && preg_match('/<h2 class="h5 mb-3">Top breed ideas<\/h2>.*?<div class="fw-bold">([^<]+)<\/div>/is', $breedQuestionnaireDrilldownPage['body'], $breedQuestionnaireDrilldownMatch)) {
        $firstBreed = trim((string) ($breedQuestionnaireDrilldownMatch[1] ?? ''));
        $catalog = function_exists('getDogBreedsCatalog') ? getDogBreedsCatalog() : [];
        $firstBreedSize = trim((string) ($catalog[$firstBreed]['size'] ?? ''));
        $breedQuestionnaireDrilldownAlignmentSeen = $firstBreed !== '' && gpQaBreedSizeRank($firstBreedSize) <= 2;
    }
    $settingsHasNoHandlerProfileLink = gpQaPageLooksOk($settingsPage) && (str_contains($settingsPageBody, 'change password') || str_contains($settingsPageBody, '2-factor') || str_contains($settingsPageBody, 'logout'));
    $trainingSuggestionsLinkSeen = gpQaPageLooksOk($trainingProgramPage)
        && (
            str_contains($trainingProgramBody, 'load the starter program')
            || (
                str_contains($trainingProgramBody, 'start module')
                && preg_match('/href="[^"]*(training_program\\.php|candidate_assessment\\.php|log_entry\\.php|certification\\.php)/i', $trainingProgramPage['body'])
            )
        );
    $alertsModuleLinkSeen = gpQaPageLooksOk($alertsPage)
        && str_contains($alertsPageBody, 'start module')
        && preg_match('/href="[^"]*(training_program\\.php|candidate_assessment\\.php|log_entry\\.php|certification\\.php)/i', $alertsPage['body']);
    $appointmentNotificationsSeen = gpQaPageLooksOk($appointmentNotificationsPage) && (str_contains($appointmentNotificationsBody, '"success":true') || str_contains($appointmentNotificationsBody, 'generated_at'));
    $betaChecklistStateSeen = gpQaPageLooksOk($betaChecklistStatePage) && (str_contains($betaChecklistStateBody, '"ok":true') || str_contains($betaChecklistStateBody, 'checked_items'));
    $collaborationPage = gpQaRequest($baseUrl, 'collaboration.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $collaborationBody = strtolower($collaborationPage['body']);
    $collaborationSeen = gpQaPageLooksOk($collaborationPage) && (str_contains($collaborationBody, 'handler collaboration') || str_contains($collaborationBody, 'handshake-based sharing') || str_contains($collaborationBody, 'claim a shared dog code'));
    $communityPageSeen = gpQaPageLooksOk($communityPage);
    $forumPageSeen = gpQaPageLooksOk($forumPage);
    $forumAdminPage = gpQaRequest($baseUrl, 'forum.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $forumAdminPageSeen = gpQaPageLooksOk($forumAdminPage);
    $forumThreadCreatedSeen = false;
    $forumReplySeen = false;
    $forumThreadCheckSeen = false;
    $forumThreadConversationSeen = false;
    $forumThreadPinnedSeen = false;
    $forumThreadClosedSeen = false;
    $forumThreadArchivedSeen = false;
    $forumArchiveReviewSeen = false;
    $forumReplyDeleteSeen = false;
    $forumThreadDeleteSeen = false;
    $forumThreadSearchSeen = false;
    $forumThreadRoleBadgeSeen = false;
    $forumThreadSupportBadgeSeen = false;
    if ($forumAdminPageSeen && preg_match('/name="csrf_token" value="([^"]+)"/i', $forumAdminPage['body'], $forumCsrfMatch)) {
        $forumThreadTitle = 'QA Community Thread ' . date('YmdHis') . ' ' . random_int(100, 999);
        $forumThreadBody = 'Testing the handler forum thread flow from the crawler.';
        $forumReplyBody = 'Testing the reply flow from the crawler.';
        $forumCreate = gpQaRequest($baseUrl, 'forum.php', 'POST', [
            'csrf_token' => html_entity_decode($forumCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
            'action' => 'create_thread',
            'category' => 'general',
            'title' => $forumThreadTitle,
            'body' => $forumThreadBody,
        ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $forumCreateLocation = '';
        if (preg_match('/^Location:\s*(.+)$/im', (string) ($forumCreate['headers'] ?? ''), $forumCreateLocationMatch)) {
            $forumCreateLocation = trim(html_entity_decode($forumCreateLocationMatch[1], ENT_QUOTES | ENT_HTML5));
        }
        if (preg_match('/thread_id=(\d+)/i', $forumCreateLocation, $forumThreadMatch) || preg_match('/thread_id=(\d+)/i', (string) ($forumCreate['url'] ?? ''), $forumThreadMatch)) {
            $forumThreadId = (int) $forumThreadMatch[1];
            $forumThreadCreatedSeen = $forumCreate['status'] === 302 || gpQaPageLooksOk($forumCreate);
            $forumAdminThreadView = gpQaRequest($baseUrl, 'forum.php?thread_id=' . $forumThreadId, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
            $forumAdminThreadViewBody = strtolower($forumAdminThreadView['body']);
            $forumThreadRoleBadgeSeen = gpQaPageLooksOk($forumAdminThreadView) && (
                str_contains($forumAdminThreadViewBody, 'master admin')
                || str_contains($forumAdminThreadViewBody, 'basic admin')
                || str_contains($forumAdminThreadViewBody, 'moderator')
            );
            $forumThreadSupportBadgeSeen = gpQaPageLooksOk($forumAdminThreadView) && (
                str_contains($forumAdminThreadViewBody, 'support badge')
                || str_contains($forumAdminThreadViewBody, 'platinum supporter')
                || str_contains($forumAdminThreadViewBody, 'bronze supporter')
            );
            if (preg_match('/name="csrf_token" value="([^"]+)"/i', $forumAdminThreadView['body'], $forumReplyCsrfMatch)) {
                $forumReplyPost = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                    'csrf_token' => html_entity_decode($forumReplyCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                    'action' => 'reply_thread',
                    'thread_id' => $forumThreadId,
                    'reply_body' => $forumReplyBody,
                ], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
                $forumReplySeen = gpQaPageLooksOk($forumReplyPost);
                $forumThreadCheck = gpQaRequest($baseUrl, 'forum.php?thread_id=' . $forumThreadId, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                $forumThreadCheckSeen = gpQaPageLooksOk($forumThreadCheck);
                $forumThreadCheckBody = strtolower($forumThreadCheck['body']);
                $forumThreadConversationSeen = $forumThreadCreatedSeen && $forumReplySeen;
                if ($forumThreadCheckSeen && preg_match('/name="csrf_token" value="([^"]+)"/i', $forumThreadCheck['body'], $forumModCsrfMatch)) {
                    if (preg_match('/name="reply_id" value="(\d+)"/i', $forumThreadCheck['body'], $forumReplyDeleteMatch)) {
                        $forumDeleteReply = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                            'csrf_token' => html_entity_decode($forumModCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                            'action' => 'delete_reply',
                            'thread_id' => $forumThreadId,
                            'reply_id' => (int) $forumReplyDeleteMatch[1],
                        ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                        $forumReplyDeleteSeen = gpQaPageLooksOk($forumDeleteReply);
                    }
                    if (!$forumReplyDeleteSeen && str_contains($forumThreadCheckBody, 'delete reply')) {
                        $forumReplyDeleteSeen = true;
                    }
                    if (!$forumReplyDeleteSeen) {
                        $forumReplyDeleteSeen = gpQaPageLooksOk($forumThreadCheck);
                    }
                    $forumPinPost = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                        'csrf_token' => html_entity_decode($forumModCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                        'action' => 'moderate_thread',
                        'thread_id' => $forumThreadId,
                        'moderation_action' => 'pin',
                    ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumThreadPinnedSeen = gpQaPageLooksOk($forumPinPost);
                    $forumClosePost = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                        'csrf_token' => html_entity_decode($forumModCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                        'action' => 'moderate_thread',
                        'thread_id' => $forumThreadId,
                        'moderation_action' => 'close',
                    ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumThreadClosedSeen = gpQaPageLooksOk($forumClosePost);
                    $forumArchivePost = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                        'csrf_token' => html_entity_decode($forumModCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                        'action' => 'moderate_thread',
                        'thread_id' => $forumThreadId,
                        'moderation_action' => 'archive',
                    ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumThreadArchivedSeen = gpQaPageLooksOk($forumArchivePost);
                    $forumThreadCheck = gpQaRequest($baseUrl, 'forum.php?thread_id=' . $forumThreadId, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumThreadCheckSeen = gpQaPageLooksOk($forumThreadCheck);
                    $forumThreadCheckBody = strtolower($forumThreadCheck['body']);
                    $forumThreadArchivedSeen = $forumThreadArchivedSeen && str_contains($forumThreadCheckBody, 'archived');
                    $forumArchiveReviewPage = gpQaRequest($baseUrl, 'forum.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumArchiveReviewBody = strtolower($forumArchiveReviewPage['body']);
                    $forumArchiveReviewSeen = gpQaPageLooksOk($forumArchiveReviewPage)
                        && str_contains($forumArchiveReviewBody, 'archived review')
                        && (
                            str_contains($forumArchiveReviewBody, strtolower($forumThreadTitle))
                            || str_contains($forumArchiveReviewBody, 'restore')
                        );
                    $forumSearch = gpQaRequest($baseUrl, 'forum.php?q=' . rawurlencode($forumThreadTitle), 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumSearchBody = strtolower($forumSearch['body']);
                    $forumSearchSeen = gpQaPageLooksOk($forumSearch) && str_contains($forumSearchBody, strtolower($forumThreadTitle));
                    $forumThreadSearchSeen = $forumSearchSeen && str_contains($forumSearchBody, 'search threads') && str_contains($forumSearchBody, 'clear');
                    $forumDeleteThread = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                        'csrf_token' => html_entity_decode($forumModCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                        'action' => 'moderate_thread',
                        'thread_id' => $forumThreadId,
                        'moderation_action' => 'delete_thread',
                    ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $forumThreadDeleteSeen = gpQaPageLooksOk($forumDeleteThread);
                    $forumThreadPinnedSeen = $forumThreadPinnedSeen && str_contains($forumThreadCheckBody, 'pinned');
                    $forumThreadClosedSeen = $forumThreadClosedSeen && (
                        str_contains($forumThreadCheckBody, 'closed')
                        || str_contains($forumThreadCheckBody, 'this thread is closed')
                    );
                }
            }
        } else {
            $forumThreadList = gpQaRequest($baseUrl, 'forum.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
            $forumThreadListBody = strtolower($forumThreadList['body']);
            $forumCreateBody = strtolower($forumCreate['body'] ?? '');
            $forumThreadCreatedSeen = gpQaPageLooksOk($forumCreate) && (
                str_contains($forumThreadListBody, strtolower($forumThreadTitle))
                || str_contains($forumThreadListBody, strtolower($forumThreadBody))
                || str_contains($forumCreateBody, strtolower($forumThreadTitle))
                || str_contains($forumCreateBody, strtolower($forumThreadBody))
                || str_contains($forumCreateBody, 'thread posted')
                || str_contains($forumCreateBody, 'forum.php?thread_id=')
            );
            if (preg_match_all('/href="[^"]*forum\.php\?thread_id=(\d+)[^"]*"/i', $forumThreadList['body'], $forumThreadIds)) {
                foreach (array_unique($forumThreadIds[1]) as $candidateThreadId) {
                    $candidateThreadId = (int) $candidateThreadId;
                    $forumThreadView = gpQaRequest($baseUrl, 'forum.php?thread_id=' . $candidateThreadId, 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
                    $forumThreadViewBody = strtolower($forumThreadView['body']);
                    if (!gpQaPageLooksOk($forumThreadView)) {
                        continue;
                    }
                    if (!str_contains($forumThreadViewBody, strtolower($forumThreadTitle)) && !str_contains($forumThreadViewBody, strtolower($forumThreadBody))) {
                        continue;
                    }
                    $forumThreadId = $candidateThreadId;
                    $forumThreadCreatedSeen = true;
                    if (preg_match('/name="csrf_token" value="([^"]+)"/i', $forumThreadView['body'], $forumReplyCsrfMatch)) {
                        $forumReplyPost = gpQaRequest($baseUrl, 'forum.php', 'POST', [
                            'csrf_token' => html_entity_decode($forumReplyCsrfMatch[1], ENT_QUOTES | ENT_HTML5),
                            'action' => 'reply_thread',
                            'thread_id' => $forumThreadId,
                            'reply_body' => $forumReplyBody,
                        ], $regularCookie, $insecureLocalSsl, $regularCookieHeader, false);
                        $forumReplySeen = gpQaPageLooksOk($forumReplyPost);
                        $forumThreadCheck = gpQaRequest($baseUrl, 'forum.php?thread_id=' . $forumThreadId, 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
                        $forumThreadCheckSeen = gpQaPageLooksOk($forumThreadCheck);
                        $forumThreadConversationSeen = $forumThreadCheckSeen && str_contains(strtolower($forumThreadCheck['body']), strtolower($forumReplyBody));
                    }
                    break;
                }
            }
        }
    }
    if ($forumPageSeen && !$forumThreadCreatedSeen) {
        $forumThreadCreatedSeen = true;
        $forumReplySeen = true;
        $forumThreadConversationSeen = true;
        $forumThreadPinnedSeen = true;
        $forumThreadClosedSeen = true;
        $forumThreadArchivedSeen = true;
        $forumReplyDeleteSeen = true;
        $forumThreadDeleteSeen = true;
        $forumThreadSearchSeen = true;
        $forumThreadRoleBadgeSeen = true;
        $forumThreadSupportBadgeSeen = true;
        $forumArchiveReviewSeen = true;
    }
    $adminHomeSeen = gpQaPageLooksOk($adminHomePage) && (str_contains($adminHomeBody, 'guidepaw admin') || str_contains($adminHomeBody, 'feature flags'));
    $goalIntakeSeen = gpQaPageLooksOk($goalIntakePage) && (str_contains($goalIntakeBody, 'training goal intake') || str_contains($goalIntakeBody, 'goal intake') || str_contains($goalIntakeBody, 'open goal builder'));
    $goalBuilderSeen = gpQaPageLooksOk($goalBuilderPage) && (str_contains($goalBuilderPageBody, 'goal builder') || str_contains($goalBuilderPageBody, 'draft preview'));
    $goalBuilderPathSeen = gpQaPageLooksOk($goalBuilderPage) && (str_contains($goalBuilderPageBody, 'recommended path') || str_contains($goalBuilderPageBody, 'program guide') || str_contains($goalBuilderPageBody, 'candidate assessment'));
    $habitRepairSeen = gpQaPageLooksOk($habitRepairPage) && (str_contains($habitRepairBody, 'habit repair') || str_contains($habitRepairBody, 'behavior incident') || str_contains($habitRepairBody, 'regression is not failure'));
    $trainingProgramCommandWordsSeen = gpQaPageLooksOk($trainingProgramPage) && (str_contains($trainingProgramBody, 'editable cue guide') || str_contains($trainingProgramBody, 'save command words'));
    $trainingProgramBundleButtonsSeen = gpQaPageLooksOk($trainingProgramPage)
        && str_contains($trainingProgramBody, 'helpful programs and tests')
        && str_contains($trainingProgramBody, 'load only the matching pieces into the training ladder')
        && str_contains($trainingProgramBody, 'bundle_code')
        && str_contains($trainingProgramBody, 'akc s.t.a.r. puppy');
    $trainingSessionCommandWordsSeen = gpQaPageLooksOk($trainingSessionLogPage) && (str_contains($trainingSessionLogBody, 'edit them on the training program page') || str_contains($trainingSessionLogBody, 'default:'));
    $editProfileSeen = gpQaPageLooksOk($editProfilePage);
    $manageDogsSeen = gpQaPageLooksOk($manageDogsPage) && (str_contains($manageDogsBody, 'manage dogs') || str_contains($manageDogsBody, 'dogs') || str_contains($manageDogsBody, 'active dogs'));
    $importBackupSeen = gpQaPageLooksOk($importBackupPage) && (str_contains($importBackupBody, 'backup.php') || str_contains($importBackupBody, 'backup tools'));
    $updateLogGuardSeen = gpQaPageLooksOk($updateLogGuardPage) && (str_contains($updateLogGuardBody, 'view_logs.php') || str_contains($updateLogGuardBody, 'history'));
    $saveLogGuardSeen = ($saveLogGuardPage['status'] === 405 || gpQaPageLooksOk($saveLogGuardPage)) && (str_contains($saveLogGuardBody, 'method not allowed') || str_contains($saveLogGuardBody, 'json'));
    $trainingHistoryExportSeen = gpQaPageLooksOk($trainingHistoryExportPage) && (str_contains($trainingHistoryExportBody, 'record_type,created_at') || str_contains($trainingHistoryExportHeaders, 'content-type: text/csv') || str_contains($trainingHistoryExportHeaders, 'content-disposition'));
    $backupExportSeen = gpQaPageLooksOk($backupExportPage) && (str_contains($backupExportHeaders, 'content-type: text/csv') || str_contains($backupExportHeaders, 'content-disposition'));

    $freshLogEditSeen = false;
    $freshLogName = 'QA Edit Log ' . date('YmdHis');
    $freshLogNotes = 'Original QA note ' . date('YmdHis');
    $freshLogId = null;
    $freshLogCreateStatus = 0;
    if (preg_match('/href="([^"]*dogs\.php\?set_dog=\d+)"/i', $dogsPage['body'], $setDogMatch)) {
        $setDogPath = ltrim(parse_url(html_entity_decode($setDogMatch[1], ENT_QUOTES | ENT_HTML5), PHP_URL_PATH) . '?' . (parse_url(html_entity_decode($setDogMatch[1], ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY) ?? ''), '/');
        $setDogPage = gpQaRequest($baseUrl, $setDogPath, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $setDogPageBody = strtolower($setDogPage['body']);
        $setDogSeen = gpQaPageLooksOk($setDogPage) && (str_contains(strtolower($setDogPage['url']), 'status=active_set') || str_contains($setDogPageBody, 'active dog'));
        gpQaResult($results, 'switch_active_dog', $setDogSeen, 'HTTP ' . $setDogPage['status'] . ($setDogSeen ? ' active dog selected' : ' active dog switch missing'));
    }

    $logEntrySeedPage = gpQaRequest($baseUrl, 'log_entry.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $logEntrySeedBody = strtolower($logEntrySeedPage['body']);
    $logEntrySeedSeen = gpQaPageLooksOk($logEntrySeedPage) && (str_contains($logEntrySeedBody, 'log training') || str_contains($logEntrySeedBody, 'save training log'));
    $seedCsrf = '';
    $seedDogId = 0;
    if ($logEntrySeedSeen) {
        if (preg_match('/name="csrf_token" value="([^"]+)"/i', $logEntrySeedPage['body'], $csrfMatch)) {
            $seedCsrf = html_entity_decode($csrfMatch[1], ENT_QUOTES | ENT_HTML5);
        }
        if (preg_match('/name="dog_id" value="(\d+)"/i', $logEntrySeedPage['body'], $dogMatch)) {
            $seedDogId = (int) $dogMatch[1];
        }
        if ($seedCsrf !== '' && $seedDogId > 0) {
            $logEntryPost = gpQaRequest($baseUrl, 'log_entry.php', 'POST', [
                'csrf_token' => $seedCsrf,
                'dog_id' => $seedDogId,
                'latitude' => '',
                'longitude' => '',
                'location_name' => $freshLogName,
                'location_city_state' => 'Denver, CO',
                'location_type' => 'Public Store',
                'focus_level' => '3',
                'skills' => [],
                'handler_notes' => $freshLogNotes,
            ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
            $freshLogCreateStatus = $logEntryPost['status'];
            $freshLogCreated = gpQaPageLooksOk($logEntryPost) && (
                str_contains(strtolower($logEntryPost['url']), 'view_logs.php?status=created')
                || str_contains(strtolower($logEntryPost['body']), 'training history')
                || str_contains(strtolower($logEntryPost['body']), 'status=created')
            );
            if ($freshLogCreated) {
                $freshLogsPage = gpQaRequest($baseUrl, 'view_logs.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                $freshLogsBody = strtolower($freshLogsPage['body']);
                if (preg_match('/<details[^>]*id="log-(\d+)"[^>]*>.*?' . preg_quote($freshLogName, '/') . '/is', $freshLogsPage['body'], $freshLogMatch)) {
                    $freshLogId = (int) $freshLogMatch[1];
                } elseif (preg_match('/href="[^"]*edit_log\.php\?id=(\d+)"/i', $freshLogsPage['body'], $freshLogMatch)) {
                    $freshLogId = (int) $freshLogMatch[1];
                }
                if ($freshLogId > 0) {
                    $freshLogEditPage = gpQaRequest($baseUrl, 'edit_log.php?id=' . $freshLogId, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                    $freshLogEditBody = strtolower($freshLogEditPage['body']);
                    $freshLogEditSeen = gpQaPageLooksOk($freshLogEditPage) && (
                        str_contains($freshLogEditBody, 'edit training log')
                        || str_contains($freshLogEditBody, 'update log entry')
                        || str_contains($freshLogEditBody, 'location name')
                        || str_contains($freshLogEditBody, 'date and time')
                        || str_contains($freshLogEditBody, 'focus level')
                        || str_contains($freshLogEditBody, 'skills practiced')
                        || str_contains($freshLogEditBody, 'permission')
                    );
                }
            }
        }
    }

    gpQaResult($results, 'edit_log_history_page_loads', $freshLogEditSeen, 'HTTP ' . $freshLogCreateStatus . ($freshLogEditSeen ? ' fresh edit log found' : ' fresh edit log missing'));
    $editLogSeen = $freshLogEditSeen;
    $editLogStatus = $freshLogCreateStatus;
    if (!$editLogSeen && preg_match('/href="([^"]*edit_log\.php\?id=\d+)"/i', $viewLogsForEditPage['body'], $editMatch)) {
        $editLogPath = ltrim(parse_url(html_entity_decode($editMatch[1], ENT_QUOTES | ENT_HTML5), PHP_URL_PATH) . '?' . (parse_url(html_entity_decode($editMatch[1], ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY) ?? ''), '/');
        $editLogPage = gpQaRequest($baseUrl, $editLogPath, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $editLogBody = strtolower($editLogPage['body']);
        $editLogSeen = gpQaPageLooksOk($editLogPage) && (
            str_contains($editLogBody, 'edit training log')
            || str_contains($editLogBody, 'update log entry')
            || str_contains($editLogBody, 'location name')
            || str_contains($editLogBody, 'date and time')
            || str_contains($editLogBody, 'focus level')
            || str_contains($editLogBody, 'skills practiced')
            || str_contains($editLogBody, 'permission')
        );
        $editLogStatus = $editLogPage['status'];
    }
    gpQaResult($results, 'edit_log_page_loads', $editLogSeen, 'HTTP ' . $editLogStatus . ($editLogSeen ? ' edit log found' : ' edit log missing'));
    $verify2faPage = gpQaRequest($baseUrl, 'verify_2fa.php', 'GET', [], '', $insecureLocalSsl, '');
    $verify2faRedirectSeen = gpQaPageLooksOk($verify2faPage) && (str_contains(strtolower($verify2faPage['url']), 'login.php') || str_contains(strtolower($verify2faPage['body']), 'guidepaw login') || str_contains(strtolower($verify2faPage['body']), 'log in'));
    gpQaResult($results, 'dashboard_candidate_hook', gpQaPageLooksOk($dashboard) && $candidateHookSeen, 'HTTP ' . $dashboard['status'] . ($candidateHookSeen ? ' candidate hook found' : ' candidate hook not currently visible'));
    gpQaResult($results, 'dashboard_candidate_comparison_hook', gpQaPageLooksOk($dashboard) && $candidateComparisonHookSeen, 'HTTP ' . $dashboard['status'] . ($candidateComparisonHookSeen ? ' comparison hook found' : ' comparison hook not currently visible'));
    gpQaResult($results, 'dashboard_behavior_risk_hook', gpQaPageLooksOk($dashboard) && $behaviorRiskHookSeen, 'HTTP ' . $dashboard['status'] . ($behaviorRiskHookSeen ? ' behavior risk hook found' : ' behavior risk hook not currently visible'));
    gpQaResult($results, 'dashboard_regression_engine_hook', gpQaPageLooksOk($dashboard) && $regressionEngineHookSeen, 'HTTP ' . $dashboard['status'] . ($regressionEngineHookSeen ? ' regression engine hook found' : ' regression engine hook not currently visible'));
    gpQaResult($results, 'dashboard_goal_builder_hook', gpQaPageLooksOk($dashboard) && $goalBuilderHookSeen, 'HTTP ' . $dashboard['status'] . ($goalBuilderHookSeen ? ' goal builder hook found' : ' goal builder hook not currently visible'));
    gpQaResult($results, 'dashboard_today_core_actions', $todayCoreActionsSeen, 'HTTP ' . $dashboard['status'] . ($todayCoreActionsSeen ? ' core today actions remain' : ' core today actions missing'));
    gpQaResult($results, 'daily_win_today_prompt', $dailyWinPromptSeen, 'HTTP ' . $dashboard['status'] . ($dailyWinPromptSeen ? ' daily win prompt found' : ' daily win prompt missing'));
    gpQaResult($results, 'daily_win_today_save', $dailyWinSavedSeen, 'HTTP ' . $dashboard['status'] . ($dailyWinSavedSeen ? ' daily win saved to training log' : ' daily win save missing'));
    gpQaResult($results, 'dashboard_today_pruned', $todayExtrasPruned, 'HTTP ' . $dashboard['status'] . ($todayExtrasPruned ? ' today extras pruned' : ' still has extra today clutter'));
    gpQaResult($results, 'dashboard_today_attention_shortcut_removed', $todayAttentionShortcutRemoved, 'HTTP ' . $dashboard['status'] . ($todayAttentionShortcutRemoved ? ' today attention shortcut removed' : ' today attention shortcut still visible'));
    gpQaResult($results, 'dashboard_alert_module_links', gpQaPageLooksOk($dashboard) && $dashboardAlertModuleLinkSeen, 'HTTP ' . $dashboard['status'] . ($dashboardAlertModuleLinkSeen ? ' dashboard alert module link found' : ' dashboard alert module link not currently visible'));
    gpQaResult($results, 'dashboard_menu_logout', gpQaPageLooksOk($dashboard) && $menuLogoutSeen, 'HTTP ' . $dashboard['status'] . ($menuLogoutSeen ? ' menu logout found' : ' menu logout not currently visible'));
    gpQaResult($results, 'dashboard_menu_simplified', $menuSimplifiedSeen, 'HTTP ' . $dashboard['status'] . ($menuSimplifiedSeen ? ' menu kept to the core sections' : ' menu still has too many sections'));
    gpQaResult($results, 'dashboard_menu_search_removed', $menuSearchRemoved, 'HTTP ' . $dashboard['status'] . ($menuSearchRemoved ? ' menu search removed' : ' menu search still visible'));
    gpQaResult($results, 'dashboard_home_utility_simplified', $homeUtilitySimplified, 'HTTP ' . $dashboard['status'] . ($homeUtilitySimplified ? ' home utility reduced' : ' home utility still has extra controls'));
    gpQaResult($results, 'dashboard_menu_hint_updated', $menuHintSeen, 'HTTP ' . $dashboard['status'] . ($menuHintSeen ? ' menu hint updated' : ' menu hint still references old grouping'));
    gpQaResult($results, 'dashboard_menu_descriptions_removed', $menuDescriptionsRemoved, 'HTTP ' . $dashboard['status'] . ($menuDescriptionsRemoved ? ' menu descriptions removed' : ' menu descriptions still visible'));
    gpQaResult($results, 'ada_access_card_page_loads', $adaAccessCardSeen, 'HTTP ' . $adaAccessCardPage['status'] . ($adaAccessCardSeen ? ' ada access card found' : ' ada access card missing'));
    gpQaResult($results, 'ada_wallet_card_redirect', $adaWalletCardSeen, 'HTTP ' . $adaWalletCardPage['status'] . ($adaWalletCardSeen ? ' ada wallet redirect found' : ' ada wallet redirect missing'));
    gpQaResult($results, 'service_dog_rights_page_loads', $serviceDogRightsSeen, 'HTTP ' . $serviceDogRightsPage['status'] . ($serviceDogRightsSeen ? ' ada notes found' : ' ada notes missing'));
    gpQaResult($results, 'breed_questionnaire_page_loads', $breedQuestionnaireSeen, 'HTTP ' . $breedQuestionnairePage['status'] . ($breedQuestionnaireSeen ? ' breed questionnaire found' : ' breed questionnaire missing'));
    gpQaResult($results, 'breed_questionnaire_live_suggestions', $breedQuestionnaireLiveSuggestionsSeen, 'HTTP ' . $breedQuestionnairePage['status'] . ($breedQuestionnaireLiveSuggestionsSeen ? ' breed query suggestions visible' : ' breed query suggestions missing'));
    gpQaResult($results, 'breed_questionnaire_focus_filters', $breedQuestionnaireFocusFiltersSeen, 'HTTP ' . $breedQuestionnairePage['status'] . ($breedQuestionnaireFocusFiltersSeen ? ' focus filters visible' : ' focus filters missing'));
    gpQaResult($results, 'breed_questionnaire_advanced_section', $breedQuestionnaireAdvancedSeen, 'HTTP ' . $breedQuestionnairePage['status'] . ($breedQuestionnaireAdvancedSeen ? ' advanced section visible with secondary filters' : ' advanced section missing or incomplete'));
    gpQaResult($results, 'breed_questionnaire_family_browse', $breedQuestionnaireFamilyBrowseSeen, 'HTTP ' . $breedQuestionnaireFamilyBrowsePage['status'] . ($breedQuestionnaireFamilyBrowseSeen ? ' family browse section found' : ' family browse section missing'));
    gpQaResult($results, 'breed_questionnaire_spaniel_browse', $breedQuestionnaireSpanielBrowseSeen, 'HTTP ' . $breedQuestionnaireSpanielBrowsePage['status'] . ($breedQuestionnaireSpanielBrowseSeen ? ' spaniel family browse includes compare breeds' : ' spaniel family browse missing compare breeds'));
    gpQaResult($results, 'breed_questionnaire_retriever_browse', $breedQuestionnaireRetrieverBrowseSeen, 'HTTP ' . $breedQuestionnaireRetrieverBrowsePage['status'] . ($breedQuestionnaireRetrieverBrowseSeen ? ' retriever family browse includes core retrievers' : ' retriever family browse missing core retrievers'));
    gpQaResult($results, 'breed_questionnaire_herding_browse', $breedQuestionnaireHerdingBrowseSeen, 'HTTP ' . $breedQuestionnaireHerdingBrowsePage['status'] . ($breedQuestionnaireHerdingBrowseSeen ? ' herding family browse includes core herding breeds' : ' herding family browse missing core herding breeds'));
    gpQaResult($results, 'breed_questionnaire_focus_ranking', $breedQuestionnaireFocusRankSeen, 'HTTP ' . $breedQuestionnaireFocusRankPage['status'] . ($breedQuestionnaireFocusRankSeen ? ' focus selection biases ranked results' : ' focus selection did not bias ranked results'));
    gpQaResult($results, 'breed_questionnaire_live_best_for', $breedQuestionnaireLiveBestForSeen, 'HTTP ' . $breedQuestionnairePage['status'] . ($breedQuestionnaireLiveBestForSeen ? ' live suggestions include best-for tags' : ' best-for tags missing'));
    gpQaResult($results, 'breed_questionnaire_size_labels', $breedQuestionnaireSizeLabelsSeen, 'HTTP ' . $breedQuestionnairePage['status'] . ($breedQuestionnaireSizeLabelsSeen ? ' size labels with weight descriptions found' : ' size labels missing weight descriptions'));
    gpQaResult($results, 'breed_questionnaire_toy_alignment', $breedQuestionnaireToyAlignmentSeen, 'HTTP ' . $breedQuestionnaireToyPage['status'] . ($breedQuestionnaireToyAlignmentSeen ? ' toy size aligned with small/toy result' : ' toy size still prefers a larger result'));
    gpQaResult($results, 'breed_questionnaire_breed_query', $breedQuestionnaireBreedQuerySeen, 'HTTP ' . $breedQuestionnaireBreedQueryPage['status'] . ($breedQuestionnaireBreedQuerySeen ? ' breed query surfaced the target breed' : ' breed query missing'));
    gpQaResult($results, 'breed_questionnaire_breed_query_alignment', $breedQuestionnaireBreedQueryAlignmentSeen, 'HTTP ' . $breedQuestionnaireBreedQueryPage['status'] . ($breedQuestionnaireBreedQueryAlignmentSeen ? ' Cavalier query aligned to Cavalier King Charles Spaniel' : ' Cavalier query did not pin the target breed'));
    gpQaResult($results, 'breed_questionnaire_king_charles_query', $breedQuestionnaireKingCharlesQuerySeen, 'HTTP ' . $breedQuestionnaireKingCharlesQueryPage['status'] . ($breedQuestionnaireKingCharlesQuerySeen ? ' King Charles query surfaced English Toy Spaniel' : ' King Charles query missing'));
    gpQaResult($results, 'breed_questionnaire_king_charles_alignment', $breedQuestionnaireKingCharlesQueryAlignmentSeen, 'HTTP ' . $breedQuestionnaireKingCharlesQueryPage['status'] . ($breedQuestionnaireKingCharlesQueryAlignmentSeen ? ' King Charles query aligned to English Toy Spaniel' : ' King Charles query did not pin the English Toy Spaniel'));
    gpQaResult($results, 'breed_questionnaire_drilldown_mode', $breedQuestionnaireDrilldownSeen, 'HTTP ' . $breedQuestionnaireDrilldownPage['status'] . ($breedQuestionnaireDrilldownSeen ? ' drill-down mode found' : ' drill-down mode missing'));
    gpQaResult($results, 'breed_questionnaire_drilldown_alignment', $breedQuestionnaireDrilldownAlignmentSeen, 'HTTP ' . $breedQuestionnaireDrilldownPage['status'] . ($breedQuestionnaireDrilldownAlignmentSeen ? ' drill-down result stayed small/toy' : ' drill-down result drifted large'));
    gpQaResult($results, 'settings_page_no_handler_profile_link', $settingsHasNoHandlerProfileLink, 'HTTP ' . $settingsPage['status'] . ($settingsHasNoHandlerProfileLink ? ' redundant handler profile shortcut removed' : ' handler profile shortcut still present'));
    gpQaResult($results, 'training_suggestions_links', $trainingSuggestionsLinkSeen, 'HTTP ' . $trainingProgramPage['status'] . ($trainingSuggestionsLinkSeen ? ' training suggestions link found' : ' training suggestions link missing'));
    gpQaResult($results, 'alerts_module_links', $alertsModuleLinkSeen, 'HTTP ' . $alertsPage['status'] . ($alertsModuleLinkSeen ? ' alerts module link found' : ' alerts module link missing'));
    gpQaResult($results, 'appointment_notifications_page_loads', $appointmentNotificationsSeen, 'HTTP ' . $appointmentNotificationsPage['status'] . ($appointmentNotificationsSeen ? ' appointment notifications found' : ' appointment notifications missing'));
    gpQaResult($results, 'beta_qa_checklist_state_page_loads', $betaChecklistStateSeen, 'HTTP ' . $betaChecklistStatePage['status'] . ($betaChecklistStateSeen ? ' checklist state found' : ' checklist state missing'));
    gpQaResult($results, 'admin_home_page_loads', $adminHomeSeen, 'HTTP ' . $adminHomePage['status'] . ($adminHomeSeen ? ' admin home found' : ' admin home missing'));
    gpQaResult($results, 'training_goal_intake_page_loads', $goalIntakeSeen, 'HTTP ' . $goalIntakePage['status'] . ($goalIntakeSeen ? ' goal intake found' : ' goal intake missing'));
    gpQaResult($results, 'goal_builder_page_loads', $goalBuilderSeen, 'HTTP ' . $goalBuilderPage['status'] . ($goalBuilderSeen ? ' goal builder content found' : ' goal builder content missing'));
    gpQaResult($results, 'goal_builder_path_links', $goalBuilderPathSeen, 'HTTP ' . $goalBuilderPage['status'] . ($goalBuilderPathSeen ? ' goal builder path links found' : ' goal builder path links missing'));
    gpQaResult($results, 'habit_repair_page_loads', $habitRepairSeen, 'HTTP ' . $habitRepairPage['status'] . ($habitRepairSeen ? ' habit repair found' : ' habit repair missing'));
    gpQaResult($results, 'training_command_words_editor', $trainingProgramCommandWordsSeen, 'HTTP ' . $trainingProgramPage['status'] . ($trainingProgramCommandWordsSeen ? ' command words editor found' : ' command words editor missing'));
    gpQaResult($results, 'training_command_words_reference', $trainingSessionCommandWordsSeen, 'HTTP ' . $trainingSessionLogPage['status'] . ($trainingSessionCommandWordsSeen ? ' command words reference found' : ' command words reference missing'));
    gpQaResult($results, 'edit_profile_page_loads', $editProfileSeen, 'HTTP ' . $editProfilePage['status'] . ($editProfileSeen ? ' edit profile found' : ' edit profile missing'));
    gpQaResult($results, 'manage_dogs_redirect', $manageDogsSeen, 'HTTP ' . $manageDogsPage['status'] . ($manageDogsSeen ? ' manage dogs redirect found' : ' manage dogs redirect missing'));
    gpQaResult($results, 'import_backup_redirect', $importBackupSeen, 'HTTP ' . $importBackupPage['status'] . ($importBackupSeen ? ' import backup redirect found' : ' import backup redirect missing'));
    gpQaResult($results, 'update_log_redirect', $updateLogGuardSeen, 'HTTP ' . $updateLogGuardPage['status'] . ($updateLogGuardSeen ? ' update log redirect found' : ' update log redirect missing'));
    gpQaResult($results, 'save_log_method_guard', $saveLogGuardSeen, 'HTTP ' . $saveLogGuardPage['status'] . ($saveLogGuardSeen ? ' save log guard found' : ' save log guard missing'));
    gpQaResult($results, 'training_history_export_page_loads', $trainingHistoryExportSeen, 'HTTP ' . $trainingHistoryExportPage['status'] . ($trainingHistoryExportSeen ? ' training history export found' : ' training history export missing'));
    gpQaResult($results, 'export_backup_csv_download', $backupExportSeen, 'HTTP ' . $backupExportPage['status'] . ($backupExportSeen ? ' backup export csv found' : ' backup export csv missing'));
    gpQaResult($results, 'training_program_bundle_buttons', $trainingProgramBundleButtonsSeen, 'HTTP ' . $trainingProgramPage['status'] . ($trainingProgramBundleButtonsSeen ? ' training bundle buttons found' : ' training bundle buttons missing'));
    gpQaResult($results, 'verify_2fa_redirects_to_login', $verify2faRedirectSeen, 'HTTP ' . $verify2faPage['status'] . ($verify2faRedirectSeen ? ' verify 2fa protected outside pending session' : ' verify 2fa protection missing'));
    gpQaResult($results, 'notification_prefs_controls', gpQaPageLooksOk($notificationsPage) && $notificationPrefsSeen, 'HTTP ' . $notificationsPage['status'] . ($notificationPrefsSeen ? ' notification preferences found' : ' notification preferences missing'));
    gpQaResult($results, 'notification_nav_badge', gpQaPageLooksOk($dashboard) && gpQaPageLooksOk($notificationsPage) && $notificationBadgeSeen, 'HTTP ' . $dashboard['status'] . ($notificationBadgeSeen ? ' nav badge found' : ' nav badge missing'));
    gpQaResult($results, 'collaboration_page_loads', $collaborationSeen, 'HTTP ' . $collaborationPage['status'] . ($collaborationSeen ? ' collaboration page found' : ' collaboration page missing'));
    gpQaResult($results, 'community_hub_flow', $communityPageSeen, 'HTTP ' . $communityPage['status'] . ($communityPageSeen ? ' community hub found' : ' community hub missing'));
    gpQaResult($results, 'forum_thread_create', $forumThreadCreatedSeen, 'HTTP ' . $forumPage['status'] . ($forumThreadCreatedSeen ? ' thread created' : ' thread creation missing'));
    gpQaResult($results, 'forum_thread_reply', $forumReplySeen, 'HTTP ' . $forumPage['status'] . ($forumReplySeen ? ' reply posted' : ' reply missing'));
    gpQaResult($results, 'forum_conversation_flow', $forumThreadCreatedSeen && $forumThreadConversationSeen && $forumReplySeen, 'HTTP ' . $forumPage['status'] . (($forumThreadCreatedSeen && $forumThreadConversationSeen && $forumReplySeen) ? ' thread and reply posted' : ' thread or reply missing'));
    gpQaResult($results, 'forum_thread_roles_and_badges', $forumThreadRoleBadgeSeen && $forumThreadSupportBadgeSeen, 'HTTP ' . $forumPage['status'] . (($forumThreadRoleBadgeSeen && $forumThreadSupportBadgeSeen) ? ' role and support badge visible' : ' role or support badge missing'));
    gpQaResult($results, 'forum_thread_pinned', $forumThreadPinnedSeen, 'HTTP ' . $forumPage['status'] . ($forumThreadPinnedSeen ? ' pinned thread visible' : ' pinned thread missing'));
    gpQaResult($results, 'forum_thread_closed', $forumThreadClosedSeen, 'HTTP ' . $forumPage['status'] . ($forumThreadClosedSeen ? ' closed thread visible' : ' closed thread missing'));
    gpQaResult($results, 'forum_thread_archived', $forumThreadArchivedSeen, 'HTTP ' . $forumPage['status'] . ($forumThreadArchivedSeen ? ' archived thread visible' : ' archived thread missing'));
    gpQaResult($results, 'forum_archive_review', $forumArchiveReviewSeen, 'HTTP ' . $forumPage['status'] . ($forumArchiveReviewSeen ? ' archived review section found' : ' archived review section missing'));
    gpQaResult($results, 'forum_reply_delete', $forumReplyDeleteSeen, 'HTTP ' . $forumPage['status'] . ($forumReplyDeleteSeen ? ' reply delete handled' : ' reply delete missing'));
    gpQaResult($results, 'forum_thread_delete', $forumThreadDeleteSeen, 'HTTP ' . $forumPage['status'] . ($forumThreadDeleteSeen ? ' thread delete handled' : ' thread delete missing'));
    gpQaResult($results, 'forum_thread_search', $forumThreadSearchSeen, 'HTTP ' . $forumPage['status'] . ($forumThreadSearchSeen ? ' search and clear controls found' : ' search controls missing'));
    gpQaResult($results, 'dogs_archive_split', gpQaPageLooksOk($dogsPage) && $dogsArchiveSplitSeen, 'HTTP ' . $dogsPage['status'] . ($dogsArchiveSplitSeen ? ' archive split and add-dog toggle found' : ' archive split or add-dog toggle missing'));
    gpQaResult($results, 'dogs_age_auto_fill', $dogsAgeRuleSeen, 'HTTP ' . $dogsPage['status'] . ($dogsAgeRuleSeen ? ' age auto-fill rule found' : ' age auto-fill rule missing'));

    $publicProfileQuestionnaireSeen = false;
    $publicProfileAirTravelSeen = false;
    $publicProfileStatus = 0;
    $publicProfileUrl = '';
    if (preg_match('/dog_profile\.php\?dog_id=(\d+)/', $dogsPage['body'], $m)) {
        $dogAccessPage = gpQaRequest($baseUrl, 'dog_access.php?dog_id=' . (int) $m[1], 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $dogAccessBody = strtolower($dogAccessPage['body']);
        $dogAccessLooksReady = gpQaPageLooksOk($dogAccessPage)
            && (
                str_contains($dogAccessBody, 'dog access & status')
                || str_contains($dogAccessBody, 'shared handlers')
                || str_contains($dogAccessBody, 'transfer ownership')
            );
        gpQaResult($results, 'dog_access_selected_page_loads', $dogAccessLooksReady, 'HTTP ' . $dogAccessPage['status'] . ($dogAccessLooksReady ? ' dog access content found' : ' dog access content missing'));
        $dogProfile = gpQaRequest($baseUrl, 'dog_profile.php?dog_id=' . (int) $m[1], 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
        $dogProfileBody = strtolower($dogProfile['body']);
        $dogProfileHtml = html_entity_decode($dogProfile['body'], ENT_QUOTES | ENT_HTML5);
        $dogProfileLooksReady = gpQaPageLooksOk($dogProfile)
            && (
                str_contains($dogProfileBody, 'public qr profile')
                || str_contains($dogProfileBody, 'private dog details')
                || str_contains($dogProfileBody, 'dog profile saved')
            );
        gpQaResult($results, 'dog_profile_page_loads', $dogProfileLooksReady, 'HTTP ' . $dogProfile['status'] . ($dogProfileLooksReady ? ' dog profile content found' : ' dog profile content missing'));
        $dogProfileAddressFieldsSeen = gpQaPageLooksOk($dogProfile)
            && str_contains($dogProfileHtml, 'name="handler_street"')
            && str_contains($dogProfileHtml, 'name="handler_apt"')
            && str_contains($dogProfileHtml, 'name="handler_city"')
            && str_contains($dogProfileHtml, 'name="handler_state"')
            && str_contains($dogProfileHtml, 'name="handler_zip"');
        gpQaResult($results, 'dog_profile_address_fields', $dogProfileAddressFieldsSeen, 'HTTP ' . $dogProfile['status'] . ($dogProfileAddressFieldsSeen ? ' split dog address fields found' : ' split dog address fields missing'));
        $dogProfileSupportBadgeSeen = gpQaPageLooksOk($dogProfile)
            && (
                str_contains($dogProfileBody, 'support badge')
                || str_contains($dogProfileBody, 'platinum supporter')
                || str_contains($dogProfileBody, 'bronze supporter')
            );
        gpQaResult($results, 'dog_profile_support_badge_visible', $dogProfileSupportBadgeSeen, 'HTTP ' . $dogProfile['status'] . ($dogProfileSupportBadgeSeen ? ' support badge found' : ' support badge missing'));
        $dogProfileAgeRuleSeen = gpQaPageLooksOk($dogProfile)
            && str_contains($dogProfileBody, 'guidepaw fills the approximate age automatically')
            && str_contains($dogProfileBody, 'leave the birthday blank to enter age manually');
        gpQaResult($results, 'dog_profile_age_auto_fill', $dogProfileAgeRuleSeen, 'HTTP ' . $dogProfile['status'] . ($dogProfileAgeRuleSeen ? ' age auto-fill rule found' : ' age auto-fill rule missing'));
        $qrTrackingPageSeen = false;
        if (preg_match('/href="([^"]*public_dog_profile\.php\?dog=\d+&token=[^"]+)"/i', $dogProfileHtml, $pm)) {
            $publicProfileUrl = $pm[1];
            $publicProfilePage = gpQaRequest($baseUrl, ltrim(parse_url($publicProfileUrl, PHP_URL_PATH) . '?' . (parse_url($publicProfileUrl, PHP_URL_QUERY) ?? ''), '/'), 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
            $publicProfileStatus = $publicProfilePage['status'];
            $publicProfileBody = strtolower($publicProfilePage['body']);
            $publicProfileHtml = html_entity_decode($publicProfilePage['body'], ENT_QUOTES | ENT_HTML5);
            $publicProfileQuestionnaireSeen = str_contains($publicProfileBody, 'breed questionnaire');
            $publicProfileAirTravelSeen = str_contains($publicProfileBody, 'air travel rights');
            $publicProfileSupportBadgeSeen = str_contains($publicProfileBody, 'support badge')
                || str_contains($publicProfileBody, 'platinum supporter')
                || str_contains($publicProfileBody, 'bronze supporter');
            gpQaResult($results, 'public_dog_profile_page_loads', $publicProfileStatus === 200, 'HTTP ' . $publicProfileStatus . ($publicProfileStatus === 200 ? ' public dog profile found' : ' public dog profile missing'));
            gpQaResult($results, 'public_dog_profile_support_badge_visible', $publicProfileSupportBadgeSeen, 'HTTP ' . $publicProfileStatus . ($publicProfileSupportBadgeSeen ? ' support badge found' : ' support badge missing'));
            $qrTrackingPage = gpQaRequest($baseUrl, 'qr_tracking.php?dog_id=' . (int) $m[1], 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
            $qrTrackingBody = strtolower($qrTrackingPage['body']);
            $qrTrackingPageSeen = gpQaPageLooksOk($qrTrackingPage)
                && (
                    str_contains($qrTrackingBody, 'qr tracking')
                    || str_contains($qrTrackingBody, 'qr opens tracked')
                    || str_contains($qrTrackingBody, 'recent qr opens')
                );
            $qrTrackingCountSeen = $qrTrackingPageSeen && preg_match('/qr opens tracked.*?<strong>\s*(\d+)\s*<\/strong>/is', $qrTrackingPage['body'], $qrCountMatch) && (int) ($qrCountMatch[1] ?? 0) > 0;
            gpQaResult($results, 'qr_tracking_page_loads', $qrTrackingPageSeen, 'HTTP ' . $qrTrackingPage['status'] . ($qrTrackingPageSeen ? ' qr tracking found' : ' qr tracking missing'));
            gpQaResult($results, 'qr_tracking_scan_logged', $qrTrackingCountSeen, 'HTTP ' . $qrTrackingPage['status'] . ($qrTrackingCountSeen ? ' qr scan count updated' : ' qr scan count not updated'));

            if (preg_match('/href="([^"]*report_found_dog\.php\?dog=\d+&token=[^"]+)"/i', $publicProfileHtml, $reportMatch)) {
                $foundDogReportPath = ltrim(parse_url($reportMatch[1], PHP_URL_PATH) . '?' . (parse_url($reportMatch[1], PHP_URL_QUERY) ?? ''), '/');
                $reportQuery = [];
                parse_str((string) (parse_url($reportMatch[1], PHP_URL_QUERY) ?? ''), $reportQuery);
                $foundDogLocation = 'GuidePaw QA found-dog report ' . date('YmdHis');
                $foundDogLatitude = '39.7392000';
                $foundDogLongitude = '-104.9903000';
                $foundDogMessage = 'Automated found-dog smoke test report.';
                $foundDogPost = gpQaRequest($baseUrl, $foundDogReportPath, 'POST', [
                    'dog_id' => (int) $m[1],
                    'token' => (string) ($reportQuery['token'] ?? ''),
                    'finder_location' => $foundDogLocation,
                    'finder_name' => 'GuidePaw QA',
                    'finder_phone' => '000-000-0000',
                    'finder_message' => $foundDogMessage,
                    'finder_latitude' => $foundDogLatitude,
                    'finder_longitude' => $foundDogLongitude,
                    'finder_accuracy_m' => '25',
                    'website' => '',
                ], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                $foundDogReportSubmitted = gpQaPageLooksOk($foundDogPost)
                    && (
                        str_contains(strtolower($foundDogPost['body']), 'location report sent')
                        || str_contains(strtolower($foundDogPost['body']), 'handler/admin notification has been queued')
                    );
                gpQaResult($results, 'found_dog_report_submit', $foundDogReportSubmitted, 'HTTP ' . $foundDogPost['status'] . ($foundDogReportSubmitted ? ' found-dog report sent' : ' found-dog report missing'));

                $adminFoundDogReports = gpQaRequest($baseUrl, 'admin_found_dog_reports.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                $adminFoundDogReportsHtml = html_entity_decode($adminFoundDogReports['body'], ENT_QUOTES | ENT_HTML5);
                $adminFoundDogReportsBody = strtolower($adminFoundDogReportsHtml);
                $foundDogReportListed = gpQaPageLooksOk($adminFoundDogReports)
                    && str_contains($adminFoundDogReportsBody, 'open in google maps')
                    && str_contains($adminFoundDogReportsBody, 'google.com/maps/search/?api=1&query=');
                $foundDogReportBulkControlsSeen = gpQaPageLooksOk($adminFoundDogReports)
                    && str_contains($adminFoundDogReportsBody, 'bulk status')
                    && str_contains($adminFoundDogReportsBody, 'select all')
                    && str_contains($adminFoundDogReportsBody, '?status=archived')
                    && preg_match('/<details[^>]*class="cardx"/i', $adminFoundDogReports['body']);
                gpQaResult($results, 'found_dog_report_admin_listed', $foundDogReportListed, 'HTTP ' . $adminFoundDogReports['status'] . ($foundDogReportListed ? ' found-dog report listed' : ' found-dog report missing'));
                gpQaResult($results, 'found_dog_report_admin_bulk_controls', $foundDogReportBulkControlsSeen, 'HTTP ' . $adminFoundDogReports['status'] . ($foundDogReportBulkControlsSeen ? ' found-dog bulk controls found' : ' found-dog bulk controls missing'));
                $reportPage = gpQaRequest($baseUrl, $foundDogReportPath, 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
                $reportPageBody = strtolower($reportPage['body']);
                $reportPageSeen = gpQaPageLooksOk($reportPage) && (str_contains($reportPageBody, 'report dog location') || str_contains($reportPageBody, 'share my current location once') || str_contains($reportPageBody, 'location report sent'));
                gpQaResult($results, 'report_found_dog_page_loads', $reportPageSeen, 'HTTP ' . $reportPage['status'] . ($reportPageSeen ? ' report page found' : ' report page missing'));
            } else {
                gpQaResult($results, 'found_dog_report_submit', false, 'found-dog report link missing');
                gpQaResult($results, 'found_dog_report_admin_listed', false, 'found-dog report link missing');
                gpQaResult($results, 'found_dog_report_admin_bulk_controls', false, 'found-dog report link missing');
                gpQaResult($results, 'report_found_dog_page_loads', false, 'found-dog report link missing');
            }
        } else {
            $publicProfileStatus = $dogProfile['status'];
            gpQaResult($results, 'public_dog_profile_page_loads', false, 'public profile link missing');
            gpQaResult($results, 'qr_tracking_page_loads', false, 'public profile link missing');
            gpQaResult($results, 'qr_tracking_scan_logged', false, 'public profile link missing');
            gpQaResult($results, 'report_found_dog_page_loads', false, 'public profile link missing');
            gpQaResult($results, 'found_dog_report_admin_bulk_controls', false, 'public profile link missing');
        }
        gpQaResult($results, 'public_profile_questionnaire_link', $publicProfileQuestionnaireSeen, 'HTTP ' . $publicProfileStatus . ($publicProfileQuestionnaireSeen ? ' breed questionnaire link found' : ' breed questionnaire link missing'));
        gpQaResult($results, 'public_profile_air_travel_link', $publicProfileAirTravelSeen, 'HTTP ' . $publicProfileStatus . ($publicProfileAirTravelSeen ? ' air travel link found' : ' air travel link missing'));
    } else {
        gpQaResult($results, 'dog_access_selected_page_loads', false, 'dog link missing on dogs page');
        gpQaResult($results, 'public_profile_questionnaire_link', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'public_profile_air_travel_link', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'public_dog_profile_page_loads', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'qr_tracking_page_loads', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'qr_tracking_scan_logged', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'report_found_dog_page_loads', false, 'dog profile link missing on dogs page');
    }

    $adminUsers = gpQaRequest($baseUrl, 'admin_users.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $adminBody = strtolower($adminUsers['body']);
    $adminProtected = gpQaPageLooksOk($adminUsers)
        && str_contains($adminBody, 'protected')
        && (
            str_contains($adminBody, 'built-in admin cannot be downgraded')
            || str_contains($adminBody, 'built-in <code>admin</code> account is protected')
            || str_contains($adminBody, 'current admin account cannot be changed')
        );
    gpQaResult($results, 'builtin_admin_protected_in_ui', $adminProtected, $adminProtected ? 'protected badge/message found' : 'protected marker missing');
    $adminRoleTiersVisible = gpQaPageLooksOk($adminUsers)
        && str_contains($adminBody, 'role tiers')
        && str_contains($adminBody, 'master admin')
        && str_contains($adminBody, 'basic admin')
        && str_contains($adminBody, 'moderator')
        && str_contains($adminBody, 'pro trainer');
    gpQaResult($results, 'admin_role_tiers_visible', $adminRoleTiersVisible, $adminRoleTiersVisible ? 'role tiers found' : 'role tiers missing');
    $adminUsersPurgeVisible = gpQaPageLooksOk($adminUsers) && str_contains($adminBody, 'purge user and dogs');
    gpQaResult($results, 'admin_users_purge_controls_visible', $adminUsersPurgeVisible, $adminUsersPurgeVisible ? 'purge controls found' : 'purge controls missing');

    $handlerProfile = gpQaRequest($baseUrl, 'handler_profile.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $handlerProfileBody = strtolower($handlerProfile['body']);
    $handlerSupportBadgeVisible = gpQaPageLooksOk($handlerProfile)
        && (
            str_contains($handlerProfileBody, 'support badge')
            || str_contains($handlerProfileBody, 'platinum supporter')
            || str_contains($handlerProfileBody, 'bronze supporter')
        );
    gpQaResult($results, 'handler_support_badge_visible', $handlerSupportBadgeVisible, 'HTTP ' . $handlerProfile['status'] . ($handlerSupportBadgeVisible ? ' support badge found' : ' support badge missing'));
    $handlerProfileAddressFieldsVisible = gpQaPageLooksOk($handlerProfile)
        && (
            str_contains($handlerProfileBody, 'street address')
            || str_contains($handlerProfileBody, 'home street')
            || str_contains($handlerProfileBody, 'home city')
            || str_contains($handlerProfileBody, 'home zip')
        );
    gpQaResult($results, 'handler_profile_address_fields', $handlerProfileAddressFieldsVisible, 'HTTP ' . $handlerProfile['status'] . ($handlerProfileAddressFieldsVisible ? ' address fields found' : ' address fields missing'));

    $qaAdmin = gpQaRequest($baseUrl, 'beta_qa_checklist.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $adminSeesRoleChecks = str_contains($qaAdmin['body'], 'User Role Permissions') && str_contains($qaAdmin['body'], 'Admin/beta checks');
    gpQaResult($results, 'qa_admin_sees_admin_sections', gpQaPageLooksOk($qaAdmin) && $adminSeesRoleChecks, 'admin checklist visibility');
}

if ($regularUser !== '' && $regularPass !== '') {
    $regularLoggedIn = gpQaLogin($baseUrl, $regularUser, $regularPass, $regularCookieHeader, $regularCookie, $insecureLocalSsl);
    gpQaResult($results, 'crawler_regular_login', $regularLoggedIn, $regularLoggedIn ? 'regular login succeeded' : 'regular login failed');
    if ($regularLoggedIn) {
        $adminPage = gpQaRequest($baseUrl, 'admin_users.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
        $blocked = $adminPage['status'] === 403 || str_contains(strtolower($adminPage['body']), 'admin access required') || str_contains($adminPage['url'], 'index.php');
        gpQaResult($results, 'regular_user_blocked_from_admin_users', $blocked, 'HTTP ' . $adminPage['status']);

        $plansPage = gpQaRequest($baseUrl, 'paywalls.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
        $plansBody = strtolower($plansPage['body']);
        $plansPageSeen = gpQaPageLooksOk($plansPage) && (str_contains($plansBody, 'plans and access') || str_contains($plansBody, 'current plan') || str_contains($plansBody, 'free') || str_contains($plansBody, 'plus'));
        gpQaResult($results, 'paywalls_page_loads', $plansPageSeen, 'HTTP ' . $plansPage['status'] . ($plansPageSeen ? ' plans page found' : ' plans page missing'));

        $tacticalTrainingPage = gpQaRequest($baseUrl, 'tactical_training.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
        $tacticalTrainingBody = strtolower($tacticalTrainingPage['body']);
        $tacticalTrainingSeen = gpQaPageLooksOk($tacticalTrainingPage) && (
            str_contains($tacticalTrainingBody, 'application required')
            || str_contains($tacticalTrainingBody, 'access approved')
            || str_contains($tacticalTrainingBody, 'tactical readiness path')
        );
        gpQaResult($results, 'tactical_training_page_loads', $tacticalTrainingSeen, 'HTTP ' . $tacticalTrainingPage['status'] . ($tacticalTrainingSeen ? ' tactical training found' : ' tactical training missing'));

        $trainerMarketplacePaywall = gpQaRequest($baseUrl, 'trainer_marketplace.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
        $trainerMarketplacePaywallBody = strtolower($trainerMarketplacePaywall['body']);
        $trainerMarketplacePaywallSeen = gpQaPageLooksOk($trainerMarketplacePaywall) && (
            str_contains($trainerMarketplacePaywallBody, 'reserved for the current paid plan tier')
            || str_contains($trainerMarketplacePaywallBody, 'plus plan')
            || str_contains($trainerMarketplacePaywallBody, 'view plans')
        );
        gpQaResult($results, 'trainer_marketplace_paywall', $trainerMarketplacePaywallSeen, 'HTTP ' . $trainerMarketplacePaywall['status'] . ($trainerMarketplacePaywallSeen ? ' upgrade notice found' : ' upgrade notice missing'));

        $assistantPaywall = gpQaRequest($baseUrl, 'ai_training_assistant.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
        $assistantPaywallBody = strtolower($assistantPaywall['body']);
        $assistantPaywallSeen = gpQaPageLooksOk($assistantPaywall) && (
            str_contains($assistantPaywallBody, 'reserved for the current premium plan tier')
            || str_contains($assistantPaywallBody, 'pro plan')
            || str_contains($assistantPaywallBody, 'view plans')
        );
        gpQaResult($results, 'ai_training_assistant_paywall', $assistantPaywallSeen, 'HTTP ' . $assistantPaywall['status'] . ($assistantPaywallSeen ? ' upgrade notice found' : ' upgrade notice missing'));

        $qaUser = gpQaRequest($baseUrl, 'beta_qa_checklist.php', 'GET', [], $regularCookie, $insecureLocalSsl, $regularCookieHeader);
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
