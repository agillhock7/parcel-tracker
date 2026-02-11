<?php
declare(strict_types=1);

namespace App\Tracking;

final class Ship24Client
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeoutSeconds;
    private string $logDir;

    public function __construct(string $apiKey, string $logDir, ?string $baseUrl = null, int $timeoutSeconds = 30)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim((string)($baseUrl ?? getenv('SHIP24_API_BASE') ?: 'https://api.ship24.com/public/v1'), '/');
        $this->timeoutSeconds = max(8, min(90, $timeoutSeconds));
        $this->logDir = $logDir;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function providerName(): string
    {
        return 'ship24';
    }

    /**
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   status?:string,
     *   carrier?:string,
     *   events?:list<array{event_time:string,location:?string,description:string,raw_payload:array<string,mixed>}>
     * }
     */
    public function fetchTracking(string $trackingNumber, ?string $carrierHint = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Tracking API key is not configured.'];
        }

        $trackingNumber = strtoupper(trim($trackingNumber));
        if ($trackingNumber === '') {
            return ['ok' => false, 'error' => 'Tracking number is required.'];
        }

        $payload = ['trackingNumber' => $trackingNumber];
        $courierCode = $this->normalizeCourierCode($carrierHint);
        if ($courierCode !== null) {
            $payload['courierCode'] = $courierCode;
        }

        $res = $this->requestJson('POST', '/trackers/track', $payload);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            $error = $this->extractApiError($res['json']);
            $this->log('ship24 track failed: status=' . $res['status'] . ' error=' . $error . ' raw=' . substr((string)$res['raw'], 0, 300));
            return ['ok' => false, 'error' => $error !== '' ? $error : ('Ship24 returned HTTP ' . $res['status'])];
        }

        $tracking = $this->extractTrackingObject($res['json']);
        if ($tracking === null) {
            return ['ok' => false, 'error' => 'Ship24 response did not include tracking events yet.'];
        }

        $events = $this->normalizeEvents($tracking);
        $status = $this->mapMilestone((string)($tracking['statusMilestone'] ?? $tracking['status'] ?? 'unknown'));
        $carrier = $this->extractCarrier($tracking);

        return [
            'ok' => true,
            'status' => $status,
            'carrier' => $carrier,
            'events' => $events,
        ];
    }

    private function normalizeCourierCode(?string $carrier): ?string
    {
        $carrier = strtolower(trim((string)$carrier));
        if ($carrier === '') {
            return null;
        }

        $map = [
            'ups' => 'ups',
            'fedex' => 'fedex',
            'federal express' => 'fedex',
            'usps' => 'usps',
            'united states postal service' => 'usps',
            'dhl' => 'dhl',
            'dhl express' => 'dhl',
            'dhl ecommerce' => 'dhl-ecommerce',
        ];
        if (isset($map[$carrier])) {
            return $map[$carrier];
        }

        $code = preg_replace('/[^a-z0-9-]+/', '-', $carrier);
        $code = trim((string)$code, '-');
        return $code !== '' ? $code : null;
    }

    /** @param array<string,mixed> $json */
    private function extractApiError(array $json): string
    {
        $candidates = [
            $json['message'] ?? null,
            $json['error'] ?? null,
            $json['data']['message'] ?? null,
            $json['data']['error'] ?? null,
            $json['errors'][0]['message'] ?? null,
        ];
        foreach ($candidates as $v) {
            $txt = trim((string)$v);
            if ($txt !== '') {
                return $txt;
            }
        }
        return '';
    }

    /** @param array<string,mixed> $json @return array<string,mixed>|null */
    private function extractTrackingObject(array $json): ?array
    {
        $candidates = [
            $json['data']['trackings'][0] ?? null,
            $json['data']['tracking'] ?? null,
            $json['tracking'] ?? null,
            $json['data'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (is_array($candidate['events'] ?? null) || isset($candidate['statusMilestone']) || isset($candidate['trackingNumber'])) {
                return $candidate;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $tracking */
    private function extractCarrier(array $tracking): ?string
    {
        $values = [
            $tracking['courierCode'] ?? null,
            $tracking['courier']['code'] ?? null,
            $tracking['courier']['name'] ?? null,
            $tracking['courierName'] ?? null,
        ];
        foreach ($values as $v) {
            $txt = trim((string)$v);
            if ($txt !== '') {
                return $txt;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $tracking
     * @return list<array{event_time:string,location:?string,description:string,raw_payload:array<string,mixed>}>
     */
    private function normalizeEvents(array $tracking): array
    {
        $out = [];
        $events = $tracking['events'] ?? [];
        if (is_array($events)) {
            foreach ($events as $ev) {
                if (!is_array($ev)) {
                    continue;
                }

                $description = trim((string)($ev['status'] ?? $ev['statusCode'] ?? $ev['statusMilestone'] ?? 'Tracking update'));
                if ($description === '') {
                    continue;
                }

                $occurrence = (string)($ev['occurrenceDatetime'] ?? $ev['datetime'] ?? $ev['createdAt'] ?? '');
                $out[] = [
                    'event_time' => $this->normalizeTime($occurrence),
                    'location' => $this->eventLocation($ev),
                    'description' => $description,
                    'raw_payload' => $ev,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['event_time'], $b['event_time']));
        if ($out !== []) {
            return $out;
        }

        $out[] = [
            'event_time' => gmdate('Y-m-d H:i:s'),
            'location' => null,
            'description' => 'Tracking created',
            'raw_payload' => $tracking,
        ];
        return $out;
    }

    /** @param array<string,mixed> $event */
    private function eventLocation(array $event): ?string
    {
        $parts = [];
        foreach ([
            $event['location']['city'] ?? null,
            $event['location']['state'] ?? null,
            $event['location']['countryCode'] ?? null,
            $event['location']['zipCode'] ?? null,
            $event['location'] ?? null,
        ] as $val) {
            if (is_array($val)) {
                continue;
            }
            $txt = trim((string)$val);
            if ($txt !== '') {
                $parts[] = $txt;
            }
        }
        if ($parts === []) {
            return null;
        }
        return implode(', ', array_unique($parts));
    }

    private function normalizeTime(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return gmdate('Y-m-d H:i:s');
        }

        try {
            $dt = new \DateTimeImmutable($input);
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $v = str_replace('T', ' ', $input);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
                $v .= ':00';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v)) {
                return $v;
            }
        }

        return gmdate('Y-m-d H:i:s');
    }

    private function mapMilestone(string $milestone): string
    {
        $m = strtolower(trim($milestone));
        $m = str_replace([' ', '-'], '_', $m);

        return match ($m) {
            'info_received', 'pending' => 'created',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'available_for_pickup', 'delivered' => 'delivered',
            'failed_attempt', 'exception' => 'exception',
            default => 'unknown',
        };
    }

    /** @param array<string,mixed>|null $body */
    private function requestJson(string $method, string $path, ?array $body): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: ParcelTracker/1.0',
        ];
        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : null;
        if ($payload === false) {
            $payload = null;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['status' => 0, 'json' => [], 'raw' => ''];
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(8, $this->timeoutSeconds));
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $rawText = is_string($raw) ? $raw : '';
            $json = json_decode($rawText, true);
            return [
                'status' => $status,
                'json' => is_array($json) ? $json : [],
                'raw' => $rawText,
            ];
        }

        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'ignore_errors' => true,
                'timeout' => $this->timeoutSeconds,
                'header' => implode("\r\n", $headers) . "\r\n",
            ],
        ];
        if ($payload !== null) {
            $opts['http']['content'] = $payload;
        }

        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        $status = $this->extractStatusCode($http_response_header ?? null);
        $rawText = is_string($raw) ? $raw : '';
        $json = json_decode($rawText, true);
        return [
            'status' => $status,
            'json' => is_array($json) ? $json : [],
            'raw' => $rawText,
        ];
    }

    private function extractStatusCode($responseHeaders): int
    {
        if (!is_array($responseHeaders)) {
            return 0;
        }
        foreach ($responseHeaders as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    private function log(string $line): void
    {
        $message = '[' . gmdate('c') . '] ' . $line . "\n";
        @file_put_contents($this->logDir . '/app.log', $message, FILE_APPEND);
    }
}
