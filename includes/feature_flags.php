<?php
if (!function_exists('currentUserIsAdmin')) {
    function currentUserIsAdmin(): bool {
        return !empty($_SESSION['is_admin']);
    }
}

function getFeatureFlags(PDO $pdo): array {
    $stmt = $pdo->query("SELECT flag_key, label, description, is_enabled FROM feature_flags ORDER BY sort_order, label");
    $flags = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $flags[$row['flag_key']] = $row;
    }
    return $flags;
}

function featureEnabled(PDO $pdo, string $key): bool {
    static $cache = null;
    if ($cache === null) {
        $cache = getFeatureFlags($pdo);
    }
    return !isset($cache[$key]) || (int)$cache[$key]['is_enabled'] === 1;
}
