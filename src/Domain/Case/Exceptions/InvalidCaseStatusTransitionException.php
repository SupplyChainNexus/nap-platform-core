<?php

declare(strict_types=1);

namespace NAP\Domain\Case\Exceptions;

use DomainException;
use NAP\Domain\Case\CaseStatus;

final class InvalidCaseStatusTransitionException extends DomainException
{
    public static function fromStatus(CaseStatus $from, CaseStatus $to): self
    {
        return new self(
            sprintf('Cannot transition case status from "%s" to "%s".', $from->value, $to->value)
        );
    }
}