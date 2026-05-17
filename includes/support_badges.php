<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

if (!function_exists('gpSupportBadgeConfig')) {
    function gpSupportBadgeConfig(): array
    {
        return [
            'bronze' => [
                'label' => 'Bronze supporter',
                'rank' => 1,
                'days' => 30,
                'min_cents' => 0,
                'max_cents' => 2500,
                'image' => 'assets/brand/support-badges/bronze.png',
                'class' => 'text-bg-secondary',
            ],
            'silver' => [
                'label' => 'Silver supporter',
                'rank' => 2,
                'days' => 30,
                'min_cents' => 2600,
                'max_cents' => 5000,
                'image' => 'assets/brand/support-badges/silver.png',
                'class' => 'text-bg-info',
            ],
            'gold' => [
                'label' => 'Gold supporter',
                'rank' => 3,
                'days' => 90,
                'min_cents' => 5100,
                'max_cents' => 10000,
                'image' => 'assets/brand/support-badges/gold.png',
                'class' => 'text-bg-warning',
            ],
            'platinum' => [
                'label' => 'Platinum supporter',
                'rank' => 4,
                'days' => 365,
                'min_cents' => 10100,
                'max_cents' => 15000,
                'image' => 'assets/brand/support-badges/platinum.png',
                'class' => 'text-bg-primary',
            ],
            'platinum_lifetime' => [
                'label' => 'Platinum supporter for life',
                'rank' => 5,
                'days' => null,
                'min_cents' => 15100,
                'max_cents' => null,
                'image' => 'assets/brand/support-badges/platinum.png',
                'class' => 'text-bg-primary',
            ],
            'special_platinum_lifetime' => [
                'label' => 'Platinum supporter for life',
                'rank' => 5,
                'days' => null,
                'min_cents' => null,
                'max_cents' => null,
                'image' => 'assets/brand/support-badges/platinum.png',
                'class' => 'text-bg-primary',
            ],
        ];
    }
}

if (!function_exists('gpSupportBadgeNormalizeThresholds')) {
    function gpSupportBadgeNormalizeThresholds(int $amountCents): int
    {
        return max(0, $amountCents);
    }
}

if (!function_exists('gpSupportBadgeTierForAmount')) {
    function gpSupportBadgeTierForAmount(int $amountCents): ?string
    {
        $amountCents = gpSupportBadgeNormalizeThresholds($amountCents);
        if ($amountCents <= 0) {
            return null;
        }
        if ($amountCents <= 2500) {
            return 'bronze';
        }
        if ($amountCents <= 5000) {
            return 'silver';
        }
        if ($amountCents <= 10000) {
            return 'gold';
        }
        if ($amountCents <= 15000) {
            return 'platinum';
        }
        return 'platinum_lifetime';
    }
}

if (!function_exists('gpSupportBadgeImagePath')) {
    function gpSupportBadgeImagePath(string $tier): string
    {
        $tier = trim($tier);
        $config = gpSupportBadgeConfig();
        return $config[$tier]['image'] ?? $config['bronze']['image'];
    }
}

if (!function_exists('gpSupportBadgeLabel')) {
    function gpSupportBadgeLabel(string $tier): string
    {
        $tier = trim($tier);
        $config = gpSupportBadgeConfig();
        return $config[$tier]['label'] ?? 'Supporter';
    }
}

if (!function_exists('gpSupportBadgeRank')) {
    function gpSupportBadgeRank(string $tier): int
    {
        $tier = trim($tier);
        $config = gpSupportBadgeConfig();
        return (int) ($config[$tier]['rank'] ?? 0);
    }
}

if (!function_exists('gpSupportBadgeDurationDays')) {
    function gpSupportBadgeDurationDays(string $tier): ?int
    {
        $tier = trim($tier);
        $config = gpSupportBadgeConfig();
        return array_key_exists($tier, $config) ? $config[$tier]['days'] : null;
    }
}

if (!function_exists('gpSupportBadgeIsLifetimeTier')) {
    function gpSupportBadgeIsLifetimeTier(string $tier): bool
    {
        return in_array(trim($tier), ['platinum_lifetime', 'special_platinum_lifetime'], true);
    }
}

if (!function_exists('gpSupportBadgeSpecialLifetimeUser')) {
    function gpSupportBadgeSpecialLifetimeUser(array $user): bool
    {
        $username = strtolower(trim((string) ($user['username'] ?? '')));
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        return $username === 'admin' || $email === 'jphutching@gmail.com';
    }
}

if (!function_exists('gpSupportBadgeNormalizeEventRow')) {
    function gpSupportBadgeNormalizeEventRow(array $row): ?array
    {
        $amountCents = (int) ($row['amount_total_cents'] ?? 0);
        $tier = gpSupportBadgeTierForAmount($amountCents);
        if ($tier === null) {
            return null;
        }

        $grantedAt = trim((string) ($row['created_at'] ?? $row['updated_at'] ?? ''));
        if ($grantedAt === '') {
            return null;
        }

        $durationDays = gpSupportBadgeDurationDays($tier);
        $expiresAt = null;
        if ($durationDays !== null) {
            $expiresAt = (new DateTimeImmutable($grantedAt))->modify('+' . $durationDays . ' days')->format('Y-m-d H:i:sP');
        }

        return [
            'tier' => $tier,
            'label' => gpSupportBadgeLabel($tier),
            'rank' => gpSupportBadgeRank($tier),
            'image' => gpSupportBadgeImagePath($tier),
            'amount_cents' => $amountCents,
            'granted_at' => $grantedAt,
            'expires_at' => $expiresAt,
            'lifetime' => gpSupportBadgeIsLifetimeTier($tier),
        ];
    }
}

if (!function_exists('gpSupportBadgeFromEvents')) {
    function gpSupportBadgeFromEvents(array $events, array $user = []): ?array
    {
        if (gpSupportBadgeSpecialLifetimeUser($user)) {
            return [
                'tier' => 'special_platinum_lifetime',
                'label' => gpSupportBadgeLabel('special_platinum_lifetime'),
                'rank' => gpSupportBadgeRank('special_platinum_lifetime'),
                'image' => gpSupportBadgeImagePath('special_platinum_lifetime'),
                'amount_cents' => 0,
                'granted_at' => null,
                'expires_at' => null,
                'lifetime' => true,
                'source' => 'special_account',
            ];
        }

        $now = new DateTimeImmutable('now');
        $best = null;
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $badge = gpSupportBadgeNormalizeEventRow($event);
            if ($badge === null) {
                continue;
            }
            if (!$badge['lifetime'] && !empty($badge['expires_at'])) {
                try {
                    $expires = new DateTimeImmutable((string) $badge['expires_at']);
                    if ($expires < $now) {
                        continue;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
            if ($best === null || (int) $badge['rank'] > (int) $best['rank'] || ((int) $badge['rank'] === (int) $best['rank'] && strcmp((string) $badge['granted_at'], (string) $best['granted_at']) > 0)) {
                $best = $badge;
            }
        }

        return $best;
    }
}

if (!function_exists('gpSupportBadgeForUser')) {
    function gpSupportBadgeForUser(PDO $pdo, array $user): ?array
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return gpSupportBadgeFromEvents([], $user);
        }

        if (!function_exists('gpStripeSupportPaymentsTableExists') || !gpStripeSupportPaymentsTableExists($pdo)) {
            return gpSupportBadgeFromEvents([], $user);
        }

        $stmt = $pdo->prepare("
            SELECT
                amount_total_cents,
                created_at,
                updated_at,
                support_type,
                support_mode,
                stripe_event_type,
                stripe_checkout_session_id,
                payment_status
            FROM support_funding_events
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute([$userId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return gpSupportBadgeFromEvents($events, $user);
    }
}

if (!function_exists('gpSupportBadgeMoney')) {
    function gpSupportBadgeMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
