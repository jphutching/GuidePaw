<?php
declare(strict_types=1);

require_once __DIR__ . '/public_contact_defaults.php';

function gpEnsureDogQrTrackingTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS dog_qr_scan_events (
        id SERIAL PRIMARY KEY,
        dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
        viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_hash TEXT,
        user_agent TEXT,
        referrer TEXT,
        path TEXT
    )");
}

function gpDogQrTrackingTableReady(PDO $pdo): bool
{
    return tableExists($pdo, 'dog_qr_scan_events');
}

function gpLogDogQrScan(PDO $pdo, int $dogId): void
{
    if ($dogId <= 0 || !gpDogQrTrackingTableReady($pdo)) {
        return;
    }

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $secret = (string) appEnv('APP_QR_TRACKING_SECRET', appEnv('APP_SECRET', 'guidepaw-qr'));
    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . $secret) : null;
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $referrer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $path = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));

    $stmt = $pdo->prepare('INSERT INTO dog_qr_scan_events (dog_id, ip_hash, user_agent, referrer, path) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$dogId, $ipHash, $userAgent !== '' ? $userAgent : null, $referrer !== '' ? $referrer : null, $path !== '' ? $path : null]);
}

function gpDogQrTrackingSummary(PDO $pdo, int $dogId): array
{
    $summary = [
        'total_views' => 0,
        'last_viewed_at' => null,
        'recent_views' => [],
    ];

    if (!gpDogQrTrackingTableReady($pdo) || $dogId <= 0) {
        return $summary;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_views, MAX(viewed_at) AS last_viewed_at FROM dog_qr_scan_events WHERE dog_id = ?');
        $stmt->execute([$dogId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary['total_views'] = (int) ($row['total_views'] ?? 0);
        $summary['last_viewed_at'] = $row['last_viewed_at'] ?? null;

        $recentStmt = $pdo->prepare('SELECT viewed_at, user_agent, referrer, path FROM dog_qr_scan_events WHERE dog_id = ? ORDER BY viewed_at DESC, id DESC LIMIT 12');
        $recentStmt->execute([$dogId]);
        $summary['recent_views'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return $summary;
    }

    return $summary;
}
