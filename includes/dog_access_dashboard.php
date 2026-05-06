<?php
declare(strict_types=1);

function gpDashboardIncomingDogTransfers(PDO $pdo, int $userId): array
{
    if (!function_exists('tableExists') || !tableExists($pdo, 'dog_transfer_requests')) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT tr.*, d.name AS dog_name, from_u.username AS from_username, from_u.display_name AS from_display_name
        FROM dog_transfer_requests tr
        JOIN dogs d ON d.id = tr.dog_id
        JOIN users from_u ON from_u.id = tr.from_user_id
        WHERE tr.to_user_id = ? AND tr.status = 'pending'
        ORDER BY tr.requested_at DESC
        LIMIT 5");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gpDashboardRenderDogTransferAlerts(array $transfers): void
{
    if (!$transfers) {
        return;
    }
    ?>
    <section class="card command-card mb-3 border-warning">
        <div class="card-body">
            <div class="command-title">
                <div>
                    <h2 class="h5 mb-1">Pending Dog Transfer</h2>
                    <div class="small text-muted">A handler is asking you to accept ownership of a dog profile.</div>
                </div>
                <a href="dog_access.php" class="btn btn-warning btn-sm">Review Now</a>
            </div>
            <div class="vstack gap-2">
                <?php foreach ($transfers as $transfer): ?>
                    <div class="rounded-3 border bg-warning-subtle p-3">
                        <div class="fw-bold"><?= e($transfer['dog_name'] ?? 'Dog profile') ?></div>
                        <div class="small text-muted">
                            From <?= e(($transfer['from_display_name'] ?? '') ?: ($transfer['from_username'] ?? 'another handler')) ?>
                            <?php if (!empty($transfer['requested_at'])): ?>
                                · <?= e(date('M j, Y', strtotime((string) $transfer['requested_at']))) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($transfer['note'])): ?>
                            <div class="small mt-2"><?= nl2br(e($transfer['note'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
