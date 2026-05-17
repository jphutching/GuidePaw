<?php
declare(strict_types=1);

if (!function_exists('gpCostHttpJson')) {
    function gpCostHttpJson(string $url, array $options = []): array
    {
        $timeout = max(3, (int) ($options['timeout'] ?? 15));
        $headers = ['Accept: application/json'];
        foreach ((array) ($options['headers'] ?? []) as $header) {
            if (is_string($header) && trim($header) !== '') {
                $headers[] = trim($header);
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Could not initialize curl.'];
        }

        $requestOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout + 10,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        if ($method === 'GET') {
            $requestOptions[CURLOPT_HTTPGET] = true;
        } else {
            $requestOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (!empty($options['userpwd']) && is_string($options['userpwd'])) {
            $requestOptions[CURLOPT_USERPWD] = $options['userpwd'];
        }

        if (array_key_exists('json', $options)) {
            $payload = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                curl_close($ch);
                return ['ok' => false, 'error' => 'Could not encode JSON payload.'];
            }
            $requestOptions[CURLOPT_POSTFIELDS] = $payload;
            $requestOptions[CURLOPT_POST] = true;
        } elseif (array_key_exists('form', $options)) {
            $requestOptions[CURLOPT_POSTFIELDS] = http_build_query((array) $options['form']);
            $requestOptions[CURLOPT_POST] = true;
        }

        curl_setopt_array($ch, $requestOptions);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => $status, 'error' => $error !== '' ? $error : 'Request failed.'];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'status' => $status, 'error' => 'Could not decode JSON response.', 'body' => mb_substr((string) $response, 0, 500)];
        }

        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'json' => $decoded];
    }
}

if (!function_exists('gpCostMonthRange')) {
    function gpCostMonthRange(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        return [$start, $now];
    }
}

if (!function_exists('gpTwilioUsageSnapshot')) {
    function gpTwilioUsageSnapshot(): array
    {
        $sid = trim((string) gpEnv('TWILIO_ACCOUNT_SID', ''));
        $token = trim((string) gpEnv('TWILIO_AUTH_TOKEN', ''));
        if ($sid === '' || $token === '') {
            return ['connected' => false, 'label' => 'Twilio SMS', 'status' => 'missing credentials'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Usage/Records/ThisMonth.json?category=sms';
        $res = gpCostHttpJson($url, [
            'userpwd' => $sid . ':' . $token,
            'headers' => ['Accept: application/json'],
            'timeout' => 15,
        ]);

        if (empty($res['ok'])) {
            return [
                'connected' => true,
                'label' => 'Twilio SMS',
                'status' => 'fetch failed',
                'error' => (string) ($res['error'] ?? 'Unknown Twilio error.'),
            ];
        }

        $json = $res['json'] ?? [];
        $records = [];
        foreach (['usageRecords', 'usage_records', 'records'] as $key) {
            if (!empty($json[$key]) && is_array($json[$key])) {
                $records = $json[$key];
                break;
            }
        }
        if (!$records && !empty($json['usageRecord']) && is_array($json['usageRecord'])) {
            $records = [$json['usageRecord']];
        }

        $record = [];
        foreach ($records as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $category = strtolower(trim((string) ($candidate['category'] ?? '')));
            if ($category === 'sms' || $category === 'sms-outbound' || $category === 'sms-inbound') {
                $record = $candidate;
                break;
            }
        }
        if ($record === [] && $records) {
            $first = reset($records);
            if (is_array($first)) {
                $record = $first;
            }
        }

        return [
            'connected' => true,
            'label' => 'Twilio SMS',
            'status' => 'connected',
            'monthly_cents' => (int) round(((float) ($record['price'] ?? 0)) * 100),
            'currency' => strtolower(trim((string) ($record['priceUnit'] ?? $record['price_unit'] ?? 'usd'))) ?: 'usd',
            'message_count' => (float) ($record['count'] ?? $record['usage'] ?? 0),
            'usage_unit' => (string) ($record['usageUnit'] ?? $record['usage_unit'] ?? 'messages'),
            'as_of' => (string) ($record['asOf'] ?? $record['as_of'] ?? ''),
            'raw' => $record,
        ];
    }
}

if (!function_exists('gpRenderCostPlanLabel')) {
    function gpRenderCostPlanLabel(array $resource): string
    {
        foreach ([
            $resource['plan'] ?? null,
            $resource['instanceType'] ?? null,
            $resource['instance_type'] ?? null,
            $resource['serviceDetails']['plan'] ?? null,
            $resource['serviceDetails']['instanceType'] ?? null,
            $resource['serviceDetails']['type'] ?? null,
            $resource['type'] ?? null,
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return strtolower($candidate);
            }
        }
        return '';
    }
}

if (!function_exists('gpRenderSnapshot')) {
    function gpRenderSnapshot(): array
    {
        $key = trim((string) gpEnv('RENDER_API_KEY', ''));
        if ($key === '') {
            return ['connected' => false, 'label' => 'Render', 'status' => 'missing API key'];
        }

        $servicesRes = gpCostHttpJson('https://api.render.com/v1/services', [
            'headers' => ['Authorization: Bearer ' . $key],
            'timeout' => 15,
        ]);
        $postgresRes = gpCostHttpJson('https://api.render.com/v1/postgres', [
            'headers' => ['Authorization: Bearer ' . $key],
            'timeout' => 15,
        ]);

        if (empty($servicesRes['ok']) && empty($postgresRes['ok'])) {
            return [
                'connected' => true,
                'label' => 'Render',
                'status' => 'fetch failed',
                'error' => (string) ($servicesRes['error'] ?? $postgresRes['error'] ?? 'Unknown Render error.'),
            ];
        }

        $services = [];
        $postgres = [];
        foreach ([$servicesRes['json'] ?? [], $postgresRes['json'] ?? []] as $payload) {
            if (!is_array($payload)) {
                continue;
            }
        }

        $extractList = static function (array $payload): array {
            if (array_is_list($payload)) {
                return $payload;
            }
            foreach (['items', 'data', 'services', 'postgres', 'databases'] as $key) {
                if (!empty($payload[$key]) && is_array($payload[$key])) {
                    return $payload[$key];
                }
            }
            return [];
        };

        if (!empty($servicesRes['json']) && is_array($servicesRes['json'])) {
            $services = $extractList($servicesRes['json']);
        }
        if (!empty($postgresRes['json']) && is_array($postgresRes['json'])) {
            $postgres = $extractList($postgresRes['json']);
        }

        $serviceSummaries = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $serviceSummaries[] = [
                'name' => trim((string) ($service['name'] ?? $service['service']['name'] ?? 'service')),
                'plan' => gpRenderCostPlanLabel($service),
                'type' => strtolower(trim((string) ($service['type'] ?? $service['serviceType'] ?? $service['service_details']['type'] ?? 'service'))),
            ];
        }

        $postgresSummaries = [];
        foreach ($postgres as $db) {
            if (!is_array($db)) {
                continue;
            }
            $postgresSummaries[] = [
                'name' => trim((string) ($db['name'] ?? $db['databaseName'] ?? 'database')),
                'plan' => gpRenderCostPlanLabel($db),
                'storage_gb' => (int) ($db['diskSizeGB'] ?? $db['disk_size_gb'] ?? $db['storageGB'] ?? $db['storage_gb'] ?? 0),
            ];
        }

        return [
            'connected' => true,
            'label' => 'Render',
            'status' => 'connected',
            'service_count' => count($serviceSummaries),
            'postgres_count' => count($postgresSummaries),
            'services' => $serviceSummaries,
            'postgres' => $postgresSummaries,
        ];
    }
}

if (!function_exists('gpZeptoMailSnapshot')) {
    function gpZeptoMailSnapshot(): array
    {
        $token = trim((string) gpEnv('ZEPTO_OAUTH_TOKEN', ''));
        $apiUrl = rtrim(trim((string) gpEnv('ZEPTO_API_URL', 'https://api.zeptomail.com/v1.1/email')), '/');
        if ($token === '') {
            return ['connected' => false, 'label' => 'ZeptoMail', 'status' => 'missing OAuth token'];
        }

        [$start, $end] = gpCostMonthRange();
        $query = http_build_query([
            'date_from' => $start->format('d/m/Y, h:i A'),
            'date_to' => $end->format('d/m/Y, h:i A'),
            'limit' => 1,
        ]);
        $res = gpCostHttpJson($apiUrl . '/?' . $query, [
            'headers' => ['Authorization: Zoho-oauthtoken ' . $token],
            'timeout' => 15,
        ]);

        if (empty($res['ok'])) {
            return [
                'connected' => true,
                'label' => 'ZeptoMail',
                'status' => 'fetch failed',
                'error' => (string) ($res['error'] ?? 'Unknown ZeptoMail error.'),
            ];
        }

        $json = $res['json'] ?? [];
        $count = 0;
        if (isset($json['metadata']['count'])) {
            $count = (int) $json['metadata']['count'];
        } elseif (isset($json['count'])) {
            $count = (int) $json['count'];
        }

        return [
            'connected' => true,
            'label' => 'ZeptoMail',
            'status' => 'connected',
            'email_count' => $count,
            'range_start' => $start->format('Y-m-d'),
            'range_end' => $end->format('Y-m-d'),
        ];
    }
}

if (!function_exists('gpBusinessProviderSnapshots')) {
    function gpBusinessProviderSnapshots(): array
    {
        return [
            'twilio' => gpTwilioUsageSnapshot(),
            'render' => gpRenderSnapshot(),
            'zeptomail' => gpZeptoMailSnapshot(),
        ];
    }
}
