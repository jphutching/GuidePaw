<?php
declare(strict_types=1);

require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/public_contact_defaults.php';

function gpEnsureFoundDogReportsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS found_dog_reports (
        id SERIAL PRIMARY KEY,
        dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
        finder_location TEXT,
        finder_latitude DECIMAL(10,7),
        finder_longitude DECIMAL(10,7),
        finder_accuracy_m INTEGER,
        finder_name TEXT,
        finder_phone TEXT,
        finder_message TEXT,
        status TEXT NOT NULL DEFAULT 'new',
        ip_hash TEXT,
        user_agent TEXT,
        notification_sent BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}

function gpFoundDogColumnExists(PDO $pdo, string $table, string $column): bool
{
    return gpPublicContactColumnExists($pdo, $table, $column);
}

function gpFoundDogFlag(string $key, bool $fallback = false): bool
{
    $value = strtolower(trim((string) gpEnv($key, $fallback ? 'true' : 'false')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function gpFoundDogFetchPublicDog(PDO $pdo, int $dogId): ?array
{
    $possibleColumns = [
        'id', 'user_id', 'owner_user_id', 'name', 'breed', 'access_role', 'chip_number', 'microchip_id',
        'chip_registry', 'microchip_registry', 'handler_name', 'handler_phone', 'handler_email',
        'backup_contact_name', 'backup_contact_phone', 'found_dog_instructions', 'profile_photo_url',
        'photo_url', 'handler_photo_url', 'public_notes', 'emergency_notes'
    ];
    $columns = [];
    foreach ($possibleColumns as $column) {
        if ($column === 'id' || gpFoundDogColumnExists($pdo, 'dogs', $column)) {
            $columns[] = $column;
        }
    }
    $sql = 'SELECT ' . implode(', ', array_map(static fn($c) => '"' . str_replace('"', '""', $c) . '"', $columns ?: ['id'])) . ' FROM dogs WHERE id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dogId]);
    $dog = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dog) {
        return null;
    }

    $owner = gpFetchUserPublicContact($pdo, gpDogOwnerIdFromPublicDog($dog));
    $dog['owner_username'] = $owner['username'] ?? '';
    $dog['owner_email'] = $owner['email'] ?? '';
    $dog['_owner_public_contact'] = $owner;
    $dog['_public_contact_defaults'] = gpDogPublicContactDefaults($pdo, $dog, $owner);
    return $dog;
}

function gpFoundDogMapUrl(?string $lat, ?string $lng, string $location): string
{
    if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
    }
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($location);
}

function gpFoundDogTelegramText(array $dog, array $report, string $mapUrl): string
{
    $contact = $dog['_public_contact_defaults'] ?? [];
    $dogName = (string) ($dog['name'] ?? 'Service Dog');
    $location = (string) ($report['finder_location'] ?? 'Not provided');
    $phone = (string) ($report['finder_phone'] ?? 'Not provided');
    $name = trim((string) ($report['finder_name'] ?? ''));
    $handler = trim((string) ($contact['handler_name'] ?? ''));
    $text = "🐾 Found dog location report\n\nDog: {$dogName}\nLocation: {$location}\nFinder phone: {$phone}";
    if ($name !== '') {
        $text .= "\nFinder: {$name}";
    }
    if ($handler !== '') {
        $text .= "\nHandler: {$handler}";
    }
    return $text . "\nMap: {$mapUrl}";
}

function gpSendFoundDogTelegram(array $dog, array $report, string $mapUrl): bool
{
    if (!gpFoundDogFlag('FOUND_DOG_NOTIFY_TELEGRAM_ENABLED', gpFoundDogFlag('BETA_NOTIFY_TELEGRAM_ENABLED', false))) {
        return false;
    }

    $token = trim((string) gpEnv('TELEGRAM_BOT_TOKEN', ''));
    $chatId = trim((string) gpEnv('TELEGRAM_CHAT_ID', ''));
    if ($token === '' || $chatId === '') {
        error_log('GuidePaw found dog Telegram enabled but TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is missing.');
        return false;
    }

    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => gpFoundDogTelegramText($dog, $report, $mapUrl),
        'disable_web_page_preview' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return false;
    }

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log('GuidePaw found dog Telegram notification failed: ' . ($curlError ?: ('HTTP ' . $httpCode . ' ' . substr((string) $response, 0, 300))));
        return false;
    }
    return true;
}

function gpNotifyFoundDogReport(PDO $pdo, int $reportId): bool
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM found_dog_reports WHERE id = ? LIMIT 1');
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            return false;
        }

        $dog = gpFoundDogFetchPublicDog($pdo, (int) $report['dog_id']);
        if (!$dog) {
            return false;
        }

        $contact = $dog['_public_contact_defaults'] ?? gpDogPublicContactDefaults($pdo, $dog);
        $dogName = (string) ($dog['name'] ?? 'Service Dog');
        $location = (string) ($report['finder_location'] ?? 'Not provided');
        $lat = isset($report['finder_latitude']) ? (string) $report['finder_latitude'] : '';
        $lng = isset($report['finder_longitude']) ? (string) $report['finder_longitude'] : '';
        $accuracy = (string) ($report['finder_accuracy_m'] ?? '');
        $mapUrl = gpFoundDogMapUrl($lat, $lng, $location);
        $adminUrl = rtrim((string) gpEnv('APP_URL', 'https://beta.guidepaw.app'), '/') . '/admin_found_dog_reports.php';
        $message = trim((string) ($report['finder_message'] ?? ''));

        $body = "A location report was submitted for {$dogName}.\n\n" .
            "Handler: " . (string) ($contact['handler_name'] ?? 'Not provided') . "\n" .
            "Location / cross street: {$location}\n" .
            "Map: {$mapUrl}\n";
        if ($lat !== '' && $lng !== '') {
            $body .= "GPS: {$lat}, {$lng}" . ($accuracy !== '' ? " ±{$accuracy}m" : '') . "\n";
        }
        $body .= "\nFinder name: " . (string) ($report['finder_name'] ?? 'Not provided') . "\n" .
            "Finder phone: " . (string) ($report['finder_phone'] ?? 'Not provided') . "\n" .
            "Submitted: " . (string) ($report['created_at'] ?? date('Y-m-d H:i:s')) . "\n";
        if ($message !== '') {
            $body .= "\nMessage:\n{$message}\n";
        }
        $body .= "\nReview reports: {$adminUrl}\n\nGuidePaw found dog notification\n";

        $recipients = [];
        foreach ([$contact['handler_email'] ?? '', $dog['owner_email'] ?? '', gpEnv('ADMIN_NOTIFY_EMAIL', gpEnv('ADMIN_EMAIL', 'admin@guidepaw.app'))] as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = true;
            }
        }

        $sent = false;
        if (gpFoundDogFlag('FOUND_DOG_NOTIFY_EMAIL_ENABLED', true)) {
            foreach (array_keys($recipients) as $email) {
                try {
                    $sent = gpSendMail($email, 'GuidePaw found dog location report: ' . $dogName, $body) || $sent;
                } catch (Throwable $e) {
                    error_log('GuidePaw found dog email notification failed: ' . $e->getMessage());
                }
            }
        }

        $sent = gpSendFoundDogTelegram($dog, $report, $mapUrl) || $sent;

        $stmt = $pdo->prepare('UPDATE found_dog_reports SET notification_sent = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$sent ? 1 : 0, $reportId]);
        return $sent;
    } catch (Throwable $e) {
        error_log('GuidePaw found dog notification failed: ' . $e->getMessage());
        return false;
    }
}
