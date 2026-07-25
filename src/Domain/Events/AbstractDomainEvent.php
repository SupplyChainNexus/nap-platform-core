<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

use DateTimeImmutable;

abstract class AbstractDomainEvent implements DomainEventInterface
{
    protected string $aggregateId;
    /** @var array<string, mixed> */
    protected array $payload;
    protected DateTimeImmutable $occurredAt;
    protected int $version;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(string $aggregateId, array $payload, int $version = 1, ?DateTimeImmutable $occurredAt = null)
    {
        $this->aggregateId = $aggregateId;
        $this->payload = $payload;
        $this->version = $version;
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
