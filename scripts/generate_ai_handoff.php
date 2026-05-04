<?php
// GuidePaw local AI handoff export.
// Run from repo root:
// GUIDEPAW_DB_PASSWORD='your_password' php scripts/generate_ai_handoff.php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$exportsDir = $root . '/exports';

if (!is_dir($exportsDir)) {
    mkdir($exportsDir, 0775, true);
}

$dbHost = getenv('GUIDEPAW_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('GUIDEPAW_DB_NAME') ?: 'guidepaw';
$dbUser = getenv('GUIDEPAW_DB_USER') ?: 'guidepaw';
$dbPass = getenv('GUIDEPAW_DB_PASSWORD') ?: (getenv('PGPASSWORD') ?: '');

function runCmd(string $cmd, string $cwd): string
{
    $old = getcwd();
    chdir($cwd);
    $output = shell_exec($cmd . ' 2>&1');
    chdir($old);
    return trim((string)$output);
}

function md(string $value): string
{
    return str_replace(["\r\n", "\r"], "\n", $value);
}

$pdo = new PDO(
    "pgsql:host={$dbHost};dbname={$dbName}",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$activeStmt = $pdo->query("
    SELECT id, report_type, status, priority, title, page_workflow, details, created_at, updated_at
    FROM feedback_reports
    WHERE status IN ('new', 'reviewing', 'planned')
    ORDER BY created_at DESC
");
$activeReports = $activeStmt->fetchAll();

$recentStmt = $pdo->query("
    SELECT id, report_type, status, priority, title, page_workflow, details, created_at, updated_at
    FROM feedback_reports
    WHERE status IN ('fixed', 'closed')
    ORDER BY updated_at DESC
    LIMIT 12
");
$recentReports = $recentStmt->fetchAll();

$gitStatus = runCmd('git status --short', $root);
$gitBranch = runCmd('git branch --show-current', $root);
$gitHead = runCmd('git rev-parse --short HEAD', $root);
$gitLog = runCmd('git --no-pager log --oneline -12', $root);
$deployScript = file_exists($root . '/scripts/deploy_local.sh') ? 'scripts/deploy_local.sh exists' : 'scripts/deploy_local.sh missing';

$timestamp = date('Y-m-d H:i:s');
$fileStamp = date('Ymd_His');
$outFile = $exportsDir . "/guidepaw_ai_handoff_{$fileStamp}.md";

$lines = [];
$lines[] = "# GuidePaw AI Handoff";
$lines[] = "";
$lines[] = "Generated: {$timestamp}";
$lines[] = "Repo: {$root}";
$lines[] = "Branch: {$gitBranch}";
$lines[] = "HEAD: {$gitHead}";
$lines[] = "Deploy script: {$deployScript}";
$lines[] = "";
$lines[] = "## Current Git Status";
$lines[] = "```";
$lines[] = $gitStatus !== '' ? $gitStatus : 'Working tree clean';
$lines[] = "```";
$lines[] = "";
$lines[] = "## Recent Commits";
$lines[] = "```";
$lines[] = $gitLog;
$lines[] = "```";
$lines[] = "";
$lines[] = "## Active Feedback / Roadmap Items";
$lines[] = "";

if (!$activeReports) {
    $lines[] = "No active feedback items.";
} else {
    foreach ($activeReports as $r) {
        $lines[] = "### #{$r['id']} {$r['report_type']} — {$r['status']} — {$r['title']}";
        $lines[] = "";
        $lines[] = "- Priority: {$r['priority']}";
        $lines[] = "- Workflow/page: " . ($r['page_workflow'] ?: 'General');
        $lines[] = "- Created: {$r['created_at']}";
        $lines[] = "- Updated: {$r['updated_at']}";
        $lines[] = "";
        $lines[] = md((string)$r['details']);
        $lines[] = "";
    }
}

$lines[] = "## Recently Fixed / Closed Feedback";
$lines[] = "";

foreach ($recentReports as $r) {
    $preview = mb_substr(trim((string)$r['details']), 0, 240);
    $lines[] = "- #{$r['id']} {$r['report_type']} — {$r['status']} — {$r['title']}: {$preview}";
}

$lines[] = "";
$lines[] = "## Suggested ChatGPT Starter";
$lines[] = "";
$lines[] = "```";
$lines[] = "You are helping me continue GuidePaw development. Use this handoff as the current state. GitHub/repo state is the source of truth. Do not regress current functionality. Review the active feedback items, propose the safest next patch, give terminal commands, and include validation/commit/deploy steps.";
$lines[] = "```";
$lines[] = "";

file_put_contents($outFile, implode("\n", $lines));

echo "Created AI handoff:\n{$outFile}\n";
