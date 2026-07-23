<?php

declare(strict_types=1);

namespace NAP\SharedKernel\Domain\Contracts;

interface IdGeneratorInterface
{
    public function generate(): string;
}