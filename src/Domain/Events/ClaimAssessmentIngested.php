<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

final class ClaimAssessmentIngested extends AbstractDomainEvent
{
    public function getEventName(): string
    {
        return "ClaimAssessmentIngested";
    }
}
