<?php

declare(strict_types=1);

namespace NAP\SharedKernel\Domain\Contracts;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}