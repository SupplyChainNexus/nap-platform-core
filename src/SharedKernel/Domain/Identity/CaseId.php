<?php

declare(strict_types=1);

namespace NAP\SharedKernel\Domain\Identity;

use Stringable;

final readonly class CaseId implements Stringable
{
    public function __construct(
        private string $id
    ) {}

    public function value(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
