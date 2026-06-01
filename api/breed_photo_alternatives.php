<?php
/**
 * Admin — fetch alternative photo URLs for a breed from all sources.
 * GET /api/breed_photo_alternatives.php?breed=Labrador+Retriever&skip=https://...
 * Returns: {"alternatives": [{"url":"...","source":"wikipedia"}, ...]}
 * Does NOT write to DB — caller chooses which URL to save.
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/breed_photos.php';

header('Content-Type: application/json');

if (!gpCurrentUserIsAdmin($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Admin only.']);
    exit;
}

$breed   = trim((string) ($_GET['breed'] ?? ''));
$skipUrl = trim((string) ($_GET['skip']  ?? ''));

if ($breed === '') {
    echo json_encode(['success' => false, 'message' => 'breed required.']);
    exit;
}

$alternatives = [];
$seen         = $skipUrl !== '' ? [$skipUrl => true] : [];

// ── Wikipedia ────────────────────────────────────────────────────────────────
$wt = breedWikipediaTitle($breed);
if ($wt !== null) {
    $url = fetchWikipediaPhoto($wt);
    if ($url !== '' && !isset($seen[$url])) {
        $alternatives[] = ['url' => $url, 'source' => 'wikipedia'];
        $seen[$url] = true;
    }
}

// ── Dog CEO ──────────────────────────────────────────────────────────────────
$slug = breedPhotoSlug($breed);
if ($slug !== null) {
    // Fetch 3 random images and include up to 2 different ones
    for ($i = 0; $i < 3 && count($alternatives) < 4; $i++) {
        $ch = curl_init('https://dog.ceo/api/breed/' . $slug . '/images/random');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'GuidePaw/1.0 (guidepaw.app)',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw !== false && $code === 200) {
            $data = json_decode($raw, true);
            $url  = (string) ($data['message'] ?? '');
            if ($url !== '' && !isset($seen[$url])) {
                $alternatives[] = ['url' => $url, 'source' => 'dog_ceo'];
                $seen[$url] = true;
            }
        }
    }
}

// ── Unsplash ─────────────────────────────────────────────────────────────────
$unsplashKey = trim((string) gpEnv('UNSPLASH_ACCESS_KEY', ''));
if ($unsplashKey !== '' && count($alternatives) < 5) {
    // Fetch page 2 so we get photos different from the cached one
    $apiUrl = 'https://api.unsplash.com/search/photos?' . http_build_query([
        'query'       => $breed . ' dog',
        'per_page'    => 5,
        'page'        => 2,
        'orientation' => 'squarish',
    ]);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER     => ['Authorization: Client-ID ' . $unsplashKey],
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code === 200) {
        $data = json_decode($raw, true);
        foreach (($data['results'] ?? []) as $photo) {
            $url = (string) ($photo['urls']['regular'] ?? '');
            if ($url !== '' && !isset($seen[$url])) {
                $alternatives[] = ['url' => $url, 'source' => 'unsplash'];
                $seen[$url] = true;
                if (count($alternatives) >= 5) break;
            }
        }
    }
}

// ── Pexels ───────────────────────────────────────────────────────────────────
$pexelsKey = trim((string) gpEnv('PEXELS_API_KEY', ''));
if ($pexelsKey !== '' && count($alternatives) < 5) {
    $apiUrl = 'https://api.pexels.com/v1/search?' . http_build_query([
        'query'    => $breed . ' dog',
        'per_page' => 5,
        'page'     => 2,
    ]);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $pexelsKey],
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code === 200) {
        $data = json_decode($raw, true);
        foreach (($data['photos'] ?? []) as $photo) {
            $url = (string) ($photo['src']['large'] ?? '');
            if ($url !== '' && !isset($seen[$url])) {
                $alternatives[] = ['url' => $url, 'source' => 'pexels'];
                $seen[$url] = true;
                if (count($alternatives) >= 5) break;
            }
        }
    }
}

echo json_encode(['success' => true, 'breed' => $breed, 'alternatives' => $alternatives]);
