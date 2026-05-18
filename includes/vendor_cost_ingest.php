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

if (!function_exists('gpVendorCostMonthlySeries')) {
    function gpVendorCostMonthlySeries(PDO $pdo, ?string $vendorSlug = null, int $months = 6): array
    {
        gpVendorCostEnsureSchema($pdo);

        $months = max(1, min(24, $months));
        $lookbackMonths = max(0, $months - 1);
        $params = [];
        $where = '';
        if ($vendorSlug !== null && trim($vendorSlug) !== '') {
            $where = 'AND vendor_slug = ?';
            $params[] = strtolower(trim($vendorSlug));
        }

        $stmt = $pdo->prepare("
            SELECT
                TO_CHAR(date_trunc('month', created_at), 'YYYY-MM') AS month_key,
                COALESCE(SUM(imported_total_cents), 0) AS imported_total_cents,
                COALESCE(SUM(row_count), 0) AS row_count,
                COUNT(*) AS import_count
            FROM vendor_cost_imports
            WHERE created_at >= (date_trunc('month', CURRENT_TIMESTAMP) - INTERVAL '{$lookbackMonths} months')
            {$where}
            GROUP BY 1
            ORDER BY 1 ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $series = [];
        foreach ($rows as $row) {
            $monthKey = trim((string) ($row['month_key'] ?? ''));
            if ($monthKey === '') {
                continue;
            }

            $series[$monthKey] = [
                'month_key' => $monthKey,
                'imported_total_cents' => (int) ($row['imported_total_cents'] ?? 0),
                'row_count' => (int) ($row['row_count'] ?? 0),
                'import_count' => (int) ($row['import_count'] ?? 0),
            ];
        }

        return $series;
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

if (!function_exists('gpVendorCostReadPdfText')) {
    function gpVendorCostReadPdfText(string $path): array
    {
        $path = trim($path);
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return ['ok' => false, 'error' => 'The uploaded PDF could not be read.'];
        }

        $text = '';
        if (function_exists('exec') && trim((string) shell_exec('command -v pdftotext 2>/dev/null')) !== '') {
            $command = 'pdftotext -layout -nopgbrk -q ' . escapeshellarg($path) . ' -';
            $output = [];
            $exitCode = 0;
            @exec($command, $output, $exitCode);
            if ($exitCode === 0) {
                $text = trim(implode("\n", $output));
            }
        }

        if ($text === '') {
            $binary = file_get_contents($path);
            if (!is_string($binary) || $binary === '') {
                return ['ok' => false, 'error' => 'The PDF did not contain extractable text.'];
            }
            $text = gpVendorPdfExtractTextFromBinary($binary);
            if ($text === '') {
                $text = gpVendorPdfExtractPrintableTextFromBinary($binary);
            }
        }

        if ($text === '') {
            return ['ok' => false, 'error' => 'Could not extract text from the PDF.'];
        }

        return ['ok' => true, 'text' => $text];
    }
}

if (!function_exists('gpVendorPdfDecodeHexString')) {
    function gpVendorPdfDecodeHexString(string $hex, array $map): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex) ?? '';
        if ($hex === '') {
            return '';
        }
        if ((strlen($hex) % 4) !== 0) {
            $hex = str_pad($hex, (int) ceil(strlen($hex) / 4) * 4, '0', STR_PAD_RIGHT);
        }
        $text = '';
        for ($i = 0; $i < strlen($hex); $i += 4) {
            $code = strtoupper(substr($hex, $i, 4));
            $text .= $map[$code] ?? '';
        }
        return $text;
    }
}

if (!function_exists('gpVendorPdfExtractTextFromBinary')) {
    function gpVendorPdfExtractTextFromBinary(string $pdf): string
    {
        $objBodies = [];
        if (preg_match_all('/(\d+)\s+0\s+obj(.*?)endobj/s', $pdf, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objBodies[(int) $match[1]] = (string) $match[2];
            }
        }

        $fontToUnicode = [];
        if (preg_match_all('/\/(F\d+)\s+(\d+)\s+0\s+R\b/', $pdf, $fontRefs, PREG_SET_ORDER)) {
            foreach ($fontRefs as $fontRef) {
                $fontName = (string) $fontRef[1];
                $fontObj = (int) $fontRef[2];
                $body = $objBodies[$fontObj] ?? '';
                if ($body !== '' && preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R\b/', $body, $toUnicodeMatch)) {
                    $fontToUnicode[$fontName] = (int) $toUnicodeMatch[1];
                }
            }
        }

        $cmapMaps = [];
        foreach ($fontToUnicode as $fontName => $cmapObj) {
            $body = $objBodies[$cmapObj] ?? '';
            if ($body === '') {
                continue;
            }
            $stream = '';
            if (preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $body, $streamMatch)) {
                $stream = (string) $streamMatch[1];
            }
            if ($stream === '') {
                continue;
            }
            $cmap = @gzuncompress($stream);
            if ($cmap === false) {
                $cmap = @gzinflate($stream);
            }
            if (!is_string($cmap) || $cmap === '') {
                continue;
            }
            $map = [];
            if (preg_match_all('/<([0-9A-Fa-f]{4})>\s*<([0-9A-Fa-f]{4})>\s*\[([^\]]+)\]/s', $cmap, $rangeMatches, PREG_SET_ORDER)) {
                foreach ($rangeMatches as $rangeMatch) {
                    $start = hexdec($rangeMatch[1]);
                    $end = hexdec($rangeMatch[2]);
                    preg_match_all('/<([0-9A-Fa-f]{4})>/', $rangeMatch[3], $chars);
                    $codePoint = $start;
                    foreach ($chars[1] as $charHex) {
                        $map[strtoupper(str_pad(dechex($codePoint), 4, '0', STR_PAD_LEFT))] = mb_convert_encoding(hex2bin($charHex), 'UTF-8', 'UTF-16BE');
                        $codePoint++;
                        if ($codePoint > $end) {
                            break;
                        }
                    }
                }
            }
            $cmapMaps[$fontName] = $map;
        }

        $textParts = [];
        if (preg_match_all('/BT(.*?)ET/s', $pdf, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $blockMatch) {
                $block = (string) $blockMatch[1];
                $fontName = '';
                if (preg_match('/\/(F\d+)\s+\d+(?:\.\d+)?\s+Tf/', $block, $fontMatch)) {
                    $fontName = (string) $fontMatch[1];
                }
                $map = $cmapMaps[$fontName] ?? [];
                $blockText = '';
                if (preg_match_all('/<([0-9A-Fa-f]+)>/', $block, $hexMatches)) {
                    foreach ($hexMatches[1] as $hex) {
                        $blockText .= gpVendorPdfDecodeHexString((string) $hex, $map);
                    }
                }
                $blockText = trim(preg_replace('/\s+/', ' ', $blockText) ?? '');
                if ($blockText !== '') {
                    $textParts[] = $blockText;
                }
            }
        }

        return trim(implode("\n", $textParts));
    }
}

if (!function_exists('gpVendorPdfExtractPrintableTextFromBinary')) {
    function gpVendorPdfExtractPrintableTextFromBinary(string $pdf): string
    {
        $chunks = [];
        if (preg_match_all('/[ -~]{8,}/', $pdf, $matches)) {
            foreach ($matches[0] as $match) {
                $line = trim((string) $match);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^[\/%<>{}\[\]0-9A-Fa-f\s\-\.\(\),:$]+$/', $line) === 1) {
                    continue;
                }
                $chunks[] = preg_replace('/\s+/', ' ', $line) ?? $line;
            }
        }
        if ($chunks === []) {
            return '';
        }
        $chunks = array_values(array_unique($chunks));
        return trim(implode("\n", $chunks));
    }
}

if (!function_exists('gpVendorCostImportPorkbunPdf')) {
    function gpVendorCostImportPorkbunPdf(PDO $pdo, string $path, int $calendarYear = 0): array
    {
        gpVendorCostEnsureSchema($pdo);

        $pdfText = gpVendorCostReadPdfText($path);
        if (empty($pdfText['ok'])) {
            return $pdfText;
        }

        $text = trim((string) ($pdfText['text'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'error' => 'The PDF text was empty after extraction.'];
        }

        $invoiceNumber = '';
        $invoiceDate = '';
        if (preg_match('/Invoice #:\s*([A-Za-z0-9\-]+)/i', $text, $m)) {
            $invoiceNumber = trim((string) $m[1]);
        }
        if (preg_match('/Date:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $text, $m)) {
            $invoiceDate = trim((string) $m[1]);
        } elseif (preg_match('/Date:\s*([A-Za-z]{3,9}\s+\d{1,2},\s+\d{4})/i', $text, $m)) {
            $invoiceDate = trim((string) $m[1]);
        }

        $lines = preg_split('/\R/', $text) ?: [];
        $rows = [];
        $invoiceTotalCents = null;
        $pendingTotalLine = false;
        $pendingDomainRow = false;
        $pendingDomainRowLabel = '';
        $pendingDomainRowDate = $invoiceDate;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $line) ?? '');
            if ($line === '') {
                continue;
            }

            if ($invoiceTotalCents === null && $pendingTotalLine) {
                if (preg_match_all('/([0-9][0-9,]*\.[0-9]{2})/', $line, $moneyMatches) && !empty($moneyMatches[1])) {
                    $invoiceTotalCents = gpVendorParseMoneyCents((string) end($moneyMatches[1]));
                    $pendingTotalLine = false;
                    continue;
                }
            }

            if ($pendingDomainRow) {
                if (preg_match_all('/([0-9][0-9,]*\.[0-9]{2})/', $line, $moneyMatches) && !empty($moneyMatches[1])) {
                    $amountCents = gpVendorParseMoneyCents((string) end($moneyMatches[1]));
                    if ($amountCents !== null && $amountCents > 0) {
                        $rows[] = [
                            'date' => $pendingDomainRowDate,
                            'amount_cents' => $amountCents,
                            'order_ref' => $invoiceNumber,
                            'item' => $pendingDomainRowLabel !== '' ? $pendingDomainRowLabel : 'guidepaw.app Domain Registration',
                        ];
                        if ($invoiceTotalCents === null) {
                            $invoiceTotalCents = $amountCents;
                        }
                    }
                }
                $pendingDomainRow = false;
                $pendingDomainRowLabel = '';
                continue;
            }

            if ($invoiceTotalCents === null && (stripos($line, 'Total Charged') !== false || stripos($line, 'Invoice Total') !== false || stripos($line, 'Amount Due') !== false || stripos($line, 'Grand Total') !== false)) {
                if (preg_match_all('/([0-9][0-9,]*\.[0-9]{2})/', $line, $moneyMatches) && !empty($moneyMatches[1])) {
                    $invoiceTotalCents = gpVendorParseMoneyCents((string) end($moneyMatches[1]));
                    if ($invoiceTotalCents !== null) {
                        continue;
                    }
                }
                $pendingTotalLine = true;
                continue;
            }

            if (stripos($line, 'guidepaw.app') !== false && stripos($line, 'SUCCESS') !== false && stripos($line, 'Domain Registration') !== false) {
                $pendingDomainRow = true;
                $pendingDomainRowLabel = 'guidepaw.app Domain Registration';
                $pendingDomainRowDate = $invoiceDate;
                if (preg_match_all('/([0-9][0-9,]*\.[0-9]{2})/', $line, $moneyMatches) && !empty($moneyMatches[1])) {
                    $amountCents = gpVendorParseMoneyCents((string) end($moneyMatches[1]));
                    if ($amountCents !== null && $amountCents > 0) {
                        $rows[] = [
                            'date' => $invoiceDate,
                            'amount_cents' => $amountCents,
                            'order_ref' => $invoiceNumber,
                            'item' => 'guidepaw.app Domain Registration',
                        ];
                        if ($invoiceTotalCents === null) {
                            $invoiceTotalCents = $amountCents;
                        }
                        $pendingDomainRow = false;
                        $pendingDomainRowLabel = '';
                    }
                }
            }
        }

        if ($invoiceTotalCents === null || $invoiceTotalCents < 50 || $invoiceTotalCents > 1000000) {
            return ['ok' => false, 'error' => 'Could not identify a valid invoice total from the PDF.'];
        }

        $matchedOrders = $rows;
        if ($matchedOrders === []) {
            $matchedOrders[] = [
                'date' => $invoiceDate,
                'amount_cents' => $invoiceTotalCents,
                'order_ref' => $invoiceNumber,
                'item' => 'Porkbun invoice total',
            ];
        }

        $importedTotalCents = 0;
        $rowCount = 0;
        $firstDate = '';
        $lastDate = '';
        foreach ($matchedOrders as $row) {
            $amountCents = (int) ($row['amount_cents'] ?? 0);
            $importedTotalCents += $amountCents;
            $rowCount++;
            $dateText = trim((string) ($row['date'] ?? ''));
            if ($dateText !== '') {
                $dateKey = '';
                $parsedTimestamp = strtotime($dateText);
                if ($parsedTimestamp !== false) {
                    $dateKey = gmdate('Y-m-d', $parsedTimestamp);
                } else {
                    $dateKey = $dateText;
                }
                if ($dateKey !== '') {
                    if ($firstDate === '' || strcmp($dateKey, $firstDate) < 0) {
                        $firstDate = $dateKey;
                    }
                    if ($lastDate === '' || strcmp($dateKey, $lastDate) > 0) {
                        $lastDate = $dateKey;
                    }
                    $row['date'] = $dateKey;
                }
            }
            $matchedOrders[$rowCount - 1] = $row;
        }
        $importedTotalCents = max(0, $invoiceTotalCents);

        $periodLabel = $calendarYear > 0 ? (string) $calendarYear : trim($firstDate . ($firstDate !== '' && $lastDate !== '' && $firstDate !== $lastDate ? ' to ' . $lastDate : ''));
        $sourceRef = $calendarYear > 0 ? 'porkbun-pdf-' . $calendarYear : 'porkbun-pdf';
        $pdo->prepare("DELETE FROM vendor_cost_imports WHERE vendor_slug = :vendor_slug AND source_ref = :source_ref")
            ->execute([
                ':vendor_slug' => 'porkbun',
                ':source_ref' => $sourceRef,
            ]);
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
            ':source_label' => 'Porkbun PDF export',
            ':source_ref' => $sourceRef,
            ':period_label' => $periodLabel,
            ':imported_total_cents' => $importedTotalCents,
            ':row_count' => $rowCount,
            ':currency' => 'USD',
            ':raw_payload' => json_encode([
                'source' => 'pdf',
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'invoice_total_cents' => $invoiceTotalCents,
                'matched_orders' => $matchedOrders,
                'extracted_text_preview' => mb_substr($text, 0, 3000),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        gpBusinessCostUpsert($pdo, [
            'slug' => 'domain_dns',
            'category' => 'current',
            'label' => 'Domain / DNS',
            'summary' => 'Porkbun domain renewals imported from PDF export',
            'billing_cycle' => 'annual',
            'unit_cost_cents' => max(0, $importedTotalCents),
            'quantity' => 1,
            'currency' => 'USD',
            'sort_order' => 60,
            'is_active' => true,
            'notes' => 'Imported from Porkbun PDF export for ' . ($periodLabel !== '' ? $periodLabel : 'the selected period') . '.',
        ]);

        return [
            'ok' => true,
            'vendor_slug' => 'porkbun',
            'source_label' => 'Porkbun PDF export',
            'period_label' => $periodLabel,
            'row_count' => $rowCount,
            'total_cents' => $importedTotalCents,
            'first_date' => $firstDate,
            'last_date' => $lastDate,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
        ];
    }
}
