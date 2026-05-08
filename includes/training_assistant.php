<?php
declare(strict_types=1);

function gpTrainingAssistantTopics(): array
{
    return [
        'general' => [
            'label' => 'General support',
            'icon' => '💬',
            'summary' => 'Short troubleshooting plan for training support questions.',
        ],
        'leash_pulling' => [
            'label' => 'Leash pulling',
            'icon' => '🦮',
            'summary' => 'Use when loose-leash behavior is breaking down.',
        ],
        'barking' => [
            'label' => 'Barking / vocalizing',
            'icon' => '🔊',
            'summary' => 'Use when the dog is noisy, frustrated, or over-alert.',
        ],
        'crate_truck' => [
            'label' => 'Crate / truck settle',
            'icon' => '🚚',
            'summary' => 'Use when travel, crate time, or vehicle settling is the issue.',
        ],
        'public_distraction' => [
            'label' => 'Public distraction',
            'icon' => '🧠',
            'summary' => 'Use when public access, dogs, people, or noise are the problem.',
        ],
        'regression' => [
            'label' => 'Regression',
            'icon' => '↩️',
            'summary' => 'Use when skills dropped after travel, illness, stress, or time off.',
        ],
        'recall' => [
            'label' => 'Recall / check-in',
            'icon' => '📣',
            'summary' => 'Use when coming when called is slow, hesitant, or unreliable.',
        ],
        'shutdown' => [
            'label' => 'Shutdown / fear',
            'icon' => '⚠️',
            'summary' => 'Use when the dog is freezing, refusing, or showing strong fear signals.',
        ],
    ];
}

function gpTrainingAssistantDefaultTopic(string $issueText): string
{
    $issueText = strtolower($issueText);
    if (str_contains($issueText, 'pull') || str_contains($issueText, 'leash')) {
        return 'leash_pulling';
    }
    if (str_contains($issueText, 'bark') || str_contains($issueText, 'whine') || str_contains($issueText, 'noise')) {
        return 'barking';
    }
    if (str_contains($issueText, 'crate') || str_contains($issueText, 'truck') || str_contains($issueText, 'car') || str_contains($issueText, 'vehicle')) {
        return 'crate_truck';
    }
    if (str_contains($issueText, 'public') || str_contains($issueText, 'distraction') || str_contains($issueText, 'store') || str_contains($issueText, 'crowd')) {
        return 'public_distraction';
    }
    if (str_contains($issueText, 'regress') || str_contains($issueText, 'backslide') || str_contains($issueText, 'worse')) {
        return 'regression';
    }
    if (str_contains($issueText, 'recall') || str_contains($issueText, 'come') || str_contains($issueText, 'check in')) {
        return 'recall';
    }
    if (str_contains($issueText, 'freeze') || str_contains($issueText, 'shutdown') || str_contains($issueText, 'fear') || str_contains($issueText, 'panic')) {
        return 'shutdown';
    }
    return 'general';
}

function gpTrainingAssistantSafetyFlags(string $text): array
{
    $text = strtolower($text);
    $flags = [];
    foreach ([
        'bite' => 'Bite history or snap behavior',
        'attack' => 'Attack or serious aggression',
        'panic' => 'Panic / unable to recover',
        'shutdown' => 'Shutdown / freeze',
        'medical' => 'Possible medical issue',
        'pain' => 'Possible pain or injury',
        'vomit' => 'Possible illness',
        'limp' => 'Possible injury or soreness',
    ] as $needle => $label) {
        if ($text !== '' && str_contains($text, $needle)) {
            $flags[] = $label;
        }
    }
    return array_values(array_unique($flags));
}

function gpTrainingAssistantAnalyze(array $input): array
{
    $issue = cleanTextarea($input['issue'] ?? '', 1200);
    $context = cleanText($input['context'] ?? '', 80);
    $whatTried = cleanTextarea($input['what_tried'] ?? '', 900);
    $flagText = cleanTextarea($input['safety_flags'] ?? '', 600);
    $topic = (string) ($input['topic'] ?? '');
    $topics = gpTrainingAssistantTopics();
    if (!isset($topics[$topic])) {
        $topic = gpTrainingAssistantDefaultTopic($issue . ' ' . $context . ' ' . $whatTried);
    }

    $topicData = $topics[$topic];
    $safetyFlags = gpTrainingAssistantSafetyFlags($flagText . ' ' . $issue . ' ' . $whatTried);

    $plan = [
        'title' => $topicData['label'],
        'icon' => $topicData['icon'],
        'summary' => $topicData['summary'],
        'next_steps' => [],
        'avoid' => [],
        'safety' => [],
        'follow_up' => [],
    ];

    switch ($topic) {
        case 'leash_pulling':
            $plan['next_steps'] = [
                'Shorten the session to 3-5 reps.',
                'Reward every moment of slack leash before asking for more movement.',
                'Start in the easiest place available, then move up one distraction level at a time.',
            ];
            $plan['avoid'] = [
                'Do not rehearse full-speed pulling on long walks.',
                'Do not increase distance until the dog can reset quickly after a mistake.',
            ];
            $plan['follow_up'] = [
                'What reward is strongest right now: food, toy, or release to sniff?',
                'Does the pulling start at the door, after speed changes, or around distractions?',
            ];
            break;
        case 'barking':
            $plan['next_steps'] = [
                'Reduce arousal before asking for quiet behavior.',
                'Mark and reward the first beat of calm instead of waiting for perfect silence.',
                'Move away from the trigger if the dog cannot reset within 10-15 seconds.',
            ];
            $plan['avoid'] = [
                'Do not keep repeating cues while the dog is escalating.',
                'Do not push duration work when the dog is already over threshold.',
            ];
            $plan['follow_up'] = [
                'Is the barking excitement, frustration, alerting, or anxiety?',
                'What happens if you increase distance from the trigger by 10 feet?',
            ];
            break;
        case 'crate_truck':
            $plan['next_steps'] = [
                'Keep the crate or truck session short and predictable.',
                'Reward the first calm settle, then end before the dog starts to unravel.',
                'Pair quiet confinement with a known settle cue or mat cue.',
            ];
            $plan['avoid'] = [
                'Do not use the crate only for stressful transitions.',
                'Do not turn travel settle into a long obedience drill.',
            ];
            $plan['follow_up'] = [
                'Is the issue motion, confinement, temperature, or noise?',
                'Does the dog settle faster after exercise or after a calmer start?',
            ];
            break;
        case 'public_distraction':
            $plan['next_steps'] = [
                'Drop back one difficulty level and get one clean win.',
                'Use shorter reps with obvious rewards before the dog loses focus.',
                'Ask for a simple, known behavior in the distraction and then release.',
            ];
            $plan['avoid'] = [
                'Do not proof new skills in the hardest environment first.',
                'Do not stack dogs, people, noise, and duration all at once.',
            ];
            $plan['follow_up'] = [
                'Which distraction causes the first loss of focus: dogs, people, carts, noise, or food?',
                'Does the dog recover faster in open space, along a wall, or farther from traffic?',
            ];
            break;
        case 'regression':
            $plan['next_steps'] = [
                'Return to the last reliable step and make it easier again.',
                'Check for missed practice, handler inconsistency, or a new stressor.',
                'Rebuild success rate before asking for duration or distraction.',
            ];
            $plan['avoid'] = [
                'Do not keep testing the same hard rep while the dog is failing.',
                'Do not interpret one bad day as a permanent loss of the skill.',
            ];
            $plan['follow_up'] = [
                'What changed right before the regression: schedule, route, handler, or health?',
                'Which exact rep still works every time?',
            ];
            break;
        case 'recall':
            $plan['next_steps'] = [
                'Practice recall in a very short, low-distraction setup.',
                'Use a high-value reinforcer and release quickly after success.',
                'Pay for fast turns and quick check-ins before adding distance.',
            ];
            $plan['avoid'] = [
                'Do not call the dog for something unpleasant right now.',
                'Do not let the cue become background noise by repeating it.',
            ];
            $plan['follow_up'] = [
                'Is the dog slow to start, slow to turn, or slow to disengage?',
                'What is the strongest reward that gets the dog moving immediately?',
            ];
            break;
        case 'shutdown':
            $plan['next_steps'] = [
                'Stop asking for more and lower the stress level immediately.',
                'Give the dog distance, time, and a very simple success if possible.',
                'Recheck health, pain, and environmental pressure before resuming work.',
            ];
            $plan['avoid'] = [
                'Do not force repetition through shutdown or fear.',
                'Do not try to proof or train through panic.',
            ];
            $plan['follow_up'] = [
                'Did the dog eat, move, and recover normally earlier today?',
                'Was there a sound, handling change, or environment shift before the shutdown?',
            ];
            break;
        default:
            $plan['next_steps'] = [
                'Name the behavior in one sentence and choose the easiest version of the exercise.',
                'Shorten the session until the dog can win before fatigue or frustration builds.',
                'Use one cue, one reward, then pause and evaluate.',
            ];
            $plan['avoid'] = [
                'Do not change three variables at once.',
                'Do not run the hardest version of the behavior as the first rep.',
            ];
            $plan['follow_up'] = [
                'What exactly is happening, and what should happen instead?',
                'Where does the behavior break first: start, middle, or finish?',
            ];
            break;
    }

    if ($context !== '') {
        $plan['summary'] .= ' Context: ' . $context . '.';
    }
    if ($whatTried !== '') {
        $plan['summary'] .= ' Already tried: ' . $whatTried . '.';
    }
    if ($safetyFlags) {
        $plan['safety'] = $safetyFlags;
    }

    return $plan;
}
