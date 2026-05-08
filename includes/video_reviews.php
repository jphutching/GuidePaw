<?php
declare(strict_types=1);

require_once __DIR__ . '/media_reviews.php';

function gpVideoReviewLogEntries(PDO $pdo, int $userId, int $dogId, int $limit = 10): array
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
          AND dl.media_type = 'video'
          AND dl.media_url IS NOT NULL
          AND (
            dl.user_id = ?
            OR EXISTS (
                SELECT 1 FROM dog_handlers dh
                WHERE dh.dog_id = dl.dog_id
                  AND dh.user_id = ?
                  AND dh.status = 'accepted'
            )
          )
        ORDER BY dl.log_date DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $dogId, $userId, $userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDashboardOpenVideoReviews(PDO $pdo, int $userId): array
{
    if (!gpMediaReviewTableReady($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            dl.id AS daily_log_id,
            dl.dog_id,
            dl.location_name,
            dl.log_date,
            dl.media_url,
            dl.media_mime,
            dl.media_type,
            mr.id AS review_id,
            mr.rating_camera_stability,
            mr.rating_audio_clarity,
            mr.rating_training_value,
            mr.review_notes,
            mr.next_step,
            mr.reviewed_at,
            d.name AS dog_name
        FROM daily_logs dl
        JOIN dogs d ON d.id = dl.dog_id
        LEFT JOIN daily_log_media_reviews mr
          ON mr.daily_log_id = dl.id
         AND mr.reviewer_user_id = ?
        WHERE d.owner_user_id = ?
          AND dl.media_type = 'video'
          AND dl.media_url IS NOT NULL
          AND (mr.id IS NULL OR COALESCE(mr.reviewed_at, mr.updated_at, mr.created_at) >= CURRENT_TIMESTAMP - INTERVAL '14 days')
        ORDER BY dl.log_date DESC, dl.id DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDashboardRenderVideoReviewAlerts(array $reviews): void
{
    if (!$reviews) {
        return;
    }
    ?>
    <section class="card command-card mb-3 border-info">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Video Review</h2>
                    <div class="small text-muted">Checkpoint clips waiting for a quick self-check or advance decision.</div>
                </div>
                <a href="video_review.php" class="btn btn-info btn-sm">Open Videos</a>
            </div>
            <div class="vstack gap-2">
                <?php foreach ($reviews as $review): ?>
                    <div class="rounded-3 border bg-info-subtle p-3">
                        <div class="fw-bold"><?= e($review['location_name'] ?? 'Training clip') ?></div>
                        <div class="small text-muted">
                            <?= e((string) ($review['dog_name'] ?? 'Dog profile')) ?>
                            · <?= e(date('M j, Y', strtotime((string) ($review['log_date'] ?? 'now')))) ?>
                        </div>
                        <?php if (!empty($review['review_notes'])): ?>
                            <div class="small mt-2"><?= nl2br(e($review['review_notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
