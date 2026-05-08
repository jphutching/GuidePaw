<?php
declare(strict_types=1);

function gpCommunityChallengeOptions(): array
{
    return [
        'consistency_streak' => [
            'label' => '7-Day Consistency',
            'icon' => '🔥',
            'summary' => 'Do one small training rep every day for a week and keep it easy enough to finish.',
            'daily_target' => '1 short rep per day',
            'best_for' => 'New routines, overdue follow-through, and handlers who need momentum.',
            'avoid' => 'Turning the streak into a long session or a hard proofing grind.',
            'finish_line' => 'Seven logged check-ins with at least one reward marker each day.',
        ],
        'engagement_burst' => [
            'label' => 'Engagement Burst',
            'icon' => '👀',
            'summary' => 'Build quick eye contact, name response, and handler focus in tiny repeats.',
            'daily_target' => '3 focus reps',
            'best_for' => 'Dogs that know the cue but are drifting or slow to re-engage.',
            'avoid' => 'Repeating cues, stacking distractions, or chasing perfect attention.',
            'finish_line' => 'Three clean check-ins with fast reward delivery.',
        ],
        'settle_reset' => [
            'label' => 'Calm Reset',
            'icon' => '🧘',
            'summary' => 'Practice mat work, crate calm, and quiet recovery after one success.',
            'daily_target' => '2 settle reps',
            'best_for' => 'Travel days, stressful handlers, and dogs that need off-switch practice.',
            'avoid' => 'Prolonged duration work before the dog can settle quickly.',
            'finish_line' => 'Dog settles within 30 seconds twice in one day.',
        ],
        'loose_leash' => [
            'label' => 'Leash Walking Sprint',
            'icon' => '🚶',
            'summary' => 'Keep leash pressure low for a short, boring, successful walk.',
            'daily_target' => '1 short walk',
            'best_for' => 'Dogs ready to generalize loose leash outside the house or truck.',
            'avoid' => 'Crowded settings or long walks that drain the dog before success.',
            'finish_line' => 'Three pauses, three check-ins, and a calm return.',
        ],
        'public_neutrality' => [
            'label' => 'Public Neutrality Check',
            'icon' => '🏪',
            'summary' => 'Rehearse ignoring people, carts, and noise at an easy public distance.',
            'daily_target' => '1 controlled outing',
            'best_for' => 'Teams already comfortable with home skills and short outings.',
            'avoid' => 'Too much distance, too much duration, or asking for perfect neutrality too soon.',
            'finish_line' => 'One outing where the dog stays under threshold and leaves with a reward.',
        ],
    ];
}

function gpCommunityChallengeDefault(array $trainingStats = [], ?array $latestAssessment = null): string
{
    if (!empty($latestAssessment) && (int) ($latestAssessment['focus_level_recommended'] ?? 0) <= 1) {
        return 'settle_reset';
    }

    $sessions7d = (int) ($trainingStats['sessions_7d'] ?? 0);
    $avgSuccess = $trainingStats['avg_success_rate_7d'] ?? null;

    if ($sessions7d < 3) {
        return 'consistency_streak';
    }

    if ($avgSuccess !== null && (int) $avgSuccess >= 80) {
        return 'public_neutrality';
    }

    if ($avgSuccess !== null && (int) $avgSuccess >= 60) {
        return 'loose_leash';
    }

    return 'engagement_burst';
}

function gpCommunityChallengeState(int $userId, int $dogId): array
{
    $state = $_SESSION['guidepaw_community_challenges'][$userId][$dogId] ?? [];
    if (!is_array($state)) {
        $state = [];
    }

    $challenge = (string) ($state['challenge'] ?? 'consistency_streak');
    if (!array_key_exists($challenge, gpCommunityChallengeOptions())) {
        $challenge = 'consistency_streak';
    }

    return [
        'challenge' => $challenge,
        'notes' => (string) ($state['notes'] ?? ''),
        'check_ins' => (int) ($state['check_ins'] ?? 0),
        'updated_at' => (int) ($state['updated_at'] ?? 0),
    ];
}

function gpCommunityChallengeSaveState(int $userId, int $dogId, string $challenge, string $notes = '', bool $markCheckIn = false): array
{
    $options = gpCommunityChallengeOptions();
    if (!array_key_exists($challenge, $options)) {
        $challenge = 'consistency_streak';
    }

    if (!isset($_SESSION['guidepaw_community_challenges']) || !is_array($_SESSION['guidepaw_community_challenges'])) {
        $_SESSION['guidepaw_community_challenges'] = [];
    }
    if (!isset($_SESSION['guidepaw_community_challenges'][$userId]) || !is_array($_SESSION['guidepaw_community_challenges'][$userId])) {
        $_SESSION['guidepaw_community_challenges'][$userId] = [];
    }

    $existing = gpCommunityChallengeState($userId, $dogId);

    $_SESSION['guidepaw_community_challenges'][$userId][$dogId] = [
        'challenge' => $challenge,
        'notes' => trim($notes),
        'check_ins' => $markCheckIn ? ((int) ($existing['check_ins'] ?? 0) + 1) : (int) ($existing['check_ins'] ?? 0),
        'updated_at' => time(),
    ];

    return gpCommunityChallengeState($userId, $dogId);
}

function gpCommunityChallengePlan(string $challenge): array
{
    $options = gpCommunityChallengeOptions();
    $challenge = array_key_exists($challenge, $options) ? $challenge : 'consistency_streak';
    return $options[$challenge];
}

function gpCommunityChallengeDashboardLabel(array $state): string
{
    $plan = gpCommunityChallengePlan((string) ($state['challenge'] ?? 'consistency_streak'));
    return $plan['icon'] . ' ' . $plan['label'];
}

function gpRenderCommunityChallengeDashboardCard(?array $state = null): void
{
    $plan = gpCommunityChallengePlan((string) ($state['challenge'] ?? 'consistency_streak'));
    $checkIns = (int) ($state['check_ins'] ?? 0);
    ?>
    <section class="card command-card mb-3 border-success">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Community Challenges</h2>
                    <div class="small text-muted">A small weekly training challenge to keep momentum going.</div>
                </div>
                <a href="community_challenges.php" class="btn btn-success btn-sm">Open Challenge</a>
            </div>
            <div class="rounded-3 border bg-success-subtle p-3">
                <div class="fw-bold"><?= e($plan['icon'] . ' ' . $plan['label']) ?></div>
                <div class="small text-muted"><?= e($plan['summary']) ?></div>
                <div class="small mt-2"><strong>Check-ins:</strong> <?= e((string) $checkIns) ?></div>
            </div>
        </div>
    </section>
    <?php
}
