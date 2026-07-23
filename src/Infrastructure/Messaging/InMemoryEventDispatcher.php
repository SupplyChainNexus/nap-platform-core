<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Messaging;

use NAP\SharedKernel\Domain\Contracts\EventDispatcherInterface;

final class InMemoryEventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, list<callable(object): void>> */
    private array $listeners = [];

    /**
     * @param class-string $eventClass
     * @param callable(object): void $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void
    {
        $eventClass = $event::class;

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener($event);
        }
    }
}