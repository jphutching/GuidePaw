<?php
declare(strict_types=1);

function gpTruckingModeOptions(): array
{
    return [
        'driving_day' => [
            'label' => 'Driving Day',
            'icon' => '🚚',
            'summary' => 'Keep the session short, structured, and easy to pause between stops.',
            'session_length' => '5 to 10 minutes',
            'priority' => 'Loose leash, settle, and quick resets',
            'avoid' => 'Long drills, high-rep fatigue work, and advanced public-access pushes',
            'next_step' => 'Finish with a calm crate or mat settle before the next drive segment.',
        ],
        'reset_day' => [
            'label' => 'Reset Day',
            'icon' => '🔄',
            'summary' => 'Use this when the dog needs a lighter restart after a rough stretch or travel gap.',
            'session_length' => '10 to 15 minutes',
            'priority' => 'Confidence, engagement, and one easy win',
            'avoid' => 'Multi-step proofing, stress stacking, or exposing the dog to crowded settings too soon',
            'next_step' => 'End on a clean cue the dog already knows well.',
        ],
        'weather_day' => [
            'label' => 'Weather Day',
            'icon' => '🌦️',
            'summary' => 'Adjust the plan for wind, rain, heat, glare, or reduced footing.',
            'session_length' => '5 to 12 minutes',
            'priority' => 'Comfort checks, footwork, and controlled exposure',
            'avoid' => 'Overheating, slick surfaces, and long exposure to bad footing or extreme weather',
            'next_step' => 'Practice calm entry and exit from the truck, crate, or shelter.',
        ],
        'low_energy_day' => [
            'label' => 'Low Energy Day',
            'icon' => '🪫',
            'summary' => 'Keep the dog in a low-demand mode and protect motivation.',
            'session_length' => '3 to 8 minutes',
            'priority' => 'Easy cues, settle work, and reinforcement',
            'avoid' => 'High arousal games or heavy obedience sequences',
            'next_step' => 'Reward a clean settle and stop while the dog still has some spark left.',
        ],
        'high_stress_day' => [
            'label' => 'High Stress Day',
            'icon' => '🧯',
            'summary' => 'Use when the handler day is rough and the dog should get a narrow, predictable task.',
            'session_length' => '2 to 6 minutes',
            'priority' => 'Neutrality, settle, and one repeated success',
            'avoid' => 'Crowded routes, long sessions, and new challenges',
            'next_step' => 'Finish with a short recovery period and a calm return to the crate or mat.',
        ],
    ];
}

function gpTruckingModeDefault(): string
{
    return 'driving_day';
}

function gpTruckingModeState(int $userId, int $dogId): array
{
    $state = $_SESSION['guidepaw_trucking_mode'][$userId][$dogId] ?? [];
    if (!is_array($state)) {
        $state = [];
    }

    $mode = (string) ($state['mode'] ?? gpTruckingModeDefault());
    if (!array_key_exists($mode, gpTruckingModeOptions())) {
        $mode = gpTruckingModeDefault();
    }

    return [
        'mode' => $mode,
        'notes' => (string) ($state['notes'] ?? ''),
        'updated_at' => (int) ($state['updated_at'] ?? 0),
    ];
}

function gpTruckingModeSaveState(int $userId, int $dogId, string $mode, string $notes = ''): array
{
    $options = gpTruckingModeOptions();
    if (!array_key_exists($mode, $options)) {
        $mode = gpTruckingModeDefault();
    }

    if (!isset($_SESSION['guidepaw_trucking_mode']) || !is_array($_SESSION['guidepaw_trucking_mode'])) {
        $_SESSION['guidepaw_trucking_mode'] = [];
    }
    if (!isset($_SESSION['guidepaw_trucking_mode'][$userId]) || !is_array($_SESSION['guidepaw_trucking_mode'][$userId])) {
        $_SESSION['guidepaw_trucking_mode'][$userId] = [];
    }

    $_SESSION['guidepaw_trucking_mode'][$userId][$dogId] = [
        'mode' => $mode,
        'notes' => trim($notes),
        'updated_at' => time(),
    ];

    return gpTruckingModeState($userId, $dogId);
}

function gpTruckingModePlan(string $mode): array
{
    $options = gpTruckingModeOptions();
    $mode = array_key_exists($mode, $options) ? $mode : gpTruckingModeDefault();
    return $options[$mode];
}

function gpTruckingModeDashboardLabel(array $state): string
{
    $plan = gpTruckingModePlan((string) ($state['mode'] ?? gpTruckingModeDefault()));
    return $plan['icon'] . ' ' . $plan['label'];
}

function gpRenderTruckingModeDashboardCard(?array $state = null): void
{
    $plan = gpTruckingModePlan((string) ($state['mode'] ?? gpTruckingModeDefault()));
    ?>
    <section class="card command-card mb-3 border-primary">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Trucking Mode</h2>
                    <div class="small text-muted">Pick the day type that matches the route, weather, and energy level.</div>
                </div>
                <a href="trucking_mode.php" class="btn btn-primary btn-sm">Open Planner</a>
            </div>
            <div class="rounded-3 border bg-primary-subtle p-3">
                <div class="fw-bold"><?= e($plan['icon'] . ' ' . $plan['label']) ?></div>
                <div class="small text-muted"><?= e($plan['summary']) ?></div>
            </div>
        </div>
    </section>
    <?php
}
