<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

use DateTimeImmutable;

final class PartNormalized extends AbstractDomainEvent
{
    /**
     * @param string $aggregateId
     * @param array<string, mixed> $payload
     * @param int $version
     * @param DateTimeImmutable|null $occurredAt
     */
    public function __construct(
        string $aggregateId,
        array $payload,
        int $version = 1,
        ?DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct($aggregateId, $payload, $version, $occurredAt);
    }

    public function getEventName(): string
    {
        return "PartNormalized";
    }
}
