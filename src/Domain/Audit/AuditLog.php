<?php

declare(strict_types=1);

namespace NAP\Domain\Audit;

use DateTimeImmutable;

final readonly class AuditLog
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $id,
        public string $aggregateId,
        public string $action,
        public DateTimeImmutable $occurredAt,
        public array $payload = []
    ) {}
}