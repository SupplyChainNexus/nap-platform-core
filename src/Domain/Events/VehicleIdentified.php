<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

final class VehicleIdentified extends AbstractDomainEvent
{
    public function getEventName(): string
    {
        return "VehicleIdentified";
    }
}
