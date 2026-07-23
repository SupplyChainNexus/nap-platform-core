<?php

declare(strict_types=1);

namespace NAP\Application\Commands;

final readonly class CreateCaseCommand
{
    public function __construct(
        public string $businessContext
    ) {}
}