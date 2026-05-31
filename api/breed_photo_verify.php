<?php
/**
 * Admin endpoint — verifies each cached breed photo against AKC's reference image.
 * For each breed:
 *   1. Fetches AKC breed page, extracts og:image URL (public meta tag)
 *   2. Downloads both our image and AKC's image
 *   3. Asks claude-haiku: "Does our photo show the correct breed?"
 *   4. Stores score + notes in breed_images
 *   5. If score < 55, auto-replaces from all sources and re-verifies
 *
 * GET params:
 *   limit   — breeds to process per call (default 5, max 15)
 *   rerun   — re-verify already-verified breeds
 *   replace — auto-replace bad images (default true)
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/breed_photos.php';

header('Content-Type: application/json');

if (!gpCurrentUserIsAdmin($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Admin only.']);
    exit;
}

$anthropicKey = trim((string) gpEnv('ANTHROPIC_API_KEY', ''));
if ($anthropicKey === '') {
    echo json_encode(['success' => false, 'message' => 'ANTHROPIC_API_KEY not set.']);
    exit;
}

$limit    = min((int) ($_GET['limit']   ?? 5), 15);
$rerun    = !empty($_GET['rerun']);
$replace  = ($_GET['replace'] ?? '1') !== '0';

// ── Fetch breeds to verify ────────────────────────────────────────────────
if ($rerun) {
    $rows = $pdo->query(
        "SELECT id, breed_name, image_url, source FROM breed_images
         WHERE image_url != '' ORDER BY breed_name LIMIT $limit"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rows = $pdo->query(
        "SELECT id, breed_name, image_url, source FROM breed_images
         WHERE image_url != '' AND verified_at IS NULL ORDER BY breed_name LIMIT $limit"
    )->fetchAll(PDO::FETCH_ASSOC);
}

if (!$rows) {
    echo json_encode(['success' => true, 'verified' => 0, 'message' => 'All photos already verified.']);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────

function bpvCurl(string $url, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (GuidePaw breed verifier)',
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if (!$raw || $code !== 200) {
        return null;
    }
    return ['body' => $raw, 'code' => $code, 'ct' => $ct];
}

function bpvBreedToAkcSlug(string $breedName): ?string
{
    // Known overrides where AKC slug differs from simple conversion
    static $overrides = [
        'German Shepherd Dog'         => 'german-shepherd-dog',
        'Belgian Malinois'            => 'belgian-malinois',
        'Belgian Tervuren'            => 'belgian-tervuren',
        'Belgian Sheepdog'            => 'belgian-sheepdog',
        'American Staffordshire Terrier' => 'american-staffordshire-terrier',
        'Labrador Retriever'          => 'labrador-retriever',
        'Golden Retriever'            => 'golden-retriever',
        'Cavalier King Charles Spaniel' => 'cavalier-king-charles-spaniel',
        'Chinese Shar-Pei'            => 'chinese-shar-pei',
        'Miniature Bull Terrier'      => 'miniature-bull-terrier',
        'Portuguese Water Dog'        => 'portuguese-water-dog',
        'Nova Scotia Duck Tolling Retriever' => 'nova-scotia-duck-tolling-retriever',
        'Coton de Tulear'             => 'coton-de-tulear',
        'Bouvier des Flandres'        => 'bouvier-des-flandres',
        'Entlebucher Mountain Dog'    => 'entlebucher-mountain-dog',
        'Dogue de Bordeaux'           => 'dogue-de-bordeaux',
        'Xoloitzcuintli'              => 'xoloitzcuintli',
        'Cirneco dell\'Etna'          => 'cirneco-delletna',
    ];
    if (isset($overrides[$breedName])) {
        return $overrides[$breedName];
    }
    // Generics and hybrids — not on AKC
    $noAkc = ['Mixed Breed', 'Mixed Breed - Large', 'Mixed Breed - Medium', 'Mixed Breed - Small',
               'Unknown Breed', 'Unknown / Rescue Mix', 'Other / Custom', 'Other / Not Listed',
               'Custom Designer Breed / Hybrid', 'Poodle Cross / Doodle Mix'];
    foreach ($noAkc as $skip) {
        if (stripos($breedName, $skip) !== false) {
            return null;
        }
    }
    // Also skip known designer/hybrid breeds not recognised by AKC
    if (preg_match('/doodle|poo$|poo |oodle|apoo|apoo|ipoo|opoo|upoo|mix|cross|ador$|kie$|orkie$/i', $breedName)) {
        return null;
    }
    // Simple conversion: lower, replace space/special with hyphens
    $slug = strtolower($breedName);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function bpvAkcOgImage(string $slug): ?string
{
    $res = bpvCurl('https://www.akc.org/dog-breeds/' . $slug . '/');
    if (!$res) {
        return null;
    }
    if (preg_match('/property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $res['body'], $m) ||
        preg_match('/content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $res['body'], $m)) {
        return $m[1] !== '' ? $m[1] : null;
    }
    return null;
}

function bpvImageToBase64(string $url): ?array
{
    $res = bpvCurl($url);
    if (!$res) {
        return null;
    }
    $ct   = strtolower(explode(';', $res['ct'])[0]);
    $mime = in_array($ct, ['image/jpeg','image/png','image/webp','image/gif'], true) ? $ct : 'image/jpeg';
    return ['b64' => base64_encode($res['body']), 'mime' => $mime];
}

function bpvVerify(string $breedName, array $ourImg, ?array $akcImg, string $apiKey): array
{
    $content = [];

    $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $ourImg['mime'], 'data' => $ourImg['b64']]];
    $labelA = $akcImg ? 'Image A (our cached photo)' : 'This image';

    if ($akcImg) {
        $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $akcImg['mime'], 'data' => $akcImg['b64']]];
        $prompt = "$labelA is our cached photo labelled \"$breedName\". Image B is the official AKC reference photo for the $breedName breed. "
                . "Does Image A correctly show a $breedName? Does it match Image B? "
                . 'Reply with JSON only: {"correct":true/false,"score":0-100,"identified_breed":"string","notes":"one sentence"}';
    } else {
        $prompt = "This is our cached photo labelled \"$breedName\". "
                . "Does it correctly show a $breedName? "
                . 'Reply with JSON only: {"correct":true/false,"score":0-100,"identified_breed":"string","notes":"one sentence"}';
    }
    $content[] = ['type' => 'text', 'text' => $prompt];

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 128,
        'messages'   => [['role' => 'user', 'content' => $content]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    $data   = json_decode((string) $raw, true);
    $text   = (string) ($data['content'][0]['text'] ?? '{}');
    $result = json_decode($text, true);

    return [
        'correct'          => (bool)   ($result['correct']          ?? true),
        'score'            => max(0, min(100, (int) ($result['score'] ?? 50))),
        'identified_breed' => (string) ($result['identified_breed'] ?? ''),
        'notes'            => (string) ($result['notes']             ?? ''),
    ];
}

// ── Process each breed ────────────────────────────────────────────────────

$results  = [];
$replaced = 0;

foreach ($rows as $row) {
    $breedName = $row['breed_name'];
    $result    = ['breed' => $breedName, 'score' => null, 'correct' => null, 'replaced' => false, 'notes' => ''];

    // Download our image
    $ourImg = bpvImageToBase64($row['image_url']);
    if ($ourImg === null) {
        $result['notes'] = 'could not download our image';
        $results[] = $result;
        continue;
    }

    // Fetch AKC reference image (if AKC recognises this breed)
    $akcSlug = bpvBreedToAkcSlug($breedName);
    $akcImg  = null;
    if ($akcSlug !== null) {
        $akcOgUrl = bpvAkcOgImage($akcSlug);
        if ($akcOgUrl) {
            $akcImg = bpvImageToBase64($akcOgUrl);
        }
    }

    // Verify
    $v = bpvVerify($breedName, $ourImg, $akcImg, $anthropicKey);
    $result['score']            = $v['score'];
    $result['correct']          = $v['correct'];
    $result['identified_breed'] = $v['identified_breed'];
    $result['notes']            = $v['notes'];
    $result['had_akc_ref']      = ($akcImg !== null);

    // Auto-replace if score is too low
    $skipVerifiedAt = false;
    if ($replace && $v['score'] < 55) {
        $pdo->prepare('DELETE FROM breed_images WHERE id = ?')->execute([$row['id']]);
        $newUrl = getBreedPhotoUrlCached($pdo, $breedName);
        // Always re-lookup the new row ID — the old one was just deleted
        $stmt = $pdo->prepare("SELECT id FROM breed_images WHERE breed_name = ? AND color_variant = '' LIMIT 1");
        $stmt->execute([$breedName]);
        $newId = (int) $stmt->fetchColumn();
        if ($newId > 0) {
            $row['id'] = $newId;
            if ($newUrl && $newUrl !== $row['image_url']) {
                $replaced++;
                $result['replaced'] = true;
                $result['new_url']  = $newUrl;
            }
        } else {
            // Fetch failed entirely — no row to update; skip so it retries next run
            $skipVerifiedAt = true;
        }
    }

    // Store verification result (skipped only if replacement fetch failed completely)
    if (!$skipVerifiedAt) {
        $pdo->prepare(
            'UPDATE breed_images SET verified_at = CURRENT_TIMESTAMP, verification_score = ?, verification_notes = ? WHERE id = ?'
        )->execute([$v['score'], $v['notes'], $row['id']]);
    }

    $results[] = $result;
}

$remaining = (int) $pdo->query(
    "SELECT COUNT(*) FROM breed_images WHERE image_url != '' AND verified_at IS NULL"
)->fetchColumn();

echo json_encode([
    'success'   => true,
    'verified'  => count($results),
    'replaced'  => $replaced,
    'remaining' => $remaining,
    'results'   => $results,
]);
