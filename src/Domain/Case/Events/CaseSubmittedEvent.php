<?php

declare(strict_types=1);

namespace NAP\Domain\Case\Events;

use DateTimeImmutable;
use NAP\SharedKernel\Domain\Identity\CaseId;

final readonly class CaseSubmittedEvent
{
    public function __construct(
        public CaseId $caseId,
        public DateTimeImmutable $occurredAt
    ) {}
}