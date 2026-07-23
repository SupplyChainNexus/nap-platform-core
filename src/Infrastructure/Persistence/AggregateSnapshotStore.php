<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;

final readonly class AggregateSnapshotStore
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * @param array<string, mixed> $state
     */
    public function saveSnapshot(string $aggregateId, int $version, array $state): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO case_snapshots (aggregate_id, version, state, created_at)
            VALUES (:id, :ver, :state, CURRENT_TIMESTAMP)
            ON CONFLICT(aggregate_id) DO UPDATE SET
                version = excluded.version,
                state = excluded.state,
                created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            "id" => $aggregateId,
            "ver" => $version,
            "state" => (string) json_encode($state),
        ]);
    }

    /**
     * @return array{aggregate_id: string, version: int, state: array<string, mixed>}|null
     */
    public function getSnapshot(string $aggregateId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM case_snapshots WHERE aggregate_id = :id");
        $stmt->execute(["id" => $aggregateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        /** @var array{aggregate_id: string, version: int|string, state: string} $row */
        /** @var array<string, mixed> $decodedState */
        $decodedState = (array) json_decode($row["state"], true);

        return [
            "aggregate_id" => $row["aggregate_id"],
            "version" => (int) $row["version"],
            "state" => $decodedState,
        ];
    }
}
