<?php

declare(strict_types=1);

namespace NAP\Application\Handlers;

use NAP\Application\Commands\CreateCaseCommand;
use NAP\Domain\Case\NXCase;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;
use NAP\SharedKernel\Domain\Identity\CaseId;

final readonly class CreateCaseHandler
{
    public function __construct(
        private IdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
        private InMemoryCaseRepository $repository
    ) {}

    public function handle(CreateCaseCommand $command): CaseId
    {
        $rawUuid = $this->idGenerator->generate();
        $caseId = new CaseId($rawUuid);

        $case = NXCase::create(
            $caseId,
            $command->businessContext,
            $this->clock->now()
        );

        $this->repository->save($case);

        return $caseId;
    }
}