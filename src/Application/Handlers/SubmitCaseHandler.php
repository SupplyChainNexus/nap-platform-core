<?php

declare(strict_types=1);

namespace NAP\Application\Handlers;

use DomainException;
use NAP\Application\Commands\SubmitCaseCommand;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\EventDispatcherInterface;

final readonly class SubmitCaseHandler
{
    public function __construct(
        private InMemoryCaseRepository $repository,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(SubmitCaseCommand $command): void
    {
        $case = $this->repository->find($command->caseId);

        if ($case === null) {
            throw new DomainException(sprintf('Case with ID "%s" not found.', $command->caseId->value()));
        }

        // 1. Mutate state via domain aggregate
        $case->submit($this->clock->now());

        // 2. Persist updated aggregate state
        $this->repository->save($case);

        // 3. Pull and dispatch all domain events
        foreach ($case->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}