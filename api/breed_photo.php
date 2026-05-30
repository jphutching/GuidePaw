<?php
/**
 * Public endpoint — no auth token required (breed data is public).
 * GET /api/breed_photo.php?breed=Labrador+Retriever
 * Returns: {"url": "https://...jpg"} or {"url": null}
 *
 * Checks breed_images cache first; fetches from Dog CEO API on first request.
 * Returns {"url": null} gracefully when: feature disabled, breed not mapped,
 * Dog CEO unavailable, or migration not yet applied.
 */
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/breed_photos.php';

header('Cache-Control: public, max-age=86400');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiJson(['url' => null], 405);
}

$breedName    = trim((string) ($_GET['breed'] ?? ''));
$colorVariant = trim((string) ($_GET['variant'] ?? ''));

if ($breedName === '') {
    apiJson(['url' => null], 400);
}

if (!featureEnabled($pdo, 'breed_photos_enabled')) {
    apiJson(['url' => null]);
}

if (!tableExists($pdo, 'breed_images')) {
    apiJson(['url' => null]);
}

// Serve from cache
$stmt = $pdo->prepare('SELECT image_url FROM breed_images WHERE breed_name = ? AND color_variant = ? LIMIT 1');
$stmt->execute([$breedName, $colorVariant]);
$cached = $stmt->fetchColumn();
if ($cached !== false) {
    apiJson(['url' => $cached !== '' ? $cached : null]);
}

// Not cached — look up Dog CEO slug
$slug = breedPhotoSlug($breedName);
if ($slug === null) {
    // Cache negative result so we don't keep trying
    $ins = $pdo->prepare('INSERT INTO breed_images (breed_name, color_variant, image_url, source) VALUES (?, ?, \'\', \'not_mapped\') ON CONFLICT (breed_name, color_variant) DO NOTHING');
    $ins->execute([$breedName, $colorVariant]);
    apiJson(['url' => null]);
}

// Fetch from Dog CEO API
$apiUrl = 'https://dog.ceo/api/breed/' . $slug . '/images/random';
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app)',
    CURLOPT_FOLLOWLOCATION => true,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$imageUrl = '';
if ($raw !== false && $code === 200) {
    $data = json_decode($raw, true);
    if (isset($data['status'], $data['message']) && $data['status'] === 'success' && is_string($data['message'])) {
        $imageUrl = $data['message'];
    }
}

// Cache result (empty string = tried but unavailable)
$ins = $pdo->prepare('INSERT INTO breed_images (breed_name, color_variant, image_url, source) VALUES (?, ?, ?, \'dog_ceo\') ON CONFLICT (breed_name, color_variant) DO UPDATE SET image_url = EXCLUDED.image_url, fetched_at = CURRENT_TIMESTAMP');
$ins->execute([$breedName, $colorVariant, $imageUrl]);

apiJson(['url' => $imageUrl !== '' ? $imageUrl : null]);
