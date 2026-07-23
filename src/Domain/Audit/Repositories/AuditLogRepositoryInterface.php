<?php

declare(strict_types=1);

namespace NAP\Domain\Audit\Repositories;

use NAP\Domain\Audit\AuditLog;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $log): void;

    /**
     * @return list<AuditLog>
     */
    public function findByAggregateId(string $aggregateId): array;
}