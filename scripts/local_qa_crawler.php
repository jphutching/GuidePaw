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
    if ($cookieHeader !== '') {
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

function gpQaLogin(string $baseUrl, string $username, string $password, string &$cookieHeader, string $cookieFile, bool $insecureLocalSsl): bool
{
    $res = gpQaRequest($baseUrl, 'login.php', 'POST', ['username' => $username, 'password' => $password], $cookieFile, $insecureLocalSsl, '', false);
    $body = strtolower($res['body']);
    if ($res['status'] < 200 || $res['status'] >= 400) return false;
    if (str_contains($body, 'invalid username or password')) return false;
    if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/im', $res['headers'], $matches)) {
        $cookieHeader = 'PHPSESSID=' . trim($matches[1]);
    }
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
        'feedback.php' => ['feedback', 'bug report', 'report issue'],
        'settings.php' => ['settings', 'change password', 'logout'],
        'profile.php' => ['profile', 'microchip', 'owner'],
        'quick_log.php' => ['quick log', 'quick session'],
        'log_entry.php' => ['detailed log', 'training log', 'photo, video, or audio'],
        'view_logs.php' => ['training history', 'view logs', 'queued offline logs'],
        'dogs.php' => ['manage dogs', 'archived dogs', 'active dogs'],
        'notifications.php' => ['notification', 'alerts', 'inbox'],
        'dog_access.php' => ['dog access', 'shared access', 'co-op', 'transfer'],
        'dog_access_audit.php' => ['audit', 'timeline'],
        'handler_profile.php' => ['handler profile', 'public email', 'backup contact'],
        'db_status.php' => ['database', 'schema', 'migration'],
        'admin_feedback.php' => ['admin feedback', 'feedback reports'],
        'admin_notification_test.php' => ['notification test'],
        'admin_profile_completion.php' => ['profile completion'],
        'admin_users.php' => ['user management', 'admin users'],
        'candidate_assessment.php' => ['candidate assessment', 'candidate'],
        'candidate_comparison.php' => ['candidate comparison', 'compare'],
        'behavior_risk_scoring.php' => ['behavior risk', 'risk scoring'],
        'regression_engine.php' => ['regression engine', 'reset plan'],
        'goal_builder.php' => ['goal builder', 'goal'],
        'training_program.php' => ['training program', 'training'],
        'training_session_log.php' => ['session log', 'training session'],
        'training_history.php' => ['training history', 'history'],
        'stats.php' => ['stats', 'progress'],
        'air_travel_rights.php' => ['air travel', 'service dog training'],
        'wearable_integrations.php' => ['wearable', 'snapshot'],
        'alerts.php' => ['smart alerts', 'alerts'],
        'dog_health.php' => ['health docs', 'vet'],
        'appointments.php' => ['appointments', 'vet appointments'],
        'medications.php' => ['medication', 'medications'],
        'certification.php' => ['certification', 'readiness'],
        'trainer_marketplace.php' => ['trainer marketplace', 'trainer'],
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
$regularCookie = tempnam(sys_get_temp_dir(), 'gpqa_user_');
$adminCookieHeader = '';
$regularCookieHeader = '';

echo 'GuidePaw local QA crawler targeting ' . $baseUrl . ($insecureLocalSsl ? ' with local SSL verification disabled' : '') . PHP_EOL;

$adminLoggedIn = gpQaLogin($baseUrl, $adminUser, $adminPass, $adminCookieHeader, $adminCookie, $insecureLocalSsl);
gpQaResult($results, 'crawler_admin_login', $adminLoggedIn, $adminLoggedIn ? 'admin login succeeded' : 'admin login failed');

if ($adminLoggedIn) {
    $pages = [
        'dashboard_loads' => 'index.php',
        'dogs_page_loads' => 'dogs.php',
        'notifications_page_loads' => 'notifications.php',
        'qa_checklist_page_loads' => 'beta_qa_checklist.php',
        'admin_users_page_loads' => 'admin_users.php',
        'admin_feedback_page_loads' => 'admin_feedback.php',
        'admin_found_dog_reports_page_loads' => 'admin_found_dog_reports.php',
        'admin_notification_test_page_loads' => 'admin_notification_test.php',
        'admin_profile_completion_page_loads' => 'admin_profile_completion.php',
        'api_tokens_page_loads' => 'api_tokens.php',
        'backup_tools_page_loads' => 'backup.php',
        'dog_access_page_loads' => 'dog_access.php',
        'dog_audit_page_loads' => 'dog_access_audit.php',
        'handler_profile_page_loads' => 'handler_profile.php',
        'settings_page_loads' => 'settings.php',
        'profile_page_loads' => 'profile.php',
        'quick_log_page_loads' => 'quick_log.php',
        'log_entry_page_loads' => 'log_entry.php',
        'view_logs_page_loads' => 'view_logs.php',
        'feedback_page_loads' => 'feedback.php',
        'db_status_page_loads' => 'db_status.php',
        'candidate_assessment_page_loads' => 'candidate_assessment.php',
        'candidate_comparison_page_loads' => 'candidate_comparison.php',
        'behavior_risk_scoring_page_loads' => 'behavior_risk_scoring.php',
        'regression_engine_page_loads' => 'regression_engine.php',
        'goal_builder_page_loads' => 'goal_builder.php',
        'training_program_page_loads' => 'training_program.php',
        'training_session_log_page_loads' => 'training_session_log.php',
        'training_history_page_loads' => 'training_history.php',
        'stats_page_loads' => 'stats.php',
        'air_travel_rights_page_loads' => 'air_travel_rights.php',
        'wearable_integrations_page_loads' => 'wearable_integrations.php',
        'alerts_page_loads' => 'alerts.php',
        'dog_health_page_loads' => 'dog_health.php',
        'appointments_page_loads' => 'appointments.php',
        'medications_page_loads' => 'medications.php',
        'certification_page_loads' => 'certification.php',
        'trainer_marketplace_page_loads' => 'trainer_marketplace.php',
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
        $settingsPageLooksReady = $path === 'settings.php'
            ? (
                str_contains($body, 'settings')
                || str_contains($body, 'change password')
                || str_contains($body, 'logout')
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
            )
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
            )
            : true;
        $adminFoundDogReportsPageLooksReady = $path === 'admin_found_dog_reports.php'
            ? (
                str_contains($body, 'found dog location reports')
                || str_contains($body, 'found-dog email template')
                || str_contains($body, 'no found dog location reports yet')
            )
            : true;
        $adminNotificationTestPageLooksReady = $path === 'admin_notification_test.php'
            ? (
                str_contains($body, 'notification test')
                || str_contains($body, 'current settings')
                || str_contains($body, 'send test')
            )
            : true;
        $adminProfileCompletionPageLooksReady = $path === 'admin_profile_completion.php'
            ? (
                str_contains($body, 'handler profile completion')
                || str_contains($body, 'missing required')
                || str_contains($body, 'accounts missing required')
            )
            : true;
        $apiTokensPageLooksReady = $path === 'api_tokens.php'
            ? (
                str_contains($body, 'api tokens')
                || str_contains($body, 'create token')
                || str_contains($body, 'existing tokens')
            )
            : true;
        $backupToolsPageLooksReady = $path === 'backup.php'
            ? (
                str_contains($body, 'backup & restore')
                || str_contains($body, 'full backup package')
                || str_contains($body, 'download json backup')
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
        $trainingProgramPageLooksReady = $path === 'training_program.php'
            ? (
                str_contains($body, 'training program')
                || str_contains($body, 'today\'s easy win')
                || str_contains($body, 'active goals')
            )
            : true;
        $trainingSessionLogPageLooksReady = $path === 'training_session_log.php'
            ? (
                str_contains($body, 'training session log')
                || str_contains($body, 'log session')
                || str_contains($body, 'success rate')
            )
            : true;
        $trainingHistoryPageLooksReady = $path === 'training_history.php'
            ? (
                str_contains($body, 'training history')
                || str_contains($body, 'archived')
                || str_contains($body, 'export csv')
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
            )
            : true;
        $alertsPageLooksReady = $path === 'alerts.php'
            ? (
                str_contains($body, 'smart alerts')
                || str_contains($body, 'active alerts')
                || str_contains($body, 'no active alerts')
            )
            : true;
        $dogHealthPageLooksReady = $path === 'dog_health.php'
            ? (
                str_contains($body, 'health & documents')
                || str_contains($body, 'vet contacts')
                || str_contains($body, 'documents')
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
            gpQaPageLooksOk($res) && $mediaPageLooksReady && $videoPageLooksReady && $coachPageLooksReady && $dbStatusLooksReady && $notificationPageLooksReady && $settingsPageLooksReady && $profilePageLooksReady && $quickLogPageLooksReady && $logEntryPageLooksReady && $viewLogsPageLooksReady && $adminFeedbackPageLooksReady && $adminFoundDogReportsPageLooksReady && $adminNotificationTestPageLooksReady && $adminProfileCompletionPageLooksReady && $apiTokensPageLooksReady && $backupToolsPageLooksReady && $dogsPageLooksReady && $candidatePageLooksReady && $candidateComparisonPageLooksReady && $behaviorRiskPageLooksReady && $regressionEnginePageLooksReady && $goalBuilderPageLooksReady && $trainingProgramPageLooksReady && $trainingSessionLogPageLooksReady && $trainingHistoryPageLooksReady && $statsPageLooksReady && $airTravelPageLooksReady && $wearablePageLooksReady && $alertsPageLooksReady && $dogHealthPageLooksReady && $appointmentsPageLooksReady && $medicationsPageLooksReady && $certificationPageLooksReady && $trainerMarketplaceLooksReady && $communityChallengesPageLooksReady && $truckingPageLooksReady && $assistantPageLooksReady,
            'HTTP ' . $res['status'] . ' ' . basename(parse_url($res['url'], PHP_URL_PATH) ?: $path) . ($res['error'] ? ' error=' . $res['error'] : '') . ($path === 'admin_feedback.php' ? ($adminFeedbackPageLooksReady ? ' feedback reports found' : ' feedback reports missing') : '') . ($path === 'admin_found_dog_reports.php' ? ($adminFoundDogReportsPageLooksReady ? ' found dog reports found' : ' found dog reports missing') : '') . ($path === 'admin_notification_test.php' ? ($adminNotificationTestPageLooksReady ? ' notification test found' : ' notification test missing') : '') . ($path === 'admin_profile_completion.php' ? ($adminProfileCompletionPageLooksReady ? ' profile completion found' : ' profile completion missing') : '') . ($path === 'api_tokens.php' ? ($apiTokensPageLooksReady ? ' api tokens found' : ' api tokens missing') : '') . ($path === 'backup.php' ? ($backupToolsPageLooksReady ? ' backup tools found' : ' backup tools missing') : '') . ($path === 'dogs.php' ? ($dogsPageLooksReady ? ' archive split found' : ' archive split missing') : '') . ($path === 'candidate_assessment.php' ? ($candidatePageLooksReady ? ' candidate assessment content found' : ' candidate assessment content missing') : '') . ($path === 'candidate_comparison.php' ? ($candidateComparisonPageLooksReady ? ' candidate comparison content found' : ' candidate comparison content missing') : '') . ($path === 'behavior_risk_scoring.php' ? ($behaviorRiskPageLooksReady ? ' behavior risk content found' : ' behavior risk content missing') : '') . ($path === 'regression_engine.php' ? ($regressionEnginePageLooksReady ? ' regression engine content found' : ' regression engine content missing') : '') . ($path === 'goal_builder.php' ? ($goalBuilderPageLooksReady ? ' goal builder content found' : ' goal builder content missing') : '') . ($path === 'training_program.php' ? ($trainingProgramPageLooksReady ? ' training program content found' : ' training program content missing') : '') . ($path === 'training_session_log.php' ? ($trainingSessionLogPageLooksReady ? ' session log content found' : ' session log content missing') : '') . ($path === 'training_history.php' ? ($trainingHistoryPageLooksReady ? ' training history content found' : ' training history content missing') : '') . ($path === 'stats.php' ? ($statsPageLooksReady ? ' stats content found' : ' stats content missing') : '') . ($path === 'air_travel_rights.php' ? ($airTravelPageLooksReady ? ' air travel content found' : ' air travel content missing') : '') . ($path === 'wearable_integrations.php' ? ($wearablePageLooksReady ? ' wearable sync content found' : ' wearable sync content missing') : '') . ($path === 'alerts.php' ? ($alertsPageLooksReady ? ' alerts content found' : ' alerts content missing') : '') . ($path === 'dog_health.php' ? ($dogHealthPageLooksReady ? ' health docs content found' : ' health docs content missing') : '') . ($path === 'appointments.php' ? ($appointmentsPageLooksReady ? ' appointments content found' : ' appointments content missing') : '') . ($path === 'medications.php' ? ($medicationsPageLooksReady ? ' medications content found' : ' medications content missing') : '') . ($path === 'certification.php' ? ($certificationPageLooksReady ? ' certification content found' : ' certification content missing') : '') . ($path === 'trainer_marketplace.php' ? ($trainerMarketplaceLooksReady ? ' trainer marketplace content found' : ' trainer marketplace content missing') : '') . ($path === 'community_challenges.php' ? ($communityChallengesPageLooksReady ? ' community challenges content found' : ' community challenges content missing') : '') . ($path === 'trucking_mode.php' ? ($truckingPageLooksReady ? ' trucking mode content found' : ' trucking mode content missing') : '') . ($path === 'ai_training_assistant.php' ? ($assistantPageLooksReady ? ' assistant content found' : ' assistant content missing') : '') . ($path === 'media_review.php' ? ($mediaPageLooksReady ? ' media review content found' : ' media review content missing') : '') . ($path === 'video_review.php' ? ($videoPageLooksReady ? ' video review content found' : ' video review content missing') : '') . ($path === 'coach_review.php' ? ($coachPageLooksReady ? ' coach review content found' : ' coach review content missing') : '') . ($path === 'db_status.php' ? ($dbStatusLooksReady ? ' schema migration section found' : ' schema migration section missing') : '') . ($path === 'notifications.php' ? ($notificationPageLooksReady ? ' notification controls found' : ' notification controls missing') : '') . ($path === 'settings.php' ? ($settingsPageLooksReady ? ' settings content found' : ' settings content missing') : '') . ($path === 'profile.php' ? ($profilePageLooksReady ? ' profile content found' : ' profile content missing') : '') . ($path === 'quick_log.php' ? ($quickLogPageLooksReady ? ' quick log content found' : ' quick log content missing') : '') . ($path === 'log_entry.php' ? ($logEntryPageLooksReady ? ' log entry content found' : ' log entry content missing') : '') . ($path === 'view_logs.php' ? ($viewLogsPageLooksReady ? ' history content found' : ' history content missing') : '')
        );
    }

    $dashboard = gpQaRequest($baseUrl, 'index.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $notificationsPage = gpQaRequest($baseUrl, 'notifications.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $dogsPage = gpQaRequest($baseUrl, 'dogs.php', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
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
    $healthDocsTodaySeen = str_contains($dashboardBody, 'health docs') || str_contains($dashboardBody, 'dog health');
    $appointmentsTodaySeen = str_contains($dashboardBody, 'appointments') || str_contains($dashboardBody, 'vet appointments');
    $medicationsTodaySeen = str_contains($dashboardBody, 'medications');
    $wearableHookSeen = str_contains($dashboardBody, 'wearable sync') || str_contains($dashboardBody, 'wearable snapshot');
    $alertsHookSeen = str_contains($dashboardBody, 'smart alerts');
    $certificationHookSeen = str_contains($dashboardBody, 'certification');
    $statsHookSeen = str_contains($dashboardBody, 'stats');
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
    gpQaResult($results, 'dashboard_alerts_today', gpQaPageLooksOk($dashboard) && $alertsHookSeen, 'HTTP ' . $dashboard['status'] . ($alertsHookSeen ? ' smart alerts today action found' : ' smart alerts today action not currently visible'));
    gpQaResult($results, 'dashboard_health_docs_today', gpQaPageLooksOk($dashboard) && $healthDocsTodaySeen, 'HTTP ' . $dashboard['status'] . ($healthDocsTodaySeen ? ' health docs today action found' : ' health docs today action not currently visible'));
    gpQaResult($results, 'dashboard_appointments_today', gpQaPageLooksOk($dashboard) && $appointmentsTodaySeen, 'HTTP ' . $dashboard['status'] . ($appointmentsTodaySeen ? ' appointments today action found' : ' appointments today action not currently visible'));
    gpQaResult($results, 'dashboard_medications_today', gpQaPageLooksOk($dashboard) && $medicationsTodaySeen, 'HTTP ' . $dashboard['status'] . ($medicationsTodaySeen ? ' medications today action found' : ' medications today action not currently visible'));
    gpQaResult($results, 'dashboard_certification_today', gpQaPageLooksOk($dashboard) && $certificationHookSeen, 'HTTP ' . $dashboard['status'] . ($certificationHookSeen ? ' certification today action found' : ' certification today action not currently visible'));
    gpQaResult($results, 'dashboard_stats_today', gpQaPageLooksOk($dashboard) && $statsHookSeen, 'HTTP ' . $dashboard['status'] . ($statsHookSeen ? ' stats today action found' : ' stats today action not currently visible'));
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
        if (preg_match('/href="([^"]*public_dog_profile\.php\?dog=\d+&token=[^"]+)"/i', $dogProfileHtml, $pm)) {
            $publicProfileUrl = $pm[1];
            $publicProfilePage = gpQaRequest($baseUrl, ltrim(parse_url($publicProfileUrl, PHP_URL_PATH) . '?' . (parse_url($publicProfileUrl, PHP_URL_QUERY) ?? ''), '/'), 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
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
        gpQaResult($results, 'dog_access_selected_page_loads', false, 'dog link missing on dogs page');
        gpQaResult($results, 'public_profile_questionnaire_link', false, 'dog profile link missing on dogs page');
        gpQaResult($results, 'public_profile_air_travel_link', false, 'dog profile link missing on dogs page');
    }

    $adminUsers = gpQaRequest($baseUrl, 'admin_users.php?q=admin', 'GET', [], $adminCookie, $insecureLocalSsl, $adminCookieHeader);
    $adminBody = strtolower($adminUsers['body']);
    $adminProtected = gpQaPageLooksOk($adminUsers)
        && str_contains($adminBody, 'protected')
        && (
            str_contains($adminBody, 'built-in admin cannot be downgraded')
            || str_contains($adminBody, 'built-in <code>admin</code> account is protected')
            || str_contains($adminBody, 'current admin account cannot be changed')
        );
    gpQaResult($results, 'builtin_admin_protected_in_ui', $adminProtected, $adminProtected ? 'protected badge/message found' : 'protected marker missing');

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
