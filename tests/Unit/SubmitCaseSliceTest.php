<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\SubmitCaseCommand;
use NAP\Application\Handlers\SubmitCaseHandler;
use NAP\Domain\Case\CaseStatus;
use NAP\Domain\Case\Events\CaseSubmittedEvent;
use NAP\Domain\Case\NXCase;
use NAP\Infrastructure\Messaging\InMemoryEventDispatcher;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\Infrastructure\Services\SystemClock;
use NAP\SharedKernel\Domain\Identity\CaseId;
use PHPUnit\Framework\TestCase;

final class SubmitCaseSliceTest extends TestCase
{
    public function testCanSubmitCaseAndTriggerEventHandlers(): void
    {
        // Setup infrastructure
        $repository = new InMemoryCaseRepository();
        $clock = new SystemClock();
        $dispatcher = new InMemoryEventDispatcher();

        // Register a test listener to capture dispatched events
        /** @var list<CaseSubmittedEvent> $capturedEvents */
        $capturedEvents = [];
        $dispatcher->listen(CaseSubmittedEvent::class, static function (object $event) use (&$capturedEvents): void {
            if ($event instanceof CaseSubmittedEvent) {
                $capturedEvents[] = $event;
            }
        });

        // Seed initial draft case
        $caseId = new CaseId('018e38f9-472b-7b33-8a30-89196b0521e1');
        $case = NXCase::create($caseId, 'Logistics', $clock->now());
        $repository->save($case);

        // Execute Handler
        $handler = new SubmitCaseHandler($repository, $clock, $dispatcher);
        $handler->handle(new SubmitCaseCommand($caseId));

        // Assertions
        $updatedCase = $repository->find($caseId);
        $this->assertNotNull($updatedCase);
        $this->assertSame(CaseStatus::SUBMITTED, $updatedCase->status());

        // Assert Domain Event was dispatched
        $this->assertCount(1, $capturedEvents);
        $this->assertSame($caseId, $capturedEvents[0]->caseId);
    }
}