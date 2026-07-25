<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Messaging;

use NAP\Domain\Events\DomainEventInterface;

interface EventListenerInterface
{
    public function handle(DomainEventInterface $event): void;
    public function supports(DomainEventInterface $event): bool;
}
