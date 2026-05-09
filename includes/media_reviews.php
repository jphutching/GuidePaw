<?php
require_once __DIR__ . '/validation.php';

function gpMediaReviewTableReady(PDO $pdo): bool
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
              AND table_name = 'daily_log_media_reviews'
            LIMIT 1
        ");
        $stmt->execute();
        $ready = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function gpMediaReviewLogEntries(PDO $pdo, int $userId, int $dogId, int $limit = 10): array
{
    if (!gpMediaReviewTableReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT dl.*, u.username AS handler_username, mr.rating_camera_stability, mr.rating_audio_clarity,
               mr.rating_training_value, mr.next_step, mr.review_notes, mr.reviewed_at
        FROM daily_logs dl
        JOIN users u ON u.id = dl.user_id
        LEFT JOIN daily_log_media_reviews mr
          ON mr.daily_log_id = dl.id
         AND mr.reviewer_user_id = ?
        WHERE dl.dog_id = ?
          AND dl.media_url IS NOT NULL
          AND (
            dl.user_id = ?
            OR EXISTS (
                SELECT 1 FROM dog_handlers dh
                WHERE dh.dog_id = dl.dog_id
                  AND dh.user_id = ?
                  AND dh.status = 'accepted'
                  AND dh.accepted_at IS NOT NULL
            )
          )
        ORDER BY dl.log_date DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $dogId, $userId, $userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gpSaveMediaReview(PDO $pdo, int $userId, int $logId, array $data): void
{
    if (!gpMediaReviewTableReady($pdo)) {
        throw new RuntimeException('Media review storage has not been deployed yet.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO daily_log_media_reviews
        (daily_log_id, reviewer_user_id, rating_camera_stability, rating_audio_clarity, rating_training_value, review_notes, next_step)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (daily_log_id, reviewer_user_id)
        DO UPDATE SET
            rating_camera_stability = EXCLUDED.rating_camera_stability,
            rating_audio_clarity = EXCLUDED.rating_audio_clarity,
            rating_training_value = EXCLUDED.rating_training_value,
            review_notes = EXCLUDED.review_notes,
            next_step = EXCLUDED.next_step,
            reviewed_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $logId,
        $userId,
        max(1, min(5, (int) ($data['rating_camera_stability'] ?? 3))),
        max(1, min(5, (int) ($data['rating_audio_clarity'] ?? 3))),
        max(1, min(5, (int) ($data['rating_training_value'] ?? 3))),
        cleanTextarea($data['review_notes'] ?? '', 1200),
        cleanTextarea($data['next_step'] ?? '', 500),
    ]);
}
