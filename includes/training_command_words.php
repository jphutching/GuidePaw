<?php

require_once __DIR__ . '/training_data.php';

if (!function_exists('gpTrainingCommandWordEnsureSchema')) {
    function gpTrainingCommandWordEnsureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_training_preferences (
                user_id INTEGER NOT NULL,
                preference_key VARCHAR(80) NOT NULL,
                preference_value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, preference_key)
            )
        ");
    }
}

if (!function_exists('gpTrainingCommandWordDefaults')) {
    function gpTrainingCommandWordDefaults(): array
    {
        return getTrainingCommandCueSuggestions();
    }
}

if (!function_exists('gpTrainingCommandWordKey')) {
    function gpTrainingCommandWordKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    }
}

if (!function_exists('gpTrainingCommandWordNormalizeCue')) {
    function gpTrainingCommandWordNormalizeCue(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        return substr($value, 0, 120);
    }
}

if (!function_exists('gpTrainingCommandWordLoad')) {
    function gpTrainingCommandWordLoad(PDO $pdo, int $userId): array
    {
        gpTrainingCommandWordEnsureSchema($pdo);
        $defaults = gpTrainingCommandWordDefaults();

        $stmt = $pdo->prepare("
            SELECT preference_value
            FROM user_training_preferences
            WHERE user_id = ? AND preference_key = 'command_words'
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $stored = $stmt->fetchColumn();
        $saved = [];

        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                $saved = $decoded;
            }
        }

        $groups = [];
        foreach ($defaults as $groupName => $cueItems) {
            $groupKey = gpTrainingCommandWordKey($groupName);
            $groups[$groupKey] = [
                'label' => $groupName,
                'items' => [],
            ];

            foreach ($cueItems as $cueItem) {
                $skillKey = gpTrainingCommandWordKey((string) ($cueItem['skill'] ?? ''));
                $savedCue = (string) ($saved[$groupKey][$skillKey] ?? ($cueItem['cue'] ?? ''));
                $groups[$groupKey]['items'][] = [
                    'key' => $skillKey,
                    'skill' => (string) ($cueItem['skill'] ?? ''),
                    'cue' => $savedCue !== '' ? $savedCue : (string) ($cueItem['cue'] ?? ''),
                    'default_cue' => (string) ($cueItem['cue'] ?? ''),
                    'use' => (string) ($cueItem['use'] ?? ''),
                ];
            }
        }

        return $groups;
    }
}

if (!function_exists('gpTrainingCommandWordSave')) {
    function gpTrainingCommandWordSave(PDO $pdo, int $userId, array $submitted): void
    {
        gpTrainingCommandWordEnsureSchema($pdo);
        $defaults = gpTrainingCommandWordDefaults();
        $payload = [];

        foreach ($defaults as $groupName => $cueItems) {
            $groupKey = gpTrainingCommandWordKey($groupName);
            $payload[$groupKey] = [];

            foreach ($cueItems as $cueItem) {
                $skillKey = gpTrainingCommandWordKey((string) ($cueItem['skill'] ?? ''));
                $cue = $submitted[$groupKey][$skillKey] ?? ($cueItem['cue'] ?? '');
                $cue = gpTrainingCommandWordNormalizeCue((string) $cue);
                $payload[$groupKey][$skillKey] = $cue !== '' ? $cue : (string) ($cueItem['cue'] ?? '');
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_training_preferences (user_id, preference_key, preference_value, updated_at)
            VALUES (?, 'command_words', ?, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id, preference_key)
            DO UPDATE SET preference_value = excluded.preference_value,
                          updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $userId,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
