<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Services;

use DateTimeImmutable;
use DateTimeZone;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}