<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use DateTimeImmutable;
use NAP\Domain\Audit\AuditLog;
use NAP\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use PDO;

final readonly class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function save(AuditLog $log): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (id, aggregate_id, action, payload, occurred_at)
             VALUES (:id, :aggregate_id, :action, :payload, :occurred_at)'
        );

        $stmt->execute([
            'id' => $log->id,
            'aggregate_id' => $log->aggregateId,
            'action' => $log->action,
            'payload' => json_encode($log->payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $log->occurredAt->format(DATE_ATOM),
        ]);
    }

    /**
     * @return list<AuditLog>
     */
    public function findByAggregateId(string $aggregateId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs WHERE aggregate_id = :aggregate_id ORDER BY occurred_at ASC');
        $stmt->execute(['aggregate_id' => $aggregateId]);

        /** @var list<array{id: string, aggregate_id: string, action: string, payload: string, occurred_at: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $logs = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);

            $logs[] = new AuditLog(
                id: $row['id'],
                aggregateId: $row['aggregate_id'],
                action: $row['action'],
                occurredAt: new DateTimeImmutable($row['occurred_at']),
                payload: $payload
            );
        }

        return $logs;
    }
}