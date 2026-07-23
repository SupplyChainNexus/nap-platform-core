<?php

declare(strict_types=1);

namespace NAP\Domain\Repositories;

use NAP\Domain\Case\NXCase;
use NAP\SharedKernel\Domain\Identity\CaseId;

interface CaseRepository
{
    public function load(CaseId $caseId): ?NXCase;

    public function save(NXCase $case): void;
}