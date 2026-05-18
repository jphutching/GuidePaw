<?php

function gpComposePostalAddress(array $row, string $prefix = 'home_'): string {
    $street = trim((string) ($row[$prefix . 'street'] ?? ''));
    $apt = trim((string) ($row[$prefix . 'apt'] ?? ''));
    $city = trim((string) ($row[$prefix . 'city'] ?? ''));
    $state = strtoupper(trim((string) ($row[$prefix . 'state'] ?? '')));
    $zip = trim((string) ($row[$prefix . 'zip'] ?? ''));

    $lines = [];
    if ($street !== '') {
        $line1 = $street;
        if ($apt !== '') {
            $line1 .= ', ' . $apt;
        }
        $lines[] = $line1;
    } elseif ($apt !== '') {
        $lines[] = $apt;
    }

    $line2Bits = [];
    if ($city !== '') {
        $line2Bits[] = $city;
    }
    if ($state !== '') {
        $line2Bits[] = $state;
    }
    if ($zip !== '') {
        $line2Bits[] = $zip;
    }
    if ($line2Bits) {
        if (count($line2Bits) >= 2) {
            $line2 = array_shift($line2Bits);
            $line2 = $line2 . ', ' . implode(' ', $line2Bits);
        } else {
            $line2 = $line2Bits[0];
        }
        $lines[] = $line2;
    }

    return implode("\n", $lines);
}

function gpParseLegacyPostalAddress(string $address): array {
    $address = trim(preg_replace('/\s+/', ' ', $address));
    $result = ['street' => '', 'apt' => '', 'city' => '', 'state' => '', 'zip' => ''];
    if ($address === '') {
        return $result;
    }

    $lines = preg_split('/\r\n|\r|\n/', $address) ?: [];
    if (count($lines) >= 2) {
        $firstLine = trim((string) $lines[0]);
        $secondLine = trim((string) $lines[count($lines) - 1]);
        $result['street'] = $firstLine;
        if (preg_match('/^(.+?)(?:,\s*([^,]+))?\s*,?\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $secondLine, $match)) {
            $result['city'] = trim((string) $match[1]);
            if (!empty($match[2])) {
                $result['apt'] = trim((string) $match[2]);
            }
            $result['state'] = strtoupper(trim((string) $match[3]));
            $result['zip'] = trim((string) $match[4]);
            return $result;
        }
        $result['city'] = $secondLine;
        return $result;
    }

    $parts = array_values(array_filter(array_map('trim', explode(',', $address)), static fn($part) => $part !== ''));
    if (count($parts) >= 3) {
        $result['street'] = $parts[0];
        if (count($parts) === 3) {
            $result['city'] = $parts[1];
            $stateZip = $parts[2];
        } else {
            $result['apt'] = $parts[1];
            $result['city'] = $parts[2];
            $stateZip = $parts[3] ?? '';
        }
        if (preg_match('/^([A-Z]{2})(?:\s+(\d{5}(?:-\d{4})?))?$/', trim($stateZip), $match)) {
            $result['state'] = strtoupper(trim((string) $match[1]));
            $result['zip'] = trim((string) ($match[2] ?? ''));
        }
        return $result;
    }

    $result['street'] = $address;
    return $result;
}
