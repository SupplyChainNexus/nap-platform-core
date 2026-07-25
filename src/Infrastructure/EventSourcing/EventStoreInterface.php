<?php

declare(strict_types=1);

namespace NAP\Infrastructure\EventSourcing;

use NAP\Domain\Events\DomainEventInterface;

interface EventStoreInterface
{
    public function append(DomainEventInterface $event): void;
    /**
     * @return array<int, DomainEventInterface>
     */
    public function getStream(string $aggregateId): array;
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllEvents(int $limit = 100, int $offset = 0): array;
}
