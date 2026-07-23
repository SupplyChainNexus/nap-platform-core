<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use NAP\Domain\Case\NXCase;
use NAP\SharedKernel\Domain\Identity\CaseId;

final class InMemoryCaseRepository
{
    /** @var array<string, NXCase> */
    private array $cases = [];

    public function save(NXCase $case): void
    {
        // Change $case->Id() to lowercase $case->id()
        $this->cases[$case->id()->value()] = $case;
    }

    public function find(CaseId $id): ?NXCase
    {
        return $this->cases[$id->value()] ?? null;
    }
}