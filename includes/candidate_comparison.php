<?php
declare(strict_types=1);

require_once __DIR__ . '/candidate_scoring.php';

function gpCandidateComparisonTableReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'dog_candidate_assessments'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpCandidateComparisonResolveDogIds(array $dogs, ?int $activeDogId, array $requestedDogIds = [], int $limit = 4): array
{
    $selected = [];
    $seen = [];

    foreach ($requestedDogIds as $dogId) {
        $dogId = (int) $dogId;
        if ($dogId <= 0 || isset($seen[$dogId])) {
            continue;
        }
        $selected[] = $dogId;
        $seen[$dogId] = true;
        if (count($selected) >= $limit) {
            return $selected;
        }
    }

    if (!$selected && $activeDogId !== null && $activeDogId > 0) {
        $selected[] = $activeDogId;
        $seen[$activeDogId] = true;
    }

    foreach ($dogs as $dog) {
        $dogId = (int) ($dog['id'] ?? 0);
        if ($dogId <= 0 || isset($seen[$dogId])) {
            continue;
        }
        $selected[] = $dogId;
        $seen[$dogId] = true;
        if (count($selected) >= $limit) {
            break;
        }
    }

    return $selected;
}

function gpCandidateComparisonRows(PDO $pdo, int $userId, array $dogIds): array
{
    if (!gpCandidateComparisonTableReady($pdo) || !$dogIds) {
        return [];
    }

    $dogsById = [];
    $dogsStmt = $pdo->prepare("SELECT id, name, breed FROM dogs WHERE owner_user_id = ? ORDER BY name");
    $dogsStmt->execute([$userId]);
    foreach ($dogsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dog) {
        $dogsById[(int) $dog['id']] = $dog;
    }

    $rows = [];
    foreach ($dogIds as $dogId) {
        $dogId = (int) $dogId;
        if (!isset($dogsById[$dogId])) {
            continue;
        }

        $assessment = gpLatestCandidateAssessment($pdo, $userId, $dogId);
        $row = $dogsById[$dogId];
        $row['assessment'] = $assessment ?: null;
        $row['has_assessment'] = (bool) $assessment;
        $row['average_score'] = $assessment ? round(averageCandidateScore($assessment), 1) : null;
        $row['focus_label'] = $assessment ? ('Focus Level ' . (string) ($assessment['focus_level_recommended'] ?? '—')) : 'No assessment yet';
        $row['recommended_path'] = $assessment['recommendation'] ?? null;
        $row['assessed_at'] = $assessment['created_at'] ?? null;
        $rows[] = $row;
    }

    return $rows;
}
