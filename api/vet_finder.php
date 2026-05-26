<?php
require_once __DIR__ . '/../includes/api_auth.php';

requireApiUser($pdo);

$apiKey = trim((string) gpEnv('GOOGLE_PLACES_API_KEY', ''));
if ($apiKey === '') {
    apiJson(['success' => false, 'message' => 'Vet finder not configured (missing API key).'], 503);
}

$lat         = isset($_GET['lat'])          ? (float)  $_GET['lat']          : null;
$lng         = isset($_GET['lng'])          ? (float)  $_GET['lng']          : null;
$radiusMiles = isset($_GET['radius_miles']) ? (int)    $_GET['radius_miles'] : 50;
$destination = isset($_GET['destination'])  ? trim((string) $_GET['destination']) : '';
$afterHours  = !empty($_GET['after_hours']);

if ($lat === null || $lng === null) {
    apiJson(['success' => false, 'message' => 'lat and lng are required.'], 422);
}

$radiusMiles = max(5, min(100, $radiusMiles));
$radiusMeters = $radiusMiles * 1609;

// ── Helpers ────────────────────────────────────────────────────────────────

function gpVetCurlGet(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function gpVetDistanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthMiles = 3958.8;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return (float) ($earthMiles * 2 * asin(sqrt($a)));
}

function gpVetHoursToday(array $place): string
{
    $periods = $place['opening_hours']['periods'] ?? null;
    if (!$periods) {
        return $place['opening_hours']['weekday_text'][date('N') - 1] ?? '';
    }
    $todayDow = (int) date('w');
    foreach ($periods as $p) {
        if (($p['open']['day'] ?? -1) === $todayDow) {
            $open  = $p['open']['time']  ?? '';
            $close = $p['close']['time'] ?? '';
            if ($open === '0000' && $close === '') return '24 hours';
            if ($open && $close) {
                return gpVetFmtTime($open) . ' – ' . gpVetFmtTime($close);
            }
        }
    }
    return '';
}

function gpVetFmtTime(string $hhmm): string
{
    if (strlen($hhmm) !== 4) return $hhmm;
    $h = (int) substr($hhmm, 0, 2);
    $m = substr($hhmm, 2, 2);
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12  = $h % 12 ?: 12;
    return $m === '00' ? "$h12 $ampm" : "$h12:$m $ampm";
}

function gpVetIs24hr(array $place): bool
{
    $periods = $place['opening_hours']['periods'] ?? [];
    foreach ($periods as $p) {
        if (($p['open']['time'] ?? '') === '0000' && !isset($p['close'])) return true;
        if (($p['open']['time'] ?? '') === '0000' && ($p['close']['time'] ?? '') === '') return true;
    }
    $text = strtolower(implode(' ', $place['opening_hours']['weekday_text'] ?? []));
    return str_contains($text, '24 hours') || str_contains($text, 'open 24');
}

function gpVetIsEmergency(array $place): bool
{
    $haystack = strtolower(($place['name'] ?? '') . ' ' . implode(' ', $place['types'] ?? []));
    return str_contains($haystack, 'emergency') || str_contains($haystack, '24') || gpVetIs24hr($place);
}

function gpVetFetchDetails(string $placeId, string $apiKey): array
{
    $fields = 'formatted_phone_number,opening_hours,website';
    $url    = "https://maps.googleapis.com/maps/api/place/details/json?place_id=" . urlencode($placeId) . "&fields=" . urlencode($fields) . "&key=" . urlencode($apiKey);
    $data   = gpVetCurlGet($url);
    return $data['result'] ?? [];
}

function gpVetNearbySearch(float $lat, float $lng, int $radius, string $apiKey, bool $emergencyOnly): ?array
{
    $keyword  = $emergencyOnly ? 'emergency veterinary animal hospital' : 'veterinary animal hospital clinic';
    $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json"
         . "?location=" . urlencode("$lat,$lng")
         . "&radius=$radius"
         . "&keyword=" . urlencode($keyword)
         . "&key=" . urlencode($apiKey);
    $data = gpVetCurlGet($url);
    return ($data && $data['status'] === 'OK') ? ($data['results'] ?? []) : [];
}

function gpVetBuildResult(array $place, array $details, float $fromLat, float $fromLng, string $legLabel): array
{
    $placeLat = (float) ($place['geometry']['location']['lat'] ?? 0);
    $placeLng = (float) ($place['geometry']['location']['lng'] ?? 0);
    $merged = array_merge($place, $details);
    return [
        'place_id'           => (string) ($place['place_id'] ?? ''),
        'name'               => (string) ($place['name'] ?? ''),
        'address'            => (string) ($place['vicinity'] ?? $place['formatted_address'] ?? ''),
        'phone'              => (string) ($details['formatted_phone_number'] ?? ''),
        'rating'             => isset($place['rating']) ? (float) $place['rating'] : null,
        'user_ratings_total' => (int) ($place['user_ratings_total'] ?? 0),
        'lat'                => $placeLat,
        'lng'                => $placeLng,
        'distance_miles'     => round(gpVetDistanceMiles($fromLat, $fromLng, $placeLat, $placeLng), 1),
        'open_now'           => isset($place['opening_hours']['open_now']) ? (bool) $place['opening_hours']['open_now'] : null,
        'hours_today'        => gpVetHoursToday($merged),
        'is_emergency'       => gpVetIsEmergency($merged),
        'is_24hr'            => gpVetIs24hr($merged),
        'leg_label'          => $legLabel,
        'website'            => (string) ($details['website'] ?? ''),
    ];
}

// ── Cache ──────────────────────────────────────────────────────────────────

$cacheKey  = md5("$lat,$lng,$radiusMiles,$destination,$afterHours");
$cachePath = sys_get_temp_dir() . "/gpvet_$cacheKey.json";
if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 1800) {
    $cached = json_decode(file_get_contents($cachePath), true);
    if (is_array($cached)) {
        apiJson($cached);
    }
}

// ── Search ─────────────────────────────────────────────────────────────────

$searchPoints = [['lat' => $lat, 'lng' => $lng, 'label' => 'Near you', 'radius' => $radiusMeters]];
$routeDestination = '';

if ($destination !== '') {
    $geoUrl  = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($destination) . "&key=" . urlencode($apiKey);
    $geoData = gpVetCurlGet($geoUrl);
    if ($geoData && $geoData['status'] === 'OK') {
        $destLoc = $geoData['results'][0]['geometry']['location'] ?? null;
        if ($destLoc) {
            $destLat = (float) $destLoc['lat'];
            $destLng = (float) $destLoc['lng'];
            $routeDestination = (string) ($geoData['results'][0]['formatted_address'] ?? $destination);
            $totalMiles = gpVetDistanceMiles($lat, $lng, $destLat, $destLng);
            $midLat = ($lat + $destLat) / 2;
            $midLng = ($lng + $destLng) / 2;
            $midMiles = round($totalMiles / 2);
            if ($totalMiles > 60) {
                $searchPoints[] = ['lat' => $midLat, 'lng' => $midLng, 'label' => "~{$midMiles}mi ahead", 'radius' => 40000];
            }
            $searchPoints[] = ['lat' => $destLat, 'lng' => $destLng, 'label' => 'Near destination', 'radius' => 40000];
        }
    }
}

$seen    = [];
$results = [];

foreach ($searchPoints as $point) {
    $raw = gpVetNearbySearch((float) $point['lat'], (float) $point['lng'], (int) $point['radius'], $apiKey, $afterHours);
    if (!$raw) continue;

    usort($raw, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));
    $batch = array_slice($raw, 0, 8);

    foreach ($batch as $place) {
        $pid = $place['place_id'] ?? '';
        if (!$pid || isset($seen[$pid])) continue;
        $seen[$pid] = true;
        $details = gpVetFetchDetails($pid, $apiKey);
        $results[] = gpVetBuildResult($place, $details, $lat, $lng, (string) $point['label']);
    }
}

// Sort: emergency first within each leg, then by distance
usort($results, function (array $a, array $b): int {
    $legOrder = ['Near you' => 0, 'Near destination' => 2];
    $aLeg = $legOrder[$a['leg_label']] ?? 1;
    $bLeg = $legOrder[$b['leg_label']] ?? 1;
    if ($aLeg !== $bLeg) return $aLeg <=> $bLeg;
    if ($a['is_emergency'] !== $b['is_emergency']) return $b['is_emergency'] <=> $a['is_emergency'];
    return $a['distance_miles'] <=> $b['distance_miles'];
});

if ($afterHours) {
    $results = array_values(array_filter($results, fn($r) => $r['is_emergency'] || $r['is_24hr'] || $r['open_now']));
}

$payload = [
    'success'           => true,
    'search_type'       => $routeDestination ? 'route' : 'local',
    'route_destination' => $routeDestination,
    'result_count'      => count($results),
    'vets'              => $results,
];

@file_put_contents($cachePath, json_encode($payload));
apiJson($payload);
