<?php
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$apiKey = trim((string) gpEnv('GOOGLE_PLACES_API_KEY', ''));
if ($apiKey === '') {
    echo json_encode(['success' => false, 'message' => 'Places not configured.']);
    exit;
}

$allowedTypes = ['veterinary_care', 'park', 'restaurant'];
$type = isset($_GET['type']) ? (string) $_GET['type'] : 'veterinary_care';
if (!in_array($type, $allowedTypes, true)) {
    $type = 'veterinary_care';
}

$lat    = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
$lng    = isset($_GET['lng']) ? (float) $_GET['lng'] : null;
$q      = isset($_GET['q'])   ? trim((string) $_GET['q']) : '';
$radius = isset($_GET['radius']) ? min(max((int) $_GET['radius'], 1), 50) : 25;
$radiusMeters = $radius * 1609;

$keywords = [
    'veterinary_care' => '',
    'park'            => 'dog park',
    'restaurant'      => 'dog friendly',
];

function gpPlacesCurl(string $url): ?array
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
    if (!$raw) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function gpPlacesFormat(array $results): array
{
    $out = [];
    foreach ($results as $place) {
        $out[] = [
            'place_id' => (string) ($place['place_id'] ?? ''),
            'name'     => (string) ($place['name'] ?? ''),
            'address'  => (string) ($place['vicinity'] ?? $place['formatted_address'] ?? ''),
            'lat'      => (float)  ($place['geometry']['location']['lat'] ?? 0),
            'lng'      => (float)  ($place['geometry']['location']['lng'] ?? 0),
            'rating'   => isset($place['rating']) ? (float) $place['rating'] : null,
            'open_now' => (bool)   ($place['opening_hours']['open_now'] ?? false),
        ];
    }
    return $out;
}

if ($q !== '' && $q !== '') {
    // Text Search — finds by name/query with optional location bias
    $params = ['query' => $q . ' ' . str_replace('_', ' ', $type), 'key' => $apiKey];
    if ($lat !== null && $lng !== null) {
        $params['location'] = $lat . ',' . $lng;
        $params['radius']   = $radiusMeters;
    }
    $url  = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query($params);
    $data = gpPlacesCurl($url);
} elseif ($lat !== null && $lng !== null) {
    // Nearby Search
    $params = [
        'location' => $lat . ',' . $lng,
        'radius'   => $radiusMeters,
        'type'     => $type,
        'key'      => $apiKey,
    ];
    $kw = $keywords[$type] ?? '';
    if ($kw !== '') {
        $params['keyword'] = $kw;
    }
    $url  = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?' . http_build_query($params);
    $data = gpPlacesCurl($url);
} else {
    echo json_encode(['success' => false, 'message' => 'Provide lat+lng for nearby search, or q for text search.']);
    exit;
}

if ($data === null) {
    echo json_encode(['success' => false, 'message' => 'Places request failed.']);
    exit;
}

$status = (string) ($data['status'] ?? '');
if ($status === 'REQUEST_DENIED' || $status === 'INVALID_REQUEST') {
    echo json_encode(['success' => false, 'message' => 'Places API error: ' . ($data['error_message'] ?? $status)]);
    exit;
}

$results = gpPlacesFormat($data['results'] ?? []);
echo json_encode(['success' => true, 'count' => count($results), 'results' => $results]);
