<?php

declare(strict_types=1);

namespace NAP\SharedKernel\Domain\Exceptions;

use RuntimeException;

final class ConcurrencyException extends RuntimeException
{
    public static function forAggregate(string $aggregateId, int $expectedVersion, int $currentVersion): self
    {
        return new self(sprintf(
            "Concurrency conflict for aggregate '%s': expected version %d, but current version is %d.",
            $aggregateId,
            $expectedVersion,
            $currentVersion
        ));
    }
}
