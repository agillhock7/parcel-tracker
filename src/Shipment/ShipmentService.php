<?php
declare(strict_types=1);

namespace App\Shipment;

use App\Infrastructure\JsonDb;
use RuntimeException;

final class ShipmentService
{
    public function __construct(private JsonDb $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listShipments(bool $includeArchived = false): array
    {
        $data = $this->db->read();
        $shipmentsById = $data['shipments'] ?? [];
        $eventsById = $data['events'] ?? [];

        if (is_array($shipmentsById)) {
            foreach ($shipmentsById as $id => $s) {
                if (!is_array($s)) {
                    continue;
                }

                $events = $eventsById[$id] ?? [];
                if (!is_array($events) || $events === []) {
                    continue;
                }

                // event_time is stored as YYYY-mm-dd HH:MM:SS, so string compare works.
                $latest = null;
                foreach ($events as $ev) {
                    if (!is_array($ev)) {
                        continue;
                    }
                    if ($latest === null || ((string)($ev['event_time'] ?? '') > (string)($latest['event_time'] ?? ''))) {
                        $latest = $ev;
                    }
                }

                if (is_array($latest)) {
                    $shipmentsById[$id]['last_location'] = $latest['location'] ?? null;
                }
            }
        }

        $shipments = array_values(is_array($shipmentsById) ? $shipmentsById : []);

        $shipments = array_filter($shipments, function (array $s) use ($includeArchived): bool {
            if ($includeArchived) {
                return true;
            }
            return empty($s['archived']);
        });

        usort($shipments, function (array $a, array $b): int {
            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
        });

        return array_values($shipments);
    }

    public function createShipment(string $trackingNumber, ?string $label, ?string $carrier): string
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            throw new RuntimeException('Tracking number is required.');
        }

        $id = bin2hex(random_bytes(16));
        $now = gmdate('Y-m-d H:i:s');

        $data = $this->db->read();
        $data['shipments'] ??= [];
        $data['events'] ??= [];

        $data['shipments'][$id] = [
            'id' => $id,
            'tracking_number' => $trackingNumber,
            'label' => $label ? trim($label) : null,
            'carrier' => $carrier ? trim($carrier) : null,
            'status' => 'created',
            'last_event_at' => null,
            'archived' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $data['events'][$id] = [];

        $this->db->write($data);
        return $id;
    }

    /** @return array<string, mixed> */
    public function getShipment(string $id): array
    {
        $data = $this->db->read();
        $s = $data['shipments'][$id] ?? null;
        if (!is_array($s)) {
            throw new RuntimeException('Shipment not found.');
        }
        return $s;
    }

    /** @return list<array<string, mixed>> */
    public function getEvents(string $shipmentId): array
    {
        $data = $this->db->read();
        $events = $data['events'][$shipmentId] ?? [];
        if (!is_array($events)) {
            return [];
        }
        usort($events, fn (array $a, array $b): int => strcmp((string)($b['event_time'] ?? ''), (string)($a['event_time'] ?? '')));
        return $events;
    }

    public function addEvent(
        string $shipmentId,
        string $eventTime,
        ?string $location,
        string $description,
        ?string $status
    ): void {
        $eventTime = self::normalizeDatetime($eventTime);
        $description = trim($description);
        if ($description === '') {
            throw new RuntimeException('Event description is required.');
        }

        $data = $this->db->read();
        if (!isset($data['shipments'][$shipmentId])) {
            throw new RuntimeException('Shipment not found.');
        }

        $data['events'][$shipmentId] ??= [];
        $data['events'][$shipmentId][] = [
            'id' => bin2hex(random_bytes(8)),
            'event_time' => $eventTime,
            'location' => $location ? trim($location) : null,
            'description' => $description,
        ];

        $now = gmdate('Y-m-d H:i:s');
        $data['shipments'][$shipmentId]['last_event_at'] = $eventTime;
        $data['shipments'][$shipmentId]['updated_at'] = $now;
        $data['shipments'][$shipmentId]['last_location'] = $location ? trim($location) : null;
        if ($status) {
            $data['shipments'][$shipmentId]['status'] = $status;
        }

        $this->db->write($data);
    }

    public function setArchived(string $shipmentId, bool $archived): void
    {
        $data = $this->db->read();
        if (!isset($data['shipments'][$shipmentId])) {
            throw new RuntimeException('Shipment not found.');
        }
        $data['shipments'][$shipmentId]['archived'] = $archived;
        $data['shipments'][$shipmentId]['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->db->write($data);
    }

    private static function normalizeDatetime(string $input): string
    {
        $v = trim($input);
        if ($v === '') {
            return gmdate('Y-m-d H:i:s');
        }

        // HTML datetime-local: 2026-02-11T19:10 (optionally with seconds)
        $v = str_replace('T', ' ', $v);
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/', $v)) {
            $v .= ':00';
        }
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $v)) {
            return gmdate('Y-m-d H:i:s');
        }
        return $v;
    }
}
