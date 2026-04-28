<?php

function writeAuditLog(
    PDO $pdo,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    ?string $details = null
): void {
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO admin_audit_log
        (user_id, action, target_type, target_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $action,
        $targetType,
        $targetId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}
