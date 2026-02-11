<?php
declare(strict_types=1);

namespace App\Tracking;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class AfterShipClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeoutSeconds;
    private string $logDir;

    public function __construct(string $apiKey, string $logDir, ?string $baseUrl = null, int $timeoutSeconds = 18)
    {
        $this->apiKey = trim($apiKey);
        $version = trim((string)getenv('AFTERSHIP_API_VERSION'));
        if ($version === '') {
            $version = '2026-01';
        }

        $apiBase = trim((string)($baseUrl ?? getenv('AFTERSHIP_API_BASE') ?: 'https://api.aftership.com'));
        $apiBase = rtrim($apiBase, '/');
        $this->baseUrl = $apiBase . '/tracking/' . rawurlencode($version);
        $this->timeoutSeconds = max(5, min(60, $timeoutSeconds));
        $this->logDir = $logDir;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
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

        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            return ['ok' => false, 'error' => 'Tracking number is required.'];
        }

        $slug = $this->detectBestSlug($trackingNumber, $carrierHint);
        if ($slug === null) {
            return ['ok' => false, 'error' => 'Unable to detect carrier. Set carrier as UPS/FedEx/USPS/DHL and try again.'];
        }

        $this->createTrackingIfNeeded($slug, $trackingNumber);
        $tracking = $this->getTracking($slug, $trackingNumber);
        if (!is_array($tracking)) {
            return ['ok' => false, 'error' => 'No tracking data returned from provider.'];
        }

        $events = $this->normalizeEvents($tracking);
        $status = $this->mapStatus((string)($tracking['tag'] ?? $tracking['subtag'] ?? 'unknown'));
        $carrier = (string)($tracking['slug'] ?? $slug);

        return [
            'ok' => true,
            'status' => $status,
            'carrier' => $carrier !== '' ? $carrier : null,
            'events' => $events,
        ];
    }

    private function detectBestSlug(string $trackingNumber, ?string $carrierHint): ?string
    {
        $hint = $this->normalizeCarrierSlug($carrierHint);
        $payload = ['tracking' => ['tracking_number' => $trackingNumber]];
        if ($hint !== null) {
            $payload['tracking']['slug'] = $hint;
        }

        $res = $this->requestJson('POST', '/trackings/detect-couriers', $payload);
        if ($res['status'] >= 200 && $res['status'] < 300) {
            $couriers = $res['json']['data']['couriers'] ?? $res['json']['couriers'] ?? [];
            if (is_array($couriers)) {
                foreach ($couriers as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $slug = trim((string)($row['slug'] ?? ''));
                    if ($slug !== '') {
                        return $slug;
                    }
                }
            }
        }

        return $hint;
    }

    private function createTrackingIfNeeded(string $slug, string $trackingNumber): void
    {
        $payload = [
            'tracking' => [
                'slug' => $slug,
                'tracking_number' => $trackingNumber,
            ],
        ];
        $res = $this->requestJson('POST', '/trackings', $payload);
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return;
        }

        // Existing tracking is acceptable. We still fetch the live object afterward.
        if ($res['status'] === 400 || $res['status'] === 409 || $res['status'] === 422) {
            return;
        }
        $this->log('aftership create tracking failed: status=' . $res['status'] . ' body=' . substr((string)$res['raw'], 0, 300));
    }

    /** @return array<string,mixed>|null */
    private function getTracking(string $slug, string $trackingNumber): ?array
    {
        $path = '/trackings/' . rawurlencode($slug) . '/' . rawurlencode($trackingNumber)
            . '?fields=slug,tag,subtag,checkpoints,last_updated_at,tracking_number';
        $res = $this->requestJson('GET', $path, null);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            $this->log('aftership get tracking failed: status=' . $res['status'] . ' body=' . substr((string)$res['raw'], 0, 300));
            return null;
        }

        $tracking = $res['json']['data']['tracking'] ?? $res['json']['tracking'] ?? null;
        return is_array($tracking) ? $tracking : null;
    }

    /**
     * @param array<string,mixed> $tracking
     * @return list<array{event_time:string,location:?string,description:string,raw_payload:array<string,mixed>}>
     */
    private function normalizeEvents(array $tracking): array
    {
        $out = [];
        $checkpoints = $tracking['checkpoints'] ?? [];
        if (is_array($checkpoints)) {
            foreach ($checkpoints as $cp) {
                if (!is_array($cp)) {
                    continue;
                }

                $desc = trim((string)($cp['message'] ?? $cp['checkpoint_status'] ?? $cp['tag'] ?? 'Tracking update'));
                if ($desc === '') {
                    continue;
                }

                $location = $this->buildLocation($cp);
                $rawTime = (string)($cp['checkpoint_time'] ?? $cp['created_at'] ?? $cp['updated_at'] ?? '');
                $out[] = [
                    'event_time' => $this->normalizeTime($rawTime),
                    'location' => $location,
                    'description' => $desc,
                    'raw_payload' => $cp,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['event_time'], $b['event_time']));

        if ($out === []) {
            $tag = trim((string)($tracking['tag'] ?? 'Tracking created'));
            $out[] = [
                'event_time' => gmdate('Y-m-d H:i:s'),
                'location' => null,
                'description' => $tag !== '' ? $tag : 'Tracking created',
                'raw_payload' => $tracking,
            ];
        }

        return $out;
    }

    /** @param array<string,mixed> $cp */
    private function buildLocation(array $cp): ?string
    {
        $parts = [];
        foreach (['location', 'city', 'state', 'country_name'] as $k) {
            $v = trim((string)($cp[$k] ?? ''));
            if ($v !== '') {
                $parts[] = $v;
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
            $dt = new DateTimeImmutable($input);
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
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

    private function mapStatus(string $tag): string
    {
        $tag = strtolower(trim($tag));
        $tag = str_replace([' ', '-'], '_', $tag);

        return match ($tag) {
            'pending', 'notfound', 'info_received', 'inforeceived' => 'created',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered', 'available_for_pickup' => 'delivered',
            'exception', 'attemptfail', 'expired', 'failed_attempt' => 'exception',
            default => 'unknown',
        };
    }

    private function normalizeCarrierSlug(?string $carrier): ?string
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
            'dhl ecommerce' => 'dhl_ecommerce',
        ];
        if (isset($map[$carrier])) {
            return $map[$carrier];
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $carrier);
        $slug = trim((string)$slug, '-');
        return $slug !== '' ? $slug : null;
    }

    /** @param array<string,mixed>|null $body */
    private function requestJson(string $method, string $path, ?array $body): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'aftership-api-key: ' . $this->apiKey,
            'as-api-key: ' . $this->apiKey,
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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(6, $this->timeoutSeconds));
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
        $message = '[' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM) . '] ' . $line . "\n";
        @file_put_contents($this->logDir . '/app.log', $message, FILE_APPEND);
    }
}
