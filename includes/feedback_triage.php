<?php
declare(strict_types=1);

require_once __DIR__ . '/feedback_submission.php';

function gpFeedbackTriageRows(PDO $pdo, int $limit = 25): array
{
    gpEnsureFeedbackSourceColumns($pdo);

    $sql = "
        SELECT
            id,
            COALESCE(category, report_type, 'bug') AS category,
            COALESCE(title, '') AS title,
            COALESCE(description, '') AS description,
            COALESCE(page_workflow, '') AS page_workflow,
            COALESCE(status, 'new') AS status,
            COALESCE(priority, 'normal') AS priority,
            COALESCE(source_platform, 'web') AS source_platform,
            COALESCE(source_label, 'GuidePaw Website') AS source_label,
            COALESCE(source_version, '') AS source_version,
            COALESCE(source_device, '') AS source_device,
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function gpFeedbackTriageAnalyze(array $feedback): array
{
    $title = strtolower(trim((string) ($feedback['title'] ?? '')));
    $description = strtolower(trim((string) ($feedback['description'] ?? '')));
    $workflow = strtolower(trim((string) ($feedback['page_workflow'] ?? '')));
    $category = strtolower(trim((string) ($feedback['category'] ?? 'bug')));
    $sourcePlatform = strtolower(trim((string) ($feedback['source_platform'] ?? 'web')));
    $sourceLabel = strtolower(trim((string) ($feedback['source_label'] ?? 'guidepaw website')));
    $text = trim($title . ' ' . $description . ' ' . $workflow);

    $signals = [];
    $nextSteps = [];
    $draftReply = 'Thanks. I am reviewing this and will update the status as we work through it.';
    $recommendedStatus = 'reviewing';
    $area = 'general triage';

    if ($text !== '' && (str_contains($text, 'menu') || str_contains($text, 'navigation') || str_contains($text, 'home') || str_contains($text, 'page'))) {
        $area = 'navigation and shell';
        $recommendedStatus = 'planned';
        $signals[] = 'Touches the main navigation or page shell.';
        $nextSteps[] = 'Check the current shell and remove redundant entry points before adding new ones.';
        $nextSteps[] = 'Keep the shortest path visible on phone-width screens.';
        $draftReply = 'Thanks. This looks like a navigation issue. I will keep the shortest path visible and move anything extra behind a secondary action.';
    }

    if ($text !== '' && (str_contains($text, 'error') || str_contains($text, 'crash') || str_contains($text, 'undefined function') || str_contains($text, 'sqlstate') || str_contains($text, 'application error'))) {
        $area = 'runtime bug';
        $recommendedStatus = 'reviewing';
        $signals[] = 'Looks like a runtime error or broken page path.';
        $nextSteps[] = 'Reproduce the failure with the same account and page path.';
        $nextSteps[] = 'Fix the lowest-level include or query issue first, then rerun the smoke path.';
        $draftReply = 'Thanks. This looks like a runtime bug. I am reproducing it on the same page and will fix the failing include or query first.';
    }

    if ($text !== '' && (str_contains($text, 'internet') || str_contains($text, 'offline') || str_contains($text, 'disconnect') || str_contains($text, 'wifi') || str_contains($text, 'reconnect'))) {
        $area = 'offline recovery';
        $recommendedStatus = 'planned';
        $signals[] = 'Mentions connection loss or reconnect behavior.';
        $nextSteps[] = 'Keep the offline shell working and show clear reconnect status on the device.';
        $nextSteps[] = 'Avoid hiding the last known state when the device drops network.';
        $draftReply = 'Thanks. This is an offline recovery issue. I will keep the shell usable when the connection drops and show a clear reconnect state.';
    }

    if ($text !== '' && (str_contains($text, 'ai') || str_contains($text, 'assistant') || str_contains($text, 'create and fix issues') || str_contains($text, 'triage'))) {
        $area = 'issue triage assistant';
        $recommendedStatus = 'planned';
        $signals[] = 'Matches the AI issue-assist request.';
        $nextSteps[] = 'Keep the assistant focused on summarizing feedback and suggesting next steps.';
        $nextSteps[] = 'Show a short draft reply and a recommended status instead of auto-changing anything.';
        $draftReply = 'Thanks. I am routing this through the issue assistant so it can summarize the report, suggest the next step, and recommend a status without auto-changing anything.';
    }

    if ($category === 'enhancement') {
        $signals[] = 'User asked for an enhancement.';
        if ($recommendedStatus === 'reviewing') {
            $recommendedStatus = 'planned';
        }
    }

    if ($sourcePlatform === 'android' || str_contains($sourceLabel, 'companion')) {
        $signals[] = 'Reported from the Android companion app.';
        if ($area === 'general triage') {
            $area = 'android companion';
        }
        $nextSteps[] = 'Check the Android path, source version, and whether the same issue reproduces in the web app.';
    }

    if (!$signals) {
        $signals[] = 'No strong keyword match, so treat this as a manual triage item.';
        $nextSteps[] = 'Read the report, confirm the reproduction path, and decide if it is a bug or enhancement.';
        $nextSteps[] = 'Keep the status at reviewing until the next owner is clear.';
    }

    if (!$nextSteps) {
        $nextSteps[] = 'Confirm the current page path and the exact user action first.';
        $nextSteps[] = 'Decide whether the item should be fixed in code, planned for later, or closed as already resolved.';
    }

    return [
        'area' => $area,
        'recommended_status' => $recommendedStatus,
        'signals' => $signals,
        'next_steps' => $nextSteps,
        'draft_reply' => $draftReply,
    ];
}
