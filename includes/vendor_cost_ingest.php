<?php
declare(strict_types=1);

require_once __DIR__ . '/business_costs.php';

if (!function_exists('gpVendorCostEnsureSchema')) {
    function gpVendorCostEnsureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendor_cost_imports (
                id BIGSERIAL PRIMARY KEY,
                vendor_slug TEXT NOT NULL,
                source_label TEXT NOT NULL,
                source_ref TEXT NOT NULL DEFAULT '',
                period_label TEXT NOT NULL DEFAULT '',
                imported_total_cents INTEGER NOT NULL DEFAULT 0,
                row_count INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'USD',
                raw_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS vendor_cost_imports_vendor_idx ON vendor_cost_imports (vendor_slug, created_at DESC)");
    }
}

if (!function_exists('gpVendorParseMoneyCents')) {
    function gpVendorParseMoneyCents(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $negative = false;
        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $negative = true;
            $value = trim($value, '()');
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '';
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $cents = (int) round(((float) $value) * 100);
        return $negative ? -abs($cents) : $cents;
    }
}

if (!function_exists('gpVendorHeaderIndex')) {
    function gpVendorHeaderIndex(array $headers, array $candidates): ?int
    {
        $normalized = [];
        foreach ($headers as $idx => $header) {
            $normalized[$idx] = strtolower(trim((string) $header));
        }
        foreach ($candidates as $candidate) {
            $needle = strtolower(trim((string) $candidate));
            foreach ($normalized as $idx => $header) {
                if ($header === $needle) {
                    return $idx;
                }
            }
        }
        return null;
    }
}

if (!function_exists('gpVendorParseDateYear')) {
    function gpVendorParseDateYear(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return (int) gmdate('Y', $timestamp);
        }

        if (preg_match('/\b(20\d{2})\b/', $value, $match)) {
            return (int) $match[1];
        }

        return null;
    }
}

if (!function_exists('gpVendorCostLatestImports')) {
    function gpVendorCostLatestImports(PDO $pdo, int $limit = 10): array
    {
        gpVendorCostEnsureSchema($pdo);
        $stmt = $pdo->prepare("
            SELECT *
            FROM vendor_cost_imports
            ORDER BY created_at DESC, id DESC
            LIMIT ?
        ");
        $stmt->execute([max(1, $limit)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('gpVendorCostImportPorkbunCsv')) {
    function gpVendorCostImportPorkbunCsv(PDO $pdo, string $csv, int $calendarYear = 0): array
    {
        gpVendorCostEnsureSchema($pdo);
        $csv = trim($csv);
        if ($csv === '') {
            return ['ok' => false, 'error' => 'Paste the Porkbun order history CSV first.'];
        }

        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }
        if (count($rows) < 2) {
            return ['ok' => false, 'error' => 'The CSV needs a header row plus at least one data row.'];
        }

        $headers = array_map(static fn($value): string => strtolower(trim((string) $value)), array_shift($rows));
        $amountIdx = gpVendorHeaderIndex($headers, [
            'total',
            'order total',
            'amount',
            'price',
            'grand total',
            'total usd',
            'usd',
            'charge',
        ]);
        $dateIdx = gpVendorHeaderIndex($headers, [
            'date',
            'order date',
            'created',
            'created at',
            'purchase date',
            'purchased',
        ]);
        $orderIdx = gpVendorHeaderIndex($headers, [
            'order id',
            'order number',
            'id',
        ]);
        $itemIdx = gpVendorHeaderIndex($headers, [
            'item',
            'description',
            'product',
            'domain',
            'name',
        ]);

        if ($amountIdx === null) {
            return ['ok' => false, 'error' => 'Could not find a total or amount column in the CSV.'];
        }

        $importedTotalCents = 0;
        $rowCount = 0;
        $firstDate = '';
        $lastDate = '';
        $matchedOrders = [];

        foreach ($rows as $row) {
            $row = array_pad($row, count($headers), '');
            $dateText = $dateIdx !== null ? trim((string) ($row[$dateIdx] ?? '')) : '';
            if ($calendarYear > 0 && $dateText !== '') {
                $year = gpVendorParseDateYear($dateText);
                if ($year !== $calendarYear) {
                    continue;
                }
            }

            $amountText = trim((string) ($row[$amountIdx] ?? ''));
            $amountCents = gpVendorParseMoneyCents($amountText);
            if ($amountCents === null) {
                continue;
            }

            $rowCount++;
            $importedTotalCents += $amountCents;

            $dateKey = '';
            if ($dateText !== '') {
                $parsedTimestamp = strtotime($dateText);
                if ($parsedTimestamp !== false) {
                    $dateKey = gmdate('Y-m-d', $parsedTimestamp);
                } else {
                    $dateKey = $dateText;
                }
            }

            if ($dateKey !== '') {
                if ($firstDate === '' || strcmp($dateKey, $firstDate) < 0) {
                    $firstDate = $dateKey;
                }
                if ($lastDate === '' || strcmp($dateKey, $lastDate) > 0) {
                    $lastDate = $dateKey;
                }
            }

            $matchedOrders[] = [
                'date' => $dateKey !== '' ? $dateKey : $dateText,
                'amount_cents' => $amountCents,
                'order_ref' => $orderIdx !== null ? trim((string) ($row[$orderIdx] ?? '')) : '',
                'item' => $itemIdx !== null ? trim((string) ($row[$itemIdx] ?? '')) : '',
            ];
        }

        if ($rowCount === 0) {
            return ['ok' => false, 'error' => 'No importable rows were found in the CSV.'];
        }

        $periodLabel = $calendarYear > 0 ? (string) $calendarYear : trim($firstDate . ($firstDate !== '' && $lastDate !== '' && $firstDate !== $lastDate ? ' to ' . $lastDate : ''));
        $sourceRef = $calendarYear > 0 ? 'porkbun-orders-' . $calendarYear : 'porkbun-orders';
        $stmt = $pdo->prepare("
            INSERT INTO vendor_cost_imports (
                vendor_slug,
                source_label,
                source_ref,
                period_label,
                imported_total_cents,
                row_count,
                currency,
                raw_payload
            ) VALUES (
                :vendor_slug,
                :source_label,
                :source_ref,
                :period_label,
                :imported_total_cents,
                :row_count,
                :currency,
                :raw_payload::jsonb
            )
        ");
        $stmt->execute([
            ':vendor_slug' => 'porkbun',
            ':source_label' => 'Porkbun order history CSV',
            ':source_ref' => $sourceRef,
            ':period_label' => $periodLabel,
            ':imported_total_cents' => $importedTotalCents,
            ':row_count' => $rowCount,
            ':currency' => 'USD',
            ':raw_payload' => json_encode([
                'headers' => $headers,
                'matched_orders' => $matchedOrders,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        gpBusinessCostUpsert($pdo, [
            'slug' => 'domain_dns',
            'category' => 'current',
            'label' => 'Domain / DNS',
            'summary' => 'Porkbun domain renewals imported from CSV',
            'billing_cycle' => 'annual',
            'unit_cost_cents' => max(0, $importedTotalCents),
            'quantity' => 1,
            'currency' => 'USD',
            'sort_order' => 60,
            'is_active' => true,
            'notes' => 'Imported from Porkbun order history CSV for ' . ($periodLabel !== '' ? $periodLabel : 'the selected period') . '.',
        ]);

        return [
            'ok' => true,
            'vendor_slug' => 'porkbun',
            'source_label' => 'Porkbun order history CSV',
            'period_label' => $periodLabel,
            'row_count' => $rowCount,
            'total_cents' => $importedTotalCents,
            'first_date' => $firstDate,
            'last_date' => $lastDate,
        ];
    }
}
