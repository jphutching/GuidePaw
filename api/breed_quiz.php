<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/dog_breeds_catalog.php';

requireApiUser($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input      = json_decode(file_get_contents('php://input'), true) ?: [];
$size       = strtolower(trim((string) ($input['size']       ?? 'any')));
$energy     = strtolower(trim((string) ($input['energy']     ?? 'any')));
$purpose    = strtolower(trim((string) ($input['purpose']    ?? 'any')));
$experience = strtolower(trim((string) ($input['experience'] ?? 'any')));

$catalog = getDogBreedsCatalog();
$results = [];

foreach ($catalog as $breedName => $info) {
    $score    = 0;
    $traits   = strtolower(($info['traits']      ?? '') . ' ' . ($info['temperament'] ?? ''));
    $notes    = strtolower($info['notes']         ?? '');
    $combined = $traits . ' ' . $notes;

    // Size
    if ($size !== 'any') {
        $sizeMap = [
            'tiny'   => ['toy', 'tiny', 'small'],
            'small'  => ['small', 'toy', 'mini'],
            'medium' => ['medium', 'mid'],
            'large'  => ['large', 'standard', 'tall'],
            'giant'  => ['giant', 'great', 'large'],
        ];
        foreach (($sizeMap[$size] ?? []) as $kw) {
            if (str_contains($combined, $kw) || str_contains(strtolower($breedName), $kw)) {
                $score += 2;
                break;
            }
        }
    } else {
        $score += 1;
    }

    // Energy
    if ($energy !== 'any') {
        $energyMap = [
            'low'      => ['calm', 'gentle', 'steady', 'laid-back'],
            'moderate' => ['moderate', 'adaptable', 'balanced'],
            'high'     => ['energetic', 'active', 'athletic', 'intense'],
        ];
        foreach (($energyMap[$energy] ?? []) as $kw) {
            if (str_contains($combined, $kw)) { $score += 2; break; }
        }
    } else {
        $score += 1;
    }

    // Purpose
    if ($purpose !== 'any') {
        $purposeMap = [
            'mobility'  => ['mobility', 'balance', 'brace', 'large', 'tall', 'strong', 'powerful'],
            'emotional' => ['affectionate', 'gentle', 'emotional', 'calm', 'comforting', 'sensitive', 'sweet'],
            'alert'     => ['alert', 'attentive', 'responsive', 'focused', 'loyal'],
            'general'   => ['trainable', 'service', 'public access', 'biddable', 'eager to please'],
        ];
        foreach (($purposeMap[$purpose] ?? []) as $kw) {
            if (str_contains($combined, $kw)) { $score += 2; break; }
        }
    } else {
        $score += 1;
    }

    // Experience
    if ($experience === 'new') {
        foreach (['experienced hands', 'careful training', 'trained hands', 'needs strong', 'experienced handler'] as $kw) {
            if (str_contains($combined, $kw)) { $score -= 2; break; }
        }
        foreach (['friendly', 'eager to please', 'biddable', 'gentle', 'easy to train', 'soft temperament'] as $kw) {
            if (str_contains($combined, $kw)) { $score += 2; break; }
        }
    } elseif ($experience === 'experienced') {
        foreach (['intelligent', 'athletic', 'confident', 'driven', 'intense'] as $kw) {
            if (str_contains($combined, $kw)) { $score += 1; break; }
        }
    } else {
        $score += 1;
    }

    if ($score > 0) {
        $results[] = [
            'breed'       => $breedName,
            'group'       => (string) ($info['group']       ?? ''),
            'temperament' => (string) ($info['temperament'] ?? ''),
            'traits'      => (string) ($info['traits']      ?? ''),
            'notes'       => (string) ($info['notes']       ?? ''),
            'score'       => $score,
        ];
    }
}

usort($results, static fn($a, $b) => $b['score'] <=> $a['score']);

apiJson([
    'success'      => true,
    'matches'      => array_slice($results, 0, 10),
    'total_breeds' => count($catalog),
]);
