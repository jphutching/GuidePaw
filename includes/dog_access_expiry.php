<?php
declare(strict_types=1);

function gpDogAccessExpirySchemaReady(PDO $pdo): bool
{
    if (!function_exists('tableExists') || !tableExists($pdo, 'dog_handlers')) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dog_handlers' AND column_name IN ('access_ends_at', 'revoked_at')");
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= 2;
}

function gpExpireDogHandlerAccess(PDO $pdo): int
{
    static $ran = false;
    if ($ran) {
        return 0;
    }
    $ran = true;

    if (!gpDogAccessExpirySchemaReady($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare("UPDATE dog_handlers
        SET status = 'expired', revoked_at = COALESCE(revoked_at, CURRENT_TIMESTAMP)
        WHERE status = 'accepted'
          AND access_ends_at IS NOT NULL
          AND access_ends_at < CURRENT_DATE");
    $stmt->execute();
    return $stmt->rowCount();
}
