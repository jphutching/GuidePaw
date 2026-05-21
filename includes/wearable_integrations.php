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

function gpWearableEnsureEventColumns(PDO $pdo): void
{
    if (!gpWearableIntegrationsTableReady($pdo)) {
        return;
    }

    $pdo->exec("ALTER TABLE wearable_sync_events ADD COLUMN IF NOT EXISTS rest_minutes INTEGER");
    $pdo->exec("ALTER TABLE wearable_sync_events ADD COLUMN IF NOT EXISTS play_minutes INTEGER");
    $pdo->exec("ALTER TABLE wearable_sync_events ADD COLUMN IF NOT EXISTS battery_percent INTEGER");
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
            'resting_heart_rate' => isset($decoded['resting_heart_rate']) ? (int) $decoded['resting_heart_rate'] : null,
            'sleep_hours' => isset($decoded['sleep_hours']) ? (float) $decoded['sleep_hours'] : null,
            'battery_percent' => isset($decoded['battery_percent']) ? (int) $decoded['battery_percent'] : null,
            'summary_text' => trim((string) ($decoded['summary_text'] ?? '')),
            'notes' => trim((string) ($decoded['notes'] ?? '')),
        ];
    }

    $lines = preg_split('/\r\n|\r|\n/', $payload) ?: [];
    $pairs = [];
    $csvHeaders = null;
    $csvValues = null;
    foreach ($lines as $line) {
        if (strpos($line, ',') === false) {
            continue;
        }
        $candidateHeaders = array_map('trim', str_getcsv($line));
        if (count($candidateHeaders) < 2) {
            continue;
        }
        $csvHeaders = $candidateHeaders;
        break;
    }
    if (is_array($csvHeaders)) {
        foreach ($lines as $line) {
            if (strpos($line, ',') === false) {
                continue;
            }
            $candidateValues = array_map('trim', str_getcsv($line));
            if (count($candidateValues) !== count($csvHeaders)) {
                continue;
            }
            $csvValues = $candidateValues;
            break;
        }
    }
    if (is_array($csvHeaders) && is_array($csvValues)) {
        $mapped = @array_combine(
            array_map(static fn(string $value): string => strtolower(trim($value)), $csvHeaders),
            $csvValues
        );
        if (is_array($mapped)) {
            $pairs = array_merge($pairs, $mapped);
        }
    }
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
        'rest_minutes' => isset($pairs['rest_minutes']) && is_numeric($pairs['rest_minutes']) ? (int) $pairs['rest_minutes'] : null,
        'play_minutes' => isset($pairs['play_minutes']) && is_numeric($pairs['play_minutes']) ? (int) $pairs['play_minutes'] : null,
        'distance_miles' => isset($pairs['distance_miles']) && is_numeric($pairs['distance_miles']) ? (float) $pairs['distance_miles'] : null,
        'avg_heart_rate' => isset($pairs['avg_heart_rate']) && is_numeric($pairs['avg_heart_rate']) ? (int) $pairs['avg_heart_rate'] : null,
        'resting_heart_rate' => isset($pairs['resting_heart_rate']) && is_numeric($pairs['resting_heart_rate']) ? (int) $pairs['resting_heart_rate'] : null,
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
        'rest_minutes' => gpWearableNumericValue($merged, ['rest_minutes', 'restMinutes', 'rest_time_minutes'], false),
        'play_minutes' => gpWearableNumericValue($merged, ['play_minutes', 'playMinutes', 'play_time_minutes'], false),
        'distance_miles' => gpWearableNumericValue($merged, ['distance_miles', 'distanceMiles', 'distance'], true),
        'avg_heart_rate' => gpWearableNumericValue($merged, ['avg_heart_rate', 'avgHeartRate', 'heart_rate', 'heartRate'], false),
        'resting_heart_rate' => gpWearableNumericValue($merged, ['resting_heart_rate', 'restingHeartRate', 'rest_hr', 'resting_hr'], false),
        'sleep_hours' => gpWearableNumericValue($merged, ['sleep_hours', 'sleepHours'], true),
        'battery_percent' => gpWearableNumericValue($merged, ['battery_percent', 'batteryPercent', 'battery', 'battery_level'], false),
        'summary_text' => $summaryText !== '' ? $summaryText : null,
        'notes' => $notes !== '' ? $notes : null,
        'raw_payload' => is_string($rawPayload) ? trim($rawPayload) : null,
    ];
}

function gpWearableCatalogSeed(): array
{
    return [
        ['slug' => 'tractive-gps-tracker', 'device_type' => 'dog_tracker', 'label' => 'Tractive GPS Tracker (Dog 6 / XL)', 'vendor' => 'Tractive', 'pairing_mode' => 'App + GPS', 'data_focus' => 'GPS, activity, geofence alerts', 'notes' => 'Best for location-first tracking.', 'sort_order' => 10],
        ['slug' => 'fi-series-3-plus', 'device_type' => 'dog_tracker', 'label' => 'Fi Series 3+ Smart Collar', 'vendor' => 'Fi', 'pairing_mode' => 'App + Collar', 'data_focus' => 'GPS, battery, activity', 'notes' => 'Good for daily activity and escape alerts.', 'sort_order' => 20],
        ['slug' => 'garmin-alpha-tt', 'device_type' => 'dog_tracker', 'label' => 'Garmin Alpha / TT series', 'vendor' => 'Garmin', 'pairing_mode' => 'Field tracking', 'data_focus' => 'GPS, range, location updates', 'notes' => 'Best for working-dog field tracking.', 'sort_order' => 30],
        ['slug' => 'petpace-2-3', 'device_type' => 'dog_tracker', 'label' => 'PetPace (2.0 / 3.0)', 'vendor' => 'PetPace', 'pairing_mode' => 'App + collar', 'data_focus' => 'Vitals, activity, alerts', 'notes' => 'Best for health-first monitoring.', 'sort_order' => 40],
        ['slug' => 'invoxia-biotracker', 'device_type' => 'dog_tracker', 'label' => 'Invoxia Biotracker', 'vendor' => 'Invoxia', 'pairing_mode' => 'App + tracker', 'data_focus' => 'GPS, movement, alerts', 'notes' => 'Practical option for compact tracking.', 'sort_order' => 50],
        ['slug' => 'halo-collar', 'device_type' => 'dog_tracker', 'label' => 'Halo Collar', 'vendor' => 'Halo', 'pairing_mode' => 'App + boundary system', 'data_focus' => 'GPS, boundary, activity', 'notes' => 'Best when containment matters.', 'sort_order' => 60],
        ['slug' => 'fitbark', 'device_type' => 'dog_tracker', 'label' => 'FitBark', 'vendor' => 'FitBark', 'pairing_mode' => 'App + API', 'data_focus' => 'Activity, sleep, wellness', 'notes' => 'Best for health trends and API access.', 'sort_order' => 70],
        ['slug' => 'pitpat', 'device_type' => 'dog_tracker', 'label' => 'PitPat', 'vendor' => 'PitPat', 'pairing_mode' => 'App + tracker', 'data_focus' => 'Activity, goals, movement', 'notes' => 'Simple activity-focused tracker.', 'sort_order' => 80],
        ['slug' => 'whistle', 'device_type' => 'dog_tracker', 'label' => 'Whistle', 'vendor' => 'Whistle', 'pairing_mode' => 'App + tracker', 'data_focus' => 'GPS, activity, behavior', 'notes' => 'Useful if you already have Whistle data.', 'sort_order' => 90],
        ['slug' => 'garmin-watch', 'device_type' => 'handler_wearable', 'label' => 'Garmin (Vivoactive, Forerunner, Fenix, Instinct)', 'vendor' => 'Garmin', 'pairing_mode' => 'Garmin Health API', 'data_focus' => 'Steps, HR, sleep, stress, battery', 'notes' => 'Best handler pairing with Garmin dog systems.', 'sort_order' => 10],
        ['slug' => 'apple-watch', 'device_type' => 'handler_wearable', 'label' => 'Apple Watch (Series / Ultra)', 'vendor' => 'Apple', 'pairing_mode' => 'HealthKit', 'data_focus' => 'Steps, HR, sleep, workouts', 'notes' => 'Best if the handler is on iPhone.', 'sort_order' => 20],
        ['slug' => 'samsung-galaxy-watch', 'device_type' => 'handler_wearable', 'label' => 'Samsung Galaxy Watch (Ultra / 7 / 8)', 'vendor' => 'Samsung', 'pairing_mode' => 'Health Connect / Samsung Health', 'data_focus' => 'Steps, HR, sleep, workouts', 'notes' => 'Best for Android handlers with Samsung devices.', 'sort_order' => 30],
        ['slug' => 'google-pixel-watch', 'device_type' => 'handler_wearable', 'label' => 'Google Pixel Watch (3 / 4)', 'vendor' => 'Google', 'pairing_mode' => 'Health Connect', 'data_focus' => 'Steps, HR, sleep, workouts', 'notes' => 'Good Android baseline option.', 'sort_order' => 40],
        ['slug' => 'whoop', 'device_type' => 'handler_wearable', 'label' => 'Whoop (5.0)', 'vendor' => 'Whoop', 'pairing_mode' => 'API / export', 'data_focus' => 'Strain, recovery, sleep', 'notes' => 'Useful if recovery metrics matter.', 'sort_order' => 50],
        ['slug' => 'oura-ring', 'device_type' => 'handler_wearable', 'label' => 'Oura Ring', 'vendor' => 'Oura', 'pairing_mode' => 'Oura API', 'data_focus' => 'Sleep, readiness, HR', 'notes' => 'Strong for recovery and sleep context.', 'sort_order' => 60],
        ['slug' => 'fitbit', 'device_type' => 'handler_wearable', 'label' => 'Fitbit', 'vendor' => 'Fitbit', 'pairing_mode' => 'Fitbit Web API', 'data_focus' => 'Steps, HR, sleep, activity', 'notes' => 'Useful if the handler already uses Fitbit.', 'sort_order' => 70],
        ['slug' => 'polar-suunto', 'device_type' => 'handler_wearable', 'label' => 'Polar / Suunto', 'vendor' => 'Polar', 'pairing_mode' => 'Polar AccessLink / export', 'data_focus' => 'Training, HR, sleep, recovery', 'notes' => 'Good for training and recovery workflows.', 'sort_order' => 80],
    ];
}

function gpWearableSyncModeOptions(): array
{
    return [
        'health_connect' => ['label' => 'Health Connect', 'notes' => 'Best default for Android phone sync.'],
        'healthkit' => ['label' => 'HealthKit', 'notes' => 'Best for iPhone / Apple Watch sync.'],
        'samsung_health' => ['label' => 'Samsung Health', 'notes' => 'Use when Samsung Health is the data source.'],
        'garmin_health' => ['label' => 'Garmin Health API', 'notes' => 'Use when Garmin Connect data is available.'],
        'fitbit_api' => ['label' => 'Fitbit API', 'notes' => 'Use when Fitbit developer access is configured.'],
        'oura_api' => ['label' => 'Oura API', 'notes' => 'Use for Oura sleep and readiness data.'],
        'polar_accesslink' => ['label' => 'Polar AccessLink', 'notes' => 'Use when Polar Flow access is connected.'],
        'fitbark_api' => ['label' => 'FitBark API', 'notes' => 'Use when FitBark API access is configured.'],
        'petpace_api' => ['label' => 'PetPace API / Portal', 'notes' => 'Use for PetPace health and alert data.'],
        'bridge_import' => ['label' => 'Bridge Import', 'notes' => 'Use when the phone bridge is carrying the sync.'],
        'manual' => ['label' => 'Manual Entry', 'notes' => 'Use when the data is pasted or keyed in by hand.'],
    ];
}

function gpWearableDeviceCatalogTableReady(PDO $pdo): bool
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
              AND table_name = 'wearable_device_catalog'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpWearableSetupTableReady(PDO $pdo): bool
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
              AND table_name = 'user_wearable_device_setup'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpWearableEnsureDeviceCatalog(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wearable_device_catalog (
            slug TEXT PRIMARY KEY,
            device_type TEXT NOT NULL,
            label TEXT NOT NULL,
            vendor TEXT NOT NULL DEFAULT '',
            pairing_mode TEXT NOT NULL DEFAULT '',
            data_focus TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");

    $catalog = gpWearableCatalogSeed();
    $stmt = $pdo->prepare("
        INSERT INTO wearable_device_catalog
            (slug, device_type, label, vendor, pairing_mode, data_focus, notes, sort_order)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (slug) DO UPDATE SET
            device_type = EXCLUDED.device_type,
            label = EXCLUDED.label,
            vendor = EXCLUDED.vendor,
            pairing_mode = EXCLUDED.pairing_mode,
            data_focus = EXCLUDED.data_focus,
            notes = EXCLUDED.notes,
            sort_order = EXCLUDED.sort_order
    ");
    foreach ($catalog as $row) {
        $stmt->execute([
            $row['slug'],
            $row['device_type'],
            $row['label'],
            $row['vendor'],
            $row['pairing_mode'],
            $row['data_focus'],
            $row['notes'],
            (int) $row['sort_order'],
        ]);
    }
}

function gpWearableEnsureSetupTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_wearable_device_setup (
            id BIGSERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
            handler_wearable_slug TEXT NOT NULL DEFAULT '',
            dog_tracker_slug TEXT NOT NULL DEFAULT '',
            sync_mode TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE (user_id, dog_id)
        )
    ");
}

function gpWearableCatalogEntries(PDO $pdo): array
{
    gpWearableEnsureDeviceCatalog($pdo);
    $stmt = $pdo->query("SELECT * FROM wearable_device_catalog ORDER BY device_type, sort_order, label");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpWearableCatalogEntryBySlug(PDO $pdo, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    gpWearableEnsureDeviceCatalog($pdo);
    $stmt = $pdo->prepare("SELECT * FROM wearable_device_catalog WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gpWearableCurrentSetup(PDO $pdo, int $userId, int $dogId): ?array
{
    gpWearableEnsureSetupTable($pdo);
    $stmt = $pdo->prepare("
        SELECT s.*, hd.label AS handler_wearable_label, dd.label AS dog_tracker_label
            , hd.vendor AS handler_wearable_vendor, dd.vendor AS dog_tracker_vendor
            , hd.pairing_mode AS handler_wearable_pairing_mode, dd.pairing_mode AS dog_tracker_pairing_mode
            , hd.data_focus AS handler_wearable_data_focus, dd.data_focus AS dog_tracker_data_focus
        FROM user_wearable_device_setup s
        LEFT JOIN wearable_device_catalog hd ON hd.slug = s.handler_wearable_slug
        LEFT JOIN wearable_device_catalog dd ON dd.slug = s.dog_tracker_slug
        WHERE s.user_id = ? AND s.dog_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $dogId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
        return null;
    }
    $row['sync_mode_label'] = gpWearableSyncModeOptions()[$row['sync_mode']]['label'] ?? ucfirst(str_replace('_', ' ', (string) $row['sync_mode']));
    $row['handler_wearable_label'] = trim((string) ($row['handler_wearable_label'] ?? $row['handler_wearable_slug'] ?? ''));
    $row['dog_tracker_label'] = trim((string) ($row['dog_tracker_label'] ?? $row['dog_tracker_slug'] ?? ''));
    $row['handler_wearable_pairing_mode'] = trim((string) ($row['handler_wearable_pairing_mode'] ?? ''));
    $row['dog_tracker_pairing_mode'] = trim((string) ($row['dog_tracker_pairing_mode'] ?? ''));
    $row['handler_wearable_data_focus'] = trim((string) ($row['handler_wearable_data_focus'] ?? ''));
    $row['dog_tracker_data_focus'] = trim((string) ($row['dog_tracker_data_focus'] ?? ''));
    $row['handler_wearable_vendor'] = trim((string) ($row['handler_wearable_vendor'] ?? ''));
    $row['dog_tracker_vendor'] = trim((string) ($row['dog_tracker_vendor'] ?? ''));
    return $row;
}

function gpWearableSaveSetup(PDO $pdo, int $userId, int $dogId, array $data): void
{
    gpWearableEnsureSetupTable($pdo);
    gpWearableEnsureDeviceCatalog($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO user_wearable_device_setup
            (user_id, dog_id, handler_wearable_slug, dog_tracker_slug, sync_mode, notes, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, NOW())
        ON CONFLICT (user_id, dog_id) DO UPDATE SET
            handler_wearable_slug = EXCLUDED.handler_wearable_slug,
            dog_tracker_slug = EXCLUDED.dog_tracker_slug,
            sync_mode = EXCLUDED.sync_mode,
            notes = EXCLUDED.notes,
            updated_at = NOW()
    ");
    $stmt->execute([
        $userId,
        $dogId,
        trim((string) ($data['handler_wearable_slug'] ?? '')),
        trim((string) ($data['dog_tracker_slug'] ?? '')),
        trim((string) ($data['sync_mode'] ?? '')),
        trim((string) ($data['notes'] ?? '')),
    ]);
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
        'total_rest_minutes' => 0,
        'total_play_minutes' => 0,
        'avg_battery_percent' => null,
        'avg_distance_miles' => null,
        'avg_heart_rate' => null,
        'avg_resting_heart_rate' => null,
        'avg_sleep_hours' => null,
    ];

    $distance = [];
    $heart = [];
    $resting = [];
    $sleep = [];
    $battery = [];
    foreach ($events as $event) {
        $summary['total_steps'] += (int) ($event['steps'] ?? 0);
        $summary['total_active_minutes'] += (int) ($event['active_minutes'] ?? 0);
        $summary['total_rest_minutes'] += (int) ($event['rest_minutes'] ?? 0);
        $summary['total_play_minutes'] += (int) ($event['play_minutes'] ?? 0);
        if ($event['distance_miles'] !== null && $event['distance_miles'] !== '') {
            $distance[] = (float) $event['distance_miles'];
        }
        if ($event['avg_heart_rate'] !== null && $event['avg_heart_rate'] !== '') {
            $heart[] = (int) $event['avg_heart_rate'];
        }
        if ($event['resting_heart_rate'] !== null && $event['resting_heart_rate'] !== '') {
            $resting[] = (int) $event['resting_heart_rate'];
        }
        if ($event['sleep_hours'] !== null && $event['sleep_hours'] !== '') {
            $sleep[] = (float) $event['sleep_hours'];
        }
        if ($event['battery_percent'] !== null && $event['battery_percent'] !== '') {
            $battery[] = (int) $event['battery_percent'];
        }
    }

    if ($distance) {
        $summary['avg_distance_miles'] = array_sum($distance) / count($distance);
    }
    if ($heart) {
        $summary['avg_heart_rate'] = array_sum($heart) / count($heart);
    }
    if ($resting) {
        $summary['avg_resting_heart_rate'] = array_sum($resting) / count($resting);
    }
    if ($sleep) {
        $summary['avg_sleep_hours'] = array_sum($sleep) / count($sleep);
    }
    if ($battery) {
        $summary['avg_battery_percent'] = array_sum($battery) / count($battery);
    }

    return $summary;
}

function gpWearableRecordEvent(PDO $pdo, int $userId, array $data): int
{
    if (!gpWearableIntegrationsTableReady($pdo)) {
        throw new RuntimeException('Wearable sync storage has not been deployed yet.');
    }
    gpWearableEnsureEventColumns($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO wearable_sync_events
        (user_id, dog_id, source, device_name, recorded_for_date, steps, active_minutes, rest_minutes, play_minutes, distance_miles, avg_heart_rate, resting_heart_rate, sleep_hours, battery_percent, summary_text, notes, raw_payload)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        isset($data['rest_minutes']) && $data['rest_minutes'] !== '' ? (int) $data['rest_minutes'] : null,
        isset($data['play_minutes']) && $data['play_minutes'] !== '' ? (int) $data['play_minutes'] : null,
        isset($data['distance_miles']) && $data['distance_miles'] !== '' ? (float) $data['distance_miles'] : null,
        isset($data['avg_heart_rate']) && $data['avg_heart_rate'] !== '' ? (int) $data['avg_heart_rate'] : null,
        isset($data['resting_heart_rate']) && $data['resting_heart_rate'] !== '' ? (int) $data['resting_heart_rate'] : null,
        isset($data['sleep_hours']) && $data['sleep_hours'] !== '' ? (float) $data['sleep_hours'] : null,
        isset($data['battery_percent']) && $data['battery_percent'] !== '' ? (int) $data['battery_percent'] : null,
        trim((string) ($data['summary_text'] ?? '')) ?: null,
        trim((string) ($data['notes'] ?? '')) ?: null,
        trim((string) ($data['raw_payload'] ?? '')) ?: null,
    ]);

    return (int) $stmt->fetchColumn();
}
