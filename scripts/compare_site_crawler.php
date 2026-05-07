<?php
declare(strict_types=1);

/**
 * GuidePaw dual-site crawler/comparator.
 *
 * Compares local and beta GuidePaw sites by logging in, crawling safe internal GET links,
 * and comparing status/error results by path.
 *
 * Usage:
 *   php scripts/compare_site_crawler.php \
 *     --local-url=https://10.147.18.184 \
 *     --beta-url=https://beta.guidepaw.app \
 *     --local-admin-user=admin --local-admin-pass='...' \
 *     --beta-admin-user=admin --beta-admin-pass='...'
 *
 * Optional regular-user checks:
 *   --local-regular-user='test acct' --local-regular-pass='...'
 *   --beta-regular-user='test acct' --beta-regular-pass='...'
 *
 * Optional:
 *   --max-pages=160
 *   --insecure-local-ssl=yes
 */

$options = getopt('', [
    'local-url::',
    'beta-url::',
    'local-admin-user::',
    'local-admin-pass::',
    'beta-admin-user::',
    'beta-admin-pass::',
    'local-regular-user::',
    'local-regular-pass::',
    'beta-regular-user::',
    'beta-regular-pass::',
    'max-pages::',
    'insecure-local-ssl::',
]);

$localUrl = rtrim((string)($options['local-url'] ?? getenv('GUIDEPAW_LOCAL_URL') ?: 'https://10.147.18.184'), '/');
$betaUrl = rtrim((string)($options['beta-url'] ?? getenv('GUIDEPAW_BETA_URL') ?: 'https://beta.guidepaw.app'), '/');
$maxPages = max(25, min(400, (int)($options['max-pages'] ?? getenv('GUIDEPAW_COMPARE_MAX_PAGES') ?: 160)));
$insecureLocalSsl = strtolower((string)($options['insecure-local-ssl'] ?? getenv('GUIDEPAW_INSECURE_LOCAL_SSL') ?: 'yes')) !== 'no';

$localAdminUser = (string)($options['local-admin-user'] ?? getenv('GUIDEPAW_LOCAL_ADMIN_USER') ?: getenv('GUIDEPAW_ADMIN_USER') ?: 'admin');
$localAdminPass = (string)($options['local-admin-pass'] ?? getenv('GUIDEPAW_LOCAL_ADMIN_PASS') ?: getenv('GUIDEPAW_ADMIN_PASS') ?: '');
$betaAdminUser = (string)($options['beta-admin-user'] ?? getenv('GUIDEPAW_BETA_ADMIN_USER') ?: getenv('GUIDEPAW_ADMIN_USER') ?: 'admin');
$betaAdminPass = (string)($options['beta-admin-pass'] ?? getenv('GUIDEPAW_BETA_ADMIN_PASS') ?: getenv('GUIDEPAW_ADMIN_PASS') ?: '');

$localRegularUser = (string)($options['local-regular-user'] ?? getenv('GUIDEPAW_LOCAL_REGULAR_USER') ?: getenv('GUIDEPAW_REGULAR_USER') ?: '');
$localRegularPass = (string)($options['local-regular-pass'] ?? getenv('GUIDEPAW_LOCAL_REGULAR_PASS') ?: getenv('GUIDEPAW_REGULAR_PASS') ?: '');
$betaRegularUser = (string)($options['beta-regular-user'] ?? getenv('GUIDEPAW_BETA_REGULAR_USER') ?: getenv('GUIDEPAW_REGULAR_USER') ?: '');
$betaRegularPass = (string)($options['beta-regular-pass'] ?? getenv('GUIDEPAW_BETA_REGULAR_PASS') ?: getenv('GUIDEPAW_REGULAR_PASS') ?: '');

function gpCmpLine(string $status, string $message): void
{
    echo str_pad($status, 7) . ' ' . $message . PHP_EOL;
}

function gpCmpShell(string $cmd): string
{
    $out = @shell_exec($cmd . ' 2>/dev/null');
    return trim((string)$out);
}

function gpCmpRepoStatus(): void
{
    if (!is_dir('.git')) {
        gpCmpLine('SKIP', 'Git repo status: not running from repo root.');
        return;
    }
    $localHead = gpCmpShell('git rev-parse --short HEAD');
    $branch = gpCmpShell('git rev-parse --abbrev-ref HEAD');
    $dirty = gpCmpShell('git status --porcelain');
    $originMain = gpCmpShell('git ls-remote origin refs/heads/main | awk \'{print substr($1,1,7)}\'');
    if ($localHead !== '' && $originMain !== '' && $localHead === $originMain && $dirty === '') {
        gpCmpLine('PASS', "Local repo {$branch}@{$localHead} matches origin/main and working tree is clean.");
    } else {
        gpCmpLine('WARN', "Local repo status: branch={$branch} local={$localHead} origin/main={$originMain} dirty=" . ($dirty === '' ? 'no' : 'yes'));
    }
}

function gpCmpRequest(string $baseUrl, string $path, string $method = 'GET', array $fields = [], string $cookieFile = '', bool $insecureSsl = false): array
{
    $url = $baseUrl . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ['User-Agent: GuidePawDualSiteCrawler/1.0'],
    ]);
    if ($insecureSsl) {
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
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'headers' => '', 'body' => '', 'url' => $finalUrl, 'content_type' => $contentType, 'error' => $err];
    }
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize), 'url' => $finalUrl, 'content_type' => $contentType, 'error' => $err];
}

function gpCmpPathFromUrl(string $url, string $baseUrl): string
{
    $parts = parse_url($url);
    $path = (string)($parts['path'] ?? '/');
    $query = (string)($parts['query'] ?? '');
    $path = ltrim($path, '/');
    if ($path === '') $path = 'index.php';
    if ($query !== '') $path .= '?' . $query;
    return $path;
}

function gpCmpLooksBad(array $res): ?string
{
    if ($res['status'] < 200 || $res['status'] >= 400) return 'HTTP ' . $res['status'];
    $body = strtolower($res['body']);
    $badPatterns = [
        'php fatal error', '<b>fatal error</b>', 'fatal error:',
        'database connection failed', 'uncaught error:', 'uncaught exception',
        'warning: require(', 'failed opening required', 'stack trace:',
    ];
    foreach ($badPatterns as $bad) {
        if (str_contains($body, $bad)) return 'runtime marker: ' . $bad;
    }
    return null;
}

function gpCmpLogin(string $baseUrl, string $user, string $pass, string $cookie, bool $insecureSsl): bool
{
    if ($user === '' || $pass === '') return false;
    $res = gpCmpRequest($baseUrl, 'login.php', 'POST', ['username' => $user, 'password' => $pass], $cookie, $insecureSsl);
    $body = strtolower($res['body']);
    return $res['status'] >= 200 && $res['status'] < 400
        && !str_contains($body, 'invalid username or password')
        && !(str_contains($body, 'handler login') && !str_contains($body, 'dashboard'));
}

function gpCmpIsSafePath(string $path): bool
{
    $lower = strtolower($path);
    if ($path === '' || str_starts_with($lower, '#')) return false;
    if (preg_match('/^(mailto:|tel:|sms:|javascript:|data:)/i', $path)) return false;
    $unsafe = ['logout', 'delete', 'purge', 'deactivate', 'reactivate', 'approve', 'deny', 'revoke', 'remove', 'accept_transfer', 'decline_transfer', 'mark_read', 'mark_all_read', 'dismiss_beta_banner', 'csrf', 'token=', 'action=export'];
    foreach ($unsafe as $needle) {
        if (str_contains($lower, $needle)) return false;
    }
    $ext = strtolower(pathinfo(parse_url($lower, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'pdf', 'zip'], true)) return false;
    return true;
}

function gpCmpExtractLinks(string $body, string $baseUrl): array
{
    $links = [];
    if (preg_match_all('/\b(?:href|action)=["\']([^"\']+)["\']/i', $body, $m)) {
        foreach ($m[1] as $href) {
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5);
            if (!gpCmpIsSafePath($href)) continue;
            if (preg_match('/^https?:\/\//i', $href)) {
                $baseHost = parse_url($baseUrl, PHP_URL_HOST);
                $hrefHost = parse_url($href, PHP_URL_HOST);
                if ($baseHost !== $hrefHost) continue;
                $path = gpCmpPathFromUrl($href, $baseUrl);
            } else {
                $path = ltrim($href, '/');
                if ($path === '') $path = 'index.php';
            }
            if (gpCmpIsSafePath($path)) $links[$path] = true;
        }
    }
    return array_keys($links);
}

function gpCmpCrawlSite(string $label, string $baseUrl, string $user, string $pass, bool $insecureSsl, int $maxPages): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'gpcmp_' . preg_replace('/[^a-z0-9]+/i', '_', $label) . '_');
    $loggedIn = gpCmpLogin($baseUrl, $user, $pass, $cookie, $insecureSsl);
    gpCmpLine($loggedIn ? 'PASS' : 'FAIL', "{$label}: login as {$user}" . ($loggedIn ? ' succeeded.' : ' failed.'));

    $queue = ['index.php', 'notifications.php', 'beta_qa_checklist.php', 'admin_users.php', 'dog_access.php', 'dog_access_audit.php', 'handler_profile.php', 'feedback.php', 'manage_dogs.php', 'dogs.php', 'admin.php'];
    $seen = [];
    $results = [];

    while ($queue && count($seen) < $maxPages) {
        $path = array_shift($queue);
        if (!$path || isset($seen[$path]) || !gpCmpIsSafePath($path)) continue;
        $seen[$path] = true;
        $res = gpCmpRequest($baseUrl, $path, 'GET', [], $cookie, $insecureSsl);
        $bad = gpCmpLooksBad($res);
        if (str_contains(strtolower($label), 'regular') && $path === 'admin_users.php' && (int)$res['status'] === 403) {
            $bad = null;
        }
        $results[$path] = [
            'status' => $res['status'],
            'bad' => $bad,
            'final_path' => gpCmpPathFromUrl($res['url'], $baseUrl),
            'bytes' => strlen($res['body']),
        ];
        gpCmpLine($bad ? 'FAIL' : 'PASS', "{$label}: {$path} => HTTP {$res['status']}" . ($bad ? " ({$bad})" : ''));
        if (!$bad && stripos((string)$res['content_type'], 'text/html') !== false) {
            foreach (gpCmpExtractLinks($res['body'], $baseUrl) as $link) {
                if (!isset($seen[$link]) && count($seen) + count($queue) < $maxPages * 2) {
                    $queue[] = $link;
                }
            }
        }
    }

    @unlink($cookie);
    return ['logged_in' => $loggedIn, 'results' => $results];
}

function gpCmpCompareResults(array $local, array $beta): int
{
    $allPaths = array_unique(array_merge(array_keys($local['results']), array_keys($beta['results'])));
    sort($allPaths);
    $diffs = 0;
    foreach ($allPaths as $path) {
        if (preg_match('/[?&](dog_id|set_dog)=\d+/', $path)) {
            continue;
        }
        $l = $local['results'][$path] ?? null;
        $b = $beta['results'][$path] ?? null;
        if (!$l || !$b) {
            gpCmpLine('DIFF', $path . ' exists in crawl on ' . ($l ? 'local only' : 'beta only'));
            $diffs++;
            continue;
        }
        $lClass = ($l['status'] >= 200 && $l['status'] < 400 && !$l['bad']) ? 'ok' : 'bad';
        $bClass = ($b['status'] >= 200 && $b['status'] < 400 && !$b['bad']) ? 'ok' : 'bad';
        if ($lClass !== $bClass || $l['status'] !== $b['status']) {
            gpCmpLine('DIFF', "{$path}: local HTTP {$l['status']} {$lClass}; beta HTTP {$b['status']} {$bClass}");
            $diffs++;
        }
    }
    return $diffs;
}

if ($localAdminPass === '' || $betaAdminPass === '') {
    gpCmpLine('FAIL', 'Missing admin credentials. Set GUIDEPAW_LOCAL_ADMIN_PASS and GUIDEPAW_BETA_ADMIN_PASS, or GUIDEPAW_ADMIN_PASS if both are same.');
    exit(2);
}

gpCmpRepoStatus();
echo PHP_EOL . 'Admin crawl' . PHP_EOL;
$localAdmin = gpCmpCrawlSite('LOCAL admin', $localUrl, $localAdminUser, $localAdminPass, $insecureLocalSsl, $maxPages);
$betaAdmin = gpCmpCrawlSite('BETA admin', $betaUrl, $betaAdminUser, $betaAdminPass, false, $maxPages);
$adminDiffs = gpCmpCompareResults($localAdmin, $betaAdmin);

$regularDiffs = 0;
if ($localRegularUser !== '' && $localRegularPass !== '' && $betaRegularUser !== '' && $betaRegularPass !== '') {
    echo PHP_EOL . 'Regular user crawl' . PHP_EOL;
    $localRegular = gpCmpCrawlSite('LOCAL regular', $localUrl, $localRegularUser, $localRegularPass, $insecureLocalSsl, $maxPages);
    $betaRegular = gpCmpCrawlSite('BETA regular', $betaUrl, $betaRegularUser, $betaRegularPass, false, $maxPages);
    $regularDiffs = gpCmpCompareResults($localRegular, $betaRegular);
} else {
    gpCmpLine('SKIP', 'Regular user comparison skipped because one or more regular credentials are missing.');
}

$failedPages = 0;
foreach ([$localAdmin, $betaAdmin] as $crawl) {
    foreach ($crawl['results'] as $r) if ($r['bad']) $failedPages++;
}

$totalDiffs = $adminDiffs + $regularDiffs;
echo PHP_EOL . "Summary: failed_pages={$failedPages}; comparison_diffs={$totalDiffs}; admin_paths=" . count($localAdmin['results']) . '/' . count($betaAdmin['results']) . PHP_EOL;
exit(($failedPages || $totalDiffs || !$localAdmin['logged_in'] || !$betaAdmin['logged_in']) ? 1 : 0);
