<?php
// GuidePaw Daily Win Banner
// Shows today's training activity for the active dog.

if (!function_exists('guidepawDailyWinBanner')) {
    function guidepawDailyWinBanner(PDO $pdo, int $userId, ?int $dogId = null): void
    {
        if ($userId <= 0 || !$dogId) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS log_count,
                AVG(focus_level)::numeric(10,2) AS avg_focus,
                MAX(log_date) AS last_log_at
            FROM daily_logs
            WHERE user_id = ?
              AND dog_id = ?
              AND log_date::date = CURRENT_DATE
        ");
        $stmt->execute([$userId, $dogId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $count = (int)($row['log_count'] ?? 0);
        $avgFocus = $row['avg_focus'] !== null ? round((float)$row['avg_focus'], 1) : null;

        if (!function_exists('e')) {
            function e($value) {
                return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            }
        }

        if ($count > 0) {
            $message = $count === 1
                ? "1 training win logged today."
                : $count . " training wins logged today.";

            $sub = $avgFocus !== null
                ? "Average focus: " . $avgFocus . "/5. Keep stacking small wins."
                : "Keep stacking small wins.";
        } else {
            $message = "No training win logged yet today.";
            $sub = "Log one quick session, success, task, or field note to count today’s win.";
        }
        ?>
        <div class="card shadow-sm border-0 mb-3" style="border-radius:18px; background:linear-gradient(135deg,#ecfdf5,#eff6ff);">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div style="font-size:2rem; line-height:1;">🏆</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-uppercase small text-muted">Daily Win</div>
                        <div class="h5 mb-1"><?= e($message) ?></div>
                        <div class="text-muted small"><?= e($sub) ?></div>
                    </div>
                    <a class="btn btn-success btn-sm" href="quick_log.php">Log Win</a>
                </div>
            </div>
        </div>
        <?php
    }
}
