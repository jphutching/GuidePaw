<?php
declare(strict_types=1);

function gpGoalBuilderCategoryOptions(): array
{
    return [
        'potty' => [
            'label' => 'Potty routine',
            'icon' => '🚻',
            'problem_hint' => 'Accidents, schedule drift, or unclear potty habits.',
            'desired_hint' => 'Dog eliminates on schedule with clean routines.',
            'context_hint' => 'Home, yard, truck stop, rest area, or hotel.',
            'success_hint' => 'Potty happens promptly after wake-up, meals, and breaks.',
            'maintenance_hint' => 'Keep the routine consistent and reduce freedom only after success.',
            'reward_hint' => 'Use a fast food reward, praise, and immediate release.',
        ],
        'leash' => [
            'label' => 'Loose leash / pulling',
            'icon' => '🐾',
            'problem_hint' => 'Pulling, forging, or hard leash pressure on walks.',
            'desired_hint' => 'Dog walks with a loose leash and checks in voluntarily.',
            'context_hint' => 'Sidewalks, parking lots, truck stops, and quiet neighborhoods.',
            'success_hint' => 'Walk 20 steps with loose leash and at least 2 check-ins.',
            'maintenance_hint' => 'Short routes, quick resets, and reward before the leash tightens.',
            'reward_hint' => 'Reward position, eye contact, and calm movement.',
        ],
        'barking' => [
            'label' => 'Barking',
            'icon' => '🔇',
            'problem_hint' => 'Barking at triggers, doors, windows, or social excitement.',
            'desired_hint' => 'Dog notices the trigger and chooses quiet recovery.',
            'context_hint' => 'Cab, home, yard, or public threshold areas.',
            'success_hint' => 'Dog can see the trigger and recover without repeated barking.',
            'maintenance_hint' => 'Lower the difficulty, manage distance, and reward quiet check-ins.',
            'reward_hint' => 'Use calm praise, food, and distance from the trigger.',
        ],
        'cab_calm' => [
            'label' => 'Cab calm / settling',
            'icon' => '🚚',
            'problem_hint' => 'Restlessness, whining, pacing, or difficulty settling in the truck.',
            'desired_hint' => 'Dog settles quietly in the cab or crate.',
            'context_hint' => 'Truck cab, sleeper, crate, or resting mat.',
            'success_hint' => 'Dog settles within 60 seconds and holds calm for the target time.',
            'maintenance_hint' => 'Start after potty and short movement, then slowly lengthen duration.',
            'reward_hint' => 'Use a chew, mat reward, and quiet reinforcement.',
        ],
        'jumping' => [
            'label' => 'Jumping',
            'icon' => '⬇️',
            'problem_hint' => 'Jumping on people during greetings or excitement.',
            'desired_hint' => 'Dog keeps four paws on the floor and offers a sit or settle.',
            'context_hint' => 'Doorway, home greeting, store entrance, or truck stop.',
            'success_hint' => 'Dog greets with four paws down in 3 repeated opportunities.',
            'maintenance_hint' => 'Prevent rehearsals, reward the floor, and keep greetings brief.',
            'reward_hint' => 'Use food, access, and calm greeting praise.',
        ],
        'public_manners' => [
            'label' => 'Public manners',
            'icon' => '🏪',
            'problem_hint' => 'Over-arousal, scanning, or difficulty staying focused in public.',
            'desired_hint' => 'Dog stays neutral and responsive in public spaces.',
            'context_hint' => 'Stores, lobbies, rest areas, sidewalks, and waiting rooms.',
            'success_hint' => 'Dog completes one short outing without threshold blowups.',
            'maintenance_hint' => 'Use easier outings first, then add duration or distractions slowly.',
            'reward_hint' => 'Reward calm focus, loose leash, and clean exits.',
        ],
        'psd_foundation' => [
            'label' => 'PSD foundation',
            'icon' => '🎓',
            'problem_hint' => 'Need a structured foundation for service-dog work.',
            'desired_hint' => 'Dog builds neutrality, recovery, and handler engagement.',
            'context_hint' => 'Low-distraction home setups, planned field trips, and controlled public reps.',
            'success_hint' => 'Dog can complete the foundation behavior with stable recovery.',
            'maintenance_hint' => 'Keep the work short, clear, and repeatable.',
            'reward_hint' => 'Use high-value food and quick release.',
        ],
        'other' => [
            'label' => 'Other',
            'icon' => '🧩',
            'problem_hint' => 'A different behavior or routine that needs a measurable plan.',
            'desired_hint' => 'A clear observable behavior the dog can repeat.',
            'context_hint' => 'The environment where the issue happens most often.',
            'success_hint' => 'One behavior the handler can measure without guessing.',
            'maintenance_hint' => 'Short sessions, clear cue, and predictable reinforcement.',
            'reward_hint' => 'Pick the reinforcer the dog works for most reliably.',
        ],
    ];
}

function gpGoalBuilderSuggestCategory(string $problemText, string $desiredText = ''): string
{
    $text = strtolower(trim($problemText . ' ' . $desiredText));
    if ($text === '') {
        return 'other';
    }

    $matches = [
        'potty' => ['potty', 'accident', 'pee', 'poop', 'toilet'],
        'leash' => ['pull', 'leash', 'heel', 'walking'],
        'barking' => ['bark', 'whine', 'noise', 'react'],
        'cab_calm' => ['cab', 'truck', 'crate', 'settle', 'rest'],
        'jumping' => ['jump', 'greet', 'paws'],
        'public_manners' => ['public', 'store', 'crowd', 'neutral', 'lobby'],
        'psd_foundation' => ['service', 'psd', 'task', 'foundation'],
    ];

    foreach ($matches as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return $category;
            }
        }
    }

    return 'other';
}

function gpGoalBuilderDraft(array $input): array
{
    $categories = gpGoalBuilderCategoryOptions();
    $category = (string) ($input['goal_category'] ?? 'other');
    if (!isset($categories[$category])) {
        $category = gpGoalBuilderSuggestCategory((string) ($input['current_problem'] ?? ''), (string) ($input['desired_behavior'] ?? ''));
    }
    if (!isset($categories[$category])) {
        $category = 'other';
    }

    $plan = $categories[$category];
    $problem = trim((string) ($input['current_problem'] ?? ''));
    $desired = trim((string) ($input['desired_behavior'] ?? ''));
    $context = trim((string) ($input['context_environment'] ?? ''));
    $trigger = trim((string) ($input['trigger_description'] ?? ''));
    $budget = (int) ($input['handler_time_budget_minutes'] ?? 3);
    if ($budget <= 0) {
        $budget = 3;
    }

    $reinforcer = trim((string) ($input['reinforcer_preference'] ?? ''));
    if ($reinforcer === '') {
        $reinforcer = $plan['reward_hint'];
    }

    $success = trim((string) ($input['success_criteria'] ?? ''));
    if ($success === '') {
        $success = $plan['success_hint'];
    }

    $maintenance = trim((string) ($input['maintenance_plan'] ?? ''));
    if ($maintenance === '') {
        $maintenance = $plan['maintenance_hint'];
    }

    $defaultCurrentProblem = $problem !== '' ? $problem : $plan['problem_hint'];
    $defaultDesired = $desired !== '' ? $desired : $plan['desired_hint'];
    $defaultContext = $context !== '' ? $context : $plan['context_hint'];
    $defaultTrigger = $trigger !== '' ? $trigger : 'The most common trigger or context that causes the behavior.';

    $safety = !empty($input['safety_risk']) ? 1 : 0;

    return [
        'goal_category' => $category,
        'category_label' => $plan['label'],
        'category_icon' => $plan['icon'],
        'current_problem' => $defaultCurrentProblem,
        'desired_behavior' => $defaultDesired,
        'context_environment' => $defaultContext,
        'trigger_description' => $defaultTrigger,
        'handler_time_budget_minutes' => $budget,
        'reinforcer_preference' => $reinforcer,
        'safety_risk' => $safety,
        'success_criteria' => $success,
        'maintenance_plan' => $maintenance,
        'summary' => $defaultDesired . ' in ' . $defaultContext,
    ];
}
