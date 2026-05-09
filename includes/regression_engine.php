<?php
declare(strict_types=1);

require_once __DIR__ . '/training_progression.php';
require_once __DIR__ . '/validation.php';

function gpRegressionEngineTableReady(PDO $pdo): bool
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
              AND table_name = 'regression_events'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpRegressionEngineOpenCount(PDO $pdo, int $userId, ?int $dogId = null): int
{
    if (!gpRegressionEngineTableReady($pdo)) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*)
        FROM regression_events
        WHERE user_id = ?
          AND COALESCE(status, 'open') IN ('open', 'in_review', 'paused_for_review', 'regression_detected')
    ";
    $params = [$userId];
    if ($dogId !== null) {
        $sql .= " AND dog_id = ?";
        $params[] = $dogId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function gpRegressionEngineOpenEvents(PDO $pdo, int $userId, int $dogId, int $limit = 12): array
{
    if (!gpRegressionEngineTableReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            re.*,
            g.goal_category,
            g.success_criteria,
            tm.title AS module_title,
            tm.category AS module_category
        FROM regression_events re
        LEFT JOIN training_goals g ON g.id = re.goal_id
        LEFT JOIN training_modules tm ON tm.id = re.module_id
        WHERE re.user_id = ?
          AND re.dog_id = ?
          AND COALESCE(re.status, 'open') IN ('open', 'in_review', 'paused_for_review', 'regression_detected')
        ORDER BY
            CASE COALESCE(re.status, 'open')
                WHEN 'paused_for_review' THEN 0
                WHEN 'regression_detected' THEN 1
                WHEN 'open' THEN 2
                WHEN 'in_review' THEN 3
                ELSE 4
            END,
            re.created_at DESC,
            re.id DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $dogId, max(1, min(50, $limit))]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpRegressionEngineUpdateEvent(PDO $pdo, int $userId, int $dogId, int $eventId, array $data): void
{
    if (!gpRegressionEngineTableReady($pdo)) {
        throw new RuntimeException('Regression engine storage has not been deployed yet.');
    }

    $status = trim((string) ($data['status'] ?? 'open'));
    if (!in_array($status, ['open', 'in_review', 'paused_for_review', 'resolved', 'closed'], true)) {
        $status = 'open';
    }

    $recommendedAction = cleanTextarea($data['recommended_action'] ?? '', 1600);
    $stmt = $pdo->prepare("
        UPDATE regression_events
        SET status = ?,
            recommended_action = ?,
            resolved_at = CASE WHEN ? IN ('resolved', 'closed') THEN CURRENT_TIMESTAMP ELSE NULL END
        WHERE id = ? AND user_id = ? AND dog_id = ?
    ");
    $stmt->execute([
        $status,
        $recommendedAction ?: null,
        $status,
        $eventId,
        $userId,
        $dogId,
    ]);
}

function gpDashboardOpenRegressionEvents(PDO $pdo, int $userId, int $limit = 5): array
{
    if (!gpRegressionEngineTableReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            re.*,
            d.name AS dog_name,
            g.goal_category,
            tm.title AS module_title
        FROM regression_events re
        JOIN dogs d ON d.id = re.dog_id
        LEFT JOIN training_goals g ON g.id = re.goal_id
        LEFT JOIN training_modules tm ON tm.id = re.module_id
        WHERE re.user_id = ?
          AND COALESCE(re.status, 'open') IN ('open', 'in_review', 'paused_for_review', 'regression_detected')
        ORDER BY
            CASE COALESCE(re.status, 'open')
                WHEN 'paused_for_review' THEN 0
                WHEN 'regression_detected' THEN 1
                WHEN 'open' THEN 2
                WHEN 'in_review' THEN 3
                ELSE 4
            END,
            re.created_at DESC,
            re.id DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, max(1, min(20, $limit))]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDashboardRenderRegressionAlerts(array $events, ?int $totalCount = null): void
{
    if (!$events) {
        return;
    }
    $count = $totalCount !== null ? max(0, $totalCount) : count($events);
    ?>
    <section class="card command-card mb-3 border-warning">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Regression Engine</h2>
                    <div class="small text-muted">Open regression events and reset plans that need attention.</div>
                </div>
                <a href="regression_engine.php" class="btn btn-outline-warning btn-sm">Open engine</a>
            </div>
            <div class="attention-empty mb-3">
                <?= (int) $count ?> open regression event<?= $count === 1 ? '' : 's' ?> ready for reset-plan review.
            </div>
            <div class="vstack gap-2">
                <?php foreach (array_slice($events, 0, 3) as $event): ?>
                    <div class="alert-card warning rounded-3 border bg-white p-3">
                        <div class="fw-semibold"><?= e((string) ($event['dog_name'] ?? 'Dog')) ?> — <?= e((string) ($event['detected_reason'] ?? 'Regression event')) ?></div>
                        <div class="small text-muted text-break">
                            <?= e((string) ($event['module_title'] ?? 'Training step')) ?>
                            <?php if (!empty($event['recommended_action'])): ?> • <?= e((string) $event['recommended_action']) ?><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
