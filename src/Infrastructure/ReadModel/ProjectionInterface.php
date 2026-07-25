<?php

declare(strict_types=1);

namespace NAP\Infrastructure\ReadModel;

use NAP\Domain\Events\DomainEventInterface;

interface ProjectionInterface
{
    public function project(DomainEventInterface $event): void;
    public function reset(): void;
}
