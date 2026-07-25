<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Messaging;

use NAP\Domain\Events\DomainEventInterface;

interface EventBusInterface
{
    public function subscribe(EventListenerInterface $listener): void;
    public function dispatch(DomainEventInterface $event): void;
    /**
     * @param array<int, DomainEventInterface> $events
     */
    public function dispatchAll(array $events): void;
}
