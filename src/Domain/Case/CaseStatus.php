<?php

declare(strict_types=1);

namespace NAP\Domain\Case;

enum CaseStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case CLOSED = 'CLOSED';

    /**
     * Enforces valid state transition rules.
     */
    public function canTransitionTo(self $targetStatus): bool
    {
        return match ($this) {
            self::DRAFT => $targetStatus === self::SUBMITTED,
            self::SUBMITTED => $targetStatus === self::IN_PROGRESS,
            self::IN_PROGRESS => $targetStatus === self::CLOSED,
            self::CLOSED => false,
        };
    }
}