<?php
declare(strict_types=1);

function gpCoachReviewTableReady(PDO $pdo): bool
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
              AND table_name = 'coach_reviews'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpCoachReviewQueue(PDO $pdo, int $userId, int $dogId, int $limit = 12): array
{
    if (!gpCoachReviewTableReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            cr.*,
            g.goal_category,
            g.success_criteria,
            s.progression_status AS session_status,
            s.context_environment AS session_context,
            s.notes AS session_notes,
            s.created_at AS session_created_at
        FROM coach_reviews cr
        LEFT JOIN training_goals g ON g.id = cr.related_goal_id
        LEFT JOIN training_sessions s ON s.id = cr.related_session_id
        WHERE cr.user_id = ?
          AND cr.dog_id = ?
        ORDER BY
            CASE cr.status
                WHEN 'open' THEN 1
                WHEN 'in_review' THEN 2
                WHEN 'resolved' THEN 3
                ELSE 4
            END,
            CASE cr.priority
                WHEN 'high' THEN 0
                WHEN 'normal' THEN 1
                ELSE 2
            END,
            cr.updated_at DESC,
            cr.created_at DESC,
            cr.id DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $dogId, max(1, min(50, $limit))]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpCoachReviewRegressionEvents(PDO $pdo, int $userId, int $dogId, int $limit = 12): array
{
    $stmt = $pdo->prepare("
        SELECT re.*, g.goal_category, g.success_criteria, tm.title AS module_title
        FROM regression_events re
        LEFT JOIN training_goals g ON g.id = re.goal_id
        LEFT JOIN training_modules tm ON tm.id = re.module_id
        WHERE re.user_id = ?
          AND re.dog_id = ?
          AND COALESCE(re.status, 'open') IN ('open', 'paused_for_review', 'regression_detected', 'reviewed')
        ORDER BY re.created_at DESC, re.id DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $dogId, max(1, min(50, $limit))]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpCoachReviewCreateFromRegressionEvent(PDO $pdo, int $userId, int $dogId, int $eventId, array $data): int
{
    if (!gpCoachReviewTableReady($pdo)) {
        throw new RuntimeException('Coach review storage has not been deployed yet.');
    }

    $eventStmt = $pdo->prepare("
        SELECT *
        FROM regression_events
        WHERE id = ? AND user_id = ? AND dog_id = ?
        LIMIT 1
    ");
    $eventStmt->execute([$eventId, $userId, $dogId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        throw new RuntimeException('Coach review event not found.');
    }

    $reviewType = trim((string) ($data['review_type'] ?? 'coach_review'));
    if ($reviewType === '') {
        $reviewType = 'coach_review';
    }
    $priority = trim((string) ($data['priority'] ?? 'normal'));
    if (!in_array($priority, ['low', 'normal', 'high'], true)) {
        $priority = 'normal';
    }
    $status = trim((string) ($data['status'] ?? 'open'));
    if (!in_array($status, ['open', 'in_review', 'resolved', 'closed'], true)) {
        $status = 'open';
    }

    $reason = trim((string) ($data['reason'] ?? ''));
    if ($reason === '') {
        $reason = trim((string) ($event['detected_reason'] ?? ''));
    }
    $coachNotes = cleanTextarea($data['coach_notes'] ?? '', 1200);

    $stmt = $pdo->prepare("
        INSERT INTO coach_reviews
        (user_id, dog_id, related_goal_id, related_session_id, review_type, priority, reason, status, coach_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([
        $userId,
        $dogId,
        $event['goal_id'] !== null ? (int) $event['goal_id'] : null,
        $data['related_session_id'] ?? null,
        $reviewType,
        $priority,
        $reason,
        $status,
        $coachNotes ?: null,
    ]);

    $reviewId = (int) $stmt->fetchColumn();

    $updateEvent = $pdo->prepare("UPDATE regression_events SET status = 'in_review' WHERE id = ? AND user_id = ? AND dog_id = ?");
    $updateEvent->execute([$eventId, $userId, $dogId]);

    return $reviewId;
}

function gpCoachReviewUpdate(PDO $pdo, int $userId, int $dogId, int $reviewId, array $data): void
{
    if (!gpCoachReviewTableReady($pdo)) {
        throw new RuntimeException('Coach review storage has not been deployed yet.');
    }

    $status = trim((string) ($data['status'] ?? 'open'));
    if (!in_array($status, ['open', 'in_review', 'resolved', 'closed'], true)) {
        $status = 'open';
    }
    $priority = trim((string) ($data['priority'] ?? 'normal'));
    if (!in_array($priority, ['low', 'normal', 'high'], true)) {
        $priority = 'normal';
    }

    $stmt = $pdo->prepare("
        UPDATE coach_reviews
        SET status = ?,
            priority = ?,
            coach_notes = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ? AND dog_id = ?
    ");
    $stmt->execute([
        $status,
        $priority,
        cleanTextarea($data['coach_notes'] ?? '', 1200) ?: null,
        $reviewId,
        $userId,
        $dogId,
    ]);
}

function gpDashboardOpenCoachReviews(PDO $pdo, int $userId): array
{
    if (!gpCoachReviewTableReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT cr.*, d.name AS dog_name
        FROM coach_reviews cr
        JOIN dogs d ON d.id = cr.dog_id
        WHERE cr.user_id = ?
          AND COALESCE(cr.status, 'open') IN ('open', 'in_review')
        ORDER BY
            CASE cr.priority
                WHEN 'high' THEN 0
                WHEN 'normal' THEN 1
                ELSE 2
            END,
            cr.updated_at DESC,
            cr.created_at DESC,
            cr.id DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDashboardRenderCoachReviewAlerts(array $reviews): void
{
    if (!$reviews) {
        return;
    }
    ?>
    <section class="card command-card mb-3 border-primary">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Coach Review</h2>
                    <div class="small text-muted">Training regressions and safety concerns waiting for follow-up.</div>
                </div>
                <a href="coach_review.php" class="btn btn-primary btn-sm">Open Queue</a>
            </div>
            <div class="vstack gap-2">
                <?php foreach ($reviews as $review): ?>
                    <div class="rounded-3 border bg-primary-subtle p-3">
                        <div class="fw-bold"><?= e($review['dog_name'] ?? 'Dog profile') ?></div>
                        <div class="small text-muted">
                            <?= e(($review['review_type'] ?? 'coach_review')) ?>
                            · <?= e(($review['status'] ?? 'open')) ?>
                            <?php if (!empty($review['priority'])): ?> · <?= e(ucfirst((string) $review['priority'])) ?> priority<?php endif; ?>
                        </div>
                        <?php if (!empty($review['reason'])): ?>
                            <div class="small mt-2"><?= nl2br(e($review['reason'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
