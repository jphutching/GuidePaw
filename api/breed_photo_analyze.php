<?php
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

$limit  = min((int) ($_GET['limit'] ?? 10), 30);
$rerun  = !empty($_GET['rerun']); // re-analyze already-scored images

// ── Fetch images to analyze ───────────────────────────────────────────────
if ($rerun) {
    $rows = $pdo->query(
        "SELECT id, breed_name, image_url, source FROM breed_images
         WHERE image_url != '' ORDER BY breed_name LIMIT $limit"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rows = $pdo->query(
        "SELECT id, breed_name, image_url, source FROM breed_images
         WHERE image_url != '' AND analyzed_at IS NULL ORDER BY breed_name LIMIT $limit"
    )->fetchAll(PDO::FETCH_ASSOC);
}

if (!$rows) {
    echo json_encode(['success' => true, 'analyzed' => 0, 'message' => 'All cached images already analyzed.']);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────

function gpFetchImageBytes(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'GuidePaw/1.0',
    ]);
    $bytes = curl_exec($ch);
    $info  = curl_getinfo($ch);
    curl_close($ch);
    if (!$bytes || (int) $info['http_code'] !== 200) {
        return null;
    }
    $ct   = strtolower(explode(';', (string) $info['content_type'])[0]);
    $mime = in_array($ct, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $ct : 'image/jpeg';
    return ['bytes' => $bytes, 'mime' => $mime];
}

function gpScoreBreedImage(array $img, string $apiKey): int
{
    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 64,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $img['mime'], 'data' => base64_encode($img['bytes'])]],
                ['type' => 'text', 'text' => 'Rate 0-100: how completely is the full dog visible head-to-tail? 100=entire dog clear, 50=half cut off, 0=just face or no dog. JSON only: {"score":N}'],
            ],
        ]],
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
    return max(0, min(100, (int) ($result['score'] ?? 0)));
}

// ── Analyze each row ──────────────────────────────────────────────────────

$results = [];

foreach ($rows as $row) {
    $img = gpFetchImageBytes($row['image_url']);
    if ($img === null) {
        $results[] = ['breed' => $row['breed_name'], 'skipped' => true, 'reason' => 'image fetch failed'];
        continue;
    }

    $score    = gpScoreBreedImage($img, $anthropicKey);
    $bestUrl  = $row['image_url'];
    $bestScore = $score;

    // Try to find a better image from Dog CEO if score is poor
    if ($score < 60 && $row['source'] === 'dog_ceo') {
        $slug = breedPhotoSlug($row['breed_name']);
        if ($slug !== null) {
            $altCh = curl_init('https://dog.ceo/api/breed/' . $slug . '/images/random/5');
            curl_setopt_array($altCh, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
            $altRaw  = curl_exec($altCh);
            curl_close($altCh);
            $altData = json_decode((string) $altRaw, true);

            foreach (($altData['message'] ?? []) as $altUrl) {
                if ($altUrl === $row['image_url']) {
                    continue;
                }
                $altImg = gpFetchImageBytes($altUrl);
                if ($altImg === null) {
                    continue;
                }
                $altScore = gpScoreBreedImage($altImg, $anthropicKey);
                if ($altScore > $bestScore) {
                    $bestScore = $altScore;
                    $bestUrl   = $altUrl;
                }
                if ($bestScore >= 80) {
                    break;
                }
            }
        }
    }

    $pdo->prepare(
        'UPDATE breed_images SET image_url = ?, full_dog_score = ?, analyzed_at = CURRENT_TIMESTAMP WHERE id = ?'
    )->execute([$bestUrl, $bestScore, $row['id']]);

    $results[] = [
        'breed'         => $row['breed_name'],
        'original_score'=> $score,
        'best_score'    => $bestScore,
        'improved'      => $bestUrl !== $row['image_url'],
    ];
}

$remaining = (int) $pdo->query(
    "SELECT COUNT(*) FROM breed_images WHERE image_url != '' AND analyzed_at IS NULL"
)->fetchColumn();

echo json_encode([
    'success'   => true,
    'analyzed'  => count($results),
    'remaining' => $remaining,
    'results'   => $results,
]);
