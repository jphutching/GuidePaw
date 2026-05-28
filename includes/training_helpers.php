<?php

function getDogTrainingProfile(PDO $pdo, int $dogId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM dog_training_profiles WHERE dog_id = ? LIMIT 1");
    $stmt->execute([$dogId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getDogTrainingItems(PDO $pdo, int $dogId): array {
    $stmt = $pdo->prepare("SELECT * FROM dog_training_items WHERE dog_id = ? ORDER BY level ASC, category ASC, sort_order ASC, id ASC");
    $stmt->execute([$dogId]);
    return $stmt->fetchAll() ?: [];
}

function seedDogTrainingProgram(PDO $pdo, int $dogId, ?array $onlyCategories = null): int {
    $check = $pdo->prepare("SELECT COUNT(*) FROM dog_training_items WHERE dog_id = ?");
    $check->execute([$dogId]);
    if ($onlyCategories === null && (int) $check->fetchColumn() > 0) {
        return 0;
    }

    $template = getTrainingProgramTemplate();
    $existingStmt = $pdo->prepare("
        SELECT 1
        FROM dog_training_items
        WHERE dog_id = ? AND category = ? AND item_name = ? AND COALESCE(track_code, '') = COALESCE(?, '')
        LIMIT 1
    ");
    $stmt = $pdo->prepare("INSERT INTO dog_training_items (dog_id, category, track_code, level, item_name, description, sort_order) VALUES (?,?,?,?,?,?,?)");
    $inserted = 0;
    foreach ($template as $category => $items) {
        if ($onlyCategories !== null && !in_array($category, $onlyCategories, true)) {
            continue;
        }
        foreach ($items as $idx => $item) {
            $trackCode = $item['track'] ?? null;
            $existingStmt->execute([$dogId, $category, $item['item_name'], $trackCode]);
            if ($existingStmt->fetchColumn()) {
                continue;
            }
            $stmt->execute([
                $dogId,
                $category,
                $trackCode,
                (int) ($item['level'] ?? 1),
                $item['item_name'],
                $item['description'] ?? null,
                $idx + 1,
            ]);
            $inserted++;
        }
    }

    return $inserted;
}

function upsertDogTrainingProfile(PDO $pdo, int $dogId, int $userId, array $data): void {
    $existing = getDogTrainingProfile($pdo, $dogId);
    $fields = [
        $data['training_mode'] ?? 'self_training',
        $data['trainer_name'] ?? null,
        $data['business_name'] ?? null,
        $data['credentials'] ?? null,
        $data['trainer_phone'] ?? null,
        $data['trainer_email'] ?? null,
        $data['trainer_website'] ?? null,
        $data['handler_experience'] ?? null,
        $data['handler_goals'] ?? null,
        $data['candidate_stage'] ?? 'prospect',
        $data['candidate_notes'] ?? null,
        $data['training_focus'] ?? null,
        $data['notes'] ?? null,
    ];
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE dog_training_profiles SET training_mode=?, trainer_name=?, business_name=?, credentials=?, trainer_phone=?, trainer_email=?, trainer_website=?, handler_experience=?, handler_goals=?, candidate_stage=?, candidate_notes=?, training_focus=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE dog_id=?");
        $stmt->execute(array_merge($fields, [$dogId]));
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO dog_training_profiles (dog_id, created_by_user_id, training_mode, trainer_name, business_name, credentials, trainer_phone, trainer_email, trainer_website, handler_experience, handler_goals, candidate_stage, candidate_notes, training_focus, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute(array_merge([$dogId, $userId], $fields));
}

function getTrainingSuggestions(PDO $pdo, int $userId, int $dogId): array {
    if (!hasDogAccess($pdo, $userId, $dogId)) {
        return [];
    }

    $profile = getDogTrainingProfile($pdo, $dogId) ?: ['training_mode' => 'self_training', 'candidate_stage' => 'prospect'];
    $items = getDogTrainingItems($pdo, $dogId);
    $suggestions = [];
    if (!$items) {
        $suggestions[] = 'Add a training program to start getting personalized coaching suggestions.';
        return $suggestions;
    }

    $notStarted = array_values(array_filter($items, fn($item) => ($item['status'] ?? '') === 'not_started'));
    $inProgress = array_values(array_filter($items, fn($item) => ($item['status'] ?? '') === 'in_progress'));
    $needsProof = array_values(array_filter($items, fn($item) => ($item['status'] ?? '') === 'proofing'));
    $candidateItems = array_values(array_filter($items, fn($item) => ($item['track_code'] ?? '') === 'candidate_screen'));
    $candidateMastered = count(array_filter($candidateItems, fn($item) => ($item['status'] ?? '') === 'mastered'));
    $candidateStarted = count(array_filter($candidateItems, fn($item) => in_array(($item['status'] ?? ''), ['in_progress', 'proofing', 'mastered'], true)));

    $recentLogStmt = $pdo->prepare("SELECT focus_level, location_type, log_date FROM daily_logs WHERE dog_id = ? ORDER BY log_date DESC LIMIT 5");
    $recentLogStmt->execute([$dogId]);
    $recentLogs = $recentLogStmt->fetchAll() ?: [];
    $avgFocus = null;
    if ($recentLogs) {
        $avgFocus = array_sum(array_map(fn($r) => (int) $r['focus_level'], $recentLogs)) / count($recentLogs);
    }

    $candidateStage = $profile['candidate_stage'] ?? 'prospect';
    if (in_array($candidateStage, ['prospect', 'foundation'], true) && $candidateItems && $candidateMastered < 5) {
        $suggestions[] = 'Build the foundation first — focus on recovery, neutrality, and handling tolerance before adding specialized service tasks.';
    }
    if ($candidateItems && $candidateStarted === 0) {
        $suggestions[] = 'Start with one candidate-screen session this week so you know whether this dog is building the right raw material for service work.';
    }
    if (!empty($recentLogs) && $avgFocus !== null && $avgFocus < 3) {
        $suggestions[] = 'Recent focus is soft. Go back to short Level 1-2 reps like watch me, place, and loose-leash work before adding pressure.';
    }
    if ($notStarted) {
        $first = $notStarted[0];
        $suggestions[] = 'Next new skill: ' . $first['item_name'] . ' (' . $first['category'] . '). Keep sessions short and end on a win.';
    }
    if ($inProgress) {
        $first = $inProgress[0];
        $suggestions[] = 'Keep building ' . $first['item_name'] . ' in one easier setting and one real-world setting this week.';
    }
    if ($needsProof) {
        $first = $needsProof[0];
        $suggestions[] = 'Proof ' . $first['item_name'] . ' around higher distraction before marking it reliable.';
    }

    $trackCounts = [];
    foreach ($items as $item) {
        $track = $item['track_code'] ?? '';
        if (!$track) {
            continue;
        }
        $trackCounts[$track]['total'] = ($trackCounts[$track]['total'] ?? 0) + 1;
        if (($item['status'] ?? '') === 'mastered') {
            $trackCounts[$track]['mastered'] = ($trackCounts[$track]['mastered'] ?? 0) + 1;
        }
    }

    if (($trackCounts['akc_cgc']['mastered'] ?? 0) >= 8) {
        $suggestions[] = 'CGC track looks strong. Consider a mock AKC Canine Good Citizen run-through.';
    }
    if (($trackCounts['akc_cgca']['mastered'] ?? 0) >= 2 || ($trackCounts['akc_urban']['mastered'] ?? 0) >= 2) {
        $suggestions[] = 'Community and urban skills are coming along. Add one deliberate field trip to proof busier public behavior.';
    }
    if (($trackCounts['service_task']['mastered'] ?? 0) >= 2 && ($trackCounts['public_access_benchmark']['mastered'] ?? 0) < 2) {
        $suggestions[] = 'Task work is moving, but public access still needs reps. Pair one task drill with one calm public outing this week.';
    }

    if (($profile['training_mode'] ?? 'self_training') === 'self_training') {
        $suggestions[] = 'Self-training tip: film one short session this week and review timing, reward placement, and leash tension.';
        $suggestions[] = 'Self-training tip: work one skill in the cab, one at a stop, and one in a public space to avoid context-only learning.';
        $suggestions[] = 'Ad hoc self-training idea: run a 3-minute reset session with engagement, one easy win, one harder rep, then quit before fatigue.';
    } elseif (($profile['training_mode'] ?? '') === 'professional_trainer' && !empty($profile['trainer_name'])) {
        $suggestions[] = 'Trainer coordination: bring your log history and one focused question to ' . $profile['trainer_name'] . ' for the next session.';
        if (!empty($profile['business_name'])) {
            $suggestions[] = 'Professional trainer note: keep homework tied to ' . $profile['business_name'] . ' so between-session reps stay consistent.';
        }
    } else {
        $suggestions[] = 'Hybrid plan: let the trainer shape the hard pieces, then rehearse daily reps yourself between sessions.';
    }

    return array_slice(array_values(array_unique($suggestions)), 0, 8);
}
