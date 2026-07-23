<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use NAP\Domain\Audit\AuditLog;
use NAP\Domain\Audit\Repositories\AuditLogRepositoryInterface;

final class InMemoryAuditLogRepository implements AuditLogRepositoryInterface
{
    /** @var list<AuditLog> */
    private array $logs = [];

    public function save(AuditLog $log): void
    {
        $this->logs[] = $log;
    }

    /**
     * @return list<AuditLog>
     */
    public function findByAggregateId(string $aggregateId): array
    {
        return array_values(
            array_filter($this->logs, static fn (AuditLog $log) => $log->aggregateId === $aggregateId)
        );
    }
}