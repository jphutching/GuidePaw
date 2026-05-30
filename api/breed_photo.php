<?php
/**
 * Public endpoint — no auth token required (breed data is public).
 * GET /api/breed_photo.php?breed=Labrador+Retriever
 * Returns: {"url": "https://...jpg"} or {"url": null}
 *
 * Checks breed_images cache first; fetches from Dog CEO then Wikipedia on miss.
 * Returns {"url": null} safely when: feature disabled, breed not mappable,
 * all sources unavailable, or migration not yet applied.
 */
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/feature_flags.php';
require_once __DIR__ . '/../includes/breed_photos.php';

header('Cache-Control: public, max-age=86400');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiJson(['url' => null], 405);
}

$breedName = trim((string) ($_GET['breed'] ?? ''));
if ($breedName === '') {
    apiJson(['url' => null], 400);
}

apiJson(['url' => getBreedPhotoUrlCached($pdo, $breedName)]);
