<?php
declare(strict_types=1);

function gpDailyWinPromptCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $actions = [
        'Practice',
        'Reinforce',
        'Refresh',
        'Rehearse',
        'Tighten',
        'Build',
        'Proof',
        'Check',
        'Chain',
        'Reset',
        'Stabilize',
        'Expand',
        'Blend',
        'Repeat',
        'Polish',
    ];

    $skills = [
        ['title' => 'recall', 'detail' => 'Call, reward, and release once the response is clean.'],
        ['title' => 'loose leash', 'detail' => 'Keep the leash slack for a short, successful rep.'],
        ['title' => 'settle', 'detail' => 'Ask for a short settle and end before frustration shows.'],
        ['title' => 'leave it', 'detail' => 'Use a tiny distraction and reward the decision away.'],
        ['title' => 'focus', 'detail' => 'Ask for eye contact or a hand target before moving on.'],
    ];

    $places = [
        ['title' => 'at home', 'detail' => 'Keep it inside and low pressure.'],
        ['title' => 'in the yard', 'detail' => 'Use the familiar space as the easy version.'],
        ['title' => 'on a short walk', 'detail' => 'Keep the outing brief and finish well.'],
        ['title' => 'in a quiet parking lot', 'detail' => 'Add just a little public distraction.'],
        ['title' => 'during a calm errand', 'detail' => 'Use a real-world setting but keep the win small.'],
    ];

    $catalog = [];
    foreach ($actions as $action) {
        foreach ($skills as $skill) {
            foreach ($places as $place) {
                $title = trim($action . ' ' . $skill['title'] . ' ' . $place['title']);
                $detail = trim($skill['detail'] . ' ' . $place['detail']);
                $catalog[] = [
                    'title' => $title,
                    'detail' => $detail,
                ];
            }
        }
    }

    $catalog = array_slice($catalog, 0, 365);
    return $catalog;
}

function gpDailyWinPromptForDate(?DateTimeInterface $date = null): array
{
    $date = $date ?? new DateTimeImmutable('now');
    $catalog = gpDailyWinPromptCatalog();
    $count = max(count($catalog), 1);
    $dayOfYear = (int) $date->format('z');
    $index = $dayOfYear % $count;
    $prompt = $catalog[$index] ?? ['title' => 'Practice recall at home', 'detail' => 'Keep it short and end on success.'];

    return [
        'key' => $date->format('Y-m-d'),
        'index' => $index,
        'day' => $dayOfYear + 1,
        'total' => $count,
        'title' => $prompt['title'],
        'detail' => $prompt['detail'],
    ];
}

function gpDailyWinLogName(array $prompt): string
{
    $name = 'Daily Win: ' . (string) ($prompt['title'] ?? 'Quick Win');
    return mb_substr($name, 0, 100);
}

function gpDailyWinExistingLog(PDO $pdo, int $userId, int $dogId, string $logName): ?array
{
    $stmt = $pdo->prepare('SELECT id, log_date, location_name, handler_notes FROM daily_logs WHERE user_id = ? AND dog_id = ? AND location_name = ? AND log_date::date = CURRENT_DATE ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $dogId, $logName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gpSaveDailyWin(PDO $pdo, int $userId, int $dogId, array $prompt, string $note = ''): int
{
    $logName = gpDailyWinLogName($prompt);
    $detail = trim((string) ($prompt['detail'] ?? ''));
    $note = trim($note);
    $handlerNotes = $detail;
    if ($note !== '') {
        $handlerNotes .= "\n\n" . $note;
    }

    $stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, dog_id, location_name, location_city_state, location_type, focus_level, skills_practiced, handler_notes, log_date) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP) RETURNING id');
    $stmt->execute([
        $userId,
        $dogId,
        $logName,
        'Today',
        'Other',
        3,
        json_encode(['Daily Win'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $handlerNotes,
    ]);

    return (int) $stmt->fetchColumn();
}
