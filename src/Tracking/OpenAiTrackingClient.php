<?php
declare(strict_types=1);

namespace App\Tracking;

final class OpenAiTrackingClient
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeoutSeconds;
    private bool $useWebSearch;
    private string $logDir;

    public function __construct(string $apiKey, string $logDir, ?string $baseUrl = null, ?string $model = null, int $timeoutSeconds = 32)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim((string)($baseUrl ?? getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1'), '/');
        $this->model = trim((string)($model ?? getenv('OPENAI_TRACKING_MODEL') ?: 'gpt-4.1-mini'));
        $this->timeoutSeconds = max(8, min(90, $timeoutSeconds));
        $this->useWebSearch = trim((string)getenv('OPENAI_TRACKING_WEB_SEARCH')) !== '0';
        $this->logDir = $logDir;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function providerName(): string
    {
        return 'openai';
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
            return ['ok' => false, 'error' => 'OpenAI API key is not configured.'];
        }

        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            return ['ok' => false, 'error' => 'Tracking number is required.'];
        }

        $carrierHint = trim((string)$carrierHint);
        $prompt = $this->buildPrompt($trackingNumber, $carrierHint !== '' ? $carrierHint : null);

        $payload = [
            'model' => $this->model,
            'input' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.0,
            'max_output_tokens' => 1200,
        ];
        if ($this->useWebSearch) {
            $payload['tools'] = [
                ['type' => 'web_search_preview'],
            ];
            $payload['tool_choice'] = 'auto';
        }

        $res = $this->requestJson('POST', '/responses', $payload);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            $bodyMsg = $this->extractErrorMessage($res['json']);
            $this->log('openai tracking failed: status=' . $res['status'] . ' err=' . $bodyMsg . ' raw=' . substr((string)$res['raw'], 0, 400));
            return ['ok' => false, 'error' => $bodyMsg !== '' ? $bodyMsg : ('OpenAI returned HTTP ' . $res['status'])];
        }

        $text = $this->extractOutputText($res['json']);
        if ($text === '') {
            return ['ok' => false, 'error' => 'OpenAI did not return tracking data.'];
        }

        $parsed = $this->decodeJsonPayload($text);
        if (!is_array($parsed)) {
            $parsed = $this->repairStructuredOutput($text, $trackingNumber, $carrierHint !== '' ? $carrierHint : null);
            if (!is_array($parsed)) {
                $this->log('openai tracking parse failure: output=' . substr($text, 0, 400));
                return ['ok' => false, 'error' => 'OpenAI response could not be parsed as structured tracking JSON.'];
            }
        }

        $events = $this->normalizeEvents($parsed['events'] ?? null, $parsed);
        $status = $this->normalizeStatus((string)($parsed['status'] ?? 'unknown'));
        if ($status === 'unknown') {
            $status = $this->inferStatusFromEventsAndNotes($events, (string)($parsed['notes'] ?? ''));
        }
        $carrier = $this->normalizeCarrier($parsed['carrier'] ?? null, $carrierHint !== '' ? $carrierHint : null);

        return [
            'ok' => true,
            'status' => $status,
            'carrier' => $carrier,
            'events' => $events,
        ];
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You are a parcel tracking resolver.',
            'Use web search when available and prefer official carrier sources.',
            'Return the most probable current status based on available evidence.',
            'Do not leave status unknown unless there is no usable signal at all.',
            'Return only JSON with this shape:',
            '{"status":"created|in_transit|out_for_delivery|delivered|exception|unknown",',
            '"carrier":"string|null","confidence":"high|medium|low",',
            '"notes":"string",',
            '"events":[{"event_time":"YYYY-MM-DD HH:MM:SS","location":"string|null","description":"string"}]}',
        ]);
    }

    private function buildPrompt(string $trackingNumber, ?string $carrierHint): string
    {
        $lines = [
            'Find the latest tracking status for this package.',
            'Tracking number: ' . $trackingNumber,
            'Current UTC time: ' . gmdate('Y-m-d H:i:s') . ' UTC',
        ];
        if ($carrierHint !== null && trim($carrierHint) !== '') {
            $lines[] = 'Carrier hint: ' . $carrierHint;
        }
        $lines[] = 'Prioritize official carrier tracking pages and recent scan details.';
        $lines[] = 'Infer a best status even when event detail is partial.';
        $lines[] = 'Return strict JSON only, no markdown fences.';
        return implode("\n", $lines);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $status = str_replace([' ', '-'], '_', $status);

        return match ($status) {
            'created', 'pending', 'info_received' => 'created',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered', 'available_for_pickup' => 'delivered',
            'exception', 'failed_attempt', 'expired' => 'exception',
            default => 'unknown',
        };
    }

    /**
     * @param list<array{event_time:string,location:?string,description:string,raw_payload:array<string,mixed>}> $events
     */
    private function inferStatusFromEventsAndNotes(array $events, string $notes): string
    {
        $texts = [];
        foreach ($events as $event) {
            $desc = strtolower(trim((string)($event['description'] ?? '')));
            if ($desc !== '') {
                $texts[] = $desc;
            }
        }
        $n = strtolower(trim($notes));
        if ($n !== '') {
            $texts[] = $n;
        }
        $haystack = implode(' ', $texts);
        if ($haystack === '') {
            return 'in_transit';
        }

        if (preg_match('/delivered|delivrd|proof.?of.?delivery|signed/', $haystack) === 1) {
            return 'delivered';
        }
        if (preg_match('/out.?for.?delivery|with.?courier|on.?vehicle.?for.?delivery/', $haystack) === 1) {
            return 'out_for_delivery';
        }
        if (preg_match('/exception|failed|attempted|undeliverable|held|return.?to.?sender|customs/', $haystack) === 1) {
            return 'exception';
        }
        if (preg_match('/in.?transit|departed|arrived|processed|facility|hub|accepted|shipment.?received/', $haystack) === 1) {
            return 'in_transit';
        }
        if (preg_match('/label.?created|info.?received|pending/', $haystack) === 1) {
            return 'created';
        }
        return 'in_transit';
    }

    private function normalizeCarrier($carrier, ?string $fallback): ?string
    {
        $carrier = trim((string)$carrier);
        if ($carrier !== '') {
            return substr($carrier, 0, 64);
        }
        $fallback = trim((string)$fallback);
        if ($fallback !== '') {
            return substr($fallback, 0, 64);
        }
        return null;
    }

    /**
     * @param mixed $events
     * @param array<string,mixed> $parsed
     * @return list<array{event_time:string,location:?string,description:string,raw_payload:array<string,mixed>}>
     */
    private function normalizeEvents($events, array $parsed): array
    {
        $out = [];
        if (is_array($events)) {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $description = trim((string)($event['description'] ?? ''));
                if ($description === '') {
                    continue;
                }
                $out[] = [
                    'event_time' => $this->normalizeTime((string)($event['event_time'] ?? '')),
                    'location' => $this->nullableTrim($event['location'] ?? null),
                    'description' => $description,
                    'raw_payload' => $event,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['event_time'], $b['event_time']));
        if ($out !== []) {
            return $out;
        }

        $fallbackDesc = trim((string)($parsed['notes'] ?? ''));
        if ($fallbackDesc === '') {
            $fallbackDesc = 'Tracking status update';
        }

        return [[
            'event_time' => gmdate('Y-m-d H:i:s'),
            'location' => null,
            'description' => $fallbackDesc,
            'raw_payload' => $parsed,
        ]];
    }

    private function normalizeTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return gmdate('Y-m-d H:i:s');
        }
        try {
            $dt = new \DateTimeImmutable($raw);
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $v = str_replace('T', ' ', $raw);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
                $v .= ':00';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v)) {
                return $v;
            }
        }
        return gmdate('Y-m-d H:i:s');
    }

    private function nullableTrim($value): ?string
    {
        $txt = trim((string)$value);
        return $txt === '' ? null : substr($txt, 0, 120);
    }

    /** @param array<string,mixed> $json */
    private function extractOutputText(array $json): string
    {
        $direct = trim((string)($json['output_text'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $outputs = $json['output'] ?? null;
        if (!is_array($outputs)) {
            return '';
        }
        foreach ($outputs as $item) {
            if (!is_array($item)) {
                continue;
            }
            $content = $item['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $chunk) {
                if (!is_array($chunk)) {
                    continue;
                }
                $text = trim((string)($chunk['text'] ?? $chunk['output_text'] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            }
        }
        return '';
    }

    private function decodeJsonPayload(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
            $text = trim($text);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $slice = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function repairStructuredOutput(string $rawText, string $trackingNumber, ?string $carrierHint): ?array
    {
        $prompt = [
            'Convert the following tracking output to strict JSON.',
            'Tracking number: ' . $trackingNumber,
            'Carrier hint: ' . ($carrierHint ?? ''),
            'Output schema:',
            '{"status":"created|in_transit|out_for_delivery|delivered|exception|unknown","carrier":"string|null","confidence":"high|medium|low","notes":"string","events":[{"event_time":"YYYY-MM-DD HH:MM:SS","location":"string|null","description":"string"}]}',
            'Only return JSON.',
            'Raw output:',
            $rawText,
        ];

        $payload = [
            'model' => $this->model,
            'input' => implode("\n", $prompt),
            'temperature' => 0.0,
            'max_output_tokens' => 900,
        ];
        $res = $this->requestJson('POST', '/responses', $payload);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return null;
        }
        $text = $this->extractOutputText($res['json']);
        if ($text === '') {
            return null;
        }
        return $this->decodeJsonPayload($text);
    }

    /** @param array<string,mixed> $json */
    private function extractErrorMessage(array $json): string
    {
        $vals = [
            $json['error']['message'] ?? null,
            $json['message'] ?? null,
            $json['error'] ?? null,
        ];
        foreach ($vals as $v) {
            if (is_array($v)) {
                continue;
            }
            $txt = trim((string)$v);
            if ($txt !== '') {
                return $txt;
            }
        }
        return '';
    }

    /** @param array<string,mixed>|null $body */
    private function requestJson(string $method, string $path, ?array $body): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
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
