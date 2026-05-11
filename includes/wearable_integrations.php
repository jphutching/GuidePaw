<?php
declare(strict_types=1);

function gpWearableIntegrationsTableReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'wearable_sync_events'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpWearableParseSummary(string $payload): array
{
    $payload = trim($payload);
    if ($payload === '') {
        return [];
    }

    $decoded = json_decode($payload, true);
    if (is_array($decoded)) {
        return [
            'source' => trim((string) ($decoded['source'] ?? 'manual')),
            'device_name' => trim((string) ($decoded['device_name'] ?? '')),
            'recorded_for_date' => trim((string) ($decoded['recorded_for_date'] ?? '')),
            'steps' => isset($decoded['steps']) ? (int) $decoded['steps'] : null,
            'active_minutes' => isset($decoded['active_minutes']) ? (int) $decoded['active_minutes'] : null,
            'distance_miles' => isset($decoded['distance_miles']) ? (float) $decoded['distance_miles'] : null,
            'avg_heart_rate' => isset($decoded['avg_heart_rate']) ? (int) $decoded['avg_heart_rate'] : null,
            'sleep_hours' => isset($decoded['sleep_hours']) ? (float) $decoded['sleep_hours'] : null,
            'summary_text' => trim((string) ($decoded['summary_text'] ?? '')),
            'notes' => trim((string) ($decoded['notes'] ?? '')),
        ];
    }

    $lines = preg_split('/\r\n|\r|\n/', $payload) ?: [];
    $pairs = [];
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode(':', $line, 2));
        if ($key !== '') {
            $pairs[strtolower($key)] = $value;
        }
    }

    return [
        'source' => trim((string) ($pairs['source'] ?? 'manual')),
        'device_name' => trim((string) ($pairs['device'] ?? $pairs['device_name'] ?? '')),
        'recorded_for_date' => trim((string) ($pairs['date'] ?? $pairs['recorded_for_date'] ?? '')),
        'steps' => isset($pairs['steps']) && is_numeric($pairs['steps']) ? (int) $pairs['steps'] : null,
        'active_minutes' => isset($pairs['active_minutes']) && is_numeric($pairs['active_minutes']) ? (int) $pairs['active_minutes'] : null,
        'distance_miles' => isset($pairs['distance_miles']) && is_numeric($pairs['distance_miles']) ? (float) $pairs['distance_miles'] : null,
        'avg_heart_rate' => isset($pairs['avg_heart_rate']) && is_numeric($pairs['avg_heart_rate']) ? (int) $pairs['avg_heart_rate'] : null,
        'sleep_hours' => isset($pairs['sleep_hours']) && is_numeric($pairs['sleep_hours']) ? (float) $pairs['sleep_hours'] : null,
        'summary_text' => trim((string) ($pairs['summary'] ?? '')),
        'notes' => trim((string) ($pairs['notes'] ?? '')),
    ];
}

function gpWearableFirstValue(array $input, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = trim((string) ($input[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function gpWearableNumericValue(array $input, array $keys, bool $float = false): mixed
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = $input[$key];
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return $float ? (float) $value : (int) $value;
        }
    }

    return null;
}

function gpWearableNormalizeDateValue(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('Y-m-d', $timestamp);
}

function gpWearableNormalizeRecordInput(array $input, string $defaultSource = 'manual'): array
{
    $merged = $input;
    $rawPayload = $input['raw_payload'] ?? $input['wearable_payload'] ?? $input['payload'] ?? '';

    if (is_string($rawPayload) && trim($rawPayload) !== '') {
        $parsed = gpWearableParseSummary($rawPayload);
        if ($parsed) {
            $merged = array_merge($parsed, $merged);
        }
    } elseif (is_array($rawPayload)) {
        $merged = array_merge($rawPayload, $merged);
        $rawPayload = json_encode($rawPayload, JSON_UNESCAPED_SLASHES);
    } elseif (is_array($input['payload'] ?? null)) {
        $merged = array_merge($input['payload'], $merged);
        if ($rawPayload === '') {
            $rawPayload = json_encode($input['payload'], JSON_UNESCAPED_SLASHES);
        }
    }

    $source = gpWearableFirstValue($merged, ['source', 'provider', 'vendor'], $defaultSource);
    $deviceName = gpWearableFirstValue($merged, ['device_name', 'deviceName', 'device', 'watch_name', 'watchName'], '');
    $recordedForDate = gpWearableNormalizeDateValue(gpWearableFirstValue($merged, ['recorded_for_date', 'recordedForDate', 'date', 'recorded_at', 'recordedAt'], ''));
    $summaryText = gpWearableFirstValue($merged, ['summary_text', 'summaryText', 'summary'], '');
    $notes = gpWearableFirstValue($merged, ['notes', 'note'], '');

    return [
        'dog_id' => isset($merged['dog_id']) && $merged['dog_id'] !== '' ? (int) $merged['dog_id'] : null,
        'source' => $source !== '' ? $source : $defaultSource,
        'device_name' => $deviceName !== '' ? $deviceName : null,
        'recorded_for_date' => $recordedForDate,
        'steps' => gpWearableNumericValue($merged, ['steps', 'step_count', 'stepCount']),
        'active_minutes' => gpWearableNumericValue($merged, ['active_minutes', 'activeMinutes', 'active_time_minutes'], false),
        'distance_miles' => gpWearableNumericValue($merged, ['distance_miles', 'distanceMiles', 'distance'], true),
        'avg_heart_rate' => gpWearableNumericValue($merged, ['avg_heart_rate', 'avgHeartRate', 'heart_rate', 'heartRate'], false),
        'sleep_hours' => gpWearableNumericValue($merged, ['sleep_hours', 'sleepHours'], true),
        'summary_text' => $summaryText !== '' ? $summaryText : null,
        'notes' => $notes !== '' ? $notes : null,
        'raw_payload' => is_string($rawPayload) ? trim($rawPayload) : null,
    ];
}

function gpWearableRecentEvents(PDO $pdo, int $userId, ?int $dogId = null, int $limit = 12): array
{
    if (!gpWearableIntegrationsTableReady($pdo)) {
        return [];
    }

    $sql = "
        SELECT w.*, d.name AS dog_name
        FROM wearable_sync_events w
        LEFT JOIN dogs d ON d.id = w.dog_id
        WHERE w.user_id = ?
    ";
    $params = [$userId];
    if ($dogId !== null) {
        $sql .= " AND (w.dog_id = ? OR w.dog_id IS NULL)";
        $params[] = $dogId;
    }
    $sql .= " ORDER BY COALESCE(w.recorded_for_date, w.created_at)::date DESC, w.created_at DESC LIMIT ?";
    $params[] = max(1, min(50, $limit));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpWearableTrendSummary(array $events): array
{
    $summary = [
        'event_count' => count($events),
        'total_steps' => 0,
        'total_active_minutes' => 0,
        'avg_distance_miles' => null,
        'avg_heart_rate' => null,
        'avg_sleep_hours' => null,
    ];

    $distance = [];
    $heart = [];
    $sleep = [];
    foreach ($events as $event) {
        $summary['total_steps'] += (int) ($event['steps'] ?? 0);
        $summary['total_active_minutes'] += (int) ($event['active_minutes'] ?? 0);
        if ($event['distance_miles'] !== null && $event['distance_miles'] !== '') {
            $distance[] = (float) $event['distance_miles'];
        }
        if ($event['avg_heart_rate'] !== null && $event['avg_heart_rate'] !== '') {
            $heart[] = (int) $event['avg_heart_rate'];
        }
        if ($event['sleep_hours'] !== null && $event['sleep_hours'] !== '') {
            $sleep[] = (float) $event['sleep_hours'];
        }
    }

    if ($distance) {
        $summary['avg_distance_miles'] = array_sum($distance) / count($distance);
    }
    if ($heart) {
        $summary['avg_heart_rate'] = array_sum($heart) / count($heart);
    }
    if ($sleep) {
        $summary['avg_sleep_hours'] = array_sum($sleep) / count($sleep);
    }

    return $summary;
}

function gpWearableRecordEvent(PDO $pdo, int $userId, array $data): int
{
    if (!gpWearableIntegrationsTableReady($pdo)) {
        throw new RuntimeException('Wearable sync storage has not been deployed yet.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO wearable_sync_events
        (user_id, dog_id, source, device_name, recorded_for_date, steps, active_minutes, distance_miles, avg_heart_rate, sleep_hours, summary_text, notes, raw_payload)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([
        $userId,
        !empty($data['dog_id']) ? (int) $data['dog_id'] : null,
        trim((string) ($data['source'] ?? 'manual')) ?: 'manual',
        trim((string) ($data['device_name'] ?? '')) ?: null,
        trim((string) ($data['recorded_for_date'] ?? '')) ?: null,
        isset($data['steps']) && $data['steps'] !== '' ? (int) $data['steps'] : null,
        isset($data['active_minutes']) && $data['active_minutes'] !== '' ? (int) $data['active_minutes'] : null,
        isset($data['distance_miles']) && $data['distance_miles'] !== '' ? (float) $data['distance_miles'] : null,
        isset($data['avg_heart_rate']) && $data['avg_heart_rate'] !== '' ? (int) $data['avg_heart_rate'] : null,
        isset($data['sleep_hours']) && $data['sleep_hours'] !== '' ? (float) $data['sleep_hours'] : null,
        trim((string) ($data['summary_text'] ?? '')) ?: null,
        trim((string) ($data['notes'] ?? '')) ?: null,
        trim((string) ($data['raw_payload'] ?? '')) ?: null,
    ]);

    return (int) $stmt->fetchColumn();
}
