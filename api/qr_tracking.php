<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/qr_tracking.php';
require_once __DIR__ . '/../includes/public_dog_profile_token.php';

$user  = requireApiUser($pdo);
$dogId = (int) ($user['active_dog_id'] ?? 0);

if ($dogId <= 0) {
    apiJson(['success' => true, 'dog_id' => null, 'dog_name' => null, 'public_url' => null, 'summary' => null]);
}

$dogStmt = $pdo->prepare('SELECT name FROM dogs WHERE id = ?');
$dogStmt->execute([$dogId]);
$dogName = (string) ($dogStmt->fetchColumn() ?: 'Dog');

// Build absolute public URL using known app base
$base      = rtrim((string) appEnv('APP_URL', 'https://guidepaw.app'), '/');
$token     = publicDogProfileToken($dogId);
$publicUrl = $base . '/public_dog_profile.php?dog=' . $dogId . '&token=' . $token;

$summary = gpDogQrTrackingSummary($pdo, $dogId);

// Simplify user_agent strings for mobile display
$recentViews = array_map(static function (array $row) use ($dogId): array {
    $ua = (string) ($row['user_agent'] ?? '');
    $device = 'Unknown';
    if (stripos($ua, 'iPhone') !== false)         $device = 'iPhone';
    elseif (stripos($ua, 'iPad') !== false)       $device = 'iPad';
    elseif (stripos($ua, 'Android') !== false)    $device = 'Android';
    elseif (stripos($ua, 'Windows') !== false)    $device = 'Windows PC';
    elseif (stripos($ua, 'Macintosh') !== false)  $device = 'Mac';
    elseif (stripos($ua, 'Linux') !== false)      $device = 'Linux';
    elseif ($ua === '')                           $device = 'Direct link';

    $browser = '';
    if (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false && stripos($ua, 'Edg') === false) $browser = 'Chrome';
    elseif (stripos($ua, 'Firefox') !== false)   $browser = 'Firefox';
    elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false)  $browser = 'Safari';
    elseif (stripos($ua, 'Edg') !== false)       $browser = 'Edge';

    return [
        'viewed_at'  => (string) ($row['viewed_at'] ?? ''),
        'device'     => $browser !== '' ? "$device / $browser" : $device,
        'referrer'   => (string) ($row['referrer'] ?? ''),
    ];
}, $summary['recent_views'] ?? []);

apiJson([
    'success'      => true,
    'dog_id'       => $dogId,
    'dog_name'     => $dogName,
    'public_url'   => $publicUrl,
    'total_views'  => (int) ($summary['total_views'] ?? 0),
    'last_viewed'  => $summary['last_viewed_at'] ?? null,
    'recent_views' => $recentViews,
]);
