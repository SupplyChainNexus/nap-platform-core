<?php

declare(strict_types=1);

namespace NAP\SharedKernel\Domain\Contracts;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}