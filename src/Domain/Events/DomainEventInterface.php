<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

use DateTimeImmutable;

interface DomainEventInterface
{
    public function getAggregateId(): string;
    public function getEventName(): string;
    /** @return array<string, mixed> */
    public function getPayload(): array;
    public function getOccurredAt(): DateTimeImmutable;
    public function getVersion(): int;
}
