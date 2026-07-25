<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Messaging;

use NAP\Domain\Events\DomainEventInterface;

final class InMemoryEventBus implements EventBusInterface
{
    /** @var array<int, EventListenerInterface> */
    private array $listeners = [];

    public function subscribe(EventListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function dispatch(DomainEventInterface $event): void
    {
        foreach ($this->listeners as $listener) {
            if ($listener->supports($event)) {
                $listener->handle($event);
            }
        }
    }

    /**
     * @param array<int, DomainEventInterface> $events
     */
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    public function getListenerCount(): int
    {
        return count($this->listeners);
    }
}
