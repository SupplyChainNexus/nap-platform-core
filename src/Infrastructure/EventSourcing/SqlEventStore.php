<?php

declare(strict_types=1);

namespace NAP\Infrastructure\EventSourcing;

use NAP\Domain\Events\AbstractDomainEvent;
use NAP\Domain\Events\AuthorizationReceived;
use NAP\Domain\Events\ClaimAssessmentIngested;
use NAP\Domain\Events\DomainEventInterface;
use NAP\Domain\Events\VehicleIdentified;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use DateTimeImmutable;
use PDO;

final class SqlEventStore implements EventStoreInterface
{
    private PDO $pdo;
    private string $driver;

    public function __construct(?DatabaseAdapter $dbAdapter = null)
    {
        $adapter = $dbAdapter ?? new DatabaseAdapter();
        $this->pdo = $adapter->getPdo();
        $this->driver = $adapter->getDriver();
        $this->initializeEventStoreSchema();
    }

    private function initializeEventStoreSchema(): void
    {
        $idType = $this->driver === "pgsql" ? "SERIAL PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
        $jsonType = $this->driver === "pgsql" ? "JSONB" : "TEXT";

        $sql = "CREATE TABLE IF NOT EXISTS event_store (
            id {$idType},
            aggregate_id VARCHAR(128) NOT NULL,
            event_name VARCHAR(128) NOT NULL,
            payload {$jsonType} NOT NULL,
            version INT NOT NULL,
            occurred_at VARCHAR(64) NOT NULL
        )";

        $this->pdo->exec($sql);
    }

    public function append(DomainEventInterface $event): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO event_store (aggregate_id, event_name, payload, version, occurred_at) VALUES (:agg_id, :name, :payload, :version, :occurred)");
        $stmt->execute([
            ":agg_id" => $event->getAggregateId(),
            ":name" => $event->getEventName(),
            ":payload" => (string) json_encode($event->getPayload()),
            ":version" => $event->getVersion(),
            ":occurred" => $event->getOccurredAt()->format("Y-m-d H:i:s.u")
        ]);
    }

    /**
     * @return array<int, DomainEventInterface>
     */
    public function getStream(string $aggregateId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM event_store WHERE aggregate_id = :agg_id ORDER BY version ASC");
        $stmt->execute([":agg_id" => $aggregateId]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->hydrateEvent($row);
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllEvents(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM event_store ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return array_map(function(array $row): array {
            $payloadRaw = is_string($row["payload"] ?? null) ? $row["payload"] : "{}";
            /** @var array<string, mixed> $payloadDecoded */
            $payloadDecoded = json_decode($payloadRaw, true) ?: [];

            return [
                "id" => is_numeric($row["id"] ?? null) ? (int) $row["id"] : 0,
                "aggregateId" => is_string($row["aggregate_id"] ?? null) ? $row["aggregate_id"] : "",
                "eventName" => is_string($row["event_name"] ?? null) ? $row["event_name"] : "",
                "payload" => $payloadDecoded,
                "version" => is_numeric($row["version"] ?? null) ? (int) $row["version"] : 1,
                "occurredAt" => is_string($row["occurred_at"] ?? null) ? $row["occurred_at"] : ""
            ];
        }, $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateEvent(array $row): DomainEventInterface
    {
        $eventName = is_string($row["event_name"] ?? null) ? $row["event_name"] : "";
        $aggregateId = is_string($row["aggregate_id"] ?? null) ? $row["aggregate_id"] : "";
        $payloadRaw = is_string($row["payload"] ?? null) ? $row["payload"] : "{}";
        /** @var array<string, mixed> $payload */
        $payload = json_decode($payloadRaw, true) ?: [];
        $version = is_numeric($row["version"] ?? null) ? (int) $row["version"] : 1;
        $occurredStr = is_string($row["occurred_at"] ?? null) ? $row["occurred_at"] : "now";
        $occurredAt = new DateTimeImmutable($occurredStr);

        return match ($eventName) {
            "AuthorizationReceived" => new AuthorizationReceived($aggregateId, $payload, $version, $occurredAt),
            "ClaimAssessmentIngested" => new ClaimAssessmentIngested($aggregateId, $payload, $version, $occurredAt),
            "VehicleIdentified" => new VehicleIdentified($aggregateId, $payload, $version, $occurredAt),
            default => new class($aggregateId, $payload, $version, $occurredAt) extends AbstractDomainEvent {
                public function getEventName(): string { return "GenericDomainEvent"; }
            }
        };
    }
}
