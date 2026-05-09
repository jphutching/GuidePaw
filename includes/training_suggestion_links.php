<?php

if (!function_exists('gpTrainingSuggestionLink')) {
    function gpTrainingSuggestionLink(string $suggestion): ?array
    {
        $text = strtolower($suggestion);

        $map = [
            'load the starter training ladder' => ['url' => 'training_program.php', 'label' => 'Start module'],
            'candidate screen first' => ['url' => 'candidate_assessment.php', 'label' => 'Start module'],
            'start with one candidate-screen session' => ['url' => 'candidate_assessment.php', 'label' => 'Start module'],
            'recent focus is soft' => ['url' => 'log_entry.php', 'label' => 'Start module'],
            'next new skill' => ['url' => 'training_program.php#training-ladder', 'label' => 'Start module'],
            'keep building' => ['url' => 'training_program.php#training-ladder', 'label' => 'Start module'],
            'proof ' => ['url' => 'training_program.php#training-ladder', 'label' => 'Start module'],
            'cgc track looks strong' => ['url' => 'training_program.php#program-guide', 'label' => 'Start module'],
            'community and urban skills are coming along' => ['url' => 'training_program.php#training-ladder', 'label' => 'Start module'],
            'task work is moving' => ['url' => 'certification.php', 'label' => 'Start module'],
            'self-training tip' => ['url' => 'log_entry.php', 'label' => 'Start module'],
            'trainer coordination' => ['url' => 'training_program.php', 'label' => 'Start module'],
            'professional trainer note' => ['url' => 'training_program.php', 'label' => 'Start module'],
            'hybrid plan' => ['url' => 'training_program.php', 'label' => 'Start module'],
        ];

        foreach ($map as $needle => $link) {
            if (str_contains($text, $needle)) {
                return $link;
            }
        }

        return null;
    }
}
