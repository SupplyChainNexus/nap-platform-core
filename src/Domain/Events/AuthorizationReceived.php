<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

final class AuthorizationReceived extends AbstractDomainEvent
{
    public function getEventName(): string
    {
        return "AuthorizationReceived";
    }
}
