<?php
declare(strict_types=1);

namespace App\Shipment;

use PDO;
use RuntimeException;

final class DbShipmentService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listShipments(int $userId, bool $includeArchived = false): array
    {
        $sql = <<<SQL
SELECT
  s.id,
  s.tracking_number,
  s.carrier,
  s.label,
  s.status,
  s.last_event_at,
  s.archived,
  s.created_at,
  s.updated_at,
  (
    SELECT te.location
    FROM tracking_events te
    WHERE te.shipment_id = s.id
    ORDER BY te.event_time DESC, te.id DESC
    LIMIT 1
  ) AS last_location
FROM shipments s
WHERE s.user_id = :user_id
SQL;

        if (!$includeArchived) {
            $sql .= ' AND s.archived = 0';
        }
        $sql .= ' ORDER BY s.updated_at DESC, s.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function createShipment(int $userId, string $trackingNumber, ?string $label, ?string $carrier): int
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            throw new RuntimeException('Tracking number is required.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO shipments (user_id, tracking_number, carrier, label, status, archived, created_at, updated_at)
             VALUES (:user_id, :tracking_number, :carrier, :label, :status, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'tracking_number' => $trackingNumber,
            'carrier' => $this->nullableTrim($carrier),
            'label' => $this->nullableTrim($label),
            'status' => 'created',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** @return array<string,mixed> */
    public function getShipment(int $userId, int $shipmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, tracking_number, carrier, label, status, last_event_at, archived, created_at, updated_at
             FROM shipments WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            'id' => $shipmentId,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Shipment not found.');
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function getEvents(int $userId, int $shipmentId): array
    {
        $this->assertOwnership($userId, $shipmentId);
        $stmt = $this->pdo->prepare(
            'SELECT id, shipment_id, event_time, location, description, created_at
             FROM tracking_events
             WHERE shipment_id = :shipment_id
             ORDER BY event_time DESC, id DESC'
        );
        $stmt->execute(['shipment_id' => $shipmentId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function addEvent(
        int $userId,
        int $shipmentId,
        string $eventTime,
        ?string $location,
        string $description,
        ?string $status
    ): void {
        $this->assertOwnership($userId, $shipmentId);

        $description = trim($description);
        if ($description === '') {
            throw new RuntimeException('Event description is required.');
        }

        $eventTime = $this->normalizeDatetime($eventTime);
        $locationNorm = $this->nullableTrim($location);
        $statusNorm = $this->normalizeStatus($status);

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO tracking_events (shipment_id, event_time, location, description, created_at)
                 VALUES (:shipment_id, :event_time, :location, :description, UTC_TIMESTAMP())'
            );
            $ins->execute([
                'shipment_id' => $shipmentId,
                'event_time' => $eventTime,
                'location' => $locationNorm,
                'description' => $description,
            ]);

            $upd = $this->pdo->prepare(
                'UPDATE shipments
                 SET last_event_at = :last_event_at,
                     status = :status,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = :id AND user_id = :user_id'
            );
            $upd->execute([
                'last_event_at' => $eventTime,
                'status' => $statusNorm,
                'id' => $shipmentId,
                'user_id' => $userId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function setArchived(int $userId, int $shipmentId, bool $archived): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE shipments
             SET archived = :archived, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'archived' => $archived ? 1 : 0,
            'id' => $shipmentId,
            'user_id' => $userId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Shipment not found.');
        }
    }

    /**
     * @param list<array{event_time:string,location:?string,description:string,raw_payload?:array<string,mixed>}> $events
     */
    public function syncExternalTracking(
        int $userId,
        int $shipmentId,
        string $status,
        array $events,
        ?string $carrier
    ): int {
        $this->assertOwnership($userId, $shipmentId);

        $carrierNorm = $this->nullableTrim($carrier);
        $statusNorm = $this->normalizeStatus($status);
        $inserted = 0;
        $latestEventTime = null;

        $this->pdo->beginTransaction();
        try {
            $existsStmt = $this->pdo->prepare(
                'SELECT id
                 FROM tracking_events
                 WHERE shipment_id = :shipment_id
                   AND event_time = :event_time
                   AND description = :description
                   AND COALESCE(location, \'\') = :location
                 LIMIT 1'
            );
            $insertStmt = $this->pdo->prepare(
                'INSERT INTO tracking_events (shipment_id, event_time, location, description, raw_payload, created_at)
                 VALUES (:shipment_id, :event_time, :location, :description, :raw_payload, UTC_TIMESTAMP())'
            );

            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $eventTime = $this->normalizeDatetime((string)($event['event_time'] ?? ''));
                $location = $this->nullableTrim((string)($event['location'] ?? ''));
                $description = trim((string)($event['description'] ?? ''));
                if ($description === '') {
                    continue;
                }

                $existsStmt->execute([
                    'shipment_id' => $shipmentId,
                    'event_time' => $eventTime,
                    'description' => $description,
                    'location' => $location ?? '',
                ]);
                if ($existsStmt->fetch()) {
                    if ($latestEventTime === null || $eventTime > $latestEventTime) {
                        $latestEventTime = $eventTime;
                    }
                    continue;
                }

                $rawPayload = null;
                if (is_array($event['raw_payload'] ?? null)) {
                    $encoded = json_encode($event['raw_payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (is_string($encoded) && $encoded !== '') {
                        $rawPayload = $encoded;
                    }
                }

                $insertStmt->execute([
                    'shipment_id' => $shipmentId,
                    'event_time' => $eventTime,
                    'location' => $location,
                    'description' => $description,
                    'raw_payload' => $rawPayload,
                ]);
                $inserted++;

                if ($latestEventTime === null || $eventTime > $latestEventTime) {
                    $latestEventTime = $eventTime;
                }
            }

            $sql = 'UPDATE shipments
                    SET status = :status,
                        updated_at = UTC_TIMESTAMP()';
            $params = [
                'status' => $statusNorm,
                'id' => $shipmentId,
                'user_id' => $userId,
            ];
            if ($latestEventTime !== null) {
                $sql .= ', last_event_at = :last_event_at';
                $params['last_event_at'] = $latestEventTime;
            }
            if ($carrierNorm !== null) {
                $sql .= ', carrier = :carrier';
                $params['carrier'] = $carrierNorm;
            }
            $sql .= ' WHERE id = :id AND user_id = :user_id';

            $upd = $this->pdo->prepare($sql);
            $upd->execute($params);

            $this->pdo->commit();
            return $inserted;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function assertOwnership(int $userId, int $shipmentId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM shipments WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $shipmentId, 'user_id' => $userId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('Shipment not found.');
        }
    }

    private function nullableTrim(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $x = trim($v);
        return $x === '' ? null : $x;
    }

    private function normalizeDatetime(string $input): string
    {
        $v = trim($input);
        if ($v === '') {
            return gmdate('Y-m-d H:i:s');
        }
        $v = str_replace('T', ' ', $v);
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/', $v)) {
            $v .= ':00';
        }
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $v)) {
            return gmdate('Y-m-d H:i:s');
        }
        return $v;
    }

    private function normalizeStatus(?string $status): string
    {
        $allowed = [
            'created',
            'in_transit',
            'out_for_delivery',
            'delivered',
            'exception',
            'unknown',
        ];
        $s = trim((string)$status);
        return in_array($s, $allowed, true) ? $s : 'unknown';
    }
}
