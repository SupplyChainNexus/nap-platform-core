<?php

declare(strict_types=1);

namespace NAP\Application\Commands;

use NAP\SharedKernel\Domain\Identity\CaseId;

final readonly class SubmitCaseCommand
{
    public function __construct(
        public CaseId $caseId
    ) {}
}