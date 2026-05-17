<?php
declare(strict_types=1);

if (!function_exists('gpBusinessCostItemsTableExists')) {
    function gpBusinessCostItemsTableExists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                  AND table_name = ?
            )
        ");
        $stmt->execute(['business_cost_items']);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('gpBusinessCostEnsureSchema')) {
    function gpBusinessCostEnsureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS business_cost_items (
                slug TEXT PRIMARY KEY,
                category TEXT NOT NULL DEFAULT 'current',
                label TEXT NOT NULL,
                summary TEXT NOT NULL DEFAULT '',
                billing_cycle TEXT NOT NULL DEFAULT 'monthly',
                unit_cost_cents INTEGER NOT NULL DEFAULT 0,
                quantity NUMERIC(12,2) NOT NULL DEFAULT 1,
                currency TEXT NOT NULL DEFAULT 'USD',
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                notes TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS business_cost_items_category_idx ON business_cost_items (category)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS business_cost_items_active_idx ON business_cost_items (is_active)");

        $count = (int) $pdo->query('SELECT COUNT(*) FROM business_cost_items')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $seedRows = [
            ['slug' => 'render_web_service', 'category' => 'current', 'label' => 'Render web service', 'summary' => 'Current web app hosting', 'billing_cycle' => 'monthly', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 10, 'notes' => 'Starter plan from Render. Enter the real monthly cost here.'],
            ['slug' => 'render_postgres', 'category' => 'current', 'label' => 'Render PostgreSQL', 'summary' => 'Managed database hosting', 'billing_cycle' => 'monthly', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 20, 'notes' => 'basic-256mb. Enter the real monthly cost here.'],
            ['slug' => 'stripe_processing', 'category' => 'current', 'label' => 'Stripe processing', 'summary' => 'Card and support checkout fees', 'billing_cycle' => 'usage', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 30, 'notes' => 'Set an estimated monthly fee once transactions begin.'],
            ['slug' => 'twilio_sms', 'category' => 'current', 'label' => 'Twilio SMS', 'summary' => 'SMS notifications and alerts', 'billing_cycle' => 'usage', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 40, 'notes' => 'Opt-in only. Estimate monthly usage here.'],
            ['slug' => 'zeptomail', 'category' => 'current', 'label' => 'ZeptoMail', 'summary' => 'Transactional email', 'billing_cycle' => 'monthly', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 50, 'notes' => 'Enter the live monthly cost here.'],
            ['slug' => 'domain_dns', 'category' => 'current', 'label' => 'Domain / DNS', 'summary' => 'Domain registration and DNS', 'billing_cycle' => 'annual', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 60, 'notes' => 'Enter annual domain cost and this will annualize it.'],
            ['slug' => 'future_extra_dogs', 'category' => 'future', 'label' => 'Future extra-dog expansion', 'summary' => 'Extra storage/support overhead for more dogs', 'billing_cycle' => 'monthly', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 70, 'notes' => 'Model future infrastructure growth for extra dogs.'],
            ['slug' => 'future_qr_growth', 'category' => 'future', 'label' => 'Future QR growth', 'summary' => 'Future support for QR tracking scale-up', 'billing_cycle' => 'monthly', 'unit_cost_cents' => 0, 'quantity' => 1, 'currency' => 'USD', 'sort_order' => 80, 'notes' => 'Use for future platform expansion estimates.'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO business_cost_items (
                slug, category, label, summary, billing_cycle, unit_cost_cents,
                quantity, currency, sort_order, is_active, notes, created_at, updated_at
            ) VALUES (
                :slug, :category, :label, :summary, :billing_cycle, :unit_cost_cents,
                :quantity, :currency, :sort_order, TRUE, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        foreach ($seedRows as $row) {
            $stmt->execute($row);
        }
    }
}

if (!function_exists('gpBusinessCostRows')) {
    function gpBusinessCostRows(PDO $pdo, ?string $category = null): array
    {
        gpBusinessCostEnsureSchema($pdo);

        $params = [];
        $where = '';
        if ($category !== null && $category !== '') {
            $where = 'WHERE category = ?';
            $params[] = $category;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM business_cost_items
            {$where}
            ORDER BY sort_order ASC, label ASC, slug ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $cycle = strtolower(trim((string) ($row['billing_cycle'] ?? 'monthly')));
            $unit = (int) ($row['unit_cost_cents'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 1);
            $monthly = 0;
            $oneTime = 0;
            if ($cycle === 'annual') {
                $monthly = (int) round(($unit * $quantity) / 12);
                $oneTime = 0;
            } elseif ($cycle === 'one_time') {
                $monthly = 0;
                $oneTime = (int) round($unit * $quantity);
            } else {
                $monthly = (int) round($unit * $quantity);
                $oneTime = 0;
            }
            $row['monthly_equivalent_cents'] = $monthly;
            $row['one_time_equivalent_cents'] = $oneTime;
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('gpBusinessCostSummary')) {
    function gpBusinessCostSummary(PDO $pdo): array
    {
        $rows = gpBusinessCostRows($pdo);
        $currentMonthly = 0;
        $futureMonthly = 0;
        $oneTime = 0;
        foreach ($rows as $row) {
            if (!empty($row['is_active'])) {
                $category = strtolower(trim((string) ($row['category'] ?? 'current')));
                $currentMonthly += (int) ($row['monthly_equivalent_cents'] ?? 0) * ($category === 'current' ? 1 : 0);
                $futureMonthly += (int) ($row['monthly_equivalent_cents'] ?? 0) * ($category === 'future' ? 1 : 0);
                $oneTime += (int) ($row['one_time_equivalent_cents'] ?? 0);
            }
        }

        return [
            'current_monthly_cents' => $currentMonthly,
            'future_monthly_cents' => $futureMonthly,
            'one_time_cents' => $oneTime,
            'current_items' => array_values(array_filter($rows, static fn(array $row): bool => !empty($row['is_active']) && ($row['category'] ?? '') === 'current')),
            'future_items' => array_values(array_filter($rows, static fn(array $row): bool => !empty($row['is_active']) && ($row['category'] ?? '') === 'future')),
        ];
    }
}

if (!function_exists('gpBusinessCostUpsert')) {
    function gpBusinessCostUpsert(PDO $pdo, array $data): void
    {
        gpBusinessCostEnsureSchema($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO business_cost_items (
                slug, category, label, summary, billing_cycle, unit_cost_cents,
                quantity, currency, sort_order, is_active, notes, updated_at
            ) VALUES (
                :slug, :category, :label, :summary, :billing_cycle, :unit_cost_cents,
                :quantity, :currency, :sort_order, :is_active, :notes, CURRENT_TIMESTAMP
            )
            ON CONFLICT (slug) DO UPDATE SET
                category = EXCLUDED.category,
                label = EXCLUDED.label,
                summary = EXCLUDED.summary,
                billing_cycle = EXCLUDED.billing_cycle,
                unit_cost_cents = EXCLUDED.unit_cost_cents,
                quantity = EXCLUDED.quantity,
                currency = EXCLUDED.currency,
                sort_order = EXCLUDED.sort_order,
                is_active = EXCLUDED.is_active,
                notes = EXCLUDED.notes,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':slug' => strtolower(trim((string) ($data['slug'] ?? ''))),
            ':category' => in_array((string) ($data['category'] ?? 'current'), ['current', 'future'], true) ? (string) $data['category'] : 'current',
            ':label' => trim((string) ($data['label'] ?? '')),
            ':summary' => trim((string) ($data['summary'] ?? '')),
            ':billing_cycle' => in_array((string) ($data['billing_cycle'] ?? 'monthly'), ['monthly', 'annual', 'one_time', 'usage'], true) ? (string) $data['billing_cycle'] : 'monthly',
            ':unit_cost_cents' => max(0, (int) ($data['unit_cost_cents'] ?? 0)),
            ':quantity' => max(0, (float) ($data['quantity'] ?? 1)),
            ':currency' => strtoupper(trim((string) ($data['currency'] ?? 'USD'))) ?: 'USD',
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }
}
