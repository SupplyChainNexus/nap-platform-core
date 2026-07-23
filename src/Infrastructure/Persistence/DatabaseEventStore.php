<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use NAP\SharedKernel\Domain\Exceptions\ConcurrencyException;
use PDO;

final readonly class DatabaseEventStore
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function append(string $aggregateId, string $eventType, array $payload, string $occurredAt, ?int $expectedVersion = null): void
    {
        if ($expectedVersion !== null) {
            $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM domain_events WHERE aggregate_id = :id");
            $stmtCount->execute(["id" => $aggregateId]);
            $currentVersion = (int) $stmtCount->fetchColumn();

            if ($currentVersion !== $expectedVersion) {
                throw ConcurrencyException::forAggregate($aggregateId, $expectedVersion, $currentVersion);
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO domain_events (aggregate_id, event_type, payload, occurred_at)
            VALUES (:id, :type, :payload, :occurred_at)
        ");
        $stmt->execute([
            "id" => $aggregateId,
            "type" => $eventType,
            "payload" => (string) json_encode($payload),
            "occurred_at" => $occurredAt,
        ]);
    }

    /**
     * @return list<array{id: int, aggregate_id: string, event_type: string, payload: string, occurred_at: string}>
     */
    public function getEventsForAggregate(string $aggregateId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM domain_events WHERE aggregate_id = :id ORDER BY id ASC");
        $stmt->execute(["id" => $aggregateId]);

        /** @var list<array{id: int|string, aggregate_id: string, event_type: string, payload: string, occurred_at: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $results[] = [
                "id" => (int) $row["id"],
                "aggregate_id" => $row["aggregate_id"],
                "event_type" => $row["event_type"],
                "payload" => $row["payload"],
                "occurred_at" => $row["occurred_at"],
            ];
        }

        return $results;
    }
}
